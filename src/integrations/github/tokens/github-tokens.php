<?php

namespace Uncanny_Automator\Integrations\Github;

/**
 * GitHub Tokens
 *
 * Single source of truth for every GitHub token label used by both action
 * recipes and trigger hydration. Tokens are returned in the trigger-format
 * shape (`tokenId` / `tokenName` / `tokenType`) — `set_action_tokens()`
 * auto-converts that shape on the action side via Action_Tokens trait.
 *
 * Token IDs are preserved byte-for-byte from the prior split classes so
 * existing recipes continue to resolve. Action-side method names are
 * unchanged; trigger-side methods carry a `_trigger_` infix to avoid
 * collisions on shared concepts like repository / pull-request / release
 * where action and trigger return different field sets under the same
 * shape.
 *
 * @package Uncanny_Automator\Integrations\Github
 */
class Github_Tokens {

	/**
	 * Webhooks helper reference, set by triggers via {@see self::set_webhooks()}.
	 * Used by the dot-path payload accessor and rich-text hydration paths.
	 *
	 * @var object|null
	 */
	private static $webhooks = null;

	/**
	 * Provide the webhooks helper to the trigger-side hydration paths.
	 *
	 * @param object $webhooks Github_Webhooks instance (or anything with `get_payload_value` + `get_rich_text_value`).
	 *
	 * @return void
	 */
	public static function set_webhooks( $webhooks ) {
		self::$webhooks = $webhooks;
	}

	////////////////////////////////////////////////////////////
	// Internal label pool
	////////////////////////////////////////////////////////////

