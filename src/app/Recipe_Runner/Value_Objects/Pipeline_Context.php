<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Value_Objects;

/**
 * Immutable context object for the recipe execution pipeline.
 *
 * Constructed once from the caller's $args and passed through all stages unchanged.
 * Stages read from context but never modify it — all state changes go into Pipeline_Result.
 *
 * @package Uncanny_Automator\App\Recipe_Runner\Value_Objects
 * @since   7.2
 */
final class Pipeline_Context {

	/**
	 * @var string Trigger code (e.g. WP_LOGIN, WC_PURCHPROD).
	 */
	private $trigger_code;

	/**
	 * @var string|null Trigger meta key (e.g. WPPOST, WOOPRODUCT).
	 */
	private $trigger_meta;

	/**
	 * @var int Post ID associated with the trigger event.
	 */
	private $post_id;

	/**
	 * @var int WordPress user ID.
	 */
	private $user_id;

	/**
	 * @var bool Whether the user is signed in.
	 */
	private $is_signed_in;

	/**
	 * @var int|null Specific recipe ID to match (validation in validate_recipe_match).
	 */
	private $matched_recipe_id;

	/**
	 * @var int|null Recipe ID for filtering in get_recipes() (webhook scenarios only).
	 */
	private $webhook_recipe_id;

	/**
	 * @var int|null Specific trigger ID to match.
	 */
	private $matched_trigger_id;

	/**
	 * @var bool Whether to ignore post_id in trigger validation.
	 */
	private $ignore_post_id;

	/**
	 * @var bool Whether this is a webhook-initiated trigger.
	 */
	private $is_webhook;

	/**
	 * @var bool Whether to mark the trigger complete after numtimes check.
	 */
	private $mark_trigger_complete;

	/**
	 * @var array Original raw args for backward compatibility.
	 */
	private $raw_args;

	/**
	 * @param array $args The raw trigger args from maybe_add_trigger_entry().
	 * @param bool  $mark_trigger_complete Whether to auto-complete.
	 */
	public function __construct( array $args, bool $mark_trigger_complete = true ) {

		$this->trigger_code          = isset( $args['code'] ) ? (string) $args['code'] : '';
		$this->trigger_meta          = isset( $args['meta'] ) ? $args['meta'] : null;
		$this->post_id               = isset( $args['post_id'] ) ? (int) $args['post_id'] : 0;
		$this->user_id               = isset( $args['user_id'] ) ? (int) $args['user_id'] : (int) get_current_user_id();
		$this->is_signed_in          = ! empty( $args['is_signed_in'] );
		$this->matched_recipe_id     = isset( $args['recipe_to_match'] ) ? (int) $args['recipe_to_match'] : null;
		$this->matched_trigger_id    = isset( $args['trigger_to_match'] ) ? (int) $args['trigger_to_match'] : null;
		$this->webhook_recipe_id     = isset( $args['webhook_recipe'] ) ? (int) $args['webhook_recipe'] : null;
		$this->ignore_post_id        = ! empty( $args['ignore_post_id'] );
		$this->is_webhook            = ! empty( $args['is_webhook'] );
		$this->mark_trigger_complete = $mark_trigger_complete;

		// Normalized args for backward compatibility with existing code.
		// Must include every key downstream consumers read from $args —
		// especially event_hash (idempotency guard) and webhook keys.
		$this->raw_args = array(
			'code'             => $this->trigger_code,
			'meta'             => $this->trigger_meta,
			'post_id'          => $this->post_id,
			'user_id'          => $this->user_id,
			'recipe_to_match'  => $this->matched_recipe_id,
			'trigger_to_match' => $this->matched_trigger_id,
			'ignore_post_id'   => $this->ignore_post_id,
			'is_signed_in'     => $this->is_signed_in,
			'event_hash'       => $args['event_hash'] ?? '',
			'webhook_recipe'   => $this->webhook_recipe_id,
			'is_webhook'       => $this->is_webhook,
		);
	}

	/**
	 * @return string
	 */
	public function get_trigger_code(): string {
		return $this->trigger_code;
	}

	/**
	 * @return string|null
	 */
	public function get_trigger_meta() {
		return $this->trigger_meta;
	}

	/**
	 * @return int
	 */
	public function get_post_id(): int {
		return $this->post_id;
	}

	/**
	 * @return int
	 */
	public function get_user_id(): int {
		return $this->user_id;
	}

	/**
	 * @return bool
	 */
	public function is_signed_in(): bool {
		return $this->is_signed_in;
	}

	/**
	 * @return int|null
	 */
	public function get_matched_recipe_id() {
		return $this->matched_recipe_id;
	}

	/**
	 * @return int|null
	 */
	public function get_matched_trigger_id() {
		return $this->matched_trigger_id;
	}

	/**
	 * @return bool
	 */
	public function should_ignore_post_id(): bool {
		return $this->ignore_post_id;
	}

	/**
	 * @return int|null
	 */
	public function get_webhook_recipe_id() {
		return $this->webhook_recipe_id;
	}

	/**
	 * @return bool
	 */
	public function is_webhook(): bool {
		return $this->is_webhook;
	}

	/**
	 * @return bool
	 */
	public function should_mark_trigger_complete(): bool {
		return $this->mark_trigger_complete;
	}

	/**
	 * Get the normalized args array for backward compatibility.
	 *
	 * @return array
	 */
	public function get_args(): array {
		return $this->raw_args;
	}
}
