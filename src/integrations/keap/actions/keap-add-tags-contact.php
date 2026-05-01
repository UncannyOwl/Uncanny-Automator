<?php

namespace Uncanny_Automator\Integrations\Keap;

use Exception;
use Uncanny_Automator\Recipe\Log_Properties;

/**
 * Class KEAP_ADD_TAGS_CONTACT
 *
 * @package Uncanny_Automator
 * @property Keap_App_Helpers $helpers
 * @property Keap_Api_Caller $api
 */
class KEAP_ADD_TAGS_CONTACT extends \Uncanny_Automator\Recipe\App_Action {

	use Log_Properties;
	use Keap_Field_Helpers;
	use Keap_Tag_Fields;
	use Keap_Contact_Tokens;

	/**
	 * Set up action.
	 *
	 * @return void
	 */
	public function setup_action() {
		$this->set_integration( 'KEAP' );
		$this->set_action_code( 'KEAP_ADD_TAGS_CONTACT_CODE' );
		$this->set_action_meta( 'KEAP_ADD_TAGS_CONTACT_META' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->action_code, 'knowledge-base/keap/' ) );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				// translators: %1$s Tag field meta key, %2$s Contact field meta key
				esc_html_x( 'Add {{a tag:%1$s}} to {{a contact:%2$s}}', 'Keap', 'uncanny-automator' ),
				'TAG:' . $this->get_action_meta(),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Add {{a tag}} to {{a contact}}', 'Keap', 'uncanny-automator' ) );
		$this->set_background_processing( true );
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->get_tags_select_field_config(),
			$this->get_email_field_config( $this->get_action_meta() ),
		);
	}

	/**
	 * Define tokens.
	 *
	 * @return array
	 */
	public function define_tokens() {
		$contact   = $this->define_contact_action_tokens();
		$tag_names = $this->define_tag_name_action_token();
		return array_merge( $contact, $tag_names );
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

		// Required fields - throws error if not set and valid.
		$email = $this->get_email_from_parsed( $parsed, $this->get_action_meta() );
		$tags  = $this->get_tags_from_parsed( $parsed );

		// Send request.
		$response = $this->api->api_request(
			array(
				'action'  => 'add_tags_to_contact',
				'email'   => $email,
				'tag_ids' => $tags,
			),
			$action_data
		);

		// Examine response.
		$results  = $response['data']['results'] ?? array();
		$contact  = $response['data']['contact'] ?? 0;
		$statuses = array(
			// translators: %s: Tag ID(s)
			'DUPLICATE'        => esc_html_x( 'Tag(s) %s already applied.', 'Keap', 'uncanny-automator' ),
			// translators: %s: Tag ID(s)
			'TAG_ID_NOT_FOUND' => esc_html_x( 'Invalid tag(s) %s.', 'Keap', 'uncanny-automator' ),
			// translators: %s: Tag ID(s)
			'FAILURE'          => esc_html_x( 'Failed to apply tag(s) %s.', 'Keap', 'uncanny-automator' ),
			// translators: %s: Tag ID(s)
			'NO_PERMISSION'    => esc_html_x( 'Invalid permission to apply tag(s) %s.', 'Keap', 'uncanny-automator' ),
		);

		// Prepare any notices.
		$notices = $this->prepare_tag_notices( $results, $statuses );

		// Hydrate tokens.
		$tokens             = $this->hydrate_contact_tokens( $contact );
		$tokens['TAG_NAME'] = $this->get_tag_names_from_ids( $tags );
		$tokens['TAG']      = $tags;
		$this->hydrate_tokens( $tokens );

		// Maybe set complete with notice.
		if ( $notices ) {
			$this->set_complete_with_notice( true );
			$this->add_log_error( implode( ', ', $notices ) );
			return null;
		}

		return true;
	}
}
