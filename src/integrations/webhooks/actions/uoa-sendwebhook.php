<?php

namespace Uncanny_Automator;

/**
 * Class UOA_SENDWEBHOOK
 *
 * @package Uncanny_Automator
 */
class UOA_SENDWEBHOOK {

	use Recipe\Actions;

	use Recipe\Webhooks;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {

		$this->setup_action();

		// Migrate existing WP -> Webhook action.
		$this->maybe_migrate_wp_webhooks();

	}

	/**
	 * Setting up Webhook trigger
	 *
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'WEBHOOKS' );

		$this->set_action_code( 'WPSENDWEBHOOK' );

		$this->set_action_meta( 'WPWEBHOOK' );

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
				esc_attr__( 'Send data to {{a webhook:%1$s}}', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);

		/* translators: Action - Uncanny Automator */
		$this->set_readable_sentence(
			esc_attr__( 'Send data to {{a webhook}}', 'uncanny-automator' )
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

		$this->register_action();
	}

	/**
	 * Migrate all existing wp -> webhook actions.
	 *
	 * @return void
	 */
	public function maybe_migrate_wp_webhooks() {

		$option_key = 'automator_wpwebhooks_action_moved';

		if ( 'yes' === get_option( $option_key ) ) {
			return;
		}

		global $wpdb;

		$current_actions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id FROM $wpdb->postmeta WHERE meta_value = %s AND meta_key = %s",
				'WPSENDWEBHOOK',
				'code'
			)
		);

		if ( empty( $current_actions ) ) {
			update_option( $option_key, 'yes', false );
			return;
		}

		foreach ( $current_actions as $action ) {
			$action_id = $action->post_id;
			update_post_meta( $action_id, 'integration', 'WEBHOOKS' );
			update_post_meta( $action_id, 'integration_name', 'Webhooks' );
		}

		update_option( $option_key, 'yes', false );

	}
}