	/**
	 * Label + type definitions keyed by full token ID.
	 *
	 * Token IDs are kept identical to the prior split classes so recipe
	 * data stays valid. Where action-side and trigger-side share an ID
	 * (currently only `GITHUB_REPO_NAME`), the entry is defined once.
	 *
	 * @return array<string, array{name: string, type: string}>
	 */
	private static function token_labels() {
		return array(
			// Repository — action-side
			'GITHUB_REPO_NAME'              => array(
				'name' => esc_html_x( 'Repository name', 'GitHub', 'uncanny-automator' ),
				'type' => 'text',
			),
			'GITHUB_REPO_OWNER'             => array(
				'name' => esc_html_x( 'Repository owner', 'GitHub', 'uncanny-automator' ),
				'type' => 'text',
			),
			// Repository — trigger-side extras
			'GITHUB_REPO_ID'                => array(
				'name' => esc_html_x( 'Repository ID', 'GitHub', 'uncanny-automator' ),
				'type' => 'int',
			),
			'GITHUB_REPO_URL'               => array(
				'name' => esc_html_x( 'Repository URL', 'GitHub', 'uncanny-automator' ),
				'type' => 'text',
			),
			// Sender (trigger-only)
			'GITHUB_SENDER_LOGIN'           => array(
				'name' => esc_html_x( 'Sender login', 'GitHub', 'uncanny-automator' ),
				'type' => 'text',
			),
			'GITHUB_SENDER_AVATAR_URL'      => array(
				'name' => esc_html_x( 'Sender avatar URL', 'GitHub', 'uncanny-automator' ),
				'type' => 'text',
			),
			'GITHUB_SENDER_EMAIL'           => array(
				'name' => esc_html_x( 'Sender email', 'GitHub', 'uncanny-automator' ),
				'type' => 'text',
			),
			// Comment (action-only)
			'COMMENT'                       => array(
				'name' => esc_html_x( 'Comment', 'GitHub', 'uncanny-automator' ),
				'type' => 'text',
			),
			'COMMENT_RICH'                  => array(
				'name' => esc_html_x( 'Comment (formatted)', 'GitHub', 'uncanny-automator' ),
				'type' => 'text',
			),
			'COMMENT_URL'                   => array(
				'name' => esc_html_x( 'Comment URL', 'GitHub', 'uncanny-automator' ),
				'type' => 'text',
			),
			// Pull request — action-side
			'PR_NUMBER'                     => array(
				'name' => esc_html_x( 'Pull request number', 'GitHub', 'uncanny-automator' ),
				'type' => 'text',
			),
			'PR_TITLE'                      => array(
				'name' => esc_html_x( 'Pull request title', 'GitHub', 'uncanny-automator' ),
				'type' => 'text',
			),
			// Pull request — trigger-side
			'GITHUB_PULL_REQUEST_TITLE'     => array(
				'name' => esc_html_x( 'Pull request title', 'GitHub', 'uncanny-automator' ),
				'type' => 'text',
			),
			'GITHUB_PULL_REQUEST_BODY'      => array(
				'name' => esc_html_x( 'Pull request body', 'GitHub', 'uncanny-automator' ),
				'type' => 'text',
			),
			'GITHUB_PULL_REQUEST_BODY_RICH' => array(
				'name' => esc_html_x( 'Pull request body (formatted)', 'GitHub', 'uncanny-automator' ),
				'type' => 'text',
			),
			'GITHUB_PULL_REQUEST_URL'       => array(
				'name' => esc_html_x( 'Pull request URL', 'GitHub', 'uncanny-automator' ),
				'type' => 'text',
			),
			'GITHUB_PULL_REQUEST_ID'        => array(
				'name' => esc_html_x( 'Pull request ID', 'GitHub', 'uncanny-automator' ),
				'type' => 'text',
			),
			'GITHUB_REF'                    => array(
				'name' => esc_html_x( 'Reference', 'GitHub', 'uncanny-automator' ),
				'type' => 'text',
			),
			// Issue or PR (action-only)
			'NUMBER'                        => array(
				'name' => esc_html_x( 'Issue or Pull Request number', 'GitHub', 'uncanny-automator' ),
				'type' => 'text',
			),
			'TITLE'                         => array(
				'name' => esc_html_x( 'Issue or Pull Request title', 'GitHub', 'uncanny-automator' ),
				'type' => 'text',
			),
			'URL'                           => array(
				'name' => esc_html_x( 'Issue or Pull Request URL', 'GitHub', 'uncanny-automator' ),
				'type' => 'text',
			),
			// Release — action-side
			'TAG_NAME'                      => array(
				'name' => esc_html_x( 'Release tag name', 'GitHub', 'uncanny-automator' ),
				'type' => 'text',
			),
			'RELEASE_NAME'                  => array(
				'name' => esc_html_x( 'Release name', 'GitHub', 'uncanny-automator' ),
				'type' => 'text',
			),
			'RELEASE_NOTES'                 => array(
				'name' => esc_html_x( 'Release notes', 'GitHub', 'uncanny-automator' ),
				'type' => 'text',
			),
			'RELEASE_NOTES_RICH'            => array(
				'name' => esc_html_x( 'Release notes (formatted)', 'GitHub', 'uncanny-automator' ),
				'type' => 'text',
			),
			'BRANCH_NAME'                   => array(
				'name' => esc_html_x( 'Branch name', 'GitHub', 'uncanny-automator' ),
				'type' => 'text',
			),
			'RELEASE_URL'                   => array(
				'name' => esc_html_x( 'Release URL', 'GitHub', 'uncanny-automator' ),
				'type' => 'text',
			),
			// Release — trigger-side
			'GITHUB_RELEASE_TITLE'          => array(
				'name' => esc_html_x( 'Release title', 'GitHub', 'uncanny-automator' ),
				'type' => 'text',
			),
			'GITHUB_RELEASE_BODY'           => array(
				'name' => esc_html_x( 'Release body', 'GitHub', 'uncanny-automator' ),
				'type' => 'text',
			),
			'GITHUB_RELEASE_BODY_RICH'      => array(
				'name' => esc_html_x( 'Release body (formatted)', 'GitHub', 'uncanny-automator' ),
				'type' => 'text',
			),
			'GITHUB_RELEASE_URL'            => array(
				'name' => esc_html_x( 'Release URL', 'GitHub', 'uncanny-automator' ),
				'type' => 'text',
			),
			'GITHUB_RELEASE_TAG'            => array(
				'name' => esc_html_x( 'Release tag', 'GitHub', 'uncanny-automator' ),
				'type' => 'text',
			),
			// Label (action-only)
			'LABEL_NAME'                    => array(
				'name' => esc_html_x( 'Label name', 'GitHub', 'uncanny-automator' ),
				'type' => 'text',
			),
			// Issue (trigger-only)
			'GITHUB_ISSUE_TITLE'            => array(
				'name' => esc_html_x( 'Issue title', 'GitHub', 'uncanny-automator' ),
				'type' => 'text',
			),
			'GITHUB_ISSUE_BODY'             => array(
				'name' => esc_html_x( 'Issue body', 'GitHub', 'uncanny-automator' ),
				'type' => 'text',
			),
			'GITHUB_ISSUE_BODY_RICH'        => array(
				'name' => esc_html_x( 'Issue body (formatted)', 'GitHub', 'uncanny-automator' ),
				'type' => 'text',
			),
			'GITHUB_ISSUE_URL'              => array(
				'name' => esc_html_x( 'Issue URL', 'GitHub', 'uncanny-automator' ),
				'type' => 'text',
			),
			'GITHUB_ISSUE_NUMBER'           => array(
				'name' => esc_html_x( 'Issue number', 'GitHub', 'uncanny-automator' ),
				'type' => 'int',
			),
			'GITHUB_ISSUE_STATE'            => array(
				'name' => esc_html_x( 'Issue state', 'GitHub', 'uncanny-automator' ),
				'type' => 'text',
			),
			'GITHUB_ISSUE_CREATOR'          => array(
				'name' => esc_html_x( 'Issue creator', 'GitHub', 'uncanny-automator' ),
				'type' => 'text',
			),
		);
	}

