<?php

namespace Uncanny_Automator;

/**
 * Class IFTTT_SENDWEBHOOK
 *
 * @package Uncanny_Automator
 */
class IFTTT_SENDWEBHOOK {

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
		$this->set_integration( 'IFTTT' );
		$this->set_action_code( 'IFTTTSENDWEBHOOK' );
		$this->set_action_meta( 'WEBHOOK' );
		$this->set_author( 'Uncanny Automator' );
		$this->set_support_link( Automator()->get_author_support_link( $this->action_code, 'knowledge-base/send-data-to-a-ifttt-webhook?utm_source=uncanny_automator&utm_medium=ifttt-send_data_to_webhook&utm_content=help_button' ) );
		$this->set_requires_user( false );
		/* translators: Action - IFTTT */
		$this->set_sentence( sprintf( esc_attr__( 'Send data to IFTTT {{webhook:%1$s}}', 'uncanny-automator' ), $this->get_action_meta() ) );
		/* translators: Action - IFTTT */
		$this->set_readable_sentence( esc_attr__( 'Send data to IFTTT {{webhook}}', 'uncanny-automator' ) );
		$this->set_options_group(
			Automator()->send_webhook->fields->options_group(
				$this->get_action_meta(),
				true,
				'json',
				array(
					'json',
					'x-www-form-urlencoded',
				)
			)
		);
		$this->set_buttons( Automator()->send_webhook->fields->buttons( $this->get_action_meta(), $this->get_support_link() ) );
		$this->register_action();
	}
}
