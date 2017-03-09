<?php

namespace Frontend\Modules\Storychief\Command;

use Backend\Modules\Blog\Engine\Model as Blog;
use Common\ModulesSettings;
use Frontend\Modules\Storychief\Helpers\Helper as Storychief;
use Common\Exception\RedirectException;
use Symfony\Component\HttpFoundation\JsonResponse;

final class DeleteArticleHandler {

	protected $settings;

	public function __construct(ModulesSettings $settings) {
		$this->settings = $settings;
	}

	/**
	 * @param DeleteArticle $command
	 * @throws RedirectException
	 */
	public function handle(DeleteArticle $command) {
		Blog::delete([$command->payload['external_id']]);

		$response = new JsonResponse(Storychief::hashData($this->settings->get('Storychief', 'api_key'), [
			'id'        => $command->payload['external_id'],
			'permalink' => null,
		]), 200);

		throw new RedirectException('', $response);
	}
}
