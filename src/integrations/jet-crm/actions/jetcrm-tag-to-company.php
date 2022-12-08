<?php

namespace Uncanny_Automator;

use Uncanny_Automator\Recipe\Actions;

/**
 * Class JETCRM_TAG_TO_COMPANY
 *
 * @package Uncanny_Automator
 */
class JETCRM_TAG_TO_COMPANY {

	use Actions;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->setup_action();
		$this->set_helpers( new Jet_Crm_Helpers() );
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	protected function setup_action() {
		$this->set_integration( 'JETCRM' );
		$this->set_action_code( 'JETCRM_COMPANY_TAG' );
		$this->set_action_meta( 'JETCRM_TAG' );
		$this->set_requires_user( false );
		/* translators: Action - JetPack CRM */
		$this->set_sentence( sprintf( esc_attr__( 'Add {{a tag:%1$s}} to a company', 'uncanny-automator' ), $this->get_action_meta() ) );
		/* translators: Action - JetPack CRM */
		$this->set_readable_sentence( esc_attr__( 'Add {{a tag}} to a company', 'uncanny-automator' ) );
		$this->set_options_callback( array( $this, 'load_options' ) );
		$this->register_action();
	}

	/**
	 * load_options
	 *
	 * @return array
	 */
	public function load_options() {
		return Automator()->utilities->keep_order_of_options(
			array(
				'options_group' => array(
					$this->get_action_meta() => array(
						$this->get_helpers()->get_all_jetpack_tags( $this->get_action_meta(), false, array(), ZBS_TYPE_COMPANY ),
						Automator()->helpers->recipe->field->text(
							array(
								'option_code' => 'COMPANY_EMAIL',
								'input_type'  => 'email',
								'label'       => esc_attr__( 'Email', 'uncanny-automator' ),
							)
						),
					),
				),
			)
		);

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
		$tag           = isset( $parsed[ $this->get_action_meta() ] ) ? sanitize_text_field( $parsed[ $this->get_action_meta() ] ) : '';
		$company_email = isset( $parsed['COMPANY_EMAIL'] ) ? sanitize_email( $parsed['COMPANY_EMAIL'] ) : '';

		if ( empty( $tag ) || empty( $company_email ) ) {
			return;
		}

		global $wpdb;
		$contact_id = $wpdb->get_var( $wpdb->prepare( "SELECT `ID` FROM `{$wpdb->prefix}zbs_companies` WHERE zbsco_email LIKE %s", $company_email ) );
		if ( ! empty( $contact_id ) ) {
			$tag = $this->get_helpers()->check_if_tag_exists( $tag, ZBS_TYPE_COMPANY );
			$this->get_helpers()->link_tag_with_object( $tag, $contact_id, ZBS_TYPE_COMPANY );
			Automator()->complete->action( $user_id, $action_data, $recipe_id );

			return;
		}

		$action_data['do-nothing']           = true;
		$action_data['complete_with_errors'] = true;
		Automator()->complete->action( $user_id, $action_data, $recipe_id, sprintf( __( 'Company was not found matching (%s)', 'uncanny-automator' ), $company_email ) );
	}
}
