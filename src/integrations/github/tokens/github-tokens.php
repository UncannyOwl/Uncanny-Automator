<?php

namespace Uncanny_Automator\Integrations\Github;

/**
 * GitHub Tokens Class
 *
 * Provides common token definitions for GitHub actions.
 *
 * @package Uncanny_Automator\Integrations\Github\Tokens
 */
class GitHub_Tokens {

	/**
	 * Get repository token definitions.
	 *
	 * @return array
	 */
	public static function get_repository_token_definitions() {
		return array(
			'GITHUB_REPO_NAME'  => array(
				'name' => esc_html_x( 'Repository name', 'GitHub', 'uncanny-automator' ),
				'type' => 'text',
			),
			'GITHUB_REPO_OWNER' => array(
				'name' => esc_html_x( 'Repository owner', 'GitHub', 'uncanny-automator' ),
				'type' => 'text',
			),
		);
	}

	/**
	 * Get comment token definitions.
	 *
	 * @return array
	 */
	public static function get_comment_token_definitions() {
		return array(
			'COMMENT'      => array(
				'name' => esc_html_x( 'Comment', 'GitHub', 'uncanny-automator' ),
				'type' => 'text',
			),
			'COMMENT_RICH' => array(
				'name' => esc_html_x( 'Comment (formatted)', 'GitHub', 'uncanny-automator' ),
				'type' => 'text',
			),
			'COMMENT_URL'  => array(
				'name' => esc_html_x( 'Comment URL', 'GitHub', 'uncanny-automator' ),
				'type' => 'text',
			),
		);
	}

	/**
	 * Get pull request token definitions.
	 *
	 * @return array
	 */
	public static function get_pull_request_token_definitions() {
		return array(
			'PR_NUMBER' => array(
				'name' => esc_html_x( 'Pull request number', 'GitHub', 'uncanny-automator' ),
				'type' => 'text',
			),
			'PR_TITLE'  => array(
				'name' => esc_html_x( 'Pull request title', 'GitHub', 'uncanny-automator' ),
				'type' => 'text',
			),
		);
	}

	/**
	 * Get issue or pull request token definitions.
	 *
	 * @return array
	 */
	public static function get_issue_or_pr_token_definitions() {
		return array(
			'NUMBER' => array(
				'name' => esc_html_x( 'Issue or Pull Request number', 'GitHub', 'uncanny-automator' ),
				'type' => 'text',
			),
			'TITLE'  => array(
				'name' => esc_html_x( 'Issue or Pull Request title', 'GitHub', 'uncanny-automator' ),
				'type' => 'text',
			),
			'URL'    => array(
				'name' => esc_html_x( 'Issue or Pull Request URL', 'GitHub', 'uncanny-automator' ),
				'type' => 'text',
			),
		);
	}

	/**
	 * Get release token definitions.
	 *
	 * @return array
	 */
	public static function get_release_token_definitions() {
		return array(
			'TAG_NAME'           => array(
				'name' => esc_html_x( 'Release tag name', 'GitHub', 'uncanny-automator' ),
				'type' => 'text',
			),
			'RELEASE_NAME'       => array(
				'name' => esc_html_x( 'Release name', 'GitHub', 'uncanny-automator' ),
				'type' => 'text',
			),
			'RELEASE_NOTES'      => array(
				'name' => esc_html_x( 'Release notes', 'GitHub', 'uncanny-automator' ),
				'type' => 'text',
			),
			'RELEASE_NOTES_RICH' => array(
				'name' => esc_html_x( 'Release notes (formatted)', 'GitHub', 'uncanny-automator' ),
				'type' => 'text',
			),
			'BRANCH_NAME'        => array(
				'name' => esc_html_x( 'Branch name', 'GitHub', 'uncanny-automator' ),
				'type' => 'text',
			),
			'RELEASE_URL'        => array(
				'name' => esc_html_x( 'Release URL', 'GitHub', 'uncanny-automator' ),
				'type' => 'text',
			),
		);
	}

	/**
	 * Get label token definitions.
	 *
	 * @return array
	 */
	public static function get_label_token_definitions() {
		return array(
			'LABEL_NAME' => array(
				'name' => esc_html_x( 'Label name', 'GitHub', 'uncanny-automator' ),
				'type' => 'text',
			),
		);
	}

	/**
	 * Get combined repository, label, and issue/PR token definitions for label actions.
	 *
	 * @return array
	 */
	public static function get_repository_label_unified_token_definitions() {
		return array_merge(
			self::get_repository_token_definitions(),
			self::get_label_token_definitions(),
			self::get_issue_or_pr_token_definitions()
		);
	}

	/**
	 * Generate Issue or PR URL based on repository parts, number, and readable title.
	 *
	 * @param array $repo_parts Repository parts with 'owner' and 'name' keys
	 * @param int $number Issue/PR number
	 * @param string $readable_title The readable title with [Issue] or [PR] prefix
	 *
	 * @return string The generated URL
	 */
	public static function generate_issue_or_pr_url( $repo_parts, $number, $readable_title ) {
		// Check if we can determine PR from the start of the title.
		// If not always fallback to issues as GitHub will redirect properly anyways.
		$url_type = 0 === strncmp( $readable_title, '[PR] ', 5 )
			? 'pull'
			: 'issues';

		return sprintf(
			'https://github.com/%s/%s/%s/%d',
			$repo_parts['owner'],
			$repo_parts['name'],
			$url_type,
			$number
		);
	}

	/**
	 * Clean issue/PR title by removing [Issue] or [PR] prefix.
	 *
	 * @param string $title The title with prefix
	 *
	 * @return string The cleaned title
	 */
	public static function clean_issue_pr_title( $title ) {
		// Remove [Issue] or [PR] prefix and any leading whitespace
		return preg_replace( '/^\[(?:Issue|PR)\]\s*/', '', $title );
	}

	/**
	 * Parse markdown content to HTML using the Markdown_Parser service.
	 *
	 * @param string $markdown The markdown content to parse.
	 * @return string The parsed HTML content.
	 */
	public static function parse_markdown_to_html( $markdown ) {
		if ( empty( $markdown ) ) {
			return '';
		}

		$parser = new \Uncanny_Automator\Services\Markdown\Markdown_Parser();
		return $parser->parse( $markdown );
	}
}
