<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Field\Services;

use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\App\Recipe_Builder\Field\Services\Utilities\Field_Validator;
use WP_Error;

/**
 * Single entry point for recipe-item field validation.
 *
 * Composes the pure Field_Registry_Service (definitions) with the
 * Field_Validator engine. `validate_config` is the caller-supplies-config
 * path (create / update / execution); `validate_for_publish` is the
 * fail-closed gate run on every item->live transition; `check_item_save`
 * asks the item itself whether it accepts the values a save would store.
 */
final class Field_Validation_Service {

	/**
	 * Singleton instance.
	 *
	 * @var Field_Validation_Service|null
	 */
	private static ?Field_Validation_Service $instance = null;

	/**
	 * Field definition source (unscoped).
	 *
	 * @var Field_Registry_Service
	 */
	private Field_Registry_Service $registry;

	/**
	 * Validation engine.
	 *
	 * @var Field_Validator
	 */
	private Field_Validator $validator;

	/**
	 * Build the service, defaulting to the production registry and validator.
	 *
	 * @param Field_Registry_Service|null $registry  Definition source (test seam).
	 * @param Field_Validator|null        $validator Validation engine (test seam).
	 */
	public function __construct(
		?Field_Registry_Service $registry = null,
		?Field_Validator $validator = null
	) {
		$this->registry  = $registry ?? Field_Registry_Service::instance();
		$this->validator = $validator ?? new Field_Validator();
	}

	/**
	 * Singleton accessor.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Factory for tests — fresh instance with the supplied (or lazy-default)
	 * dependencies.
	 */
	public static function create_with_dependencies(
		?Field_Registry_Service $registry = null,
		?Field_Validator $validator = null
	): self {
		return new self( $registry, $validator );
	}

