<?php

namespace Uncanny_Automator\Integrations\Wp_Webhooks;

use Uncanny_Automator\Recipe\Abstract_Helpers;

/**
 * Class Wpwh_Helpers
 *
 * @package Uncanny_Automator\Integrations\Wp_Webhooks
 */
class Wpwh_Helpers extends Abstract_Helpers {

	/**
	 * Lazy-instantiated tokens helper.
	 *
	 * @var Wpwh_Tokens|null
	 */
	private $tokens = null;

	/**
	 * Get the tokens helper.
	 *
	 * @return Wpwh_Tokens
	 */
	public function tokens() {

		if ( null === $this->tokens ) {
			$this->tokens = new Wpwh_Tokens( $this );
		}

		return $this->tokens;
	}

	/**
	 * Build the webhook trigger select field config.
	 *
	 * @param string $option_code Field option_code (defaults to 'WPWHTRIGGER').
	 * @param string $label       Optional override label.
	 *
	 * @return array
	 */
	public function webhook_trigger_field( $option_code = 'WPWHTRIGGER', $label = '' ) {

		if ( '' === $label ) {
			$label = esc_html_x( 'Webhook triggers', 'WP Webhooks', 'uncanny-automator' );
		}

		return array(
			'option_code' => $option_code,
			'label'       => $label,
			'input_type'  => 'select',
			'required'    => true,
			'options'     => array(),
			'remote_data' => $this->remote_data_load_config( 'webhook_triggers' ),
		);
	}

	/**
	 * Fetch the list of active WP Webhooks trigger names.
	 *
	 * Reachable via `POST /wp-json/uap/v2/remote-data/wpwebhooks/webhook_triggers`.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_webhook_triggers( $request ): array {

		unset( $request );

		$options = array(
			array(
				'text'  => esc_html_x( 'Any trigger', 'WP Webhooks', 'uncanny-automator' ),
				'value' => '-1',
			),
		);

		if ( ! function_exists( 'WPWHPRO' ) ) {
			return $this->remote_data_success( $options );
		}

		$triggers        = WPWHPRO()->webhook->get_triggers();
		$active_webhooks = WPWHPRO()->settings->get_active_webhooks( 'all' );

		if ( empty( $triggers ) || ! is_array( $triggers ) ) {
			return $this->remote_data_success( $options );
		}

		foreach ( $triggers as $trigger ) {
			if ( ! isset( $trigger['trigger'], $active_webhooks['triggers'][ $trigger['trigger'] ] ) ) {
				continue;
			}
			$options[] = array(
				'text'  => isset( $trigger['name'] ) ? $trigger['name'] : $trigger['trigger'],
				'value' => $trigger['trigger'],
			);
		}

		return $this->remote_data_success( $options );
	}

	/**
	 * Match recipe condition for the incoming webhook trigger name.
	 *
	 * @param string      $action       The incoming webhook_name.
	 * @param array|null  $recipes      The candidate recipes.
	 * @param string|null $trigger_meta The trigger meta key.
	 *
	 * @return array|false
	 */
	public function match_action_condition( $action, $recipes = null, $trigger_meta = null ) {

		if ( null === $recipes ) {
			return false;
		}

		$recipe_ids = array();

		foreach ( $recipes as $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				if ( ! isset( $trigger['meta'][ $trigger_meta ] ) ) {
					continue;
				}
				$saved = $trigger['meta'][ $trigger_meta ];
				if ( $saved === $action || '-1' === (string) $saved ) {
					$recipe_ids[ $recipe['ID'] ] = $recipe['ID'];
					break;
				}
			}
		}

		if ( ! empty( $recipe_ids ) ) {
			return array(
				'recipe_ids' => $recipe_ids,
				'result'     => true,
			);
		}

		return false;
	}

	/**
	 * Persist the raw webhook payload into the trigger log meta so tokens can hydrate.
	 *
	 * @param mixed $params The decoded payload.
	 * @param array $args   Trigger log identifiers (trigger_id, user_id, trigger_log_id, run_number, meta_key).
	 *
	 * @return mixed
	 */
	public function extract_and_save_data( $params, $args ) {

		$data = $params;

		if ( ! $data ) {
			return $data;
		}

		$insert = array(
			'user_id'        => (int) $args['user_id'],
			'trigger_id'     => (int) $args['trigger_id'],
			'trigger_log_id' => (int) $args['trigger_log_id'],
			'meta_key'       => (string) $args['meta_key'],
			'meta_value'     => maybe_serialize( $data ),
			'run_number'     => (int) $args['run_number'],
		);

		\Automator()->insert_trigger_meta( $insert );

		return $data;
	}

	/**
	 * Convert a SimpleXMLElement payload into a plain array.
	 *
	 * @param \SimpleXMLElement $parent The root element.
	 *
	 * @return array
	 */
	public function xml_to_array( \SimpleXMLElement $parent ) {

		$array = array();

		foreach ( $parent as $name => $element ) {
			( $node = &$array[ $name ] )
				&& ( 1 === count( $node ) ? $node = array( $node ) : 1 )
				&& $node = &$node[];

			$node = $element->count() ? $this->xml_to_array( $element ) : trim( $element );
		}

		return $array;
	}

	/**
	 * Decode the webhook payload based on the trigger's response type.
	 *
	 * @param string $raw_body         Raw body string from the outbound request.
	 * @param string $body_data_format 'json' | 'xml' | other (treated as JSON).
	 *
	 * @return mixed Decoded array on success, original string otherwise.
	 */
	public function decode_payload( $raw_body, $body_data_format = 'json' ) {

		if ( 'xml' === $body_data_format ) {
			try {
				$xml = new \SimpleXMLElement( $raw_body );
				return $this->xml_to_array( $xml );
			} catch ( \Throwable $e ) {
				unset( $e );
				return array();
			}
		}

		$decoded = json_decode( $raw_body, true );

		return null === $decoded ? array() : $decoded;
	}
}
