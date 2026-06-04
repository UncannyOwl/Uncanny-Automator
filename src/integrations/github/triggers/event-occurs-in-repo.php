<?php

namespace Uncanny_Automator\Integrations\Github;

/**
 * Trigger: {An event} occurs in {a repository}
 *
 * @package Uncanny_Automator\Integrations\Github
 *
 * @property Github_App_Helpers $helpers
 * @property Github_Api_Caller $api
 * @property Github_Webhooks $webhooks
 */
class EVENT_OCCURS_IN_REPO extends \Uncanny_Automator\Recipe\App_Trigger {

	/**
	 * Event type meta key.
	 *
	 * @var string
	 */
	const EVENT_TYPE_META = 'EVENT_TYPE';

	/**
	 * Event action meta key.
	 *
	 * @var string
	 */
	const EVENT_TYPE_ACTION_META = 'EVENT_TYPE_ACTION';

	/**
	 * Setup the trigger.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		$this->set_integration( 'GITHUB' );
		$this->set_trigger_code( 'EVENT_OCCURS_IN_REPO' );
		$this->set_trigger_meta( 'EVENT_OCCURS_IN_REPO_META' );
		$this->set_sentence(
			sprintf(
				// translators: %1$s: Event type. %2$s: Repository name.
				esc_attr_x( '{{An event:%1$s}} occurs in {{a repository:%2$s}}', 'GitHub', 'uncanny-automator' ),
				self::EVENT_TYPE_META . ':' . $this->get_trigger_meta(),
				$this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence( esc_attr_x( '{{An event}} occurs in {{a repository}}', 'GitHub', 'uncanny-automator' ) );
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

		$events = $this->helpers->get_event_options();

		return array(
			array(
				'option_code'     => self::EVENT_TYPE_META,
				'label'           => esc_html_x( 'Event type', 'GitHub', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => $events,
				'options_show_id' => false,
				'relevant_tokens' => array(),
			),
			array(
				'option_code'        => self::EVENT_TYPE_ACTION_META,
				'label'              => esc_html_x( 'Event type action', 'GitHub', 'uncanny-automator' ),
				'input_type'         => 'select',
				'default_value'      => '-1',
				'required'           => false,
				'options'            => array(),
				'options_show_id'    => false,
				'relevant_tokens'    => array(),
				'remote_data'        => $this->helpers->remote_data_parent_config(
					'event_actions',
					array( self::EVENT_TYPE_META )
				),
				'dynamic_visibility' => $this->event_type_action_dynamic_visibility_config( $events ),
			),
			$this->helpers->get_webhook_repo_option_config( $this->get_trigger_meta(), 'all' ),
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

		// Get the repository ID, event type, payload, and event type.
		$repo_id    = $trigger['meta'][ $this->get_trigger_meta() ];
		$event_type = $trigger['meta'][ self::EVENT_TYPE_META ];
		$payload    = $hook_args[0];  // GitHub payload
		$event      = $hook_args[1];  // Event type

		// Validate repository and event.
		$repo_matches  = $this->webhooks->webhook_matches_repository( $payload, $repo_id );
		$event_matches = $event_type === $event;
		$valid         = $repo_matches && $event_matches;

		// If we're not valid bail at this point.
		if ( ! $valid ) {
			return false;
		}

		// Check if our Event has an action to validate.
		$event_action = $trigger['meta'][ self::EVENT_TYPE_ACTION_META ];

		// Allow empty or any action selection to run ( should always be -1 as it's set to default ).
		if ( empty( $event_action ) || '-1' === (string) $event_action ) {
			return true;
		}

		// Validate the event action from payload.
		return $this->webhooks->webhook_matches_action( $payload, $event_action );
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
					'tokenId'   => 'GITHUB_EVENT_TYPE',
					'tokenName' => esc_html_x( 'Event type', 'GitHub', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
				array(
					'tokenId'   => 'GITHUB_ACTION',
					'tokenName' => esc_html_x( 'Action', 'GitHub', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
				array(
					'tokenId'   => 'GITHUB_PAYLOAD',
					'tokenName' => esc_html_x( 'Full payload (JSON)', 'GitHub', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
			),
			Github_Tokens::get_trigger_sender_token_definitions()
		);

		// Conditional tokens based on the saved event-type selection.
		$event = $trigger['meta'][ self::EVENT_TYPE_META ] ?? '';
		if ( ! empty( $event ) && $this->is_pull_request_event( $event ) ) {
			$pr_tokens = array_map(
				function ( $token ) {
					$token['tokenIdentifier'] = 'EVENT_OCCURS_IN_REPO';
					return $token;
				},
				Github_Tokens::get_trigger_pull_request_token_definitions()
			);
			$trigger_tokens = array_merge( $trigger_tokens, $pr_tokens );
		}

		/**
		 * Filter GitHub conditional tokens based on the event type selection.
		 *
		 * @param array  $trigger_tokens The trigger token definitions added by this trigger.
		 * @param string $event          The selected event type (`''` when unset).
		 *
		 * @return array
		 *
		 * @example:
		 * add_filter( 'automator_github_event_repo_conditional_tokens', function( $trigger_tokens, $event ) {
		 *     if ( 'pull_request' === $event ) {
		 *         $trigger_tokens[] = array(
		 *             'tokenId'   => 'GITHUB_PULL_REQUEST_ID',
		 *             'tokenName' => esc_html_x( 'Pull request ID', 'GitHub', 'uncanny-automator' ),
		 *             'tokenType' => 'text',
		 *         );
		 *     }
		 *     return $trigger_tokens;
		 * }, 10, 2 );
		 */
		$trigger_tokens = apply_filters( 'automator_github_event_repo_conditional_tokens', $trigger_tokens, $event );

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
		$event   = $hook_args[1];  // Event type

