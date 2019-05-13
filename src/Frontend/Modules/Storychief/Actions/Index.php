<?php

namespace Frontend\Modules\Storychief\Actions;

use Backend\Core\Engine\Model;
use Common\Exception\RedirectException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Frontend\Modules\Storychief\Command\DeleteArticle;
use Frontend\Modules\Storychief\Command\UpdateArticle;
use Frontend\Modules\Storychief\Command\PublishArticle;
use Frontend\Core\Engine\Base\Block as FrontendBaseBlock;

class Index extends FrontendBaseBlock
{


	/**
	 * Execute the action
	 */
	public function execute(): void
	{
		parent::execute();

		$request = $this->getParsedRequest();
		$commandBus = Model::get('command_bus');

		if (!$this->isValidRequest($request)) throw new RedirectException('', new JsonResponse(null, 400));

		if (isset($request->request->get('meta')['fb-page-ids'])) {
			$this->updateSiteHtmlHeader(explode(',', $request->request->get('meta')['fb-page-ids']));
		}

		switch ($request->request->get('meta')['event']) {
			case 'publish':
				$commandBus->handle(new PublishArticle($request->request->get('data')));
				break;
			case 'delete':
				$commandBus->handle(new DeleteArticle($request->request->get('data')));
				break;
			case 'update':
				$commandBus->handle(new UpdateArticle($request->request->get('data')));
				break;
		}

		throw new RedirectException('', new JsonResponse(null, 200));
	}

	/**
	 * @return Request
	 */
	protected function getParsedRequest()
	{
		$request = $this->getRequest();
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
	protected function isValidRequest(Request $request)
	{
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
	 * Updates Fork Core setting with FB page id's
	 *
	 * @param array $pageIds
	 */
	protected function updateSiteHtmlHeader(array $pageIds)
	{
		$regex = '/<meta\s+property="fb:pages"\s+content=".*"\s*\/>/';
		$matches = [];
		$haystack = $this->get('fork.settings')->get('Core', 'site_html_header');

		preg_match($regex, $haystack, $matches);

		foreach ($matches as $match) {
			preg_match('#content="(.*?)"#', $match, $match);
			$ids = explode(',', $match[1]);
			foreach ($ids as $id) {
				if (!in_array(trim($id), $pageIds)) {
					$pageIds[] = $id;
				}
			}
		}

		$siteHtmlHeader = '<meta property="fb:pages" content="' . implode(',', $pageIds) . '"/>' . preg_replace($regex, '', $haystack);
		$this->get('fork.settings')->set(
			'Core',
			'site_html_header',
			$siteHtmlHeader
		);
	}
}
