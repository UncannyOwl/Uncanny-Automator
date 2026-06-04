<?php

namespace Uncanny_Automator\Integrations\Wp_Webhooks;

use Uncanny_Automator\Recipe\Trigger;
use Uncanny_Automator\Recipe\Trigger_Definition;

/**
 * Class WPWH_TRIGGERTRIGGERED
 *
 * @package Uncanny_Automator\Integrations\Wp_Webhooks
 *
 * @property Wpwh_Helpers $item_helpers
 */
class WPWH_TRIGGERTRIGGERED extends Trigger {

	/**
	 * Opt this trigger into the lazy-loading path.
	 *
	 * @return Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'WPWHTRIGGERTRIGGERED', 'WPWEBHOOKS' )
			->trigger_meta( 'WPWHTRIGGER' )
			->hook( 'wpwhpro/admin/webhooks/webhook_trigger_sent', 10, 4 );
	}

	/**
	 * Setup the trigger.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		$this->set_is_pro( false );
		$this->set_is_login_required( false );
		// translators: %1$s is the selected webhook trigger.
		$this->set_sentence( sprintf( esc_html_x( '{{A webhook trigger:%1$s}} is triggered', 'WP Webhooks', 'uncanny-automator' ), $this->get_trigger_meta() ) );
		$this->set_readable_sentence( esc_html_x( '{{A webhook trigger}} is triggered', 'WP Webhooks', 'uncanny-automator' ) );
	}

	/**
	 * Define trigger options.
	 *
	 * @return array[]
	 */
	public function options() {
		return array(
			$this->item_helpers->webhook_trigger_field( $this->get_trigger_meta() ),
		);
	}

	/**
	 * Define tokens — dynamic per selected webhook trigger.
	 *
	 * @param array $trigger The trigger settings.
	 * @param array $tokens  Existing tokens.
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {

		$webhook_value = isset( $trigger['meta'][ $this->get_trigger_meta() ] ) ? (string) $trigger['meta'][ $this->get_trigger_meta() ] : '';

		if ( '' === $webhook_value || '-1' === $webhook_value ) {
			return $tokens;
		}

		return array_merge(
			$tokens,
			$this->item_helpers->tokens()->webhook_trigger_tokens( $webhook_value, $this->get_trigger_meta() )
		);
	}

	/**
	 * Validate trigger against hook arguments.
	 *
	 * Hook: wpwhpro/admin/webhooks/webhook_trigger_sent
	 * Args: ( $response, $url, $http_args, $webhook )
	 *
	 * @param array $trigger   The trigger settings.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		if ( ! isset( $trigger['meta'][ $this->get_trigger_meta() ] ) ) {
			return false;
		}

		$webhook = isset( $hook_args[3] ) ? $hook_args[3] : null;

		if ( ! is_array( $webhook ) || empty( $webhook['webhook_name'] ) ) {
			return false;
		}

		$incoming = (string) $webhook['webhook_name'];
		$saved    = (string) $trigger['meta'][ $this->get_trigger_meta() ];

		if ( '-1' === $saved ) {
			return true;
		}

		return $incoming === $saved;
	}

	/**
	 * Hydrate token values from the webhook payload.
	 *
	 * @param array $trigger   The completed trigger settings.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		$http_args = isset( $hook_args[2] ) && is_array( $hook_args[2] ) ? $hook_args[2] : array();
		$webhook   = isset( $hook_args[3] ) && is_array( $hook_args[3] ) ? $hook_args[3] : array();

		$incoming         = isset( $webhook['webhook_name'] ) ? (string) $webhook['webhook_name'] : '';
		$body_data_format = isset( $webhook['settings']['wpwhpro_trigger_response_type'] ) ? (string) $webhook['settings']['wpwhpro_trigger_response_type'] : 'json';
		$raw_body         = isset( $http_args['body'] ) ? $http_args['body'] : '';

		$payload = $this->item_helpers->decode_payload( $raw_body, $body_data_format );

		$base = array(
			$this->get_trigger_meta() => $incoming,
		);

		return array_merge( $base, $this->item_helpers->tokens()->hydrate_dynamic_payload_tokens( $incoming, $payload ) );
	}
}
