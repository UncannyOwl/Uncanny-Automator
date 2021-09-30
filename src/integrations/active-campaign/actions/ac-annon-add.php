<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

/**
 * Class AC_ANNON_ADD
 * @package Uncanny_Automator
 */
class AC_ANNON_ADD {

	use \Uncanny_Automator\Recipe\Actions;

	public $prefix = '';

	public function __construct() {
		$this->prefix          = 'AC_ANNON_ADD';
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
		$this->set_requires_user( false );

		/* translators: Action - WordPress */
		$this->set_sentence( sprintf( esc_attr__( 'Add {{a contact:%1$s}} to ActiveCampaign', 'uncanny-automator' ), $this->get_action_meta() ) );

		/* translators: Action - WordPress */
		$this->set_readable_sentence( esc_attr__( 'Add {{a contact}} to ActiveCampaign', 'uncanny-automator' ) );

		$options_group = array(
			$this->get_action_meta() => array(
				array(
					'option_code' => $this->get_action_meta(),
					/* translators: Email address */
					'label'       => esc_attr__( 'Email address', 'uncanny-automator' ),
					'input_type'  => 'email',
					'required'    => true,
				),
				array(
					'option_code' => $this->prefix . '_FIRST_NAME',
					/* translators: First name */
					'label'       => esc_attr__( 'First name', 'uncanny-automator' ),
					'input_type'  => 'text',
					'required'    => true,
				),
				array(
					'option_code' => $this->prefix . '_LAST_NAME',
					/* translators: Last name */
					'label'       => esc_attr__( 'Last name', 'uncanny-automator' ),
					'input_type'  => 'text',
					'required'    => true,
				),
				array(
					'option_code' => $this->prefix . '_PHONE',
					'label'       => esc_attr__( 'Phone number', 'uncanny-automator' ),
					'placeholder' => esc_attr__( '(+00) 987 123 4567', 'uncanny-automator' ),
					'input_type'  => 'text',
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

		$email     = isset( $parsed[ $this->get_action_meta() ] ) ? sanitize_text_field( $parsed[ $this->get_action_meta() ] ) : 0;
		$firstname = isset( $parsed[ $this->prefix . '_FIRST_NAME' ] ) ? sanitize_text_field( $parsed[ $this->prefix . '_FIRST_NAME' ] ) : 0;
		$lastname  = isset( $parsed[ $this->prefix . '_LAST_NAME' ] ) ? sanitize_text_field( $parsed[ $this->prefix . '_LAST_NAME' ] ) : 0;
		$phone     = isset( $parsed[ $this->prefix . '_PHONE' ] ) ? sanitize_text_field( $parsed[ $this->prefix . '_PHONE' ] ) : 0;

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
				/* translators: Last name */
				$error_message = sprintf( esc_html__( 'Request to ActiveCampaign returned with status: %s', 'uncanny-automator' ), $body->statusCode );

				$action_data['complete_with_errors'] = true;
				Automator()->complete->action( $user_id, $action_data, $recipe_id, $error_message );
			}
		}
	}
}
