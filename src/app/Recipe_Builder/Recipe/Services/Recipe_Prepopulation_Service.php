<?php

declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Services;

use Uncanny_Automator\App\Recipe_Builder\Recipe\Settings\Services\Recipe_Settings_Service;
use Uncanny_Automator\App\Recipe_Builder\Trigger\Services\Trigger_CRUD_Service;
use Uncanny_Automator\App\Recipe_Builder\Trigger\Services\Trigger_Registry_Service;
use WP_Error;

/**
 * Bootstraps a brand-new recipe from a partner quick link.
 *
 * Composes the partner "quick link" recipe bootstrap: a brand-new recipe is
 * assigned its type (user/anonymous), given a single trigger — with the
 * optional single option value riding the create call's configuration — and,
 * when a value was applied, the trigger is published. Extracted from the
 * legacy Populate_From_Query flow, which previously drove the legacy
 * Recipe_Post_Rest_Api callbacks; this service fans out to the canonical
 * CRUD/settings services instead.
 *
 * Trigger_CRUD_Service::add_to_recipe() expects the caller to supply the
 * definition-shaped config (integration/sentence/type) — the same
 * registry enrichment the REST and MCP transports perform — so this service
 * resolves the definition through Trigger_Registry_Service before creating.
 *
 * The URL query-param contract (parsing, nonce, capability, the public
 * `automator_trigger_populated_from_query` hook) stays in the src/core entry
 * class; this service is transport-agnostic and takes a normalized array.
 */
class Recipe_Prepopulation_Service {

	/**
	 * Singleton instance.
	 *
	 * @var Recipe_Prepopulation_Service|null
	 */
	private static $instance = null;

	/**
	 * Recipe settings service handling the recipe-type write.
	 *
	 * @var Recipe_Settings_Service
	 */
	private $settings_service;

	/**
	 * Trigger CRUD service handling trigger create/status.
	 *
	 * @var Trigger_CRUD_Service
	 */
	private $trigger_crud;

	/**
	 * Trigger registry service resolving trigger definitions by code.
	 *
	 * @var Trigger_Registry_Service
	 */
	private $trigger_registry;

	/**
	 * Constructor with lazy-default dependency injection.
	 *
	 * @param Recipe_Settings_Service|null  $settings_service Optional settings service.
	 * @param Trigger_CRUD_Service|null     $trigger_crud     Optional trigger CRUD service.
	 * @param Trigger_Registry_Service|null $trigger_registry Optional trigger registry service.
	 */
	public function __construct(
		?Recipe_Settings_Service $settings_service = null,
		?Trigger_CRUD_Service $trigger_crud = null,
		?Trigger_Registry_Service $trigger_registry = null
	) {
		$this->settings_service = $settings_service ?? Recipe_Settings_Service::instance();
		$this->trigger_crud     = $trigger_crud ?? Trigger_CRUD_Service::instance();
		$this->trigger_registry = $trigger_registry ?? Trigger_Registry_Service::instance();
	}

	/**
	 * Get singleton instance.
	 *
	 * @return Recipe_Prepopulation_Service
	 */
	public static function instance(): Recipe_Prepopulation_Service {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Populate a new recipe from a normalized parameter map.
	 *
	 * Operations run in order: set recipe type → resolve trigger definition →
	 * add trigger (option value riding `configuration`) → (optional) publish
	 * the trigger. The option value + publish pair applies only when
	 * option_code, option_value AND option_value_readable are all present —
	 * mirroring the legacy all-or-nothing gate. Publishing is fail-closed: if
	 * the trigger's persisted config fails validation the trigger stays draft
	 * and the error is returned. Created posts are NOT rolled back on
	 * later-step failure — parity with the legacy flow.
	 *
	 * Deliberately not wrapped in Recipe_Change_Events::operation(): each
	 * service call is its own operation, so the quick-link trigger reports its
	 * own `created` event (Pro's Schedule, webhook URL and magic button
	 * listeners need it) instead of riding the recipe type update.
	 *
	 * @param int   $recipe_id Recipe post ID (a freshly created recipe).
	 * @param array $params {
	 *     Normalized prepopulation parameters.
	 *     @type string $trigger_code          Trigger code (required).
	 *     @type bool   $is_anon               True → anonymous recipe type. Default false.
	 *     @type string $option_code           Option code to set (optional).
	 *     @type string $option_value          Option value (optional).
	 *     @type string $option_value_readable Readable option value (optional).
	 * }
	 * @return int|WP_Error Created trigger ID on success, WP_Error on failure.
	 */
	public function populate( int $recipe_id, array $params ) {

		if ( empty( $params['trigger_code'] ) ) {
			return new WP_Error(
				'missing_trigger_code',
				esc_html_x( 'Trigger code is required.', 'Recipe prepopulation error', 'uncanny-automator' )
			);
		}

		$trigger_code = (string) $params['trigger_code'];

		$recipe_type = ! empty( $params['is_anon'] ) ? 'anonymous' : 'user';
		$type_result = $this->settings_service->set_recipe_type( $recipe_id, $recipe_type );
		if ( is_wp_error( $type_result ) ) {
			return $type_result;
		}

		$definition = $this->trigger_registry->get_trigger_definition( $trigger_code, false );
		if ( is_wp_error( $definition ) ) {
			return $definition;
		}
		if ( empty( $definition ) ) {
			return new WP_Error(
				'trigger_definition_not_found',
				esc_html_x( 'Trigger definition not found.', 'Recipe prepopulation error', 'uncanny-automator' )
			);
		}

		$has_option    = $this->has_complete_option( $params );
		$configuration = array();
		if ( $has_option ) {
			$option_code                                 = (string) $params['option_code'];
			$configuration[ $option_code ]               = (string) $params['option_value'];
			$configuration[ $option_code . '_readable' ] = (string) $params['option_value_readable'];
		}

		$added = $this->trigger_crud->add_to_recipe(
			$recipe_id,
			$trigger_code,
			array(
				'integration'   => $definition['integration'] ?? '',
				'sentence'      => $definition['sentence'] ?? '',
				'meta_code'     => $trigger_code,
				'type'          => $definition['trigger_type'] ?? '',
				'tokens'        => $definition['tokens'] ?? array(),
				'configuration' => $configuration,
			)
		);
		if ( is_wp_error( $added ) ) {
			return $added;
		}

		$trigger_id = (int) ( $added['trigger_id'] ?? 0 );
		if ( $trigger_id <= 0 ) {
			return new WP_Error(
				'trigger_id_missing',
				esc_html_x( 'Trigger was created but no trigger ID was returned.', 'Recipe prepopulation error', 'uncanny-automator' )
			);
		}

		if ( ! $has_option ) {
			return $trigger_id;
		}

		$published = $this->trigger_crud->set_status( $trigger_id, $recipe_id, true );
		if ( is_wp_error( $published ) ) {
			return $published;
		}

		return $trigger_id;
	}

	/**
	 * Whether the parameter map carries a complete option triple.
	 *
	 * All three of option_code / option_value / option_value_readable must be
	 * present — the legacy flow only applied a value when the full triple
	 * arrived on the query string.
	 *
	 * @param array $params Normalized prepopulation parameters.
	 * @return bool True when the option triple is complete.
	 */
	private function has_complete_option( array $params ): bool {
		return isset( $params['option_code'], $params['option_value'], $params['option_value_readable'] )
			&& '' !== (string) $params['option_code'];
	}
}
