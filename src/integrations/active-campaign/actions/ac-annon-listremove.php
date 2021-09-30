<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

/**
 * Class AC_ANNON_LISTREMOVE
 * @package Uncanny_Automator
 */
class AC_ANNON_LISTREMOVE {

	use \Uncanny_Automator\Recipe\Actions;

	public $prefix = '';

	public function __construct() {

		$this->prefix          = 'AC_ANNON_LISTREMOVE';
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
		$this->set_sentence(
			sprintf(
				esc_attr__( 'Remove {{a contact:%1$s}} from {{a list:%2$s}}', 'uncanny-automator' ),
				$this->prefix . '_CONTACT_ID' . ':' . $this->get_action_meta(),
				$this->get_action_meta()
			)
		);

		/* translators: Action - WordPress */
		$this->set_readable_sentence( esc_attr__( 'Remove {{a contact}} from {{a list}}', 'uncanny-automator' ) );

		$options_group = array(
			$this->get_action_meta() => array(
				array(
					'option_code' => $this->prefix . '_CONTACT_ID',
					/* translators: Email field */
					'label'       => esc_attr__( 'Email', 'uncanny-automator' ),
					'placeholder' => esc_attr__( 'me@domain.com', 'uncanny-automator' ),
					'input_type'  => 'email',
					'required'    => true,
				),
				array(
					'option_code'           => $this->get_action_meta(),
					/* translators: Email field */
					'label'                 => esc_attr__( 'List', 'uncanny-automator' ),
					'input_type'            => 'select',
					'supports_custom_value' => false,
					'required'              => true,
					'is_ajax'               => true,
					'endpoint'              => 'active-campaign-list-retrieve',
					'fill_values_in'        => $this->get_action_meta(),
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

		$ac_helper = Automator()->helpers->recipe->active_campaign->options;

		$list_id       = isset( $parsed[ $this->get_action_meta() ] ) ? sanitize_text_field( $parsed[ $this->get_action_meta() ] ) : 0;
		$contact_email = isset( $parsed[ $this->prefix . '_CONTACT_ID' ] ) ? sanitize_text_field( $parsed[ $this->prefix . '_CONTACT_ID' ] ) : 0;

		$contact = $ac_helper->get_user_by_email( $contact_email );

		// Get the contact id from email.
		if ( true === $contact['error'] ) {
			$action_data['complete_with_errors'] = true;
			Automator()->complete->action( $user_id, $action_data, $recipe_id, $contact['message'] );
			return;
		}

		$contact_id = isset( $contact['message']->id ) ? $contact['message']->id : 0;

		$form_data = array(
			'action'    => 'list_update_contact',
			'url'       => get_option( 'uap_active_campaign_api_url', '' ),
			'token'     => get_option( 'uap_active_campaign_api_key', '' ),
			'listId'    => $list_id,
			'contactId' => $contact_id,
			'status'    => 2,
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
