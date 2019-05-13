<?php

namespace Backend\Modules\Storychief\Installer;

use Backend\Core\Engine\Model;
use Backend\Core\Installer\ModuleInstaller;
use Common\Exception\RedirectException;
use Common\ModuleExtraType;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Installer for the analytics module
 */
class Installer extends ModuleInstaller
{
	/**
	 * Install the module
	 *
	 */
	private $postId;

	public function install(): void
	{
		$this->addModule('Storychief');
		$this->importLocale(__DIR__ . '/Data/locale.xml');
		$this->configureBackendNavigation();
		$this->configureBackendRights();
		$this->configureFrontendExtras();
		$this->configureFrontendPages();

		// install the blog module if needed.
		if (!Model::isModuleInstalled('Blog')) {
			$response = new RedirectResponse(Model::createURLForAction('install_module') . '&module=Blog');
			throw new RedirectException('', $response);
		}
	}


	private function configureFrontendPages(): void
	{
		foreach ($this->getLanguages() as $language) {

			$this->insertPage(
				['title' => 'Storychief Webhook', 'language' => $language],
				null,
				['extra_id' => $this->postId, 'position' => 'main']
			);
		}
	}

	private function configureFrontendExtras(): void
	{
		$this->postId = $this->insertExtra($this->getModule(), ModuleExtraType::block(), 'Storychief', 'Index');
	}

	private function configureBackendNavigation(): void
	{
		// Set navigation for "Settings"
		$navigationSettingsId = $this->setNavigation(null, 'Settings');
		$navigationModulesId = $this->setNavigation($navigationSettingsId, 'Modules');
		$this->setNavigation($navigationModulesId, $this->getModule(), 'storychief/settings');
	}

	private function configureBackendRights(): void
	{
		// add the needed rights
		$this->setModuleRights(1, $this->getModule());
		$this->setActionRights(1, $this->getModule(), 'Settings');
	}
}
