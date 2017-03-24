<?php

namespace Frontend\Modules\Storychief\Command;

use Common\Exception\RedirectException;
use Common\ModulesSettings;
use Symfony\Component\Filesystem\Filesystem;
use Backend\Modules\Blog\Engine\Model as Blog;
use Backend\Modules\Users\Engine\Model as Users;
use Backend\Core\Language\Language as BL;
use Backend\Core\Engine\Model as BackendModel;
use Backend\Modules\Search\Engine\Model as BackendSearchModel;
use Symfony\Component\HttpFoundation\JsonResponse;
use Frontend\Modules\Storychief\Helpers\Helper as Storychief;

final class PublishArticleHandler {

	protected $settings;

	public function __construct(ModulesSettings $settings) {
		$this->settings = $settings;
	}

	/**
	 * @param  PublishArticle $command
	 * @throws RedirectException
	 */
	public function handle(PublishArticle $command) {
		BL::setWorkingLanguage($this->settings->get('Storychief', 'language'));

		$post_id = Blog::getMaximumId() + 1;
		$post_url = Blog::getURL(Storychief::sluggify($command->payload['title']));
		$item = [
			'id'           => $post_id,
			'title'        => $command->payload['title'],
			'text'         => $command->payload['content'],
			'introduction' => $command->payload['excerpt'],
			'user_id'      => 1,
		];
		$meta = [
			'url' => $post_url,
		];
		$tags = [];

		// Category
		if (isset($command->payload['category']['data']['name'])) {
			$categoryId = Blog::getCategoryId($command->payload['category']['data']['name'], 'en');
			if (!$categoryId) {
				$categoryId = Blog::insertCategory([
					'title'    => $command->payload['category']['data']['name'],
					'language' => BL::getWorkingLanguage()
				], [
					'keywords'              => $command->payload['category']['data']['name'],
					'keywords_overwrite'    => 'N',
					'description'           => $command->payload['category']['data']['name'],
					'description_overwrite' => 'N',
					'title'                 => $command->payload['category']['data']['name'],
					'title_overwrite'       => 'N',
					'url'                   => Blog::getURLForCategory(Storychief::sluggify($command->payload['category']['data']['name'])),
					'url_overwrite'         => 'N',
					'custom'                => null,
					'data'                  => null,
				]);
			}

			$item['category_id'] = $categoryId;
		}


		// Tags
		if (!empty($command->payload['tags']['data'])) {
			foreach ($command->payload['tags']['data'] as $tag) {
				$tags[] = $tag['name'];
			}
		}

		// Author
		if (isset($command->payload['author']['data']['email']) && Users::existsEmail($command->payload['author']['data']['email'])) {
			$author_id = Users::getIdByEmail($command->payload['author']['data']['email']);
			$item['user_id'] = $author_id;
		}

		// Meta Tags
		if (isset($command->payload['amphtml']) && !empty($command->payload['amphtml'])) {
			$meta['custom'] = '<link rel="amphtml" href="' . $command->payload['amphtml'] . '" />';
		}

		// Insert
		$revision_id = Blog::insertCompletePost($item, $meta, $tags);

		// Image
		if (isset($command->payload['featured_image']['data']['url'])) {
			$imagePath = FRONTEND_FILES_PATH . '/blog/images';
			$image_name = $post_url
				. '-' . BL::getWorkingLanguage()
				. '-' . $revision_id
				. '.' . pathinfo($command->payload['featured_image']['data']['url'], PATHINFO_EXTENSION);
			$imageUri = $imagePath . '/source/' . $image_name;

			// create folders if needed
			$filesystem = new Filesystem();
			$filesystem->mkdir(array($imagePath . '/source', $imagePath . '/128x128'));

			// sideload the image
			copy($command->payload['featured_image']['data']['url'], $imageUri);

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
				'title' => $command->payload['title'],
				'text'  => $command->payload['content'],
			)
		);

		$response = new JsonResponse(Storychief::hashData($this->settings->get('Storychief', 'api_key'), [
			'id'        => $post_id,
			'permalink' => SITE_URL . BackendModel::getURLForBlock('Blog', 'detail') . '/' . $post_url,
		]), 200);

		throw new RedirectException('', $response);
	}
}
