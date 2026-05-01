<?php
//phpcs:disable PHPCompatibility.Operators.NewOperators.t_coalesceFound

namespace Uncanny_Automator\Integrations\Aweber;

use Exception;

/**
 * Class AWEBER_SUBSCRIBER_TAG_ADD
 *
 * @package Uncanny_Automator
 * @property Aweber_App_Helpers $helpers
 * @property Aweber_Api_Caller $api
 */
class AWEBER_SUBSCRIBER_TAG_ADD extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Spins up new action inside "AWEBER" integration.
	 *
	 * @return void
	 */
	public function setup_action() {
		$this->set_integration( 'AWEBER' );
		$this->set_action_code( 'AWEBER_SUBSCRIBER_TAG_ADD_CODE' );
		$this->set_action_meta( 'AWEBER_SUBSCRIBER_TAG_ADD_META' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->action_code, 'knowledge-base/aweber/' ) );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				// translators: 1: Tag field meta key, 2: Subscriber field meta key
				esc_html_x( 'Add {{a tag:%1$s}} to {{a subscriber:%2$s}}', 'AWeber', 'uncanny-automator' ),
				'NON_EXISTING:' . $this->get_action_meta(),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Add {{a tag}} to {{a subscriber}}', 'AWeber', 'uncanny-automator' ) );
		$this->set_background_processing( true );
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->helpers->get_account_option_config(),
			$this->helpers->get_list_option_config(),
			$this->helpers->get_email_option_config(
				$this->get_action_meta(),
				esc_html_x( 'Subscriber email', 'AWeber', 'uncanny-automator' )
			),
			array(
				'option_code' => 'TAGS',
				'label'       => esc_html_x( 'Tags', 'AWeber', 'uncanny-automator' ),
				'description' => esc_html_x( 'Please enter a comma-separated list of values.', 'AWeber', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => true,
			),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int   $user_id     The user ID.
	 * @param array $action_data The action data.
	 * @param int   $recipe_id   The recipe ID.
	 * @param array $args        The args.
	 * @param array $parsed      The parsed values.
	 *
	 * @return bool
	 * @throws Exception If validation fails or API request fails.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$account_id = $this->helpers->get_account_from_parsed( $parsed );
		$list_id    = $this->helpers->get_list_from_parsed( $parsed );
		$email      = $this->helpers->get_email_from_parsed( $parsed, $this->get_action_meta() );

		// Process and validate tags.
		$tags = array_filter( array_map( 'trim', explode( ',', $parsed['TAGS'] ?? '' ) ) );
		if ( empty( $tags ) ) {
			throw new Exception( esc_html_x( 'At least one tag is required.', 'AWeber', 'uncanny-automator' ) );
		}

		$body = array(
			'action'     => 'add_tags_subscriber',
			'account_id' => $account_id,
			'list_id'    => $list_id,
			'email'      => $email,
			'tags'       => wp_json_encode( $tags ),
		);

		$this->api->api_request( $body, $action_data );

		return true;
	}
}
