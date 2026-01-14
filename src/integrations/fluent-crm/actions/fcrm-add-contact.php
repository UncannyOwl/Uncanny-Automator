<?php

namespace Uncanny_Automator;

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
		$this->set_is_deprecated( true );
		/* translators: Action - FluentCRM */
		$this->set_sentence( sprintf( esc_html_x( 'Add/Update {{a contact:%1$s}}', 'FluentCRM', 'uncanny-automator' ), $this->get_action_meta() ) );
		/* translators: Action - FluentCRM */
		$this->set_readable_sentence( esc_html_x( 'Add/Update {{a contact}}', 'FluentCRM', 'uncanny-automator' ) );
		$this->set_options_callback( array( $this, 'load_options' ) );
		$this->register_action();
	}

	/**
	 * @return array[]
	 */
	public function load_options() {

		$options_group = array(
			$this->get_action_meta() => $this->get_all_fields(),
		);

		return Automator()->utilities->keep_order_of_options(
			array(
				'options_group' => $options_group,
			)
		);
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

		$data['email']           = Automator()->parse->text( $action_data['meta']['FCRMUSEREMAIL'], $recipe_id, $user_id, $args );
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
		$data['lists']           = $this->validate_multiselect_value( $action_data['meta']['FCRMLIST'] );
		$data['tags']            = $this->validate_multiselect_value( $action_data['meta']['FCRMTAG'] );
		$data['query_timestamp'] = time();
		$custom_fields           = fluentcrm_get_custom_contact_fields();
		if ( $custom_fields ) {
			foreach ( $custom_fields as $k => $custom_field ) {

				if ( apply_filters( "automator_fluentcrm_omit_custom_field-{$custom_field['slug']}", false, $custom_field ) ) { // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
					continue;
				}

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
						$data['custom_values'][ $custom_field['slug'] ] = $this->validate_multiselect_value( Automator()->parse->text( $action_data['meta'][ 'FLUENTCRM_CUSTOMFIELD_' . $k ], $recipe_id, $user_id, $args ), true );
						break;
					case 'date':
						$data['custom_values'][ $custom_field['slug'] ] = $this->validate_date_value( Automator()->parse->text( $action_data['meta'][ 'FLUENTCRM_CUSTOMFIELD_' . $k ], $recipe_id, $user_id, $args ) );
						break;
					case 'date_time':
						$data['custom_values'][ $custom_field['slug'] ] = $this->validate_date_value( Automator()->parse->text( $action_data['meta'][ 'FLUENTCRM_CUSTOMFIELD_' . $k ], $recipe_id, $user_id, $args ), 'Y-m-d H:i:s' );
						break;
					default:
						$data['custom_values'][ $custom_field['slug'] ] = Automator()->parse->text( $action_data['meta'][ 'FLUENTCRM_CUSTOMFIELD_' . $k ], $recipe_id, $user_id, $args );
						break;
				}
			}
		}

		$contact = FluentCrmApi( 'contacts' )->createOrUpdate( $data, true );

		if ( ! $contact ) {
			$action_data['do-nothing']           = true;
			$action_data['complete_with_errors'] = true;
			/* translators: Subscriber email */
			$message = sprintf( esc_html_x( 'We are not able to create or update a contact %s.', 'FluentCRM', 'uncanny-automator' ), $data['email'] );
			Automator()->complete->action( $user_id, $action_data, $recipe_id, $message );

			return;
		}

		if ( 'pending' === $contact->status ) {
			$contact->sendDoubleOptinEmail();
		}

		Automator()->complete->action( $user_id, $action_data, $recipe_id );
	}

	/**
	 * Validate and format date values.
	 *
	 * @param string $value The date value to validate
	 * @param string $format The expected format (optional, defaults to 'Y-m-d')
	 *
	 * @return string The formatted date or empty string if invalid
	 */
	private function validate_date_value( $value, $format = 'Y-m-d' ) {
		if ( empty( $value ) ) {
			return $value;
		}

		// Try to parse the date using the expected format
		$date = \DateTime::createFromFormat( $format, $value );

		if ( $date && $date->format( $format ) === $value ) {
			return $value; // Date is already in correct format
		}

		// Try to parse using strtotime for more flexible date parsing
		$timestamp = strtotime( $value );
		if ( false !== $timestamp ) {
			return gmdate( $format, $timestamp );
		}

		// If we can't parse the date, return empty string to indicate invalid format
		return '';
	}

	/**
	 * Validate and format multiselect values.
	 *
	 * @param mixed $value The multiselect value
	 * @param bool  $is_custom_field Whether the value is from a custom field
	 *
	 * @return array The formatted array of values
	 */
	private function validate_multiselect_value( $value, $is_custom_field = false ) {
		// If value is already an array, return it
		if ( is_array( $value ) ) {
			return $is_custom_field ? $value : array_map( 'intval', $value );
		}

		// If value is a JSON string, decode it
		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
				return $is_custom_field ? $decoded : array_map( 'intval', $decoded );
			}
		}

		// If value is a single value (custom value), convert to array
		if ( ! empty( $value ) ) {
			return array( $is_custom_field ? $value : intval( $value ) );
		}

		// Return empty array if no valid value
		return array();
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
					'label'       => esc_attr_x( 'First name', 'FluentCRM', 'uncanny-automator' ),
					'required'    => false,
				)
			),
			// Last name field.
			Automator()->helpers->recipe->field->text(
				array(
					'option_code' => 'FCRMLASTNAME',
					'label'       => esc_attr_x( 'Last name', 'FluentCRM', 'uncanny-automator' ),
					'required'    => false,
				)
			),
			// Email field.
			Automator()->helpers->recipe->field->text(
				array(
					'option_code' => 'FCRMUSEREMAIL',
					'label'       => esc_attr_x( 'Email', 'FluentCRM', 'uncanny-automator' ),
					'input_type'  => 'email',
				)
			),
			// Phone field.
			Automator()->helpers->recipe->field->text(
				array(
					'option_code' => 'FCRMPHONE',
					'label'       => esc_attr_x( 'Phone', 'FluentCRM', 'uncanny-automator' ),
					'required'    => false,
				)
			),
			// Date of birth field.
			Automator()->helpers->recipe->field->text(
				array(
					'option_code' => 'FCRMDATEOFBIRTH',
					'label'       => esc_attr_x( 'Date of birth', 'FluentCRM', 'uncanny-automator' ),
					'input_type'  => 'date',
					'required'    => false,
				)
			),
			// Address fields.
			Automator()->helpers->recipe->field->text(
				array(
					'option_code' => 'FCRMADDRESSLINE1',
					'label'       => esc_attr_x( 'Address line 1', 'FluentCRM', 'uncanny-automator' ),
					'required'    => false,
				)
			),
			Automator()->helpers->recipe->field->text(
				array(
					'option_code' => 'FCRMADDRESSLINE2',
					'label'       => esc_attr_x( 'Address line 2', 'FluentCRM', 'uncanny-automator' ),
					'required'    => false,
				)
			),
			Automator()->helpers->recipe->field->text(
				array(
					'option_code' => 'FCRMCITY',
					'label'       => esc_attr_x( 'City', 'FluentCRM', 'uncanny-automator' ),
					'required'    => false,
				)
			),
			Automator()->helpers->recipe->field->text(
				array(
					'option_code' => 'FCRMSTATE',
					'label'       => esc_attr_x( 'State', 'FluentCRM', 'uncanny-automator' ),
					'required'    => false,
				)
			),
			Automator()->helpers->recipe->field->text(
				array(
					'option_code' => 'FCRMPOSTALCODE',
					'label'       => esc_attr_x( 'Postal code', 'FluentCRM', 'uncanny-automator' ),
					'required'    => false,
				)
			),
			Automator()->helpers->recipe->field->text(
				array(
					'option_code' => 'FCRMCOUNTRY',
					'label'       => esc_attr_x( 'Country', 'FluentCRM', 'uncanny-automator' ),
					'required'    => false,
				)
			),
			// Status field
			Automator()->helpers->recipe->field->select(
				array(
					'input_type'            => 'select',
					'option_code'           => 'FCRMSTATUS',
					'label'                 => esc_attr_x( 'Status', 'FluentCRM', 'uncanny-automator' ),
					'options'               => Automator()->helpers->recipe->fluent_crm->get_subscriber_statuses( false, true ),
					'supports_custom_value' => true,
					'default_value'         => null,
				)
			),
			Automator()->helpers->recipe->fluent_crm->options->fluent_crm_lists(
				esc_attr_x( 'Lists', 'Fluent Forms', 'uncanny-automator' ),
				'FCRMLIST',
				array(
					'supports_multiple_values' => true,
					'is_any'                   => false,
					'is_required'              => false,
					'supports_custom_value'    => true,
				)
			),
			Automator()->helpers->recipe->fluent_crm->options->fluent_crm_tags(
				null,
				'FCRMTAG',
				array(
					'supports_multiple_values' => true,
					'is_any'                   => false,
					'is_required'              => false,
					'supports_custom_value'    => true,
				)
			),
		);

		return array_merge( $predefined_fields, Automator()->helpers->recipe->fluent_crm->options->get_custom_field() );
	}
}
