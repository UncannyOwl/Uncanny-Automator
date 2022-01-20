<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

/**
 * Class AC_USER_ADD
 *
 * @package Uncanny_Automator
 */
class AC_USER_ADD {

	use Recipe\Actions;

	public $prefix = '';

	public function __construct() {

		$this->prefix = 'AC_USER_ADD';

		$this->ac_endpoint_uri = AUTOMATOR_API_URL . 'v2/active-campaign';

		$this->setup_action();

	}

	/**
	 * Setup Action.
	 *
	 * @return void.
	 */
	protected function setup_action() {

		$this->set_integration( 'ACTIVE_CAMPAIGN' );
		$this->set_action_code( $this->prefix . '_CODE' );
		$this->set_action_meta( $this->prefix . '_META' );
		$this->set_is_pro( false );
		$this->set_requires_user( true );

		/* translators: Action - WordPress */
		$this->set_sentence( sprintf( esc_attr__( 'Add {{the user:%1$s}} to ActiveCampaign', 'uncanny-automator' ), $this->get_action_meta() ) );

		/* translators: Action - WordPress */
		$this->set_readable_sentence( esc_attr__( 'Add {{the user}} to ActiveCampaign', 'uncanny-automator' ) );

		$options_group = array( $this->get_action_meta() => $this->get_field() );

		$this->set_options_group( $options_group );

		$this->register_action();

	}


	/**
	 * Process our action.
	 *
	 * @param int $user_id
	 * @param array $action_data
	 * @param int $recipe_id
	 * @param array $args
	 * @param $parsed
	 *
	 * @return void.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$user = get_user_by( 'ID', $user_id );

		$phone = isset( $parsed[ $this->prefix . '_PHONE_NUMBER' ] ) ? sanitize_text_field( $parsed[ $this->prefix . '_PHONE_NUMBER' ] ) : 0;

		$email     = isset( $user->data->user_email ) ? $user->data->user_email : '';
		$firstname = isset( $user->first_name ) ? $user->first_name : '';
		$lastname  = isset( $user->last_name ) ? $user->last_name : '';
		$is_update = isset( $parsed[ $this->prefix . '_UPDATE_IF_CONTACT_EXISTS' ] ) ? $parsed[ $this->prefix . '_UPDATE_IF_CONTACT_EXISTS' ] : 'false';
		$is_update = trim( wp_strip_all_tags( $is_update ) );

		$ac_helper = Automator()->helpers->recipe->active_campaign->options;

		$custom_fields = $ac_helper->get_registered_fields( $parsed, $this->prefix );

		$form_data = array(
			'action'         => 'add_contact',
			'url'            => get_option( 'uap_active_campaign_api_url', '' ),
			'token'          => get_option( 'uap_active_campaign_api_key', '' ),
			'email'          => $email,
			'firstName'      => $firstname,
			'lastName'       => $lastname,
			'phone'          => $phone,
			'updateIfExists' => $is_update, // String.
			'fields'         => wp_json_encode( $custom_fields ),
		);

		$response = wp_remote_post(
			$this->ac_endpoint_uri,
			array(
				'body' => $form_data,
			)
		);

		if ( is_wp_error( $response ) ) {

			// Something happened with the response.
			// Or, there's an error with with WordPress. etc.
			$error_message = $response->get_error_message();

			$action_data['complete_with_errors'] = true;

			Automator()->complete->action( $user_id, $action_data, $recipe_id, $error_message );

		} else {
			// Decode the response, if everythins is fine.
			$body = json_decode( wp_remote_retrieve_body( $response ) );

			if ( 200 === $body->statusCode ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				// If there are any errors.
				$errors = isset( $body->data->errors ) ? $body->data->errors : '';
				if ( ! empty( $errors ) ) {
					$error_message = array();
					foreach ( $errors as $error ) {
						$error_message[] = $error->title;
					}
					$action_data['complete_with_errors'] = true;
					Automator()->complete->action( $user_id, $action_data, $recipe_id, implode( ',', $error_message ) );
				} else {
					// All good. Complete the action.
					Automator()->complete->action( $user_id, $action_data, $recipe_id );
				}
			} else {

				/* translators: The error message */
				$error_message = sprintf( esc_html__( 'Request to ActiveCampaign returned with status: %s', 'uncanny-automator' ), $body->statusCode ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

				if ( isset( $body->error->description ) ) {
					/* translators: The error message */
					$error_message = sprintf( esc_html__( 'ActiveCampaign return with an error: %s', 'uncanny-automator' ), $body->error->description );
				}

				$action_data['complete_with_errors'] = true;

				Automator()->complete->action( $user_id, $action_data, $recipe_id, $error_message );
			}
		}
	}

	public function get_field() {

		$custom_fields = get_transient( 'ua_ac_contact_fields_list' );

		$ac_helper = Automator()->helpers->recipe->active_campaign->options;

		if ( false === $custom_fields ) {
			$ac_helper->sync_contact_fields( false );
		}

		$fields = array(
			array(
				'option_code' => $this->prefix . '_PHONE_NUMBER',
				'label'       => esc_attr__( 'Phone number', 'uncanny-automator' ),
				'placeholder' => esc_attr__( '(+00) 987 123 4567', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => false,
			),
		);

		// Add the custom fields options.
		$fields = array_merge( $fields, $ac_helper->get_custom_fields( $this->prefix ) );

		// Add the checkbox.
		$fields[] = array(
			'option_code' => $this->prefix . '_UPDATE_IF_CONTACT_EXISTS',
			'label'       => esc_attr__( 'If the contact already exists, update their info.', 'uncanny-automator' ),
			'input_type'  => 'checkbox',
		);

		return $fields;

	}
}