	/**
	 * Map a list of token IDs to trigger-format token entries.
	 *
	 * @param string[] $ids Token IDs that exist in {@see self::token_labels()}.
	 *
	 * @return array
	 */
	private static function build_tokens( array $ids ) {
		$labels = self::token_labels();
		$out    = array();
		foreach ( $ids as $id ) {
			if ( ! isset( $labels[ $id ] ) ) {
				continue;
			}
			$out[] = array(
				'tokenId'   => $id,
				'tokenName' => $labels[ $id ]['name'],
				'tokenType' => $labels[ $id ]['type'],
			);
		}
		return $out;
	}

	////////////////////////////////////////////////////////////
	// Action-side definitions
	////////////////////////////////////////////////////////////

	/**
	 * Repository tokens for actions.
	 *
	 * @return array
	 */
	public static function get_repository_token_definitions() {
		return self::build_tokens( array( 'GITHUB_REPO_NAME', 'GITHUB_REPO_OWNER' ) );
	}

	/**
	 * Comment tokens for actions.
	 *
	 * @return array
	 */
	public static function get_comment_token_definitions() {
		return self::build_tokens( array( 'COMMENT', 'COMMENT_RICH', 'COMMENT_URL' ) );
	}

	/**
	 * Pull request tokens for actions.
	 *
	 * @return array
	 */
	public static function get_pull_request_token_definitions() {
		return self::build_tokens( array( 'PR_NUMBER', 'PR_TITLE' ) );
	}

	/**
	 * Issue-or-PR tokens for actions.
	 *
	 * @return array
	 */
	public static function get_issue_or_pr_token_definitions() {
		return self::build_tokens( array( 'NUMBER', 'TITLE', 'URL' ) );
	}

	/**
	 * Release tokens for actions.
	 *
	 * @return array
	 */
	public static function get_release_token_definitions() {
		return self::build_tokens(
			array( 'TAG_NAME', 'RELEASE_NAME', 'RELEASE_NOTES', 'RELEASE_NOTES_RICH', 'BRANCH_NAME', 'RELEASE_URL' )
		);
	}

	/**
	 * Label tokens for actions.
	 *
	 * @return array
	 */
	public static function get_label_token_definitions() {
		return self::build_tokens( array( 'LABEL_NAME' ) );
	}

	/**
	 * Composite: repository + label + issue-or-PR (for label actions).
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

	////////////////////////////////////////////////////////////
	// Trigger-side definitions
	////////////////////////////////////////////////////////////

	/**
	 * Repository tokens for triggers.
	 *
	 * @return array
	 */
	public static function get_trigger_repository_token_definitions() {
		return self::build_tokens( array( 'GITHUB_REPO_ID', 'GITHUB_REPO_NAME', 'GITHUB_REPO_URL' ) );
	}

	/**
	 * Sender tokens for triggers.
	 *
	 * @return array
	 */
	public static function get_trigger_sender_token_definitions() {
		return self::build_tokens(
			array( 'GITHUB_SENDER_LOGIN', 'GITHUB_SENDER_AVATAR_URL', 'GITHUB_SENDER_EMAIL' )
		);
	}

