<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\User_Selector;

/**
 * User Selector Configuration - Dumb Data Container.
 *
 * Pure data transfer object that shuttles raw configuration data to the User_Selector aggregate.
 * This is intentionally DUMB - no validation, no business logic, no intelligence.
 *
 * Purpose:
 * - Collect raw user selector data from various sources (UI forms, API calls, database)
 * - Provide fluent builder interface for constructing user selector parameters
 * - Pass validated data to User_Selector aggregate constructor for domain validation
 *
 * Anti-patterns avoided:
 * - No validation logic (User_Selector aggregate handles this)
 * - No business rules (User_Selector aggregate enforces these)
 * - No formatting/transformation (value objects handle this)
 *
 * The User_Selector aggregate is the smart one. This is just a messenger.
 *
 * @since 7.0.0
 */
class User_Selector_Config {

	/**
	 * Raw user selector ID - no validation.
	 *
	 * @var mixed
	 */
	private $id;

	/**
	 * Raw recipe ID this selector belongs to - no validation.
	 *
	 * @var mixed
	 */
	private $recipe_id;

	/**
	 * Raw source type - no validation.
	 *
	 * Expected: 'existingUser' or 'newUser'.
	 *
	 * @var mixed
	 */
	private $source;

	/**
	 * Raw unique field type - no validation.
	 *
	 * Expected: 'email', 'id', or 'username'.
	 *
	 * @var mixed
	 */
	private $unique_field;

	/**
	 * Raw unique field value - no validation.
	 *
	 * Can contain tokens like {{trigger_id:USER_EMAIL}}.
	 *
	 * @var mixed
	 */
	private $unique_field_value;

	/**
	 * Raw fallback behavior - no validation.
	 *
	 * Expected: 'create-new-user', 'select-existing-user', or 'do-nothing'.
	 *
	 * @var mixed
	 */
	private $fallback;

	/**
	 * Raw prioritized field - no validation.
	 *
	 * Expected: 'email' or 'username'.
	 *
	 * @var mixed
	 */
	private $prioritized_field;

	/**
	 * Raw user data fields - no validation.
	 *
	 * @var array
	 */
	private $user_data = array();

	/**
	 * Set user selector ID - fluent interface.
	 *
	 * @param mixed $id Raw ID data.
	 * @return self For method chaining.
	 */
	public function id( $id ): self {
		$this->id = $id;
		return $this;
	}

	/**
	 * Set recipe ID - fluent interface.
	 *
	 * @param mixed $recipe_id Raw recipe ID.
	 * @return self For method chaining.
	 */
	public function recipe_id( $recipe_id ): self {
		$this->recipe_id = $recipe_id;
		return $this;
	}

	/**
	 * Set source type - fluent interface.
	 *
	 * @param mixed $source Raw source type.
	 * @return self For method chaining.
	 */
	public function source( $source ): self {
		$this->source = $source;
		return $this;
	}

	/**
	 * Set unique field type - fluent interface.
	 *
	 * @param mixed $unique_field Raw unique field type.
	 * @return self For method chaining.
	 */
	public function unique_field( $unique_field ): self {
		$this->unique_field = $unique_field;
		return $this;
	}

	/**
	 * Set unique field value - fluent interface.
	 *
	 * @param mixed $unique_field_value Raw unique field value.
	 * @return self For method chaining.
	 */
	public function unique_field_value( $unique_field_value ): self {
		$this->unique_field_value = $unique_field_value;
		return $this;
	}

	/**
	 * Set fallback behavior - fluent interface.
	 *
	 * @param mixed $fallback Raw fallback behavior.
	 * @return self For method chaining.
	 */
	public function fallback( $fallback ): self {
		$this->fallback = $fallback;
		return $this;
	}

	/**
	 * Set prioritized field - fluent interface.
	 *
	 * @param mixed $prioritized_field Raw prioritized field.
	 * @return self For method chaining.
	 */
	public function prioritized_field( $prioritized_field ): self {
		$this->prioritized_field = $prioritized_field;
		return $this;
	}

	/**
	 * Set user data fields - fluent interface.
	 *
	 * @param array $user_data Raw user data array.
	 * @return self For method chaining.
	 */
	public function user_data( array $user_data ): self {
		$this->user_data = $user_data;
		return $this;
	}

	/**
	 * Get raw ID.
	 *
	 * @return mixed
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Get raw recipe ID.
	 *
	 * @return mixed
	 */
	public function get_recipe_id() {
		return $this->recipe_id;
	}

	/**
	 * Get raw source.
	 *
	 * @return mixed
	 */
	public function get_source() {
		return $this->source;
	}

	/**
	 * Get raw unique field.
	 *
	 * @return mixed
	 */
	public function get_unique_field() {
		return $this->unique_field;
	}

	/**
	 * Get raw unique field value.
	 *
	 * @return mixed
	 */
	public function get_unique_field_value() {
		return $this->unique_field_value;
	}

	/**
	 * Get raw fallback.
	 *
	 * @return mixed
	 */
	public function get_fallback() {
		return $this->fallback;
	}

	/**
	 * Get raw prioritized field.
	 *
	 * @return mixed
	 */
	public function get_prioritized_field() {
		return $this->prioritized_field;
	}

	/**
	 * Get raw user data.
	 *
	 * @return array
	 */
	public function get_user_data(): array {
		return $this->user_data;
	}

	/**
	 * Convert to array representation.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return array(
			'id'                 => $this->id,
			'recipe_id'          => $this->recipe_id,
			'source'             => $this->source,
			'unique_field'       => $this->unique_field,
			'unique_field_value' => $this->unique_field_value,
			'fallback'           => $this->fallback,
			'prioritized_field'  => $this->prioritized_field,
			'user_data'          => $this->user_data,
		);
	}

	/**
	 * Create config from array.
	 *
	 * @param array $data Raw data array.
	 * @return self
	 */
	public static function from_array( array $data ): self {
		$config = new self();

		if ( isset( $data['id'] ) ) {
			$config->id( $data['id'] );
		}

		if ( isset( $data['recipe_id'] ) ) {
			$config->recipe_id( $data['recipe_id'] );
		}

		if ( isset( $data['source'] ) ) {
			$config->source( $data['source'] );
		}

		if ( isset( $data['unique_field'] ) ) {
			$config->unique_field( $data['unique_field'] );
		}

		if ( isset( $data['unique_field_value'] ) ) {
			$config->unique_field_value( $data['unique_field_value'] );
		}

		if ( isset( $data['fallback'] ) ) {
			$config->fallback( $data['fallback'] );
		}

		if ( isset( $data['prioritized_field'] ) ) {
			$config->prioritized_field( $data['prioritized_field'] );
		}

		if ( isset( $data['user_data'] ) && is_array( $data['user_data'] ) ) {
			$config->user_data( $data['user_data'] );
		}

		return $config;
	}
}
