<?php

namespace Uncanny_Automator\Integrations\Google_Site_Kit;

/**
 * Class Google_Site_Kit_Module_Activated
 *
 * Site Kit has no native "module activated" hook — it writes the active-module
 * list to the `googlesitekit_active_modules` option. This trigger listens to
 * both the add_option and update_option core hooks for that option and isolates
 * the newly-activated slug(s) with array_diff( new, old ).
 *
 * @package Uncanny_Automator\Integrations\Google_Site_Kit
 *
 * @property Google_Site_Kit_Helpers $item_helpers
 */
class Google_Site_Kit_Module_Activated extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Opt this trigger into the lazy loading path.
	 */
	public static function definition() {
		return self::new_definition( 'GOOGLE_SITE_KIT_MODULE_ACTIVATED', 'GOOGLE_SITE_KIT' )
			->trigger_type( 'anonymous' )
			->trigger_meta( 'GOOGLE_SITE_KIT_MODULE' )
			->hook( 'add_option_googlesitekit_active_modules', 10, 2 )
			->hook( 'update_option_googlesitekit_active_modules', 10, 3 );
	}

	/**
	 * Setup trigger.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		$this->set_is_login_required( false );
		$this->set_sentence(
			sprintf(
				// translators: %1$s: Module.
				esc_html_x( '{{A module:%1$s}} is activated', 'Site Kit by Google', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( '{{A module}} is activated', 'Site Kit by Google', 'uncanny-automator' ) );
	}

	/**
	 * Trigger options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code'     => $this->get_trigger_meta(),
				'label'           => esc_html_x( 'Module', 'Site Kit by Google', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'remote_data'     => $this->item_helpers->remote_data_load_config( 'modules' ),
			),
		);
	}

	/**
	 * Validate trigger — a (selected) module appears in the newly-activated diff.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		$activated = $this->resolve_activated_modules( $hook_args );

		if ( empty( $activated ) ) {
			return false;
		}

		$selected = (string) ( $trigger['meta'][ $this->get_trigger_meta() ] ?? Google_Site_Kit_Helpers::ANY );

		if ( Google_Site_Kit_Helpers::ANY !== $selected && ! in_array( $selected, $activated, true ) ) {
			return false;
		}

		$user_id = get_current_user_id();
		if ( $user_id > 0 ) {
			$this->set_user_id( $user_id );
		}

		return true;
	}

	/**
	 * Define tokens.
	 *
	 * @param array $trigger
	 * @param array $tokens
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array_merge(
			$tokens,
			array(
				array(
					'tokenId'   => 'GOOGLE_SITE_KIT_MODULE_SLUG',
					'tokenName' => esc_html_x( 'Module slug', 'Site Kit by Google', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
				array(
					'tokenId'   => 'GOOGLE_SITE_KIT_MODULE_NAME',
					'tokenName' => esc_html_x( 'Module name', 'Site Kit by Google', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
			)
		);
	}

	/**
	 * Hydrate tokens.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		$activated = $this->resolve_activated_modules( $hook_args );
		$selected  = (string) ( $trigger['meta'][ $this->get_trigger_meta() ] ?? Google_Site_Kit_Helpers::ANY );

		$slug = ( Google_Site_Kit_Helpers::ANY !== $selected && in_array( $selected, $activated, true ) )
			? $selected
			: ( isset( $activated[0] ) ? $activated[0] : '' );

		return array(
			'GOOGLE_SITE_KIT_MODULE_SLUG' => $slug,
			'GOOGLE_SITE_KIT_MODULE_NAME' => $this->item_helpers->get_module_name( $slug ),
		);
	}

	/**
	 * Newly-activated module slugs from the option-change hook args.
	 *
	 * add_option fires `( $option, $value )` (arg0 = option name, a string);
	 * update_option fires `( $old_value, $new_value, $option )` (arg0 = array).
	 * Distinguish by arg0's type so the diff is computed correctly either way.
	 *
	 * @param array $hook_args
	 *
	 * @return string[]
	 */
	private function resolve_activated_modules( $hook_args ) {

		$arg0 = isset( $hook_args[0] ) ? $hook_args[0] : null;

		if ( is_string( $arg0 ) ) {
			$old = array();
			$new = isset( $hook_args[1] ) ? (array) $hook_args[1] : array();
		} else {
			$old = is_array( $arg0 ) ? $arg0 : array();
			$new = isset( $hook_args[1] ) ? (array) $hook_args[1] : array();
		}

		return array_values( array_diff( $new, $old ) );
	}
}
