<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

/**
 * Class AC_USER_REMOVE_TAG
 * @package Uncanny_Automator
 */
class AC_USER_REMOVE_TAG {

	use \Uncanny_Automator\Recipe\Actions;

	public $prefix = '';

	public function __construct() {

		$this->prefix = 'AC_USER_REMOVE_TAG';

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
		$this->set_sentence( sprintf( esc_attr__( 'Remove {{a tag:%1$s}} from the user', 'uncanny-automator' ), $this->get_action_meta() ) );

		/* translators: Action - WordPress */
		$this->set_readable_sentence( esc_attr__( 'Remove {{a tag}} from the user', 'uncanny-automator' ) );

		$options_group = array(
			$this->get_action_meta() => array(
				array(
					'option_code'           => $this->get_action_meta(),
					/* translators: Email field */
					'label'                 => esc_attr__( 'Tag', 'uncanny-automator' ),
					'input_type'            => 'select',
					'supports_custom_value' => false,
					'required'              => true,
					'is_ajax'               => true,
					'endpoint'              => 'active-campaign-list-tags',
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

		$tag_id = isset( $parsed[ $this->get_action_meta() ] ) ? sanitize_text_field( $parsed[ $this->get_action_meta() ] ) : 0;

		// Get the contact id of the user connected to ActiveCampaign.
		$user    = get_user_by( 'ID', $user_id );
		$contact = $ac_helper->get_user_by_email( $user->data->user_email );

		if ( true === $contact['error'] ) {
			$action_data['complete_with_errors'] = true;
			Automator()->complete->action( $user_id, $action_data, $recipe_id, $contact['message'] );
		}

		$contact_id = isset( $contact['message']->id ) ? $contact['message']->id : 0;

		// Form data.
		$form_data = array(
			'action'    => 'get_contact_tags',
			'url'       => get_option( 'uap_active_campaign_api_url', '' ),
			'token'     => get_option( 'uap_active_campaign_api_key', '' ),
			'contactId' => $contact_id,
		);

		$response = wp_remote_post(
			$this->ac_endpoint_uri,
			array(
				'body' => $form_data,
			)
		);

		if ( is_wp_error( $response ) ) {
			$action_data['complete_with_errors'] = true;
			Automator()->complete->action( $user_id, $action_data, $recipe_id, $response->get_error_message() );
			return;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ) );

		$contact_tags = isset( $body->data->contactTags ) ? $body->data->contactTags : '';

		$contact_tag_id = 0;

		if ( ! empty( $contact_tags ) ) {

			foreach ( $contact_tags as $contact_tag ) {
				if ( $tag_id === $contact_tag->tag ) {
					$contact_tag_id = $contact_tag->id;
				}
			}
		}

		// Delete the tag.
		$form_data = array(
			'action'       => 'delete_contact_tag',
			'url'          => get_option( 'uap_active_campaign_api_url', '' ),
			'token'        => get_option( 'uap_active_campaign_api_key', '' ),
			'contactTagId' => $contact_tag_id,
		);

		$response = wp_remote_post(
			$this->ac_endpoint_uri,
			array(
				'body' => $form_data,
			)
		);

		if ( is_wp_error( $response ) ) {
			$action_data['complete_with_errors'] = true;
			Automator()->complete->action( $user_id, $action_data, $recipe_id, $response->get_error_message() );
			return;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ) );

		$message = isset( $body->data->message ) ? $body->data->message : '';

		if ( 0 === $contact_tag_id ) {

			$action_data['complete_with_errors'] = true;
			/* translators: The error message */
			$message = sprintf( __( 'The contact %s does not contain the specified tag.', 'uncanny-automator' ), $user->data->user_email );
			Automator()->complete->action( $user_id, $action_data, $recipe_id, $message );
			return;

		}

		if ( ! empty( $message ) ) {
			$action_data['complete_with_errors'] = true;
			Automator()->complete->action( $user_id, $action_data, $recipe_id, $message );
			return;
		}

		Automator()->complete->action( $user_id, $action_data, $recipe_id );

	}
}
