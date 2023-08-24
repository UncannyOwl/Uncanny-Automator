<?php

namespace Uncanny_Automator\Integrations\Brevo;

/**
 * Class BREVO_ADD_CONTACT_TO_LIST
 *
 * @package Uncanny_Automator
 */
class BREVO_ADD_CONTACT_TO_LIST extends \Uncanny_Automator\Recipe\Action {

	public $prefix = 'BREVO_ADD_CONTACT_TO_LIST';

	/**
	 * Define and register the action by pushing it into the Automator object.
	 *
	 * @return void
	 */
	public function setup_action() {

		$this->helpers = array_shift( $this->dependencies );

		$this->set_integration( 'BREVO' );
		$this->set_action_code( $this->prefix . '_CODE' );
		$this->set_action_meta( $this->prefix . '_META' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->action_code, 'knowledge-base/brevo/' ) );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				/* translators: %1$s Contact Email, %2$s List*/
				esc_attr_x( 'Add {{a contact:%1$s}} to {{a list:%2$s}}', 'Brevo', 'uncanny-automator' ),
				$this->prefix . '_EMAIL:' . $this->get_action_meta(),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_attr_x( 'Add {{a contact}} to {{a list}}', 'Brevo', 'uncanny-automator' ) );
		$this->set_background_processing( true );

	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {

		return array(
			array(
				'option_code'    => $this->prefix . '_EMAIL',
				'label'          => _x( 'Email', 'Brevo', 'uncanny-automator' ),
				'input_type'     => 'email',
				'required'       => true,
				'fill_values_in' => $this->prefix . '_EMAIL',
			),
			array(
				'option_code'           => $this->get_action_meta(),
				'label'                 => _x( 'List', 'Brevo', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'is_ajax'               => true,
				'endpoint'              => 'automator_brevo_get_lists',
				'supports_custom_value' => false,
				'fill_values_in'        => $this->get_action_meta(),
			),
		);

	}

	/**
	 * Process the action.
	 *
	 * @param int $user_id
	 * @param array $action_data
	 * @param int $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$email = $this->helpers->get_email_from_parsed( $parsed, $this->prefix . '_EMAIL' );

		$list_id = $this->helpers->get_list_id_from_parsed( $parsed, $this->get_action_meta() );

		$response = $this->helpers->add_contact_to_list( $email, $list_id, $action_data );

		return true;
	}

}
