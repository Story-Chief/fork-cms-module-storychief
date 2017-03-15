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
class Installer extends ModuleInstaller {
	/**
	 * Install the module
	 *
	 */
	public function install() {
		$this->addModule('Storychief');

		// import locale
		$this->importLocale(__DIR__ . '/Data/locale.xml');

		// add the needed rights
		$this->setModuleRights(1, $this->getModule());
		$this->setActionRights(1, $this->getModule(), 'Settings');

		// settings navigation
		$navigationSettingsId = $this->setNavigation(null, 'Settings');
		$navigationModulesId = $this->setNavigation($navigationSettingsId, 'Modules');
		$this->setNavigation($navigationModulesId, $this->getModule(), 'storychief/settings');

		// add extra's
		$postId = $this->insertExtra($this->getModule(), ModuleExtraType::block(), 'Storychief', null, null, 'N', 1000);

		// loop languages
		foreach ($this->getLanguages() as $language) {
			$this->insertPage(
				array(
					'title'          => 'Storychief Post',
					'language'       => $language,
					'allow_edit'     => 'N',
					'allow_delete'   => 'N',
					'allow_children' => 'N',
					'allow_move'     => 'N',
					'type'           => 'root'
				),
				array(
					'url' => 'storychief-webhook',
				),
				array('extra_id' => $postId, 'position' => 'main')
			);
		}

		// install the blog module if needed.
		if (!Model::isModuleInstalled('Blog')) {
			$response = new RedirectResponse(Model::createURLForAction('install_module') .'&module=Blog');
			throw new RedirectException('', $response);
		}
	}
}
