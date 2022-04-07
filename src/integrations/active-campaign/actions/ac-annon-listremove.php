<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

use Uncanny_Automator\Recipe\Actions;

/**
 * Class AC_ANNON_LISTREMOVE
 *
 * @package Uncanny_Automator
 */
class AC_ANNON_LISTREMOVE {

	use Actions;

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
					'option_code'              => $this->get_action_meta(),
					/* translators: Email field */
					'label'                    => esc_attr__( 'List', 'uncanny-automator' ),
					'input_type'               => 'select',
					'supports_custom_value'    => true,
					'required'                 => true,
					'is_ajax'                  => true,
					'endpoint'                 => 'active-campaign-list-retrieve',
					'fill_values_in'           => $this->get_action_meta(),
					'custom_value_description' => _x( 'List ID', 'ActiveCampaign', 'uncanny-automator' ),
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

		try {
			
			$contact_id = $ac_helper->get_email_id( $contact_email );

			$body = array(
				'action'    => 'list_update_contact',
				'listId'    => $list_id,
				'contactId' => $contact_id,
				'status'    => 2,
			);

			$response = $ac_helper->api_request( $body, $action_data );

			Automator()->complete->action( $user_id, $action_data, $recipe_id );

		} catch ( \Exception $e ) {
			$ac_helper->complete_with_errors( $user_id, $action_data, $recipe_id, $e->getMessage() );
		}
	}
}
