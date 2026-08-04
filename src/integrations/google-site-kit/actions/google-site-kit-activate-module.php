<?php

namespace Uncanny_Automator\Integrations\Google_Site_Kit;

use Uncanny_Automator\Recipe\Action;

/**
 * Class Google_Site_Kit_Activate_Module
 *
 * Activates a Site Kit module by appending its slug to the
 * `googlesitekit_active_modules` option (Site Kit exposes no public Modules
 * accessor, so the documented option write mirrors set_active_modules_option()).
 *
 * Note: this activates the module at the option level only — it does not run a
 * module's on_activation() setup, and the module still needs configuration in
 * the Site Kit dashboard before it is "connected".
 *
 * @package Uncanny_Automator\Integrations\Google_Site_Kit
 *
 * @property Google_Site_Kit_Helpers $item_helpers
 */
class Google_Site_Kit_Activate_Module extends Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'GOOGLE_SITE_KIT' );
		$this->set_requires_user( false );
		$this->set_action_code( 'GOOGLE_SITE_KIT_ACTIVATE_MODULE' );
		$this->set_action_meta( 'GOOGLE_SITE_KIT_MODULE' );
		$this->set_sentence(
			sprintf(
				// translators: %1$s: Module.
				esc_html_x( 'Activate {{a module:%1$s}}', 'Site Kit by Google', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Activate {{a module}}', 'Site Kit by Google', 'uncanny-automator' ) );
	}

	/**
	 * Options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code' => $this->get_action_meta(),
				'label'       => esc_html_x( 'Module', 'Site Kit by Google', 'uncanny-automator' ),
				'input_type'  => 'select',
				'required'    => true,
				'options'     => array(),
				'remote_data' => $this->item_helpers->remote_data_load_config( 'modules_strict' ),
			),
		);
	}

	/**
	 * Define output tokens.
	 *
	 * @return array
	 */
	public function define_tokens() {
		return array(
			'GOOGLE_SITE_KIT_MODULE_SLUG' => array(
				'name' => esc_html_x( 'Module slug', 'Site Kit by Google', 'uncanny-automator' ),
				'type' => 'text',
			),
			'GOOGLE_SITE_KIT_MODULE_NAME' => array(
				'name' => esc_html_x( 'Module name', 'Site Kit by Google', 'uncanny-automator' ),
				'type' => 'text',
			),
		);
	}

	/**
	 * Process action.
	 *
	 * @param int   $user_id     The user ID.
	 * @param array $action_data The action data.
	 * @param int   $recipe_id   The recipe ID.
	 * @param array $args        The args.
	 * @param array $parsed      The parsed options.
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		if ( ! defined( 'GOOGLESITEKIT_VERSION' ) ) {
			$this->add_log_error( 'Site Kit by Google is not active.' );
			return false;
		}

		$slug = isset( $parsed[ $this->get_action_meta() ] ) ? sanitize_text_field( $parsed[ $this->get_action_meta() ] ) : '';

		if ( '' === $slug ) {
			$this->add_log_error( 'No module selected.' );
			return false;
		}

		if ( ! array_key_exists( $slug, $this->item_helpers->get_activatable_modules() ) ) {
			$this->add_log_error( sprintf( 'Unknown or non-activatable module: %s', $slug ) );
			return false;
		}

		$active = get_option( Google_Site_Kit_Helpers::ACTIVE_MODULES_OPTION, array() );
		$active = is_array( $active ) ? $active : array();

		if ( ! in_array( $slug, $active, true ) ) {
			$active[] = $slug;
			update_option( Google_Site_Kit_Helpers::ACTIVE_MODULES_OPTION, array_values( $active ) );
		}

		$this->hydrate_tokens(
			array(
				'GOOGLE_SITE_KIT_MODULE_SLUG' => $slug,
				'GOOGLE_SITE_KIT_MODULE_NAME' => $this->item_helpers->get_module_name( $slug ),
			)
		);

		return true;
	}
}
