<?php

namespace Uncanny_Automator\Integrations\Keap;

use Exception;
use Uncanny_Automator\Recipe\Log_Properties;

/**
 * Class KEAP_ADD_TAGS_CONTACT
 *
 * @package Uncanny_Automator
 */
class KEAP_ADD_TAGS_CONTACT extends \Uncanny_Automator\Recipe\Action {

	use Log_Properties;

	/**
	 * Prefix for action code / meta.
	 *
	 * @var string
	 */
	public $prefix = 'KEAP_ADD_TAGS_CONTACT';

	/**
	 * Set up action.
	 *
	 * @return void
	 */
	public function setup_action() {

		/** @var \Uncanny_Automator\Integrations\Keap\Keap_Helpers $helper */
		$this->helpers = array_shift( $this->dependencies );

		$this->set_integration( 'KEAP' );
		$this->set_action_code( $this->prefix . '_CODE' );
		$this->set_action_meta( $this->prefix . '_META' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->action_code, 'knowledge-base/keap/' ) );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				// translators: %1$s Tag Name(s), Contact Email, %2$s
				esc_attr_x( 'Add {{a tag:%1$s}} to {{a contact:%2$s}}', 'Keap', 'uncanny-automator' ),
				'TAG:' . $this->get_action_meta(),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_attr_x( 'Add {{a tag}} to {{a contact}}', 'Keap', 'uncanny-automator' ) );
		$this->set_background_processing( true );
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			// Tags.
			$this->helpers->get_tags_select_field_config(),
			// Email.
			$this->helpers->get_email_field_config( $this->get_action_meta() ),
		);
	}

	/**
	 * Define tokens.
	 *
	 * @return array
	 */
	public function define_tokens() {
		$contact   = $this->helpers->define_contact_action_tokens();
		$tag_names = $this->helpers->define_tag_name_action_token();
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
		$email = $this->helpers->get_email_from_parsed( $parsed, $this->get_action_meta() );
		$tags  = $this->helpers->get_tags_from_parsed( $parsed );

		// Send request.
		$response = $this->helpers->api_request(
			'add_tags_to_contact',
			array(
				'email'   => $email,
				'tag_ids' => $tags,
			),
			$action_data
		);

		// Examine response.
		$results  = $response['data']['results'] ?? array();
		$contact  = $response['data']['contact'] ?? 0;
		$statuses = array(
			// translators: %s Tag ID(s)
			'DUPLICATE'        => _x( 'Tag(s) %s already applied.', 'Keap', 'uncanny-automator' ),
			// translators: %s Tag ID(s)
			'TAG_ID_NOT_FOUND' => _x( 'Invalid tag(s) %s.', 'Keap', 'uncanny-automator' ),
			// translators: %s Tag ID(s)
			'FAILURE'          => _x( 'Failed to apply tag(s) %s.', 'Keap', 'uncanny-automator' ),
			// translators: %s Tag ID(s)
			'NO_PERMISSION'    => _x( 'Invalid permission to apply tag(s) %s.', 'Keap', 'uncanny-automator' ),
		);

		// Prepare any notices.
		$notices = $this->helpers->prepare_tag_notices( $results, $statuses );

		// Hydrate tokens.
		$tokens             = $this->helpers->hydrate_contact_tokens( $contact );
		$tokens['TAG_NAME'] = $this->helpers->get_tag_names_from_ids( $tags );
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
