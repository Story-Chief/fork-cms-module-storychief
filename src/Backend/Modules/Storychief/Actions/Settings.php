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
final class Settings extends BackendBaseActionEdit
{

	/**
	 * Execute the action
	 */
	public function execute(): void
	{
		parent::execute();

		$this->loadForm();
		$this->validateForm();

		$this->parse();
		$this->display();
	}

	/**
	 * Loads the settings form
	 */
	private function loadForm(): void
	{
		// init settings form
		$this->form = new BackendForm('settings');

		$languages = [];
		foreach (BL::getActiveLanguages() as $code) {
			$languages[$code] = BL::getLabel(mb_strtoupper($code), 'Core');
		}
		$this->form->addPassword(
			'api_key',
			$this->get('fork.settings')->get($this->getModule(), 'api_key'),
			255
		);
		$this->form->addDropdown(
			'language',
			$languages,
			$this->get('fork.settings')->get($this->getModule(), 'language')
		);
	}

	/**
	 * Validates the settings form
	 */
	private function validateForm()
	{
		if ($this->form->isSubmitted()) {
			if ($this->form->isCorrect()) {
				// set our settings
				$this->get('fork.settings')->set(
					$this->getModule(),
					'api_key',
					$this->form->getField('api_key')->getValue()
				);

				$this->get('fork.settings')->set(
					$this->getModule(),
					'language',
					$this->form->getField('language')->getValue()
				);

				// redirect to the settings page
				$this->redirect(BackendModel::createURLForAction('Settings') . '&report=saved');
			}
		}
	}
}