	/**
	 * Pull request tokens for triggers.
	 *
	 * @return array
	 */
	public static function get_trigger_pull_request_token_definitions() {
		return self::build_tokens(
			array(
				'GITHUB_PULL_REQUEST_TITLE',
				'GITHUB_PULL_REQUEST_BODY',
				'GITHUB_PULL_REQUEST_BODY_RICH',
				'GITHUB_PULL_REQUEST_URL',
				'GITHUB_PULL_REQUEST_ID',
				'GITHUB_REF',
			)
		);
	}

	/**
	 * Release tokens for triggers.
	 *
	 * @return array
	 */
	public static function get_trigger_release_token_definitions() {
		return self::build_tokens(
			array(
				'GITHUB_RELEASE_TITLE',
				'GITHUB_RELEASE_BODY',
				'GITHUB_RELEASE_BODY_RICH',
				'GITHUB_RELEASE_URL',
				'GITHUB_RELEASE_TAG',
			)
		);
	}

	/**
	 * Issue tokens for triggers.
	 *
	 * @return array
	 */
	public static function get_trigger_issue_token_definitions() {
		return self::build_tokens(
			array(
				'GITHUB_ISSUE_TITLE',
				'GITHUB_ISSUE_BODY',
				'GITHUB_ISSUE_BODY_RICH',
				'GITHUB_ISSUE_URL',
				'GITHUB_ISSUE_NUMBER',
				'GITHUB_ISSUE_STATE',
				'GITHUB_ISSUE_CREATOR',
			)
		);
	}

	////////////////////////////////////////////////////////////
	// Trigger-side hydration
	////////////////////////////////////////////////////////////

	/**
	 * Hydrate repository token values from a webhook payload.
	 *
	 * @param array $payload GitHub webhook payload.
	 *
	 * @return array
	 */
	public static function hydrate_repository_tokens( $payload ) {
		return array(
			'GITHUB_REPO_ID'   => self::get_value( $payload, 'repository.id' ),
			'GITHUB_REPO_NAME' => self::get_value( $payload, 'repository.name' ),
			'GITHUB_REPO_URL'  => self::get_value( $payload, 'repository.html_url' ),
		);
	}

	/**
	 * Hydrate sender token values from a webhook payload.
	 *
	 * @param array $payload GitHub webhook payload.
	 *
	 * @return array
	 */
	public static function hydrate_sender_tokens( $payload ) {
		return array(
			'GITHUB_SENDER_LOGIN'      => self::get_value( $payload, 'sender.login' ),
			'GITHUB_SENDER_AVATAR_URL' => self::get_value( $payload, 'sender.avatar_url' ),
			'GITHUB_SENDER_EMAIL'      => self::get_sender_email( $payload ),
		);
	}

	/**
	 * Hydrate pull request token values from a webhook payload.
	 *
	 * @param array $payload GitHub webhook payload.
	 *
	 * @return array
	 */
	public static function hydrate_pull_request_tokens( $payload ) {
		return array(
			'GITHUB_PULL_REQUEST_TITLE'     => self::get_value( $payload, 'pull_request.title' ),
			'GITHUB_PULL_REQUEST_BODY'      => self::get_value( $payload, 'pull_request.body' ),
			'GITHUB_PULL_REQUEST_URL'       => self::get_value( $payload, 'pull_request.html_url' ),
			'GITHUB_PULL_REQUEST_BODY_RICH' => self::$webhooks
				? self::$webhooks->get_rich_text_value( $payload, 'pull_request.body' )
				: '',
			'GITHUB_PULL_REQUEST_ID'        => self::get_value( $payload, 'pull_request.number' ),
			'GITHUB_REF'                    => self::get_value( $payload, 'pull_request.base.ref' ),
		);
	}

	/**
	 * Hydrate release token values from a webhook payload.
	 *
	 * @param array $payload GitHub webhook payload.
	 *
	 * @return array
	 */
	public static function hydrate_release_tokens( $payload ) {
		return array(
			'GITHUB_RELEASE_TITLE'     => self::get_value( $payload, 'release.name' ),
			'GITHUB_RELEASE_BODY'      => self::get_value( $payload, 'release.body' ),
			'GITHUB_RELEASE_URL'       => self::get_value( $payload, 'release.html_url' ),
			'GITHUB_RELEASE_BODY_RICH' => self::$webhooks
				? self::$webhooks->get_rich_text_value( $payload, 'release.body' )
				: '',
			'GITHUB_RELEASE_TAG'       => self::get_value( $payload, 'release.tag_name' ),
		);
	}

