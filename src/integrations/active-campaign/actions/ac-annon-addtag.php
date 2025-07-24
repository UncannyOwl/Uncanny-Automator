<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

use Uncanny_Automator\Recipe\Actions;

/**
 * Class AC_ANNON_ADDTAG
 *
 * @package Uncanny_Automator
 */
class AC_ANNON_ADDTAG {

	use Actions;

	public $prefix = 'AC_ANNON_ADDTAG';

	protected $ac_endpoint_uri = AUTOMATOR_API_URL . 'v2/active-campaign';

	/**
	 * Constructor.
	 *
	 * @return void.
	 */
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
				// translators: %1$s is the tag name, %2$s is the contact email.
				esc_attr_x( 'Add {{a tag:%1$s}} to {{a contact:%2$s}}', 'ActiveCampaign', 'uncanny-automator' ),
				$this->get_action_meta(),
				$this->prefix . '_CONTACT_ID:' . $this->get_action_meta()
			)
		);

		$this->set_readable_sentence( esc_attr_x( 'Add {{a tag}} to {{a contact}}', 'ActiveCampaign', 'uncanny-automator' ) );
		$this->set_options_callback( array( $this, 'load_options' ) );
		$this->set_background_processing( true );
		$this->register_action();
	}

	/**
	 * Load options.
	 *
	 * @return array.
	 */
	public function load_options() {

		$options_group = array(
			$this->get_action_meta() => array(
				array(
					'option_code'              => $this->get_action_meta(),
					'label'                    => esc_attr_x( 'Tag', 'ActiveCampaign', 'uncanny-automator' ),
					'input_type'               => 'select',
					'supports_custom_value'    => true,
					'required'                 => true,
					'is_ajax'                  => true,
					'endpoint'                 => 'active-campaign-list-tags',
					'fill_values_in'           => $this->get_action_meta(),
					'custom_value_description' => esc_html_x(
						"Tag ID or name. If you enter a name that doesn't already exist, the tag will be created automatically.",
						'ActiveCampaign',
						'uncanny-automator'
					),
				),
				array(
					'option_code' => $this->prefix . '_CONTACT_ID',
					'label'       => esc_attr_x( 'Email', 'ActiveCampaign', 'uncanny-automator' ),
					'placeholder' => esc_attr_x( 'me@domain.com', 'ActiveCampaign', 'uncanny-automator' ),
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

		$tag_id        = isset( $parsed[ $this->get_action_meta() ] ) ? sanitize_text_field( $parsed[ $this->get_action_meta() ] ) : 0;
		$contact_email = isset( $parsed[ $this->prefix . '_CONTACT_ID' ] ) ? sanitize_text_field( $parsed[ $this->prefix . '_CONTACT_ID' ] ) : 0;

		try {

			$contact_id = $ac_helper->get_email_id( $contact_email );

			$body = array(
				'action'    => 'add_tag',
				'tagId'     => $tag_id,
				'contactId' => $contact_id,
			);

			$response = $ac_helper->api_request( $body, $action_data );

			Automator()->complete->action( $user_id, $action_data, $recipe_id );

		} catch ( \Exception $e ) {
			$ac_helper->complete_with_errors( $user_id, $action_data, $recipe_id, $e->getMessage() );
		}
	}
}
