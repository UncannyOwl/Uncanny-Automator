<?php
namespace Uncanny_Automator;

use Exception;

/**
 * Class HELPSCOUT_CUSTOMER_PROPERTIES_UPDATE
 *
 * @package Uncanny_Automator
 */
class HELPSCOUT_CUSTOMER_PROPERTIES_UPDATE {

	use Recipe\Actions;
	use Recipe\Action_Tokens;

	protected $helper = null;

	public function __construct() {

		$this->setup_action();

		$this->helper = new Helpscout_Helpers( false );

	}

	/**
	 * Setups our action.
	 *
	 * @return void.
	 */
	protected function setup_action() {

		$this->set_integration( 'HELPSCOUT' );
		$this->set_action_code( 'HELPSCOUT_CUSTOMER_PROPERTIES_UPDATE' );
		$this->set_action_meta( 'HELPSCOUT_CUSTOMER_PROPERTIES_UPDATE_META' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/helpscout/' ) );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				/* translators: Action - WordPress */
				esc_attr__(
					'Update {{the properties:%2$s}} of {{a customer:%1$s}}',
					'uncanny-automator'
				),
				$this->get_action_meta(),
				'FIELDS_NON_EXISTENT:' . $this->get_action_meta()
			)
		);

		$this->set_readable_sentence(
			/* translators: Action - WordPress */
			esc_attr__(
				'Update {{the properties}} of {{a customer}}',
				'uncanny-automator'
			)
		);

		$this->set_options_callback( array( $this, 'load_options' ) );
		$this->set_background_processing( true );
		$this->register_action();

	}

	/**
	 * Loads the options.
	 *
	 * @return array;
	 */
	public function load_options() {
		return Automator()->utilities->keep_order_of_options(
			array(
				'options_group' => array(
					$this->get_action_meta() => array(
						array(
							'option_code'           => $this->get_action_meta(),
							'label'                 => esc_attr__( 'Email', 'uncanny-automator' ),
							'input_type'            => 'email',
							'supports_custom_value' => true,
							'required'              => true,
						),
						array(
							'option_code'           => 'FIELDS',
							'label'                 => esc_attr__( 'Property', 'uncanny-automator' ),
							'input_type'            => 'repeater',
							'supports_custom_value' => true,
							'required'              => true,
							'fields'                => array(
								array(
									'option_code' => 'PROPERTY_SLUG',
									'label'       => __( 'Property ID', 'uncanny-automator' ),
									'input_type'  => 'text',
									'read_only'   => true,
								),
								array(
									'option_code' => 'PROPERTY_NAME',
									'label'       => __( 'Name', 'uncanny-automator' ),
									'input_type'  => 'text',
									'read_only'   => true,
								),
								array(
									'option_code' => 'PROPERTY_VALUE',
									'label'       => __( 'Value', 'uncanny-automator' ),
									'input_type'  => 'text',
									'required'    => false,
								),
							),
							'ajax'                  => array(
								'endpoint'       => 'automator_helpscout_fetch_properties',
								'event'          => 'on_load',
								'mapping_column' => 'PROPERTY_SLUG',
							),
							'hide_actions'          => true,
						),
					),
				),
			)
		);
	}


	/**
	 * Process action.
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 * @param $args
	 * @param $parsed
	 *
	 * @return void.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$customer_email = $parsed[ $this->get_action_meta() ];

		try {

			if ( ! filter_var( $customer_email, FILTER_VALIDATE_EMAIL ) ) {
				throw new Exception( 'Invalid email address: ' . $customer_email, 400 );
			}

			$fields = $this->process_fields( $parsed['FIELDS'] );

			$decoded = json_decode( $fields, true );

			// The json_decode will generate a null value if its failing.
			if ( null === $decoded ) {
				$action_data['complete_with_notice'] = true;
				Automator()->complete->action( $user_id, $action_data, $recipe_id, _x( 'The JSON string generated from the repeater field is not valid.', 'Help Scout', 'uncanny-automator' ) );
				return;
			}

			// Do not allow empty repeater fields. Atleast one field must be filled-out.
			if ( empty( $decoded ) ) {
				$action_data['complete_with_notice'] = true;
				Automator()->complete->action( $user_id, $action_data, $recipe_id, _x( 'Incomplete Information: No updates were made as fields were left empty.', 'Help Scout', 'uncanny-automator' ) );
				return;
			}

			$this->helper->api_request(
				array(
					'action'         => 'update_customer_properties',
					'customer_email' => $customer_email,
					'fields'         => $fields,
				),
				$action_data
			);

			Automator()->complete->action( $user_id, $action_data, $recipe_id );

		} catch ( \Exception $e ) {

			$action_data['complete_with_errors'] = true;

			// Log error if there are any error messages.
			Automator()->complete->action( $user_id, $action_data, $recipe_id, $e->getMessage() );

		}

	}

	/**
	 * @param string $fields
	 *
	 * @return string
	 */
	private function process_fields( $fields ) {

		$fields_arr = (array) json_decode( $fields, true );

		$parameters = array();

		foreach ( $fields_arr as $field ) {
			// Prevents accidental data erasure.
			if ( isset( $field['PROPERTY_VALUE'] ) && '' !== $field['PROPERTY_VALUE'] ) {
				$parameters[] = array(
					'id'    => $field['PROPERTY_SLUG'],
					'value' => $field['PROPERTY_VALUE'],
				);
			}
		}

		return wp_json_encode( $parameters );

	}

}
