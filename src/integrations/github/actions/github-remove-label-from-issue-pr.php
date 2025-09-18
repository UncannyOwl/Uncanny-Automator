<?php

namespace Uncanny_Automator\Integrations\Github;

/**
 * Github Remove Label from Issue or Pull Request Action.
 *
 * @package Uncanny_Automator
 *
 * @property Github_App_Helpers $helpers
 * @property Github_Api_Caller $api
 */
class GITHUB_REMOVE_LABEL_FROM_ISSUE_PR extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Repository meta key.
	 *
	 * @var string
	 */
	private $repo_meta_key;

	/**
	 * Issue or Pull Request meta key.
	 *
	 * @var string
	 */
	private $issue_pr_meta_key;

	/**
	 * Setup the action.
	 *
	 * @return void
	 */
	public function setup_action() {
		$this->repo_meta_key     = $this->helpers->get_const( 'ACTION_REPO_META_KEY' );
		$this->issue_pr_meta_key = $this->helpers->get_const( 'ACTION_ISSUE_PR_META_KEY' );

		$this->set_integration( 'GITHUB' );
		$this->set_action_code( 'GITHUB_REMOVE_LABEL_FROM_ISSUE_PR_CODE' );
		$this->set_action_meta( 'GITHUB_REMOVE_LABEL_FROM_ISSUE_PR_META' );
		$this->set_requires_user( false );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/github-integration/' ) );
		$this->set_sentence(
			sprintf(
				// translators: %1$s is label name, %2$s is the issue or PR title
				esc_attr_x( "Remove {{a label:%1\$s}} from {{an issue or pull request:%2\$s}} in a repository", 'GitHub', 'uncanny-automator' ),
				$this->get_action_meta(),
				$this->issue_pr_meta_key . ':' . $this->get_action_meta(),
			)
		);
		$this->set_readable_sentence( esc_attr_x( "Remove {{a label}} from {{an issue or pull request}} in a repository", 'GitHub', 'uncanny-automator' ) );

		$this->set_action_tokens(
			GitHub_Tokens::get_repository_label_unified_token_definitions(),
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
			$this->helpers->get_issue_or_pr_option_config( $this->issue_pr_meta_key, $this->repo_meta_key ),
			$this->helpers->get_label_option_config( $this->get_action_meta(), $this->repo_meta_key, false ),
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
	 * @throws \Exception
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		// Validate the required fields.
		$repo_parts = $this->helpers->get_repo_parts_from_parsed( $parsed, $this->repo_meta_key );
		$number     = $this->helpers->get_issue_or_pr_number_from_parsed( $parsed, $this->issue_pr_meta_key );
		$label_name = $this->helpers->get_label_from_parsed( $parsed, $this->get_action_meta() );

		$body = array(
			'action' => 'remove_label_from_issue_or_pr',
			'repo'   => $repo_parts['name'],
			'owner'  => $repo_parts['owner'],
			'number' => $number,
			'label'  => $label_name,
		);

		$response = $this->api->api_request( $body, $action_data );

		// Validate response status with custom message.
		$this->api->validate_action_response_status(
			$response,
			200,
			esc_html_x( 'Failed to remove label from issue or pull request.', 'GitHub', 'uncanny-automator' )
		);

		// Get the readable title from the select.
		$readable_title = $parsed[ $this->issue_pr_meta_key . '_readable' ] ?? '';

		// Hydrate tokens.
		$this->hydrate_tokens(
			array(
				'LABEL_NAME'        => $label_name,
				'GITHUB_REPO_NAME'  => $repo_parts['name'],
				'GITHUB_REPO_OWNER' => $repo_parts['owner'],
				'NUMBER'            => $number,
				'TITLE'             => GitHub_Tokens::clean_issue_pr_title( $readable_title ),
				'URL'               => GitHub_Tokens::generate_issue_or_pr_url( $repo_parts, $number, $readable_title ),
			)
		);

		return true;
	}
}