	/**
	 * Validate a caller-supplied config against an item's field schema.
	 *
	 * Preserves the `config_validation_failed` (definitions unresolvable) and
	 * `validation_failed` (field failure) error codes that existing consumers
	 * (Action_Executor and its tests) depend on. Behaviour-equivalent to the
	 * legacy Field_Validator::validate path — normalize_field_definitions is a
	 * no-op on the grouped registry shape.
	 *
	 * @param string $code    Item code (action/trigger).
	 * @param array  $config  Field values to validate.
	 * @param string $type    Item type ('action' | 'trigger').
	 * @param bool   $partial When true, skip required-field/empty-config checks.
	 * @return true|WP_Error
	 */
	public function validate_config( string $code, array $config, string $type, bool $partial = false ) {
		try {
			$definitions = $this->registry->get_item_fields_grouped( '', $type, $code );

			if ( is_wp_error( $definitions ) ) {
				return new WP_Error(
					'config_validation_failed',
					sprintf(
						/* translators: %s Error message. */
						esc_html_x( 'Configuration validation failed: %s', 'Field validation service error', 'uncanny-automator' ),
						$definitions->get_error_message()
					)
				);
			}

			return $this->validator->validate_fields( $definitions, $config, $code, $type, $partial );

		} catch ( \Throwable $e ) {
			return new WP_Error(
				'config_validation_failed',
				sprintf(
					/* translators: %s Error message. */
					esc_html_x( 'Configuration validation failed: %s', 'Field validation service error', 'uncanny-automator' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Validate that an item is complete enough to go live.
	 *
	 * `$config` is the item's PERSISTED post meta, which legitimately carries
	 * non-field keys (sentence snapshots, *_readable display values, or meta
	 * injected by other plugins). So the guard validates required-field
	 * completeness ONLY — it does not reject unknown keys or re-validate
	 * formats (those were checked when the values were submitted). Fail-closed:
	 * an unresolvable code (not in the registry) also rejects.
	 *
	 * Translates any failure into the standardized `item_config_invalid` (422)
	 * contract the FE consumes.
	 *
	 * @param string $code   Item code.
	 * @param array  $config The item's persisted field values.
	 * @param string $type   Item type ('action' | 'trigger').
	 * @return true|WP_Error
	 */
	public function validate_for_publish( string $code, array $config, string $type ) {
		try {
			$definitions = $this->registry->get_item_fields_grouped( '', $type, $code );

			if ( is_wp_error( $definitions ) ) {
				return $this->publish_rejection( array( $definitions->get_error_message() ) );
			}

			$result = $this->validator->validate_required_present( $definitions, $config );
		} catch ( \Throwable $e ) {
			return $this->publish_rejection( array( $e->getMessage() ) );
		}

		if ( ! is_wp_error( $result ) ) {
			return true;
		}

		$data = $result->get_error_data();

		return $this->publish_rejection(
			is_array( $data ) && ! empty( $data['validation_errors'] )
				? $data['validation_errors']
				: array( $result->get_error_message() )
		);
	}

	/**
	 * Whether every visible required field in `$definitions` has a value in
	 * `$config` — the publish completeness check for callers that bring their
	 * own definitions (blocks and block modifiers) rather than an item code.
	 *
	 * @since 8.0.0
	 *
	 * @param array $definitions Field definitions (flat or grouped).
	 * @param array $config      Flat `option_code => value` map.
	 * @return true|WP_Error `validation_failed` naming each empty field.
	 */
	public function validate_required_present( array $definitions, array $config ) {
		return $this->validator->validate_required_present( $definitions, $config );
	}

	/**
	 * The item's own refusals of the field values a save would store.
	 *
	 * An item refuses a value its fields accept but it cannot use (a
	 * fraction of a month, say) through the `automator_item_save_refusals`
	 * filter: a listener checks the item type and code, and returns the
	 * refusals it was given plus its own, keyed by option code, each message
	 * the sentence the site owner reads. Required values are the field
	 * checks' job, so a listener never refuses a value that is missing.
	 *
	 * Fail-closed: an answer that is not option code => message refuses the
	 * save, so a check that cannot be read never lets a value through.
	 *
	 * @since 8.0.0
	 *
	 * @param string               $item_type Item type ('action' | 'trigger').
	 * @param string               $item_code Item code.
	 * @param array<string, mixed> $values    The item's field values as the save would store them.
	 * @return true|WP_Error `item_value_refused` carrying each refusal under `validation_errors`, or
	 *                       `item_save_check_invalid` when a listener's answer cannot be read.
	 */
	public function check_item_save( string $item_type, string $item_code, array $values ) {
		$refusals = Dispatcher::filter( 'automator_item_save_refusals', array(), $item_type, $item_code, $values );

		if ( ! $this->is_refusal_map( $refusals ) ) {
			return new WP_Error(
				'item_save_check_invalid',
				esc_html_x( 'This step’s save check gave an answer Automator can’t read, so nothing was saved.', 'Field validation service error', 'uncanny-automator' ),
				array( 'status' => 500 )
			);
		}

		if ( array() === $refusals ) {
			return true;
		}

		return new WP_Error(
			'item_value_refused',
			implode( ' ', $refusals ),
			array(
				'status'            => 400,
				'validation_errors' => $refusals,
			)
		);
	}

	/**
	 * Whether a save check's answer is option code => message, both non-empty strings.
	 *
	 * @param mixed $refusals The filtered answer.
	 * @return bool
	 */
	private function is_refusal_map( $refusals ): bool {
		if ( ! is_array( $refusals ) ) {
			return false;
		}

		foreach ( $refusals as $option_code => $message ) {
			if ( ! is_string( $option_code ) || '' === $option_code || ! is_string( $message ) || '' === $message ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Build the standardized publish-rejection error.
	 *
	 * @param array $validation_errors Per-field failure messages.
	 * @return WP_Error
	 */
	private function publish_rejection( array $validation_errors ): WP_Error {
		return new WP_Error(
			'item_config_invalid',
			esc_html_x( 'This step is missing required configuration and cannot be set live.', 'Field validation service error', 'uncanny-automator' ),
			array(
				'status'            => 422,
				'validation_errors' => $validation_errors,
			)
		);
	}
}
