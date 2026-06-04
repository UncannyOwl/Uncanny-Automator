<?php

namespace Uncanny_Automator\Integrations\Github;

/**
 * Trigger 1: A release is created in {a repository}
 *
 * This trigger is fired when a release is created in a repository.
 *
 * @package Uncanny_Automator\Integrations\Github
 *
 * @property Github_App_Helpers $helpers
 * @property Github_Api_Caller $api
 * @property Github_Webhooks $webhooks
 */
class RELEASE_CREATED_IN_REPO extends \Uncanny_Automator\Recipe\App_Trigger {

	/**
	 * Setup the trigger.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		$this->set_integration( 'GITHUB' );
		$this->set_trigger_code( 'RELEASE_CREATED_IN_REPO' );
		$this->set_trigger_meta( 'RELEASE_CREATED_IN_REPO_META' );
		$this->set_sentence(
			sprintf(
				// translators: %1$s: Repository name.
				esc_attr_x( 'A release is published in {{a repository:%1$s}}', 'GitHub', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence( esc_attr_x( 'A release is published in {{a repository}}', 'GitHub', 'uncanny-automator' ) );
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
			$this->helpers->get_webhook_repo_option_config( $this->get_trigger_meta(), 'release' ),
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
		$event_matches  = 'release' === $event;
		$action_matches = $this->webhooks->webhook_matches_action( $payload, 'published' );

		// Return true if the repository, event, and action match.
		return $repo_matches && $event_matches && $action_matches;
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
			Github_Tokens::get_trigger_release_token_definitions(),
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
			Github_Tokens::hydrate_release_tokens( $payload ),
			Github_Tokens::hydrate_sender_tokens( $payload ),
		);
	}
}
