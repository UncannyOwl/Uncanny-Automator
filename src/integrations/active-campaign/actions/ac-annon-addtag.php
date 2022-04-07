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

	public $prefix = '';

	public function __construct() {
		$this->prefix          = 'AC_ANNON_ADDTAG';
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
			/* translators: Action sentence */
				esc_attr__( 'Add {{a tag:%1$s}} to {{a contact:%2$s}}', 'uncanny-automator' ),
				$this->get_action_meta(),
				$this->prefix . '_CONTACT_ID' . ':' . $this->get_action_meta()
			//'WPTAXONOMYTERM' . ':' . $this->trigger_meta,
			)
		);

		/* translators: Action - WordPress */
		$this->set_readable_sentence( esc_attr__( 'Add {{a tag}} to {{a contact}}', 'uncanny-automator' ) );

		$options_group = array(
			$this->get_action_meta() => array(
				array(
					'option_code'              => $this->get_action_meta(),
					/* translators: Tag field */
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
