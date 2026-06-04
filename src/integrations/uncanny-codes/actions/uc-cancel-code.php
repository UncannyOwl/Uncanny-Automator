<?php

namespace Uncanny_Automator\Integrations\Uncanny_Codes;

/**
 * Class UC_CANCEL_CODE
 *
 * @package Uncanny_Automator
 * @property \Uncanny_Automator\Integrations\Uncanny_Codes\Uc_Helpers $item_helpers
 */
class UC_CANCEL_CODE extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Setup action configuration.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'UNCANNYCODE' );
		$this->set_action_code( 'UCCANCELCODE' );
		$this->set_action_meta( 'WPUCCANCELCODE' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		// translators: %1$s is the code.
		$this->set_sentence( sprintf( esc_html_x( 'Cancel {{a code:%1$s}}', 'Uncanny Codes', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'Cancel {{a code}}', 'Uncanny Codes', 'uncanny-automator' ) );
	}

	/**
	 * Define action options.
	 *
	 * @return array[]
	 */
	public function options() {
		return array(
			array(
				'option_code'           => $this->get_action_meta(),
				'label'                 => esc_html_x( 'Code', 'Uncanny Codes', 'uncanny-automator' ),
				'input_type'            => 'text',
				'required'              => true,
				'supports_custom_value' => true,
			),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int   $user_id     The user ID.
	 * @param array $action_data The action configuration.
	 * @param int   $recipe_id   The recipe ID.
	 * @param array $args        Additional arguments.
	 * @param array $parsed      Parsed token values.
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$code_name = isset( $parsed[ $this->get_action_meta() ] ) ? sanitize_text_field( $parsed[ $this->get_action_meta() ] ) : '';

		$code_data = \uncanny_learndash_codes\Database::is_coupon_valid( $code_name );

		if ( null === $code_data || ! is_object( $code_data ) ) {
			$this->add_log_error( esc_html_x( 'Invalid code provided.', 'Uncanny Codes', 'uncanny-automator' ) );
			return false;
		}

		$cancelled = $this->cancel_code( $code_data );

		if ( true !== $cancelled ) {
			$this->add_log_error( esc_html_x( 'Something went wrong! Code was not cancelled, try again.', 'Uncanny Codes', 'uncanny-automator' ) );
			return false;
		}

		return true;
	}

	/**
	 * Cancel a code by marking it inactive.
	 *
	 * @param object $code_details The code details object.
	 *
	 * @return bool
	 */
	private function cancel_code( $code_details ) {

		if ( empty( $code_details ) || ! is_object( $code_details ) || ! isset( $code_details->ID ) || ! isset( $code_details->code_group ) ) {
			return false;
		}

		$code_id  = $code_details->ID;
		$group_id = $code_details->code_group;

		global $wpdb;
		$result = $wpdb->update(
			$wpdb->prefix . \uncanny_learndash_codes\Config::$tbl_codes,
			array(
				'is_active' => 0,
			),
			array(
				'ID'         => $code_id,
				'code_group' => $group_id,
			),
			array(
				'%s',
			),
			array(
				'%d',
				'%d',
			)
		);

		if ( $result ) {
			return true;
		}

		return false;
	}
}
