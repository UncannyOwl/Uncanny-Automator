<?php

namespace Uncanny_Automator\Integrations\Github;

/**
 * Trigger: A push is made to {a repository}
 *
 * @package Uncanny_Automator\Integrations\Github
 *
 * @property Github_App_Helpers $helpers
 * @property Github_Api_Caller $api
 * @property Github_Webhooks $webhooks
 */
class PUSH_TO_REPO extends \Uncanny_Automator\Recipe\App_Trigger {

	/**
	 * Setup the trigger.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		$this->set_integration( 'GITHUB' );
		$this->set_trigger_code( 'GITHUB_PUSH_TO_REPO' );
		$this->set_trigger_meta( 'GITHUB_PUSH_TO_REPO_META' );
		$this->set_sentence(
			sprintf(
				// translators: %1$s: Repository name.
				esc_attr_x( 'A push is made to {{a repository:%1$s}}', 'GitHub', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence( esc_attr_x( 'A push is made to {{a repository}}', 'GitHub', 'uncanny-automator' ) );
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
			$this->helpers->get_webhook_repo_option_config( $this->get_trigger_meta(), 'push' ),
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

		// Validate repository and event.
		$repo_matches  = $this->webhooks->webhook_matches_repository( $payload, $repo_id );
		$event_matches = 'push' === $event;

		// Return true if the repository and event match.
		return $repo_matches && $event_matches;
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
			array(
				array(
					'tokenId'   => 'GITHUB_REF',
					'tokenName' => esc_html_x( 'Reference', 'GitHub', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
				array(
					'tokenId'   => 'GITHUB_COMMIT_MESSAGE',
					'tokenName' => esc_html_x( 'Commit message', 'GitHub', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
				array(
					'tokenId'   => 'GITHUB_PUSHER_LOGIN',
					'tokenName' => esc_html_x( 'Pusher login', 'GitHub', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
				array(
					'tokenId'   => 'GITHUB_PUSHER_AVATAR_URL',
					'tokenName' => esc_html_x( 'Pusher avatar URL', 'GitHub', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
				array(
					'tokenId'   => 'GITHUB_PUSHER_EMAIL',
					'tokenName' => esc_html_x( 'Pusher email', 'GitHub', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
				array(
					'tokenId'   => 'GITHUB_COMMIT_URL',
					'tokenName' => esc_html_x( 'Commit URL', 'GitHub', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
				array(
					'tokenId'   => 'GITHUB_COMMIT_TIMESTAMP',
					'tokenName' => esc_html_x( 'Commit timestamp', 'GitHub', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
			),
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
			array(
				'GITHUB_REF'               => $this->webhooks->get_payload_value( $payload, 'ref' ),
				'GITHUB_COMMIT_MESSAGE'    => $this->webhooks->get_payload_value( $payload, 'head_commit.message' ),
				'GITHUB_PUSHER_LOGIN'      => $this->webhooks->get_payload_value( $payload, 'pusher.name' ),
				'GITHUB_PUSHER_EMAIL'      => $this->webhooks->get_payload_value( $payload, 'pusher.email' ),
				'GITHUB_PUSHER_AVATAR_URL' => $this->webhooks->get_payload_value( $payload, 'pusher.avatar_url' ),
				'GITHUB_COMMIT_URL'        => $this->webhooks->get_payload_value( $payload, 'head_commit.url' ),
				'GITHUB_COMMIT_TIMESTAMP'  => $this->webhooks->get_payload_value( $payload, 'head_commit.timestamp' ),
			),
			Github_Tokens::hydrate_sender_tokens( $payload )
		);
	}
}
