<?php

namespace Uncanny_Automator;

/**
 * Class AIRTABLE_SENDWEBHOOK
 *
 * @package Uncanny_Automator
 */
class AIRTABLE_SENDWEBHOOK {

	use Recipe\Actions;

	use Recipe\Webhooks;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {

		$this->setup_action();
	}

	/**
	 * Setting up Webhook trigger
	 *
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'AIRTABLE' );

		$this->set_action_code( 'AIRTABLESENDWEBHOOK' );

		$this->set_action_meta( 'AIRTABLEWEBHOOK' );

		$this->set_author( 'Uncanny Automator' );

		$this->set_support_link(
			Automator()->get_author_support_link(
				$this->get_action_code(),
				'knowledge-base/send-data-to-a-webhook/?utm_source=uncanny_automator&utm_medium=automator-send_data_to_webhook&utm_content=help_button'
			)
		);

		$this->set_requires_user( false );

		/* translators: Action - Uncanny Automator */
		$this->set_sentence(
			sprintf(
			/* translators: Trigger sentence */
				esc_attr__( 'Send data to Airtable {{webhook:%1$s}}', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);

		/* translators: Action - Uncanny Automator */
		$this->set_readable_sentence(
			esc_attr__( 'Send data to Airtable {{webhook}}', 'uncanny-automator' )
		);

		$this->set_options_group(
			Automator()->send_webhook->fields->options_group(
				$this->get_action_meta()
			)
		);

		$this->set_buttons(
			Automator()->send_webhook->fields->buttons(
				$this->get_action_meta(),
				$this->get_support_link()
			)
		);

		$this->set_background_processing( true );

		$this->register_action();
	}
}
