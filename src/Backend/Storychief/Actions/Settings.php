<?php

namespace Backend\Modules\Storychief\Actions;

use Backend\Core\Engine\Base\ActionEdit as BackendBaseActionEdit;
use Backend\Core\Engine\Form as BackendForm;
use Backend\Core\Engine\Model as BackendModel;
use Backend\Core\Language\Language as BL;


/**
 * This is the settings-action (default), it will be used to couple your Storychief
 * account
 */
final class Settings extends BackendBaseActionEdit {

	/**
	 * Execute the action
	 */
	public function execute() {
		parent::execute();

		$this->loadForm();
		$this->validateForm();

		$this->parse();
		$this->display();
	}

	/**
	 * Loads the settings form
	 */
	private function loadForm() {
		// init settings form
		$this->frm = new BackendForm('settings');

		$languages = [];
		foreach (BL::getActiveLanguages() as $code) {
			$languages[$code] = BL::getLabel(mb_strtoupper($code), 'Core');
		}
		$this->frm->addPassword(
			'api_key',
			$this->get('fork.settings')->get($this->URL->getModule(), 'api_key'),
			255
		);
		$this->frm->addDropdown(
			'language',
			$languages,
			$this->get('fork.settings')->get($this->URL->getModule(), 'language')
		);
	}

	/**
	 * Validates the settings form
	 */
	private function validateForm() {
		if ($this->frm->isSubmitted()) {
			if ($this->frm->isCorrect()) {
				// set our settings
				$this->get('fork.settings')->set(
					$this->URL->getModule(),
					'api_key',
					$this->frm->getField('api_key')->getValue()
				);

				$this->get('fork.settings')->set(
					$this->URL->getModule(),
					'language',
					$this->frm->getField('language')->getValue()
				);

				// redirect to the settings page
				$this->redirect(BackendModel::createURLForAction('Settings') . '&report=saved');
			}
		}
	}
}
