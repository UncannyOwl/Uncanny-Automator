<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Recipe\Traits\Filters;

use Uncanny_Automator\App\Transports\Restful\Recipe\Legacy_Rest_Hooks;
use Uncanny_Automator\App\Recipe_Builder\Field\Services\Field_Service;

/**
 * Field-config preparation trait shared by every recipe-entity abstract.
 *
 * Concrete handlers call `prepare_field_config_for_services()` from
 * their `do_update_*` (and `do_add_*` where field config is set at
 * creation time) implementations to flatten the request fields before
 * handing them to the underlying CRUD service. Each consuming abstract
 * supplies its `event_prefix()`, so the legacy REST API's filter runs
 * for triggers and actions only.
 */
trait Filter_Field_Values_Before_Save {

	/**
	 * The grouping the route serves. Returns one of `'item'`, `'block'`,
	 * `'block_modifier'`.
	 *
	 * @return string
	 */
	abstract protected function event_prefix(): string;

	/**
	 * Authoritative field definitions (option_code => definition) for the
	 * entity being saved, used to resolve internal field types
	 * (textarea -> html/markdown) before sanitization.
	 *
	 * Defaults to none. Entities backed by a catalog (items, block-modifiers)
	 * override this to supply the registry-sourced definitions. MUST be
	 * authoritative (registry), never request-derived — see
	 * Field_Types::resolve_internal_type().
	 *
	 * @return array<string, array>
	 */
	protected function authoritative_field_definitions(): array {
		return array();
	}

	/**
	 * Prepare field config for CRUD services. Flattens via Field_Service;
	 * for triggers and actions the legacy REST API's filter then runs.
	 *
	 * @return array Flat config keyed by option_code.
	 */
	protected function prepare_field_config_for_services(): array {
		// Resolve internal field types (textarea -> html/markdown) from the
		// AUTHORITATIVE definitions before flattening, so the sanitizer routes
		// supports_tinymce/supports_markdown fields to raw pass-through instead
		// of stripping their HTML via sanitize_textarea_field(). Entities
		// without a catalog (blocks) supply no definitions — a clean no-op.
		$fields = Field_Service::instance()->resolve_internal_field_types(
			$this->get_fields(),
			$this->authoritative_field_definitions()
		);

		$config = Field_Service::instance()->flatten_fields( $fields, 'rest' );

		// The legacy REST API's filter, for triggers and actions: its return value is still stored.
		if ( 'item' === $this->event_prefix() && method_exists( $this, 'get_wp_post' ) ) {
			$config = Legacy_Rest_Hooks::filter( 'automator_field_values_before_save', array( $config, $this->get_wp_post() ) );
		}

		return $config;
	}
}
