<?php

namespace Uncanny_Automator\Services\Recipe\Structure\Triggers\Tokens\Common;

use Uncanny_Automator\Recipe\Trigger_Late_Resolver;

/**
 * Class Parser
 *
 * This parser is specifically designed for managing common trigger tokens.
 * It handles trigger context and retrieves specific values based on a unique
 * identifier, such as trigger ID, completion date, or title.
 *
 * @package Uncanny_Automator\Services\Recipe\Structure\Triggers\Tokens\Common
 */
class Parser {

	/**
	 * Context data for the trigger.
	 *
	 * @var array
	 */
	protected $context = array();

	/**
	 * The ID of the trigger being parsed.
	 *
	 * @var int
	 */
	protected $trigger_id;

	/**
	 * Unique identifier for the token being parsed.
	 *
	 * @var string
	 */
	protected $unique_identifier;

	/**
	 * Constructor.
	 *
	 * Initializes the parser with the trigger ID and unique identifier.
	 *
	 * @param int    $trigger_id        The ID of the trigger.
	 * @param string $unique_identifier The unique identifier for the token.
	 */
	public function __construct( $trigger_id, $unique_identifier ) {

		if ( ! is_int( $trigger_id ) || empty( $unique_identifier ) ) {
			throw new \InvalidArgumentException( 'Invalid trigger ID or unique identifier provided.' );
		}

		$this->trigger_id        = $trigger_id;
		$this->unique_identifier = (string) $unique_identifier;
	}

	/**
	 * Sets the context for the trigger.
	 *
	 * @param array $args Contextual data for the trigger.
	 * @return void
	 */
	public function set_trigger_context( array $args ) {
		if ( empty( $args ) || ! is_array( $args ) ) {
			throw new \InvalidArgumentException( 'Context must be a non-empty array.' );
		}

		$this->context = $args;
	}

	/**
	 * Parses the context and retrieves the value based on the unique identifier.
	 *
	 * Uses a method map to determine the appropriate processing logic for
	 * the unique identifier.
	 *
	 * @return mixed The parsed value based on the unique identifier.
	 */
	public function parse() {

		if ( empty( $this->unique_identifier ) ) {
			automator_log( 'Parser: unique_identifier is empty.' );
			return '';
		}

		$method_map = array(
			'ID'              => function () {
				return isset( $this->context['trigger_id'] ) ? $this->context['trigger_id'] : 0;
			},
			'COMPLETION_DATE' => function () {
				return $this->get_trigger_completion_date(
					isset( $this->context['trigger_log_id'] ) ? $this->context['trigger_log_id'] : ''
				);
			},
			'TITLE'           => function () {
				return $this->get_trigger_sentence(
					isset( $this->context['code'] ) ? $this->context['code'] : ''
				);
			},
		);

		if ( isset( $method_map[ $this->unique_identifier ] ) ) {
			return call_user_func( $method_map[ $this->unique_identifier ] );
		}

		automator_log( 'Parser: Unsupported unique_identifier "' . $this->unique_identifier . '".' );
		return '';
	}

	/**
	 * Retrieves the trigger's completion date based on the log ID.
	 *
	 * @param string $trigger_log_id The log ID of the trigger.
	 * @return string The completion date or an empty string if not found.
	 */
	protected function get_trigger_completion_date( $trigger_log_id ) {

		if ( empty( $trigger_log_id ) ) {
			return '';
		}

		$date_time = Automator()->db->trigger->find_column_value_by_id( 'date_time', $trigger_log_id );
		if ( empty( $date_time ) ) {
			return '';
		}

		return date_i18n(
			get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
			strtotime( $date_time )
		);
	}

	/**
	 * Retrieves the trigger's readable sentence.
	 *
	 * Resolution order (each fallback covers a distinct failure mode):
	 *
	 *   1. Per-recipe `sentence_human_readable` post meta on the trigger
	 *      post. Set by the recipe builder at save time with placeholders
	 *      already resolved to the user's actual choice (e.g. "Any subsite"
	 *      for the `-1` sentinel, or the selected entity's title). The
	 *      registry's static `sentence` carries unresolved
	 *      `{{Label:OPTION_CODE}}` placeholders that the outer action-side
	 *      parser treats as unmatched tokens and strips entirely, leaving
	 *      bare prose (e.g. " is deactivated"). The per-recipe meta avoids
	 *      that round-trip.
	 *
	 *   2. Registry — eager-registered triggers (admin / editor contexts via
	 *      `should_load_all()`) carry `select_option_name` and `sentence`
	 *      pre-baked at boot.
	 *
	 *   3. Lazy resolver — frontend / cron requests register stub-only
	 *      entries via `Trigger_Metadata_Loader`. The stub has no sentence
	 *      because sentences are i18n strings set inside `setup_trigger()`,
	 *      which requires the trigger to be constructed. `Trigger_Late_Resolver`
	 *      memoises that construction per-code per-request so the cost is
	 *      paid once and shared with the queue's validation_function and
	 *      the token-parse filter proxy.
	 *
	 * @param string $trigger_code The trigger code.
	 * @return string The trigger sentence or an empty string if not found.
	 */
	protected function get_trigger_sentence( $trigger_code ) {

		// Preferred path: per-recipe rendered sentence on the trigger post.
		if ( $this->trigger_id > 0 ) {
			$rendered = (string) get_post_meta( $this->trigger_id, 'sentence_human_readable', true );
			if ( '' !== $rendered ) {
				return $rendered;
			}
		}

		// Fallback: static template from the trigger registry. Only reached
		// when the trigger post is missing the rendered meta (older recipes
		// saved before the human-readable column existed).
		if ( empty( $trigger_code ) ) {
			return '';
		}

		$trigger = Automator()->get_trigger( $trigger_code );

		if ( is_array( $trigger ) ) {
			$eager = (string) ( $trigger['select_option_name'] ?? $trigger['sentence'] ?? '' );
			if ( '' !== $eager ) {
				return $eager;
			}
		}

		$instance = Trigger_Late_Resolver::get( $trigger_code );

		if ( null === $instance ) {
			automator_log( 'Parser: Automator returned an invalid trigger for code "' . $trigger_code . '".' );
			return '';
		}

		$readable = method_exists( $instance, 'get_readable_sentence' ) ? (string) $instance->get_readable_sentence() : '';
		if ( '' !== $readable ) {
			return $readable;
		}

		return method_exists( $instance, 'get_sentence' ) ? (string) $instance->get_sentence() : '';
	}
}
