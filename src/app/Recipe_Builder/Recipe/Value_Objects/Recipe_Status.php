<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Value_Objects;

/**
 * Recipe Status Value Object.
 *
 * Immutable value object that validates and encapsulates recipe status data.
 * Enforces valid status enumeration and prevents invalid state transitions.
 *
 * @since 7.0.0
 */
class Recipe_Status {

	const DRAFT   = 'draft';
	const PUBLISH = 'publish';
	const TRASH   = 'trash';

	private static $allowed_values = array(
		self::DRAFT,
		self::PUBLISH,
		self::TRASH,
	);

	private $value;

	/**
	 * Constructor.
	 *
	 * @param string $value Recipe status (draft or publish).
	 * @throws \InvalidArgumentException If status is invalid.
	 */
	public function __construct( $value ) {
		if ( ! in_array( $value, self::$allowed_values, true ) ) {
			throw new \InvalidArgumentException(
				sprintf(
					'Recipe status must be one of: %s. Given: %s',
					esc_html( implode( ', ', self::$allowed_values ) ),
					esc_html( $value )
				)
			);
		}
		$this->value = $value;
	}

	/**
	 * Whether a value is one of the recipe statuses: draft, publish or trash.
	 *
	 * @since 8.0.0
	 *
	 * @param string $value Status value to validate.
	 *
	 * @return bool
	 */
	public static function is_valid( string $value ): bool {
		return in_array( $value, self::$allowed_values, true );
	}

	/**
	 * Get value.
	 *
	 * @return mixed
	 */
	public function get_value() {
		return $this->value;
	}

	/**
	 * Whether the recipe is in the trash.
	 *
	 * @return bool
	 */
	public function is_trashed(): bool {
		return self::TRASH === $this->value;
	}

	/**
	 * Whether the recipe may still be edited.
	 *
	 * A trashed recipe is read-only until restored.
	 *
	 * @return bool
	 */
	public function is_writable(): bool {
		return ! $this->is_trashed();
	}

	/**
	 * Whether the given status means the recipe is "on" — i.e. published and
	 * live. Single semantic home for the `'publish' === $status` check so
	 * consumers (wire serializer, status dispatch) don't hard-code it.
	 * Mirrors Action_Status::is_item_on for the recipe-level toggle.
	 */
	public static function is_recipe_on( string $status ): bool {
		return self::PUBLISH === $status;
	}

	/**
	 * Map a WordPress post status to the FE wire's settable publish state.
	 * The wire enum has only `publish` / `draft`; any non-published status
	 * (draft, trash, auto-draft, pending) reports `draft`. `running` is never
	 * a member — running is a runtime fact carried by `can_edit`.
	 */
	public static function to_wire_status( string $status ): string {
		return self::PUBLISH === $status ? self::PUBLISH : self::DRAFT;
	}
}
