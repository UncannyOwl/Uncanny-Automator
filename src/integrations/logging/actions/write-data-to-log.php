<?php

namespace Uncanny_Automator\Integrations\Logging;

/**
 * Class WRITE_DATA_TO_LOG
 *
 * @package Uncanny_Automator
 */
class WRITE_DATA_TO_LOG extends \Uncanny_Automator\Recipe\Action {

	protected $helpers;

	/**
	 * @return void
	 */
	protected function setup_action() {
		$this->helpers = array_shift( $this->dependencies );
		$this->set_integration( 'LOGGING' );
		$this->set_action_code( 'WRITE_DATA_TO_LOG' );
		$this->set_action_meta( 'LOGGING_DATA' );
		$this->set_requires_user( false );
		// translators: 1: Data title
		$this->set_sentence( sprintf( esc_attr_x( 'Write {{data:%1$s}} to the log', 'Logging', 'uncanny-automator' ), $this->get_action_meta() . '_TITLE:' . $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_attr_x( 'Write {{data}} to the log', 'Logging', 'uncanny-automator' ) );
	}

	/**
	 * Define the Action's options
	 *
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'input_type'             => 'text',
				'required'               => true,
				'option_code'            => $this->get_action_meta() . '_TITLE',
				'supports_token'         => true,
				'label'                  => esc_html__( 'Title', 'uncanny-automator' ),
				'description'            => esc_html__( 'The title of the log entry.', 'uncanny-automator' ),
				'default_value'          => '',
				'show_label_in_sentence' => false,
			),
			array(
				'input_type'       => 'textarea',
				'required'         => true,
				'option_code'      => $this->get_action_meta() . '_DATA',
				'supports_token'   => true,
				'label'            => esc_html__( 'Data to log', 'uncanny-automator' ),
				'description'      => esc_html__( 'Any data added above will be displayed in recipe logs.', 'uncanny-automator' ),
				'default_value'    => '',
				'supports_tinymce' => true,
			),
		);
	}

	/**
	 * @param int $user_id
	 * @param array $action_data
	 * @param int $recipe_id
	 * @param array $args
	 * @param       $parsed
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		return true;
	}
}
