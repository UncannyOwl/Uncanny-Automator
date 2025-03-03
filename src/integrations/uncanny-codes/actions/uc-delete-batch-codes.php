<?php

namespace Uncanny_Automator;

use Uncanny_Automator\Recipe;
use uncanny_learndash_codes;

/**
 * Class UC_DELETE_BATCH_CODES
 *
 * @package Uncanny_Automator_Pro
 */
class UC_DELETE_BATCH_CODES {

	use Recipe\Actions;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->setup_action();
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	protected function setup_action() {
		$this->set_integration( 'UNCANNYCODE' );
		$this->set_action_code( 'UCDELETEBATCHCODES' );
		$this->set_action_meta( 'WPUCDELETEBATCHCODES' );
		$this->set_requires_user( false );
		$this->set_is_pro( false );

		/* translators: Action - Uncanny Codes */
		$this->set_sentence( sprintf( esc_attr__( 'Remove {{a number of:%1$s}} unused codes from {{a batch:%2$s}}', 'uncanny-automator' ), 'UCNUMBERS:' . $this->get_action_meta(), $this->get_action_meta() ) );

		/* translators: Action - Uncanny Codes */
		$this->set_readable_sentence( esc_attr__( 'Remove {{a number of}} unused codes from {{a batch}}', 'uncanny-automator' ) );

		$this->set_options_callback( array( $this, 'load_options' ) );
		$this->register_action();
	}

	/**
	 * load_options
	 *
	 * @return array
	 */
	public function load_options() {

		$options = array(
			'options_group' => array(
				$this->get_action_meta() => array(
					Automator()->helpers->recipe->field->int(
						array(
							'option_code' => 'UCNUMBERS',
							'label'       => esc_attr__( 'Number', 'uncanny-automator' ),
							'placeholder' => '',
						)
					),
					Automator()->helpers->recipe->uncanny_codes->options->get_all_code_batch(
						esc_html__( 'Batch', 'uncanny-automator' ),
						$this->get_action_meta(),
						false
					),
				),
			),
		);

		return Automator()->utilities->keep_order_of_options( $options );
	}

	/**
	 * Process the action.
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 * @param $args
	 * @param $parsed
	 *
	 * @return void.
	 * @throws \Exception
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		$batch_id = isset( $parsed[ $this->get_action_meta() ] ) ? absint( wp_strip_all_tags( $parsed[ $this->get_action_meta() ] ) ) : 0;
		$limit    = isset( $parsed['UCNUMBERS'] ) ? absint( sanitize_text_field( $parsed['UCNUMBERS'] ) ) : 0;

		if ( $batch_id <= 0 || $limit <= 0 ) {
			Automator()->complete->action( $user_id, $action_data, $recipe_id, esc_html__( 'Invalid request.', 'uncanny-automator' ) );

			return;
		}

		$inactive_codes_count = absint( $this->get_unused_group_codes_count( $batch_id, $limit ) );

		// Check if unused codes are available in the batch.
		if ( $inactive_codes_count <= 0 ) {
			Automator()->complete->action( $user_id, $action_data, $recipe_id, esc_html__( 'No codes found in the batch.', 'uncanny-automator' ) );

			return;
		}

		$this->delete_unused_group_codes( $batch_id, $limit );

		Automator()->complete->action( $user_id, $action_data, $recipe_id );
	}

	/**
	 *
	 * @param mixed $group
	 * @param mixed $limit
	 * @return mixed
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
	 *
	 * @param mixed $group
	 * @param mixed $limit
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
