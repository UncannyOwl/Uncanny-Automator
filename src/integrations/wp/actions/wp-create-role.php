<?php
/**
 * Creates a new WordPress role
 *
 * @package Uncanny_Automator
 */

namespace Uncanny_Automator;

use Uncanny_Automator\Recipe\Action;

/**
 * Class WP_CREATE_ROLE
 *
 * Creates a new WordPress role with specified capabilities.
 *
 * @package Uncanny_Automator
 */
class WP_CREATE_ROLE extends Action {

	/**
	 * Setup action method.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'WP' );
		$this->set_action_code( 'WP_CREATE_ROLE' );
		$this->set_action_meta( 'WP_ROLE' );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				// translators: %1$s is the role name/meta field
				esc_html_x( 'Create {{a new role:%1$s}}', 'WordPress', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Create {{a new role}}', 'WordPress', 'uncanny-automator' ) );
	}

	/**
	 * Define the action's options.
	 *
	 * @return array The options configuration.
	 */
	public function options() {
		$capabilities = $this->get_capabilities_options();

		return array(
			Automator()->helpers->recipe->field->text(
				array(
					'option_code' => 'role_name',
					'label'       => esc_html_x( 'Role name', 'WordPress', 'uncanny-automator' ),
					'input_type'  => 'text',
					'required'    => true,
				)
			),
			Automator()->helpers->recipe->field->text(
				array(
					'option_code' => 'role_slug',
					'label'       => esc_html_x( 'Role slug', 'WordPress', 'uncanny-automator' ),
					'input_type'  => 'text',
					'required'    => true,
					'description' => esc_html_x( 'The role slug must be unique and contain only lowercase letters, numbers, and underscores.', 'WordPress', 'uncanny-automator' ),
				)
			),
			Automator()->helpers->recipe->field->text(
				array(
					'option_code' => 'display_name',
					'label'       => esc_html_x( 'Display name', 'WordPress', 'uncanny-automator' ),
					'input_type'  => 'text',
					'required'    => true,
				)
			),
			Automator()->helpers->recipe->field->select_field_args(
				array(
					'option_code'              => 'CAPABILITIES',
					'options'                  => $capabilities,
					'label'                    => esc_html_x( 'Capabilities', 'WordPress', 'uncanny-automator' ),
					'required'                 => true,
					'supports_multiple_values' => true,
					'custom_value_description' => esc_html_x( 'Capability', 'WordPress', 'uncanny-automator' ),
				)
			),
		);
	}

	/**
	 * Get all WordPress capabilities as options.
	 *
	 * Retrieves all capabilities from existing roles and formats them for the select field.
	 *
	 * @return array Array of capabilities in value/text format.
	 */
	private function get_capabilities_options() {
		$wp_roles = wp_roles();

		$all_capabilities = array();

		foreach ( $wp_roles->roles as $role ) {
			if ( isset( $role['capabilities'] ) && is_array( $role['capabilities'] ) ) {
				$all_capabilities = array_merge( $all_capabilities, array_keys( $role['capabilities'] ) );
			}
		}

		// Remove duplicates and sort.
		$all_capabilities = array_unique( $all_capabilities );
		sort( $all_capabilities );

		$fields = array();
		foreach ( $all_capabilities as $cap ) {
			$fields[] = array(
				'value' => $cap,
				'text'  => $cap,
			);
		}

		return $fields;
	}

	/**
	 * Process the action.
	 *
	 * Creates a new WordPress role with the specified capabilities.
	 *
	 * @param int   $user_id     The user ID.
	 * @param array $action_data The action data.
	 * @param int   $recipe_id   The recipe ID.
	 * @param array $args        The arguments.
	 * @param array $parsed      The parsed data.
	 *
	 * @return bool True if the role was created successfully, false otherwise.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		$role_name    = isset( $parsed['role_name'] ) ? sanitize_text_field( $parsed['role_name'] ) : '';
		$role_slug    = isset( $parsed['role_slug'] ) ? sanitize_key( $parsed['role_slug'] ) : '';
		$display_name = isset( $parsed['display_name'] ) ? sanitize_text_field( $parsed['display_name'] ) : '';
		$capabilities = isset( $parsed['CAPABILITIES'] ) ? $parsed['CAPABILITIES'] : array();

		// Validate role slug format.
		if ( ! preg_match( '/^[a-z0-9_]+$/', $role_slug ) ) {
			$this->add_log_error( esc_html_x( 'The role slug must contain only lowercase letters, numbers, and underscores.', 'WordPress', 'uncanny-automator' ) );
			return false;
		}

		// Check if role already exists.
		if ( wp_roles()->is_role( $role_slug ) ) {
			$this->add_log_error(
				sprintf(
					// translators: %s is the role slug
					esc_html_x( 'The role %s already exists.', 'WordPress', 'uncanny-automator' ),
					$role_slug
				)
			);
			return false;
		}

		// Create capabilities array.
		$role_capabilities = array();
		if ( ! empty( $capabilities ) ) {
			foreach ( $capabilities as $cap ) {
				$role_capabilities[ $cap ] = true;
			}
		}

		// Add the new role.
		$result = add_role( $role_slug, $display_name, $role_capabilities );

		if ( null === $result ) {
			$this->add_log_error( esc_html_x( 'Failed to create the new role.', 'WordPress', 'uncanny-automator' ) );
			return false;
		}

		return true;
	}
}
