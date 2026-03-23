<?php
/**
 * Integration Builder
 *
 * Builds Integration objects from JSON data.
 *
 * @package Uncanny_Automator\Api\Services\Integration\Utilities\Store
 * @since 7.0.0
 */

namespace Uncanny_Automator\Api\Services\Integration\Utilities\Store;

use Uncanny_Automator\Api\Components\Integration\Integration;
use Uncanny_Automator\Api\Components\Integration\Integration_Config;
use Uncanny_Automator\Api\Services\Integration\Integration_Registry_Service;
use Uncanny_Automator\Api\Services\Token\Integration\Integration_Token_Registry_Service;
use Uncanny_Automator\Api\Components\Integration\Enums\Integration_Item_Types;
use InvalidArgumentException;

/**
 * Builds Integration domain objects from raw JSON data.
 *
 * @since 7.0.0
 */
class Integration_Builder {

	/**
	 * Details normalizer.
	 *
	 * @var Integration_Details_Normalizer
	 */
	private $normalizer;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->normalizer = new Integration_Details_Normalizer();
	}

	/**
	 * Build integration from complete.json.
	 *
	 * complete.json includes:
	 * - integration_triggers/actions/conditions/loop_filters: All item codes and data
	 * - plugin_details: Plugin metadata (plugin_file, developer_name, etc.)
	 *
	 * @param string $code Integration code
	 * @param array  $json_data Integration data from complete.json
	 *
	 * @return Integration Integration object
	 * @throws InvalidArgumentException If data is invalid
	 */
	public function build_from_json( string $code, array $json_data ) {

		$config_data = array(
			'code'          => $code,
			'name'          => $json_data['integration_name'] ?? '',
			'type'          => $this->determine_type( $json_data ),
			'required_tier' => $this->determine_tier( $json_data ),
			'details'       => $this->normalizer->normalize_details( $json_data ),
			'items'         => array(
				Integration_Item_Types::TRIGGER          => $this->normalizer->normalize_items( $json_data['integration_triggers'] ?? array() ),
				Integration_Item_Types::ACTION           => $this->normalizer->normalize_items( $json_data['integration_actions'] ?? array() ),
				Integration_Item_Types::LOOP_FILTER      => $this->normalizer->normalize_items( $json_data['integration_loop_filters'] ?? array() ),
				Integration_Item_Types::FILTER_CONDITION => $this->normalizer->normalize_items( $json_data['integration_conditions'] ?? array() ),
				Integration_Item_Types::CLOSURE          => 'WP' === $code ? $this->add_wp_closures( $json_data ) : array(),
			),
			'tokens'        => $this->get_integration_tokens( $code ),
		);

		// Add connected status for app integrations.
		if ( 'app' === $config_data['type'] ) {
			$config_data['connected'] = $this->is_app_connected( $code );
		}

		// Add connected status for third-party integrations that require connection.
		$third_party_connection_status = $this->get_third_party_connection_status( $code, $json_data );
		if ( null !== $third_party_connection_status ) {
			$config_data['connected'] = $third_party_connection_status;
		}

		// Validate using Integration component.
		$config      = Integration_Config::from_array( $config_data );
		$integration = new Integration( $config );

		return $integration;
	}

	/**
	 * Determine higher level integration type.
	 *
	 * Maps complete.json integration_type to our internal types.
	 *
	 * Types from complete.json:
	 * - app → saas_app (External APIs with credit usage)
	 * - plugin → wordpress_plugin (WordPress plugin dependencies)
	 * - built-in → wordpress_core OR automator_core (Built-in utilities)
	 * - addon → automator_addon (Automator add-on integrations)
	 * - third_party → third_party (Third-party plugin integrations)
	 *
	 * @param array $data Integration data
	 *
	 * @return string 'plugin', 'built-in', 'app' ( addon & third_party will be retained in plugin details type. )
	 */
	private function determine_type( array $data ) {
		$integration_type = $data['integration_type'] ?? 'plugin';

		$plugin_types = array( 'addon', 'third_party' );
		if ( in_array( $integration_type, $plugin_types, true ) ) {
			return 'plugin';
		}

		return $integration_type;
	}

	/**
	 * Determine required tier.
	 *
	 * Maps complete.json integration_tier directly.
	 * Tiers: 'lite', 'pro-basic', 'pro-plus', 'pro-elite'
	 *
	 * @param array $data Integration data
	 *
	 * @return string 'lite', 'pro-basic', 'pro-plus', or 'pro-elite'
	 */
	private function determine_tier( array $data ) {
		return $data['integration_tier'] ?? 'lite';
	}

	/**
	 * Check if app integration is connected.
	 *
	 * Checks the live integration registry to determine actual connection status.
	 *
	 * @param string $code Integration code
	 *
	 * @return bool True if app is connected, false otherwise
	 */
	private function is_app_connected( string $code ) {
		$integration_data = Integration_Registry_Service::get_instance()->get_integration( $code );
		return isset( $integration_data['connected'] ) && $integration_data['connected'];
	}

	/**
	 * Check if third-party integration and if it requires connection status.
	 *
	 * @param string $code Integration code
	 * @param array $data Integration data
	 *
	 * @return bool|null True if third-party integration requires connection status, false otherwise, null if not applicable
	 */
	private function get_third_party_connection_status( string $code, array $data ) {
		// Only process third-party integrations.
		if ( 'third_party' !== ( $data['integration_type'] ?? '' ) ) {
			return null;
		}

		// Only fetch if integration has settings_url (indicates connection requirement).
		$settings_url = $data['settings_url'] ?? '';
		if ( empty( $settings_url ) ) {
			return null;
		}

		// Get active integration data from registry.
		$integration_data = Integration_Registry_Service::get_instance()->get_integration( $code );
		if ( ! isset( $integration_data['connected'] ) ) {
			// Not set means integration doesn't use connection status.
			return null;
		}

		return (bool) $integration_data['connected'];
	}

	/**
	 * Add WordPress closures to integration.
	 *
	 * @param array $data Integration data
	 *
	 * @return array Integration closures
	 */
	private function add_wp_closures( array $data ) {
		return array(
			'REDIRECT' => array(
				'code'               => 'REDIRECT',
				'type'               => 'closure',
				'is_deprecated'      => false,
				'meta'               => 'REDIRECTURL',
				'sentence'           => array(
					'short'   => esc_html_x( 'Redirect when recipe is completed', 'WordPress', 'uncanny-automator' ),
					/* translators: %1$s: URL to redirect to */
					'dynamic' => esc_html_x( 'Redirect to {{a link:%1$s}} when recipe is completed', 'WordPress', 'uncanny-automator' ),
				),
				'description'        => esc_html_x( 'At the completion of a recipe, you can choose to have users immediately redirect to a specific URL.', 'WordPress', 'uncanny-automator' ),
				'required_tier'      => 'lite',
				'requires_user_data' => false,
				'url_support'        => 'https://automatorplugin.com/knowledge-base/working-with-redirects/',
			),
		);
	}

	/**
	 * Get integration level tokens for integration ( Universal, Global, Extended etc. )
	 *
	 * @param string $code Integration code
	 *
	 * @return array Integration tokens in Integration_Token format (code, name, data_type, requires_user_data)
	 */
	private function get_integration_tokens( string $code ): array {
		return Integration_Token_Registry_Service::get_instance()->get_tokens_for_integration( $code );
	}
}