	/**
	 * Hydrate issue token values from a webhook payload.
	 *
	 * @param array $payload GitHub webhook payload.
	 *
	 * @return array
	 */
	public static function hydrate_issue_tokens( $payload ) {
		return array(
			'GITHUB_ISSUE_TITLE'     => self::get_value( $payload, 'issue.title' ),
			'GITHUB_ISSUE_BODY'      => self::get_value( $payload, 'issue.body' ),
			'GITHUB_ISSUE_URL'       => self::get_value( $payload, 'issue.html_url' ),
			'GITHUB_ISSUE_BODY_RICH' => self::$webhooks
				? self::$webhooks->get_rich_text_value( $payload, 'issue.body' )
				: '',
			'GITHUB_ISSUE_NUMBER'    => self::get_value( $payload, 'issue.number' ),
			'GITHUB_ISSUE_STATE'     => self::get_value( $payload, 'issue.state' ),
			'GITHUB_ISSUE_CREATOR'   => self::get_value( $payload, 'issue.user.login' ),
		);
	}

	////////////////////////////////////////////////////////////
	// Action-side utilities
	////////////////////////////////////////////////////////////

	/**
	 * Build an Issue or Pull Request URL from repo parts + number, choosing
	 * the path segment from the readable title's `[Issue]` / `[PR]` prefix.
	 * Falls back to `issues` (GitHub redirects to `pull` when appropriate).
	 *
	 * @param array  $repo_parts     Repository parts with `owner` and `name` keys.
	 * @param int    $number         Issue/PR number.
	 * @param string $readable_title Readable title with `[Issue]` or `[PR]` prefix.
	 *
	 * @return string
	 */
	public static function generate_issue_or_pr_url( $repo_parts, $number, $readable_title ) {
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
	 * Strip the `[Issue]` / `[PR]` prefix and following whitespace from a readable title.
	 *
	 * @param string $title Title with prefix.
	 *
	 * @return string
	 */
	public static function clean_issue_pr_title( $title ) {
		return preg_replace( '/^\[(?:Issue|PR)\]\s*/', '', $title );
	}

	/**
	 * Parse markdown to HTML via the Markdown_Parser service.
	 *
	 * @param string $markdown Markdown source.
	 *
	 * @return string
	 */
	public static function parse_markdown_to_html( $markdown ) {
		if ( empty( $markdown ) ) {
			return '';
		}
		$parser = new \Uncanny_Automator\Services\Markdown\Markdown_Parser();
		return $parser->parse( $markdown );
	}

	////////////////////////////////////////////////////////////
	// Internal helpers
	////////////////////////////////////////////////////////////

	/**
	 * Fetch a value from a webhook payload via the helper's dot-path accessor.
	 *
	 * @param array  $payload GitHub webhook payload.
	 * @param string $key     Dot-notation key (e.g. `repository.id`).
	 *
	 * @return string
	 */
	private static function get_value( $payload, $key ) {
		if ( ! self::$webhooks ) {
			return '';
		}
		return self::$webhooks->get_payload_value( $payload, $key );
	}

	/**
	 * Resolve a sender email from a webhook payload using a series of fallbacks.
	 * GitHub often omits emails for privacy; this walks the common payload paths
	 * and finally exposes a filter for custom resolution.
	 *
	 * @param array $payload GitHub webhook payload.
	 *
	 * @return string
	 */
	private static function get_sender_email( $payload ) {
		$email_paths = array(
			'sender.email',             // most common for some events
			'pusher.email',             // push events
			'comment.user.email',       // comment events
			'issue.user.email',         // issue events
			'pull_request.user.email',  // PR events
			'release.author.email',     // release events
			'forkee.owner.email',       // fork events
			'deployment.creator.email', // deployment events
			'workflow_run.actor.email', // workflow run events
		);

		foreach ( $email_paths as $path ) {
			$email = self::get_value( $payload, $path );
			if ( ! empty( $email ) ) {
				return $email;
			}
		}

		/**
		 * Filter the resolved sender email — allows custom extraction (API calls,
		 * DB lookups, mapping logic) when the payload paths above don't yield one.
		 *
		 * @param string $email   Resolved email (empty if no path matched).
		 * @param array  $payload Full GitHub webhook payload.
		 *
		 * @return string
		 */
		return apply_filters( 'automator_github_sender_email', '', $payload );
	}
}
