<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator\Integrations\Active_Campaign;

/**
 * Class AC_USER_ADD
 *
 * @package Uncanny_Automator
 *
 * @property Active_Campaign_App_Helpers $helpers
 * @property Active_Campaign_Api_Caller $api
 */
class AC_USER_ADD extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Meta key prefix.
	 *
	 * @var string
	 */
	protected $prefix = 'AC_USER_ADD';

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
		$this->set_sentence(
			sprintf(
				// translators: %1$s: User phone number
				esc_attr_x( 'Add {{the user:%1$s}} to ActiveCampaign', 'ActiveCampaign', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_attr_x( 'Add {{the user}} to ActiveCampaign', 'ActiveCampaign', 'uncanny-automator' ) );
		$this->set_background_processing( true );
	}

	/**
	 * Define the options.
	 *
	 * @return array
	 */
	public function options() {
		$options = array(
			array(
				'option_code' => $this->prefix . '_PHONE_NUMBER',
				'label'       => esc_attr_x( 'Phone number', 'ActiveCampaign', 'uncanny-automator' ),
				'placeholder' => esc_attr_x( '(+00) 987 123 4567', 'ActiveCampaign', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => false,
			),
		);

		$custom_fields = $this->helpers->get_custom_fields( $this->prefix );
		$options       = array_merge( $options, $custom_fields );

		$options[] = array(
			'option_code' => $this->prefix . '_UPDATE_IF_CONTACT_EXISTS',
			'label'       => esc_attr_x( 'If the contact already exists, update their info.', 'ActiveCampaign', 'uncanny-automator' ),
			'input_type'  => 'checkbox',
			'description' => esc_html_x( 'To delete a value from a field, set its value to [delete], including the square brackets.', 'ActiveCampaign', 'uncanny-automator' ),
		);

		return $options;
	}

	/**
	 * Process the action.
	 *
	 * @param int $user_id
	 * @param array $action_data
	 * @param int $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 * @throws Exception
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		$user          = get_user_by( 'ID', $user_id );
		$phone         = sanitize_text_field( $parsed[ $this->prefix . '_PHONE_NUMBER' ] ?? '' );
		$email         = $user->data->user_email ?? '';
		$first_name    = $user->first_name ?? '';
		$last_name     = $user->last_name ?? '';
		$is_update     = sanitize_text_field( wp_strip_all_tags( $parsed[ $this->prefix . '_UPDATE_IF_CONTACT_EXISTS' ] ?? 'false' ) );
		$custom_fields = $this->helpers->get_registered_fields( $parsed, $this->prefix );

		$body = array(
			'action'         => 'add_contact',
			'email'          => $email,
			'firstName'      => $first_name,
			'lastName'       => $last_name,
			'phone'          => $phone,
			'updateIfExists' => $is_update,
			'fields'         => $custom_fields,
		);

		$body = $this->helpers->filter_add_contact_api_body( $body, compact( 'user_id', 'action_data', 'parsed', 'args', 'recipe_id' ) );

		$this->api->active_campaign_request( $body, $action_data );

		return true;
	}
}
