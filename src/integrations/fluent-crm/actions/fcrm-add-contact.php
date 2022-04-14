<?php

namespace Uncanny_Automator;

use FluentCrm\App\Models\Subscriber;

/**
 * Class FCRM_ADD_CONTACT
 *
 * @package Uncanny_Automator
 */
class FCRM_ADD_CONTACT {

	use Recipe\Actions;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->setup_action();
	}


	/**
	 * Setup Action.
	 *
	 * @return void.
	 */
	protected function setup_action() {
		$this->set_integration( 'FCRM' );
		$this->set_action_code( 'FCRMADDCONTACT' );
		$this->set_action_meta( 'FCRMUSEREMAIL' );
		$this->set_author( 'Uncanny Automator' );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'integration/fluentcrm/' ) );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		/* translators: Action - FluentCRM */
		$this->set_sentence( sprintf( esc_attr__( 'Add {{a contact:%1$s}}', 'uncanny-automator' ), $this->get_action_meta() ) );
		/* translators: Action - FluentCRM */
		$this->set_readable_sentence( esc_attr__( 'Add {{a contact}}', 'uncanny-automator' ) );
		$options_group = array(
			$this->get_action_meta() => $this->get_all_fields(),
		);
		$this->set_options_group( $options_group );
		$this->register_action();
	}

	/**
	 * Process our action.
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 * @param $args
	 * @param $parsed
	 *
	 * @return void
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		$data['email'] = Automator()->parse->text( $action_data['meta']['FCRMUSEREMAIL'], $recipe_id, $user_id, $args );
		$subscriber    = Subscriber::where( 'email', $data['email'] )->first();

		if ( ! is_null( $subscriber ) ) {
			$action_data['do-nothing']           = true;
			$action_data['complete_with_errors'] = true;
			/* translators: Subscriber email */
			$message = sprintf( esc_html__( 'Duplicate email: %s, please use different email address.', 'uncanny-automator' ), $data['email'] );
			Automator()->complete_action( $user_id, $action_data, $recipe_id, $message );

			return;
		}

		$data['first_name']      = Automator()->parse->text( $action_data['meta']['FCRMFIRSTNAME'], $recipe_id, $user_id, $args );
		$data['last_name']       = Automator()->parse->text( $action_data['meta']['FCRMLASTNAME'], $recipe_id, $user_id, $args );
		$data['phone']           = Automator()->parse->text( $action_data['meta']['FCRMPHONE'], $recipe_id, $user_id, $args );
		$data['date_of_birth']   = Automator()->parse->text( $action_data['meta']['FCRMDATEOFBIRTH'], $recipe_id, $user_id, $args );
		$data['address_line_1']  = Automator()->parse->text( $action_data['meta']['FCRMADDRESSLINE1'], $recipe_id, $user_id, $args );
		$data['address_line_2']  = Automator()->parse->text( $action_data['meta']['FCRMADDRESSLINE2'], $recipe_id, $user_id, $args );
		$data['city']            = Automator()->parse->text( $action_data['meta']['FCRMCITY'], $recipe_id, $user_id, $args );
		$data['state']           = Automator()->parse->text( $action_data['meta']['FCRMSTATE'], $recipe_id, $user_id, $args );
		$data['postal_code']     = Automator()->parse->text( $action_data['meta']['FCRMPOSTALCODE'], $recipe_id, $user_id, $args );
		$data['country']         = Automator()->parse->text( $action_data['meta']['FCRMCOUNTRY'], $recipe_id, $user_id, $args );
		$data['status']          = Automator()->parse->text( $action_data['meta']['FCRMSTATUS'], $recipe_id, $user_id, $args );
		$data['lists']           = array_map( 'intval', json_decode( $action_data['meta']['FCRMLIST'] ) );
		$data['tags']            = array_map( 'intval', json_decode( $action_data['meta']['FCRMTAG'] ) );
		$data['query_timestamp'] = time();
		$custom_fields           = fluentcrm_get_custom_contact_fields();
		if ( $custom_fields ) {
			foreach ( $custom_fields as $k => $custom_field ) {
				switch ( $custom_field['type'] ) {
					case 'checkbox':
						$checkbox_val = array();
						foreach ( $custom_field['options'] as $option ) {
							$checkbox_value = filter_var( $action_data['meta'][ 'FLUENTCRM_CUSTOMFIELD_' . $k . '_' . $option ], FILTER_VALIDATE_BOOLEAN );
							if ( true === $checkbox_value ) {
								$checkbox_val[] = $option;
							}
						}
						$data['custom_values'][ $custom_field['slug'] ] = $checkbox_val;
						break;
					case 'select-multi':
						$data['custom_values'][ $custom_field['slug'] ] = json_decode( $action_data['meta'][ 'FLUENTCRM_CUSTOMFIELD_' . $k ] );
						break;
					default:
						$data['custom_values'][ $custom_field['slug'] ] = Automator()->parse->text( $action_data['meta'][ 'FLUENTCRM_CUSTOMFIELD_' . $k ], $recipe_id, $user_id, $args );
						break;
				}
			}
		}
		$contact = Subscriber::store( $data );
		do_action( 'fluentcrm_contact_created', $contact, $data );
		Automator()->complete_action( $user_id, $action_data, $recipe_id );
	}

	/**
	 * get all fields as options for action
	 *
	 * @return array
	 */
	public function get_all_fields() {
		$predefined_fields = array(
			// First name field.
			Automator()->helpers->recipe->field->text(
				array(
					'option_code' => 'FCRMFIRSTNAME',
					'label'       => esc_attr__( 'First name', 'uncanny-automator' ),
					'required'    => false,
				)
			),
			// Last name field.
			Automator()->helpers->recipe->field->text(
				array(
					'option_code' => 'FCRMLASTNAME',
					'label'       => esc_attr__( 'Last name', 'uncanny-automator' ),
					'required'    => false,
				)
			),
			// Email field.
			Automator()->helpers->recipe->field->text(
				array(
					'option_code' => 'FCRMUSEREMAIL',
					'label'       => esc_attr__( 'Email', 'uncanny-automator' ),
					'input_type'  => 'email',
				)
			),
			// Phone field.
			Automator()->helpers->recipe->field->text(
				array(
					'option_code' => 'FCRMPHONE',
					'label'       => esc_attr__( 'Phone', 'uncanny-automator' ),
					'required'    => false,
				)
			),
			// Date of birth field.
			Automator()->helpers->recipe->field->text(
				array(
					'option_code' => 'FCRMDATEOFBIRTH',
					'label'       => esc_attr__( 'Date of birth', 'uncanny-automator' ),
					'input_type'  => 'date',
					'required'    => false,
				)
			),
			// Address fields.
			Automator()->helpers->recipe->field->text(
				array(
					'option_code' => 'FCRMADDRESSLINE1',
					'label'       => esc_attr__( 'Address line 1', 'uncanny-automator' ),
					'required'    => false,
				)
			),
			Automator()->helpers->recipe->field->text(
				array(
					'option_code' => 'FCRMADDRESSLINE2',
					'label'       => esc_attr__( 'Address line 2', 'uncanny-automator' ),
					'required'    => false,
				)
			),
			Automator()->helpers->recipe->field->text(
				array(
					'option_code' => 'FCRMCITY',
					'label'       => esc_attr__( 'City', 'uncanny-automator' ),
					'required'    => false,
				)
			),
			Automator()->helpers->recipe->field->text(
				array(
					'option_code' => 'FCRMSTATE',
					'label'       => esc_attr__( 'State', 'uncanny-automator' ),
					'required'    => false,
				)
			),
			Automator()->helpers->recipe->field->text(
				array(
					'option_code' => 'FCRMPOSTALCODE',
					'label'       => esc_attr__( 'Postal code', 'uncanny-automator' ),
					'required'    => false,
				)
			),
			Automator()->helpers->recipe->field->text(
				array(
					'option_code' => 'FCRMCOUNTRY',
					'label'       => esc_attr__( 'Country', 'uncanny-automator' ),
					'required'    => false,
				)
			),
			// Status field
			Automator()->helpers->recipe->field->select(
				array(
					'option_code'           => 'FCRMSTATUS',
					'label'                 => esc_attr__( 'Status', 'uncanny-automator' ),
					'options'               => Automator()->helpers->recipe->fluent_crm->get_subscriber_statuses( false ),
					'supports_custom_value' => false,
				)
			),
			Automator()->helpers->recipe->fluent_crm->options->fluent_crm_lists(
				esc_attr_x( 'Lists', 'Fluent Forms', 'uncanny-automator' ),
				'FCRMLIST',
				array(
					'supports_multiple_values' => true,
					'is_any'                   => false,
					'is_required'              => false,
				)
			),
			Automator()->helpers->recipe->fluent_crm->options->fluent_crm_tags(
				null,
				'FCRMTAG',
				array(
					'supports_multiple_values' => true,
					'is_any'                   => false,
					'is_required'              => false,
				)
			),
		);

		return array_merge( $predefined_fields, Automator()->helpers->recipe->fluent_crm->options->get_custom_field() );
	}

}
