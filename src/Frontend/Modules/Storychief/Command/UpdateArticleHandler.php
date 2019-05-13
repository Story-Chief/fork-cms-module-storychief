<?php

namespace Frontend\Modules\Storychief\Command;

use Common\ModulesSettings;
use Common\Exception\RedirectException;
use Backend\Core\Language\Language as BL;
use Symfony\Component\Filesystem\Filesystem;
use Backend\Core\Engine\Model as BackendModel;
use Backend\Modules\Blog\Engine\Model as Blog;
use Backend\Modules\Users\Engine\Model as Users;
use Symfony\Component\HttpFoundation\JsonResponse;
use Frontend\Modules\Storychief\Command\UpdateArticle;
use Frontend\Modules\Storychief\Helpers\Helper as Storychief;
use Backend\Modules\Search\Engine\Model as BackendSearchModel;
use Backend\Modules\Tags\Engine\Model as BackendTagsModel;

final class UpdateArticleHandler
{

	protected $settings;

	public function __construct(ModulesSettings $settings)
	{
		$this->settings = $settings;
	}

	/**
	 * @param  PublishArticle $command
	 * @throws RedirectException
	 */
	public function handle(UpdateArticle $command)
	{
		BL::setWorkingLanguage($this->settings->get('Storychief', 'language'));

		$post_id = $command->payload['external_id'];

		// First get the post so we can fetch the last revision id

		$post = Blog::get($post_id);

		if ($post['title'] === $command->payload['title']) {
			$post_url = $post['url'];
		} else {
			$post_url = Blog::getURL(Storychief::sluggify($command->payload['title']));
		}


		$item = [
			'id'           => $post_id,
			'revision_id'  => $post['revision_id'],
			'title'        => $command->payload['title'],
			'text'         => $command->payload['content'],
			'introduction' => $command->payload['excerpt'],
			'status' 		   => $post['status'],
			'language'		 => $post['language'],
			'publish_on'	 => date("Y-m-d\ H:i:s", $post['publish_on']),
			'edited_on'	   => BackendModel::getUTCDate(),
		];


		// Category
		if (isset($command->payload['category']['data']['name'])) {
			$categoryId = Blog::getCategoryId($command->payload['category']['data']['name'], 'en');
			if (!$categoryId) {
				$categoryId = Blog::insertCategory([
					'title'    => $command->payload['category']['data']['name'],
					'language' => BL::getWorkingLanguage()
				], [
					'keywords'              => $command->payload['category']['data']['name'],
					'keywords_overwrite'    => false,
					'description'           => $command->payload['category']['data']['name'],
					'description_overwrite' => false,
					'title'                 => $command->payload['category']['data']['name'],
					'title_overwrite'       => false,
					'url'                   => Blog::getURLForCategory(Storychief::sluggify($command->payload['category']['data']['name'])),
					'url_overwrite'         => false,
					'custom'                => null,
					'data'                  => null,
				]);
			}

			$item['category_id'] = $categoryId;
		}



		// Authors
		if (isset($command->payload['author']['data']['email']) && Users::existsEmail($command->payload['author']['data']['email'])) {
			$author_id = Users::getIdByEmail($command->payload['author']['data']['email']);
			$item['user_id'] = $author_id;
		}

		// Tags
		$tags = [];
		if (!empty($command->payload['tags']['data'])) {
			foreach ($command->payload['tags']['data'] as $tag) {
				$tags[] = $tag['name'];
			}
		}

		// Meta Tags
		$meta = [
			'url' => $post_url,
		];

		if (!isset($meta['keywords'])) {
			$meta['keywords'] = $item['title'];
		}
		if (!isset($meta['keywords_overwrite'])) {
			$meta['keywords_overwrite'] = false;
		}
		if (!isset($meta['description'])) {
			$meta['description'] = $item['title'];
		}
		if (!isset($meta['description_overwrite'])) {
			$meta['description_overwrite'] = false;
		}
		if (!isset($meta['title'])) {
			$meta['title'] = $item['title'];
		}
		if (!isset($meta['title_overwrite'])) {
			$meta['title_overwrite'] = false;
		}
		if (!isset($meta['url_overwrite'])) {
			$meta['url_overwrite'] = false;
		}
		if (!isset($meta['seo_index'])) {
			$meta['seo_index'] = 'index';
		}
		if (!isset($meta['seo_follow'])) {
			$meta['seo_follow'] = 'follow';
		}

		$item['meta_id'] = BackendModel::getContainer()->get('database')->insert('meta', $meta);

		if (isset($command->payload['amphtml']) && !empty($command->payload['amphtml'])) {
			$meta['custom'] = '<link rel="amphtml" href="' . $command->payload['amphtml'] . '" />';
		}

		$revision_id = Blog::update($item);

		if (!empty($tags)) {
			BackendTagsModel::saveTags($item['id'], implode(',', $tags), 'blog');
		}

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