		// Set the webhooks reference for the token helper.
		Github_Tokens::set_webhooks( $this->webhooks );

		// Get action value - for push events, use the event type as action.
		$action = $payload['action'] ?? $event;

		// Hydrate tokens default values.
		$tokens = array_merge(
			Github_Tokens::hydrate_repository_tokens( $payload ),
			Github_Tokens::hydrate_sender_tokens( $payload ),
			array(
				'GITHUB_EVENT_TYPE' => $event, // review - get readable name of event type.
				'GITHUB_ACTION'     => $action,
				'GITHUB_PAYLOAD'    => wp_json_encode( $payload ),
			),
		);

		// Add conditional tokens.
		if ( $this->is_pull_request_event( $event ) ) {
			$tokens = array_merge( $tokens, Github_Tokens::hydrate_pull_request_tokens( $payload ) );
		}

		/**
		 * Filter GitHub conditional token hydration based on the event type selection.
		 *
		 * @param array $tokens The currently hydrated tokens
		 * @param array $payload The GitHub webhook payload
		 * @param string $event The event type
		 *
		 * @return array The enhanced hydrated tokens array
		 *
		 * @example:
		 * add_filter( 'automator_github_event_repo_conditional_tokens_hydrate', function( $tokens, $payload, $event ) {
		 *     if ( 'pull_request' === $event ) {
		 *         $tokens['GITHUB_PULL_REQUEST_ID'] = $payload['pull_request']['id'] ?? '';
		 *     }
		 *     return $tokens;
		 * }, 10, 3 );
		 */
		$tokens = apply_filters( 'automator_github_event_repo_conditional_tokens_hydrate', $tokens, $payload, $event );

		return $tokens;
	}

	/**
	 * Get event type action dynamic visibility config.
	 *
	 * @param array $events
	 *
	 * @return array
	 */
	private function event_type_action_dynamic_visibility_config( $events ) {
		$conditions = array();

		// Loop through all events to capture any that may have been added via filter.
		foreach ( $events as $event ) {
			$event_actions = $this->helpers->get_event_actions_options( $event['value'] );
			// Add condition to show action filters for the event.
			if ( ! empty( $event_actions ) ) {
				$conditions[] = array(
					'option_code' => self::EVENT_TYPE_META,
					'compare'     => '==',
					'value'       => $event['value'],
				);
			}
		}

		return array(
			'default_state'    => 'hidden',
			'visibility_rules' => array(
				array(
					'operator'             => 'OR',
					'rule_conditions'      => $conditions,
					'resulting_visibility' => 'show',
				),
			),
		);
	}

	/**
	 * Check if the event is a pull request event.
	 *
	 * @param string $event The event type.
	 *
	 * @return bool
	 */
	private function is_pull_request_event( $event ) {

		$pr_events = array( 'pull_request', 'pull_request_review' );

		/**
		 * Filter GitHub identify pull request events.
		 *
		 * @link https://docs.github.com/en/developers/webhooks-and-events/webhooks/webhook-events-and-payloads
		 *
		 * @param array $pr_events
		 *
		 * @return array
		 *
		 * @example:
		 * add_filter( 'automator_github_pull_request_events', function( $pr_events ) {
		 *     if ( 'workflow_run' === $event ) {
		 *         $pr_events[] = 'pull_request_review';
		 *         );
		 *     }
		 *     return $pr_events;
		 * }, 10, 1 );
		 * } );
		 */
		$pr_events = apply_filters( 'automator_github_pull_request_events', $pr_events );

		return in_array( $event, $pr_events, true );
	}
}
