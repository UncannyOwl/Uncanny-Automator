<?php

namespace Uncanny_Automator\Integrations\Uncanny_Codes;

use uncanny_learndash_codes;

/**
 * Class UC_DELETE_BATCH_CODES
 *
 * @package Uncanny_Automator
 * @property \Uncanny_Automator\Integrations\Uncanny_Codes\Uc_Helpers $item_helpers
 */
class UC_DELETE_BATCH_CODES extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Setup action configuration.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'UNCANNYCODE' );
		$this->set_action_code( 'UCDELETEBATCHCODES' );
		$this->set_action_meta( 'WPUCDELETEBATCHCODES' );
		$this->set_requires_user( false );
		$this->set_is_pro( false );
		// translators: %1$s is the number of codes, %2$s is the batch.
		$this->set_sentence( sprintf( esc_html_x( 'Remove {{a number of:%1$s}} unused codes from {{a batch:%2$s}}', 'Uncanny Codes', 'uncanny-automator' ), 'UCNUMBERS:' . $this->get_action_meta(), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'Remove {{a number of}} unused codes from {{a batch}}', 'Uncanny Codes', 'uncanny-automator' ) );
	}

	/**
	 * Define action options.
	 *
	 * @return array[]
	 */
	public function options() {
		return array(
			array(
				'option_code'           => 'UCNUMBERS',
				'label'                 => esc_html_x( 'Number', 'Uncanny Codes', 'uncanny-automator' ),
				'input_type'            => 'int',
				'required'              => true,
				'supports_custom_value' => true,
			),
			array(
				'option_code'           => $this->get_action_meta(),
				'label'                 => esc_html_x( 'Batch', 'Uncanny Codes', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'remote_data'           => $this->item_helpers->remote_data_load_config( 'batches_strict' ),
				'options'               => array(),
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

		$batch_id = isset( $parsed[ $this->get_action_meta() ] ) ? absint( wp_strip_all_tags( $parsed[ $this->get_action_meta() ] ) ) : 0;
		$limit    = isset( $parsed['UCNUMBERS'] ) ? absint( sanitize_text_field( $parsed['UCNUMBERS'] ) ) : 0;

		if ( $batch_id <= 0 || $limit <= 0 ) {
			$this->add_log_error( esc_html_x( 'Invalid request.', 'Uncanny Codes', 'uncanny-automator' ) );
			return false;
		}

		$inactive_codes_count = absint( $this->get_unused_group_codes_count( $batch_id, $limit ) );

		// Check if unused codes are available in the batch.
		if ( $inactive_codes_count <= 0 ) {
			$this->add_log_error( esc_html_x( 'No codes found in the batch.', 'Uncanny Codes', 'uncanny-automator' ) );
			return false;
		}

		$this->delete_unused_group_codes( $batch_id, $limit );

		return true;
	}

	/**
	 * Get count of unused codes in a group.
	 *
	 * @param int $group The group ID.
	 * @param int $limit The limit.
	 *
	 * @return int
	 */
	private function get_unused_group_codes_count( $group, $limit = 0 ) {

		global $wpdb;

		if ( is_numeric( $limit ) && $limit > 0 ) {
			return $wpdb->get_var( $wpdb->prepare( "SELECT count(c.ID) FROM `{$wpdb->prefix}uncanny_codes_codes` c WHERE c.code_group = %d AND used_date IS NULL AND user_id IS NULL LIMIT %d", $group, absint( $limit ) ) );
		}

		return $wpdb->get_var(
			$wpdb->prepare( "SELECT count(c.ID) FROM `{$wpdb->prefix}uncanny_codes_codes` c WHERE c.code_group = %d AND used_date IS NULL AND user_id IS NULL", $group )
		);
	}

	/**
	 * Delete unused codes from a group.
	 *
	 * @param int $group The group ID.
	 * @param int $limit The limit.
	 *
	 * @return bool|int
	 */
	private function delete_unused_group_codes( $group, $limit ) {

		global $wpdb;

		if ( ! is_numeric( $group ) || ! is_numeric( $limit ) ) {
			return false;
		}

		return $wpdb->query(
			$wpdb->prepare( "DELETE FROM `{$wpdb->prefix}uncanny_codes_codes` WHERE code_group = %d AND used_date IS NULL AND user_id IS NULL LIMIT %d", $group, absint( $limit ) )
		);
	}
}
