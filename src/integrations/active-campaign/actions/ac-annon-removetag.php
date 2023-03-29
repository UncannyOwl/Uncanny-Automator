<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

use Uncanny_Automator\Recipe\Actions;

/**
 * Class AC_ANNON_REMOVETAG
 *
 * @package Uncanny_Automator
 */
class AC_ANNON_REMOVETAG {

	use Actions;

	public $prefix = 'AC_ANNON_REMOVETAG';

	protected $ac_endpoint_uri = AUTOMATOR_API_URL . 'v2/active-campaign';

	public function __construct() {

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

		$this->set_sentence(
			sprintf(
				/* translators: Action - WordPress */
				esc_attr__( 'Remove {{a tag:%1$s}} from {{a contact:%2$s}}', 'uncanny-automator' ),
				$this->get_action_meta(),
				$this->prefix . '_CONTACT_ID:' . $this->get_action_meta()
			)
		);

		/* translators: Action - WordPress */
		$this->set_readable_sentence( esc_attr__( 'Remove {{a tag}} from {{a contact}}', 'uncanny-automator' ) );

		$this->set_options_callback( array( $this, 'load_options' ) );

		$this->set_background_processing( true );

		$this->register_action();

	}

	public function load_options() {

		$options_group = array(
			$this->get_action_meta() => array(
				array(
					'option_code'              => $this->get_action_meta(),
					/* translators: Email field */
					'label'                    => esc_attr__( 'Tag', 'uncanny-automator' ),
					'input_type'               => 'select',
					'supports_custom_value'    => true,
					'required'                 => true,
					'is_ajax'                  => true,
					'endpoint'                 => 'active-campaign-list-tags',
					'fill_values_in'           => $this->get_action_meta(),
					'custom_value_description' => _x( 'Tag ID', 'ActiveCampaign', 'uncanny-automator' ),
				),
				array(
					'option_code' => $this->prefix . '_CONTACT_ID',
					/* translators: Contact field */
					'label'       => esc_attr__( 'Email', 'uncanny-automator' ),
					'placeholder' => esc_attr__( 'me@domain.com', 'uncanny-automator' ),
					'input_type'  => 'email',
					'required'    => true,
				),
			),
		);

		return Automator()->utilities->keep_order_of_options(
			array(
				'options_group' => $options_group,
			)
		);
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

		$contact_email = isset( $parsed[ $this->prefix . '_CONTACT_ID' ] ) ? sanitize_text_field( $parsed[ $this->prefix . '_CONTACT_ID' ] ) : 0;
		$tag_id        = isset( $parsed[ $this->get_action_meta() ] ) ? sanitize_text_field( $parsed[ $this->get_action_meta() ] ) : 0;

		try {

			$contact_id = $ac_helper->get_email_id( $contact_email );

			$contact_tag_id = $ac_helper->get_tag_id( $contact_id, $tag_id );

			// Delete the tag.
			$body = array(
				'action'       => 'delete_contact_tag',
				'contactTagId' => $contact_tag_id,
			);

			$response = $ac_helper->api_request( $body, $action_data );

			if ( ! empty( $response['data']['message'] ) ) {
				throw new \Exception( $response['data']['message'], $response['statusCode'] );
			}

			Automator()->complete->action( $user_id, $action_data, $recipe_id );

		} catch ( \Exception $e ) {
			$ac_helper->complete_with_errors( $user_id, $action_data, $recipe_id, $e->getMessage() );
		}
	}
}
