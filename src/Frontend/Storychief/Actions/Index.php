<?php

namespace Frontend\Modules\Storychief\Actions;

use Symfony\Component\Filesystem\Filesystem;
use Common\Exception\RedirectException;
use Frontend\Core\Engine\Base\Block as FrontendBaseBlock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Backend\Modules\Blog\Engine\Model as Blog;
use Backend\Modules\Users\Engine\Model as Users;
use Backend\Core\Language\Language as BL;
use Backend\Core\Engine\Model as BackendModel;
use Backend\Modules\Search\Engine\Model as BackendSearchModel;

class Index extends FrontendBaseBlock {

	/**
	 * Execute the action
	 */
	public function execute() {
		BL::setWorkingLanguage($this->get('fork.settings')->get('Storychief', 'language'));

		$request = $this->getParsedRequest();

		if (!$this->isValidRequest($request)) $this->ensureJsonResponse(null, 400);

		$method = 'handle' . preg_replace('/\s+/', '', ucwords($request->request->get('meta')['event']));
		if (!method_exists($this, $method)) return $this->missingMethod();

		return $this->{$method}($request);
	}

	/**
	 * @return Request
	 */
	protected function getParsedRequest() {
		$request = $this->get('request');
		if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
			$data = json_decode($request->getContent(), true);
			$request->request->replace(is_array($data) ? $data : array());
		}

		return $request;
	}

	/**
	 * Checks if the request is valid
	 *
	 * @param  Request $request
	 * @return bool
	 */
	protected function isValidRequest(Request $request) {
		if (!$request->isMethod('POST')) return false;
		$payload = $request->request->all();

		if (isset($payload['meta']['mac'])) {
			$givenMac = $payload['meta']['mac'];
			unset($payload['meta']['mac']);
			$calcMac = hash_hmac('sha256', json_encode($payload), $this->get('fork.settings')->get('Storychief', 'api_key'));

			return hash_equals($givenMac, $calcMac);
		}

		return false;
	}

	/**
	 * Creates a valid JSON response
	 *
	 * @param null $data
	 * @param int $code
	 * @throws RedirectException
	 */
	protected function ensureJsonResponse($data = null, $code = 200) {
		$response = new Response();
		$response->headers->set('Content-Type', 'application/json');
		$response->setStatusCode($code);

		if (!is_null($data)) {
			$data['mac'] = hash_hmac('sha256', json_encode($data), $this->get('fork.settings')->get('Storychief', 'api_key'));
			$response->setContent(json_encode($data));
		}

		throw new RedirectException('', $response);
	}

	/**
	 * Return a valid slug from a string
	 * @param $text
	 * @return mixed|string
	 */
	protected function sluggify($text) {
		$text = preg_replace('~[^\\pL\d]+~u', '-', $text);
		$text = trim($text, '-');
		if (function_exists('iconv')) $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
		$text = strtolower($text);
		$text = preg_replace('~[^-\w]+~', '', $text);
		if (empty($text)) return 'n-a';

		return $text;
	}

	/**
	 * Handle a publish webhook call
	 *
	 * @param Request $request
	 */
	protected function handlePublish(Request $request) {
		$payload = $request->request->get('data');
		$post_id = Blog::getMaximumId() + 1;
		$post_url = Blog::getURL($this->sluggify($payload['title']));
		$item = [
			'id'           => $post_id,
			'title'        => $payload['title'],
			'text'         => $payload['content'],
			'introduction' => $payload['excerpt'],
			'user_id'      => 1,
		];
		$meta = [
			'url' => $post_url,
		];
		$tags = [];

		// Category
		if (isset($payload['category']['data']['name'])) {
			$categoryId = Blog::getCategoryId($payload['category']['data']['name'], 'en');
			if (!$categoryId) {
				$categoryId = Blog::insertCategory([
					'title'    => $payload['category']['data']['name'],
					'language' => BL::getWorkingLanguage()
				], [
					'keywords'              => $payload['category']['data']['name'],
					'keywords_overwrite'    => 'N',
					'description'           => $payload['category']['data']['name'],
					'description_overwrite' => 'N',
					'title'                 => $payload['category']['data']['name'],
					'title_overwrite'       => 'N',
					'url'                   => Blog::getURLForCategory($this->sluggify($payload['category']['data']['name'])),
					'url_overwrite'         => 'N',
					'custom'                => null,
					'data'                  => null,
				]);
			}

			$item['category_id'] = $categoryId;
		}


		// Tags
		if (!empty($payload['tags']['data'])) {
			foreach ($payload['tags']['data'] as $tag) {
				$tags[] = $tag['name'];
			}
		}

		// Author
		if (isset($story['author']['data']['email']) && Users::existsEmail($story['author']['data']['email'])) {
			$author_id = Users::getIdByEmail($story['author']['data']['email']);
			$item['user_id'] = $author_id;
		}

		// Insert
		$revision_id = Blog::insertCompletePost($item, $meta, $tags);

		// Image
		if (isset($payload['featured_image']['data']['url'])) {
			$imagePath = FRONTEND_FILES_PATH . '/blog/images';
			$image_name = $post_url
				. '-' . BL::getWorkingLanguage()
				. '-' . $revision_id
				. '.' . pathinfo($payload['featured_image']['data']['url'], PATHINFO_EXTENSION);
			$imageUri = $imagePath . '/source/' . $image_name;

			// create folders if needed
			$filesystem = new Filesystem();
			$filesystem->mkdir(array($imagePath . '/source', $imagePath . '/128x128'));

			// sideload the image
			copy($payload['featured_image']['data']['url'], $imageUri);

			// upload the image & generate thumbnails
			BackendModel::generateThumbnails($imagePath, $imageUri);

			// add the image to the database without changing the revision id
			Blog::updateRevision($revision_id, array('image' => $image_name));
		}

		// Search index update
		BackendSearchModel::saveIndex(
			'Blog',
			$post_id,
			array(
				'title' => $payload['title'],
				'text'  => $payload['content'],
			)
		);

		$this->ensureJsonResponse([
			'id'        => $post_id,
			'permalink' => SITE_URL . BackendModel::getURLForBlock('Blog', 'detail') . '/' . $post_url,
		]);
	}

	/**
	 * Handle a delete webhook call
	 *
	 * @param Request $request
	 * @return array
	 */
	protected function handleDelete(Request $request) {
		$payload = $request->request->get('data');
		Blog::delete([$payload['external_id']]);

		$this->ensureJsonResponse([
			'id'        => $payload['external_id'],
			'permalink' => null,
		]);
	}

	/**
	 * Handle calls to missing methods on the controller.
	 *
	 * @return mixed
	 */
	protected function missingMethod() {
		$this->ensureJsonResponse();
	}
}
