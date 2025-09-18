<?php

namespace Uncanny_Automator\Integrations\Github;

use Exception;

/**
 * Github Add Release Tag to Branch Action.
 *
 * @package Uncanny_Automator
 *
 * @property Github_App_Helpers $helpers
 * @property Github_Api_Caller $api
 */
class GITHUB_ADD_RELEASE_TAG_TO_BRANCH extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Repository meta key.
	 *
	 * @var string
	 */
	private $repo_meta_key;

	/**
	 * Branch meta key.
	 *
	 * @var string
	 */
	const BRANCH_META_KEY = 'GITHUB_BRANCH';

	/**
	 * Setup the action.
	 *
	 * @return void
	 */
	public function setup_action() {
		$this->repo_meta_key = $this->helpers->get_const( 'ACTION_REPO_META_KEY' );

		$this->set_integration( 'GITHUB' );
		$this->set_action_code( 'GITHUB_ADD_RELEASE_TAG_TO_BRANCH_CODE' );
		$this->set_action_meta( 'GITHUB_ADD_RELEASE_TAG_TO_BRANCH_META' );
		$this->set_requires_user( false );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/github-integration/' ) );
		$this->set_sentence(
			sprintf(
				// translators: %1$s is the tag name, %2$s is the branch name.
				esc_attr_x( 'Add {{a release tag:%1$s}} to {{a branch:%2$s}}', 'GitHub', 'uncanny-automator' ),
				$this->get_action_meta(),
				self::BRANCH_META_KEY . ':' . $this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_attr_x( 'Add {{a release tag}} to {{a branch}}', 'GitHub', 'uncanny-automator' ) );

		$this->set_action_tokens(
			array_merge(
				GitHub_Tokens::get_repository_token_definitions(),
				GitHub_Tokens::get_release_token_definitions()
			),
			$this->get_action_code()
		);
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->helpers->get_repo_option_config( $this->repo_meta_key ),
			array(
				'option_code'     => self::BRANCH_META_KEY,
				'label'           => esc_html_x( 'Branch', 'GitHub', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'ajax'            => array(
					'endpoint'      => 'automator_github_get_repo_branches_options',
					'event'         => 'parent_fields_change',
					'listen_fields' => array( $this->repo_meta_key ),
				),
			),
			array(
				'option_code'     => $this->get_action_meta(),
				'label'           => esc_html_x( 'Release tag name', 'GitHub', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'description'     => esc_html_x( 'To create a new tag enter it as a custom value', 'GitHub', 'uncanny-automator' ),
				'ajax'            => array(
					'endpoint'      => 'automator_github_get_repo_tag_options',
					'event'         => 'parent_fields_change',
					'listen_fields' => array( $this->repo_meta_key ),
				),
			),
			array(
				'option_code'     => 'RELEASE_NAME',
				'label'           => esc_html_x( 'Release name', 'GitHub', 'uncanny-automator' ),
				'input_type'      => 'text',
				'required'        => false,
				'description'     => esc_html_x( 'Optional: Custom name for the release (defaults to tag name)', 'GitHub', 'uncanny-automator' ),
				'relevant_tokens' => array(),
			),
			array(
				'option_code'     => 'RELEASE_NOTES',
				'label'           => esc_html_x( 'Release notes', 'GitHub', 'uncanny-automator' ),
				'input_type'      => 'textarea',
				'required'        => false,
				'description'     => esc_html_x( 'Optional: Release notes and description. Supports GitHub flavored markdown.', 'GitHub', 'uncanny-automator' ),
				'relevant_tokens' => array(),
			),
		);
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
		// Validate the required fields.
		$repo_parts    = $this->helpers->get_repo_parts_from_parsed( $parsed, $this->repo_meta_key );
		$branch_name   = sanitize_text_field( $this->get_parsed_meta_value( self::BRANCH_META_KEY, '' ) );
		$tag_name      = $this->get_tag_name_from_parsed();
		$release_name  = sanitize_text_field( $this->get_parsed_meta_value( 'RELEASE_NAME', '' ) );
		$release_notes = sanitize_textarea_field( $this->get_parsed_meta_value( 'RELEASE_NOTES', '' ) );

		if ( empty( $branch_name ) ) {
			throw new Exception( esc_html_x( 'Branch is required.', 'GitHub', 'uncanny-automator' ) );
		}

		$body = array(
			'action'        => 'create_release',
			'repo'          => $repo_parts['name'],
			'owner'         => $repo_parts['owner'],
			'tag_name'      => $tag_name,
			'target_branch' => $branch_name,
			'release_name'  => $release_name,
			'release_notes' => $release_notes,
		);

		$response = $this->api->api_request( $body, $action_data );

		// Validate response status with custom message.
		$this->api->validate_action_response_status(
			$response,
			201,
			esc_html_x( 'Failed to create release.', 'GitHub', 'uncanny-automator' )
		);

		// Hydrate tokens.
		$this->hydrate_tokens(
			array(
				'TAG_NAME'           => $tag_name,
				'RELEASE_NAME'       => $release_name ? $release_name : $tag_name,
				'RELEASE_NOTES'      => $release_notes,
				'RELEASE_NOTES_RICH' => GitHub_Tokens::parse_markdown_to_html( $release_notes ),
				'GITHUB_REPO_NAME'   => $repo_parts['name'],
				'GITHUB_REPO_OWNER'  => $repo_parts['owner'],
				'BRANCH_NAME'        => $branch_name,
				'RELEASE_URL'        => $response['data']['html_url'],
			)
		);

		return true;
	}

	/**
	 * Get the tag name from the parsed data.
	 *
	 * @return string
	 * @throws Exception
	 */
	private function get_tag_name_from_parsed() {
		$tag      = sanitize_text_field( $this->get_parsed_meta_value( $this->get_action_meta(), '' ) );
		$readable = sanitize_text_field( $this->get_parsed_meta_value( $this->get_action_meta() . '_readable', '' ) );

		// Check if custom value is being used and sanitize tag name accordingly
		if ( $this->helpers->is_token_custom_value_text( $readable ) ) {
			$tag = $this->normalize_github_release_tag( $tag );
		}

		if ( empty( $tag ) ) {
			throw new Exception( esc_html_x( 'Tag is required.', 'GitHub', 'uncanny-automator' ) );
		}

		return $tag;
	}

	/**
	 * Sanitize tag name for GitHub compatibility.
	 * Examples :
	 * - Release 1.0.0 Beta -> release-1.0.0-beta
	 * - release---beta -> release-beta
	 *
	 * @param string $tag_name The raw tag name
	 *
	 * @return string The sanitized tag name
	 */
	private function normalize_github_release_tag( $tag ) {
		$tag = wp_strip_all_tags( (string) $tag );   // strip HTML if any
		$tag = remove_accents( $tag );               // normalize accents
		$tag = strtolower( $tag );

		// Replace any whitespace (space, tabs, newlines) with a single dash.
		$tag = preg_replace( '/\s+/', '-', $tag );

		// Collapse multiple dashes.
		$tag = preg_replace( '/-+/', '-', $tag );

		// Trim leading/trailing dashes.
		$tag = trim( $tag, '-' );

		// Don’t allow starting with '.' or '/'.
		$tag = ltrim( $tag, './' );

		return $tag;
	}
}
