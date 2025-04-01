<?php

namespace Uncanny_Automator\Services\Recipe\Structure\Triggers\Tokens\Common;

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
	 * Retrieves the trigger's sentence based on its code.
	 *
	 * @param string $trigger_code The trigger code.
	 * @return string The trigger sentence or an empty string if not found.
	 */
	protected function get_trigger_sentence( $trigger_code ) {

		if ( empty( $trigger_code ) ) {
			return '';
		}

		$trigger = Automator()->get_trigger( $trigger_code );

		if ( empty( $trigger ) || ! is_array( $trigger ) ) {
			automator_log( 'Parser: Automator returned an invalid trigger for code "' . $trigger_code . '".' );
			return '';
		}

		$select_option_name = $trigger['select_option_name'] ?? '';
		$sentence           = $trigger['sentence'] ?? '';

		return ! empty( $select_option_name ) ? $select_option_name : $sentence;
	}
}
