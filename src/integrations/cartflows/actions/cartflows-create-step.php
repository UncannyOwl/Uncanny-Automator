<?php

namespace Uncanny_Automator\Integrations\Cartflows;

use Uncanny_Automator\Recipe\Action;

/**
 * Class Cartflows_Create_Step
 *
 * Creates a step and attaches it to a funnel.
 *
 * This is deliberately not a bare wp_insert_post. CartFlows' own create_step()
 * (`Cartflows_Importer::create_step()`, and again at
 * `Cartflows_Ability_Runtime::create_step()`) also writes the step meta,
 * both taxonomy terms, and — critically — appends the step to the funnel's own
 * `wcf-steps` array. Skip that last part and the step exists in the database but
 * never appears inside its funnel. Cartflows_Helpers::attach_step_to_flow()
 * mirrors the full sequence.
 *
 * @package Uncanny_Automator\Integrations\Cartflows
 *
 * @property Cartflows_Helpers $item_helpers
 */
class Cartflows_Create_Step extends Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'CARTFLOWS' );
		$this->set_requires_user( false );
		$this->set_action_code( 'CARTFLOWS_CREATE_STEP' );
		$this->set_action_meta( 'CARTFLOWS_FLOW' );
		$this->set_sentence(
			sprintf(
				/* translators: 1: Flow the step is added to */
				esc_html_x( 'Create a step in {{a flow:%1$s}}', 'CartFlows', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Create a step in {{a flow}}', 'CartFlows', 'uncanny-automator' ) );
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
				'label'       => esc_html_x( 'Flow', 'CartFlows', 'uncanny-automator' ),
				'input_type'  => 'select',
				'required'    => true,
				'options'     => array(),
				'remote_data' => $this->item_helpers->remote_data_load_config( 'flows_strict' ),
			),
			array(
				'option_code'     => 'CARTFLOWS_STEP_TITLE',
				'label'           => esc_html_x( 'Step title', 'CartFlows', 'uncanny-automator' ),
				'input_type'      => 'text',
				'required'        => true,
				'supports_tokens' => true,
			),
			array(
				'option_code'           => 'CARTFLOWS_STEP_TYPE',
				'label'                 => esc_html_x( 'Step type', 'CartFlows', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'options_show_id'       => false,
				'supports_custom_value' => false,
				'options'               => $this->item_helpers->get_step_type_options(),
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
			'CARTFLOWS_CREATED_STEP_ID'    => array(
				'name' => esc_html_x( 'Step ID', 'CartFlows', 'uncanny-automator' ),
				'type' => 'int',
			),
			'CARTFLOWS_CREATED_STEP_TITLE' => array(
				'name' => esc_html_x( 'Step title', 'CartFlows', 'uncanny-automator' ),
				'type' => 'text',
			),
			'CARTFLOWS_CREATED_STEP_TYPE'  => array(
				'name' => esc_html_x( 'Step type', 'CartFlows', 'uncanny-automator' ),
				'type' => 'text',
			),
			'CARTFLOWS_CREATED_STEP_URL'   => array(
				'name' => esc_html_x( 'Step URL', 'CartFlows', 'uncanny-automator' ),
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

		// Every CartFlows constant this action reaches: the two post types here,
		// and the two taxonomies inside attach_step_to_flow(). CartFlows defines
		// all four together, so one failed check means it is not loaded.
		if ( ! defined( 'CARTFLOWS_STEP_POST_TYPE' )
			|| ! defined( 'CARTFLOWS_FLOW_POST_TYPE' )
			|| ! defined( 'CARTFLOWS_TAXONOMY_STEP_TYPE' )
			|| ! defined( 'CARTFLOWS_TAXONOMY_STEP_FLOW' ) ) {
			$this->add_log_error( esc_html_x( 'CartFlows is not active.', 'CartFlows', 'uncanny-automator' ) );
			return false;
		}

		$flow_id = absint( $parsed[ $this->get_action_meta() ] ?? 0 );
		$title   = trim( (string) ( $parsed['CARTFLOWS_STEP_TITLE'] ?? '' ) );
		$type    = trim( (string) ( $parsed['CARTFLOWS_STEP_TYPE'] ?? '' ) );

		if ( 0 === $flow_id || CARTFLOWS_FLOW_POST_TYPE !== get_post_type( $flow_id ) ) {
			$this->add_log_error(
				sprintf(
					/* translators: 1: Flow ID */
					esc_html_x( 'Flow not found: [%1$d].', 'CartFlows', 'uncanny-automator' ),
					$flow_id
				)
			);
			return false;
		}

		if ( '' === $title ) {
			$this->add_log_error( esc_html_x( 'A step title is required.', 'CartFlows', 'uncanny-automator' ) );
			return false;
		}

		if ( ! in_array( $type, $this->item_helpers->get_step_types(), true ) ) {
			$this->add_log_error(
				sprintf(
					/* translators: 1: Step type slug */
					esc_html_x( 'Unsupported step type: [%1$s].', 'CartFlows', 'uncanny-automator' ),
					$type
				)
			);
			return false;
		}

		// CartFlows refuses upsell/downsell steps without CartFlows Pro.
		if ( $this->item_helpers->step_type_requires_pro( $type ) && ! $this->item_helpers->cartflows_pro_active() ) {
			$this->add_log_error(
				sprintf(
					/* translators: 1: Step type, e.g. Upsell */
					esc_html_x( '%1$s steps require CartFlows Pro.', 'CartFlows', 'uncanny-automator' ),
					ucfirst( $type )
				)
			);
			return false;
		}

		$step_id = wp_insert_post(
			array(
				'post_type'   => CARTFLOWS_STEP_POST_TYPE,
				'post_title'  => $title,
				'post_status' => 'publish',
			),
			true
		);

		if ( is_wp_error( $step_id ) ) {
			$this->add_log_error(
				sprintf(
					/* translators: 1: Error message returned by WordPress */
					esc_html_x( 'Could not create the step: %1$s', 'CartFlows', 'uncanny-automator' ),
					$step_id->get_error_message()
				)
			);
			return false;
		}

		$this->item_helpers->attach_step_to_flow( $step_id, $flow_id, $type, $title );

		$this->hydrate_tokens(
			array(
				'CARTFLOWS_CREATED_STEP_ID'    => absint( $step_id ),
				'CARTFLOWS_CREATED_STEP_TITLE' => $title,
				'CARTFLOWS_CREATED_STEP_TYPE'  => $type,
				'CARTFLOWS_CREATED_STEP_URL'   => (string) get_permalink( $step_id ),
			)
		);

		return true;
	}
}
