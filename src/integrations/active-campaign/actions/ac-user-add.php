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

		$body = array(
			'action'         => 'add_contact',
			'email'          => $email,
			'firstName'      => $firstname,
			'lastName'       => $lastname,
			'phone'          => $phone,
			'updateIfExists' => $is_update, // String.
			'fields'         => wp_json_encode( $custom_fields ),
		);

		try {
			$response = $ac_helper->api_request( $body, $action_data );
			Automator()->complete->action( $user_id, $action_data, $recipe_id );
		} catch ( \Exception $e ) {
			$ac_helper->complete_with_errors( $user_id, $action_data, $recipe_id, $e->getMessage() );
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
