<?php

namespace Uncanny_Automator\Integrations\Github;

/**
 * Trigger: A pull request is merged in {a repository}
 *
 * @package Uncanny_Automator\Integrations\Github
 *
 * @property Github_App_Helpers $helpers
 * @property Github_Api_Caller $api
 * @property Github_Webhooks $webhooks
 */
class PULL_REQUEST_MERGED_IN_REPO extends \Uncanny_Automator\Recipe\App_Trigger {

	/**
	 * Setup the trigger.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		$this->set_integration( 'GITHUB' );
		$this->set_trigger_code( 'PULL_REQUEST_MERGED_IN_REPO' );
		$this->set_trigger_meta( 'PULL_REQUEST_MERGED_IN_REPO_META' );
		$this->set_sentence(
			sprintf(
				// translators: %1$s: Repository name
				esc_attr_x( 'A pull request is merged in {{a repository:%1$s}}', 'GitHub', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence( esc_attr_x( 'A pull request is merged in {{a repository}}', 'GitHub', 'uncanny-automator' ) );
		$this->add_action( 'automator_github_webhook_received' );
		$this->set_action_args_count( 2 );
		$this->set_trigger_type( 'anonymous' );
		$this->set_is_login_required( false );
		$this->set_uses_api( true );
	}

	/**
	 * Define trigger options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->helpers->get_webhook_repo_option_config( $this->get_trigger_meta(), 'pull_request' ),
		);
	}

	/**
	 * Validate the trigger.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {
		if ( ! isset( $trigger['meta'][ $this->get_trigger_meta() ], $hook_args[0], $hook_args[1] ) ) {
			return false;
		}

		// Get the repository ID, payload, and event type.
		$repo_id = $trigger['meta'][ $this->get_trigger_meta() ];
		$payload = $hook_args[0];  // GitHub payload
		$event   = $hook_args[1];  // Event type

		// Validate repository and event and action.
		$repo_matches   = $this->webhooks->webhook_matches_repository( $payload, $repo_id );
		$event_matches  = 'pull_request' === $event;
		$action_matches = $this->webhooks->webhook_matches_action( $payload, 'closed' );
		$merged_matches = true === ( $payload['pull_request']['merged'] ?? false );

		return $repo_matches && $event_matches && $action_matches && $merged_matches;
	}

	/**
	 * Define tokens.
	 *
	 * @param array $trigger
	 * @param array $tokens
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		$trigger_tokens = array_merge(
			Github_Tokens::get_trigger_repository_token_definitions(),
			Github_Tokens::get_trigger_pull_request_token_definitions(),
			Github_Tokens::get_trigger_sender_token_definitions()
		);

		return array_merge( $tokens, $trigger_tokens );
	}

	/**
	 * Hydrate tokens.
	 *
	 * @param array $completed_trigger
	 * @param array $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $completed_trigger, $hook_args ) {
		$payload = $hook_args[0];  // GitHub payload

		// Set the webhooks reference for the token helper.
		Github_Tokens::set_webhooks( $this->webhooks );

		return array_merge(
			Github_Tokens::hydrate_repository_tokens( $payload ),
			Github_Tokens::hydrate_pull_request_tokens( $payload ),
			Github_Tokens::hydrate_sender_tokens( $payload )
		);
	}
}
