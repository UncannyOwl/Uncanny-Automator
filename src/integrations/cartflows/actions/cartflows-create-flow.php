<?php

namespace Uncanny_Automator\Integrations\Cartflows;

use Uncanny_Automator\Recipe\Action;

/**
 * Class Cartflows_Create_Flow
 *
 * Creates an empty CartFlows funnel. Mirrors CartFlows' own flow creation
 * (`Cartflows_Ability_Runtime::create_flow()`): a `cartflows_flow` post
 * plus an empty `wcf-steps` array, which the funnel editor expects to be
 * present.
 *
 * @package Uncanny_Automator\Integrations\Cartflows
 *
 * @property Cartflows_Helpers $item_helpers
 */
class Cartflows_Create_Flow extends Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'CARTFLOWS' );
		$this->set_requires_user( false );
		$this->set_action_code( 'CARTFLOWS_CREATE_FLOW' );
		$this->set_action_meta( 'CARTFLOWS_FLOW_TITLE' );
		$this->set_sentence(
			sprintf(
				/* translators: 1: Flow title */
				esc_html_x( 'Create {{a flow:%1$s}}', 'CartFlows', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Create {{a flow}}', 'CartFlows', 'uncanny-automator' ) );
	}

	/**
	 * Options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code'     => $this->get_action_meta(),
				'label'           => esc_html_x( 'Flow title', 'CartFlows', 'uncanny-automator' ),
				'input_type'      => 'text',
				'required'        => true,
				'supports_tokens' => true,
			),
			array(
				'option_code'           => 'CARTFLOWS_FLOW_STATUS',
				'label'                 => esc_html_x( 'Status', 'CartFlows', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'options_show_id'       => false,
				'supports_custom_value' => false,
				'default_value'         => 'publish',
				'options'               => array(
					array(
						'value' => 'publish',
						'text'  => esc_html_x( 'Published', 'CartFlows', 'uncanny-automator' ),
					),
					array(
						'value' => 'draft',
						'text'  => esc_html_x( 'Draft', 'CartFlows', 'uncanny-automator' ),
					),
				),
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
			'CARTFLOWS_CREATED_FLOW_ID'    => array(
				'name' => esc_html_x( 'Flow ID', 'CartFlows', 'uncanny-automator' ),
				'type' => 'int',
			),
			'CARTFLOWS_CREATED_FLOW_TITLE' => array(
				'name' => esc_html_x( 'Flow title', 'CartFlows', 'uncanny-automator' ),
				'type' => 'text',
			),
			'CARTFLOWS_CREATED_FLOW_URL'   => array(
				'name' => esc_html_x( 'Flow edit URL', 'CartFlows', 'uncanny-automator' ),
				'type' => 'url',
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

		if ( ! defined( 'CARTFLOWS_FLOW_POST_TYPE' ) ) {
			$this->add_log_error( esc_html_x( 'CartFlows is not active.', 'CartFlows', 'uncanny-automator' ) );
			return false;
		}

		$title = trim( (string) ( $parsed[ $this->get_action_meta() ] ?? '' ) );

		if ( '' === $title ) {
			$this->add_log_error( esc_html_x( 'A flow title is required.', 'CartFlows', 'uncanny-automator' ) );
			return false;
		}

		$status = (string) ( $parsed['CARTFLOWS_FLOW_STATUS'] ?? 'publish' );

		if ( ! in_array( $status, array( 'publish', 'draft' ), true ) ) {
			$status = 'publish';
		}

		$flow_id = wp_insert_post(
			array(
				'post_type'    => CARTFLOWS_FLOW_POST_TYPE,
				'post_title'   => $title,
				'post_status'  => $status,
				'post_content' => '',
			),
			true
		);

		if ( is_wp_error( $flow_id ) ) {
			$this->add_log_error(
				sprintf(
					/* translators: 1: Error message returned by WordPress */
					esc_html_x( 'Could not create the flow: %1$s', 'CartFlows', 'uncanny-automator' ),
					$flow_id->get_error_message()
				)
			);
			return false;
		}

		// CartFlows expects the steps array to exist on every funnel.
		update_post_meta( $flow_id, 'wcf-steps', array() );

		$this->hydrate_tokens(
			array(
				'CARTFLOWS_CREATED_FLOW_ID'    => absint( $flow_id ),
				'CARTFLOWS_CREATED_FLOW_TITLE' => $title,
				'CARTFLOWS_CREATED_FLOW_URL'   => admin_url( 'admin.php?page=cartflows&path=flows&action=wcf-edit-flow&flow_id=' . absint( $flow_id ) ),
			)
		);

		return true;
	}
}
