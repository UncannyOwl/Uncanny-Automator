<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

/**
 * Class AC_USER_ADD
 * @package Uncanny_Automator
 */
class AC_USER_ADD {

	use \Uncanny_Automator\Recipe\Actions;

	public $prefix = '';

	public function __construct() {

		$this->prefix = 'AC_USER_ADD';

		$this->ac_endpoint_uri = AUTOMATOR_API_URL . 'v2/active-campaign';

		// Allow overwrite in wp-config.php.
		if ( DEFINED( 'UO_AUTOMATOR_DEV_AC_ENDPOINT_URL' ) ) {
			$this->ac_endpoint_uri = UO_AUTOMATOR_DEV_AC_ENDPOINT_URL;
		}

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

		$options_group = array(
			$this->get_action_meta() => array(
				array(
					'option_code' => $this->prefix . '_PHONE_NUMBER',
					'label'       => esc_attr__( 'Phone number', 'uncanny-automator' ),
					'placeholder' => esc_attr__( '(+00) 987 123 4567', 'uncanny-automator' ),
					'input_type'  => 'text',
					'required'    => false,
				),
				array(
					'input_type'  => 'text',
					'option_code' => 'ACTIVECAMPAIGNHIDDEN',
					'is_hidden'   => true,
				),
			),
		);

		$this->set_options_group( $options_group );

		$this->register_action();

	}


	/**
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

		$form_data = array(
			'action'    => 'add_contact',
			'url'       => get_option( 'uap_active_campaign_api_url', '' ),
			'token'     => get_option( 'uap_active_campaign_api_key', '' ),
			'email'     => $email,
			'firstName' => $firstname,
			'lastName'  => $lastname,
			'phone'     => $phone,
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
			$error_message                       = $response->get_error_message();
			$action_data['complete_with_errors'] = true;
			Automator()->complete->action( $user_id, $action_data, $recipe_id, $error_message );

		} else {
			// Decode the response, if everythins is fine.
			$body = json_decode( wp_remote_retrieve_body( $response ) );

			if ( 200 === $body->statusCode ) {
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
				// If status code is not 200.

				/* translators: The error message */
				$error_message = sprintf( esc_html__( 'Request to ActiveCampaign returned with status: %s', 'uncanny-automator' ), $body->statusCode );

				$action_data['complete_with_errors'] = true;
				Automator()->complete->action( $user_id, $action_data, $recipe_id, $error_message );
			}
		}

	}
}
