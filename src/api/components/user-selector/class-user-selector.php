<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\User_Selector;

use Uncanny_Automator\Api\Components\User_Selector\Value_Objects\User_Selector_Id;
use Uncanny_Automator\Api\Components\User_Selector\Value_Objects\User_Selector_Source;
use Uncanny_Automator\Api\Components\User_Selector\Value_Objects\User_Selector_Unique_Field;
use Uncanny_Automator\Api\Components\User_Selector\Value_Objects\User_Selector_Fallback;
use Uncanny_Automator\Api\Components\User_Selector\Value_Objects\User_Selector_Prioritized_Field;
use Uncanny_Automator\Api\Components\User_Selector\Value_Objects\User_Selector_User_Data;

/**
 * Root Aggregate: User_Selector
 *
 * This class models the User Selector as a domain aggregate.
 *
 * Responsibilities:
 * - Enforces cross-object business invariants at construction time.
 * - Delegates primitive validation and normalization to Value Objects.
 * - Guarantees that no User_Selector instance can exist in memory with broken
 *   domain rules about source type, fallback behavior, and user data requirements.
 *
 * Domain rules:
 * - Source must be 'existingUser' or 'newUser'.
 * - Existing user source:
 *   • Requires unique_field and unique_field_value.
 *   • Fallback must be 'create-new-user' or 'do-nothing'.
 *   • If fallback is 'create-new-user', user_data should contain creation fields.
 * - New user source:
 *   • Requires user_data with email and username.
 *   • Fallback must be 'select-existing-user' or 'do-nothing'.
 *   • If fallback is 'select-existing-user', prioritized_field is required.
 *
 * Enforcement split:
 * - Aggregate root: guarantees source-specific invariants and relationships
 *   between value objects (e.g., source vs. fallback, source vs. required fields).
 * - Value Objects: guarantee validity of individual values
 *   (source enum, fallback enum, unique field enum, etc.).
 *
 * @since 7.0.0
 */
class User_Selector {

	/**
	 * User selector unique identifier - nullable for new instances.
	 *
	 * @var User_Selector_Id|null
	 */
	private ?User_Selector_Id $id = null;

	/**
	 * Recipe ID this user selector belongs to.
	 *
	 * @var int|null
	 */
	private ?int $recipe_id = null;

	/**
	 * User selector source type - required domain value.
	 *
	 * Enforces valid sources: 'existingUser' or 'newUser'.
	 * This drives validation of required fields and fallback options.
	 *
	 * @var User_Selector_Source
	 */
	private User_Selector_Source $source;

	/**
	 * Unique field type for existing user lookup - only for 'existingUser' source.
	 *
	 * @var User_Selector_Unique_Field|null
	 */
	private ?User_Selector_Unique_Field $unique_field = null;

	/**
	 * Unique field value for existing user lookup - only for 'existingUser' source.
	 *
	 * Can contain tokens like {{trigger_id:USER_EMAIL}}.
	 *
	 * @var string|null
	 */
	private ?string $unique_field_value = null;

	/**
	 * Fallback behavior when user matching fails/succeeds unexpectedly.
	 *
	 * @var User_Selector_Fallback|null
	 */
	private ?User_Selector_Fallback $fallback = null;

	/**
	 * Prioritized field for duplicate detection - only for 'newUser' source with
	 * 'select-existing-user' fallback.
	 *
	 * @var User_Selector_Prioritized_Field|null
	 */
	private ?User_Selector_Prioritized_Field $prioritized_field = null;

	/**
	 * User data for creation - used for 'newUser' source or 'create-new-user' fallback.
	 *
	 * @var User_Selector_User_Data|null
	 */
	private ?User_Selector_User_Data $user_data = null;

	/**
	 * Original configuration.
	 *
	 * @var User_Selector_Config|null
	 */
	private ?User_Selector_Config $config = null;

	/**
	 * Construct a validated User_Selector domain aggregate.
	 *
	 * Enforces cross-object business invariants at construction time while
	 * delegating primitive validation to Value Objects. Guarantees no User_Selector
	 * instance can exist in memory with broken domain rules.
	 *
	 * @since 7.0.0
	 *
	 * @param User_Selector_Config $config Raw configuration data container.
	 * @throws \InvalidArgumentException If cross-object domain rules are violated.
	 */
	public function __construct( User_Selector_Config $config ) {
		// Initialize ID if provided.
		if ( null !== $config->get_id() ) {
			$this->id = new User_Selector_Id( $config->get_id() );
		}

		// Store recipe ID.
		$this->recipe_id = $config->get_recipe_id() ? (int) $config->get_recipe_id() : null;

		// Initialize and validate source (fail-fast).
		$this->source = new User_Selector_Source( $config->get_source() );

		// Initialize source-specific properties.
		if ( $this->source->is_existing_user() ) {
			$this->initialize_existing_user_properties( $config );
		} else {
			$this->initialize_new_user_properties( $config );
		}

		// Validate cross-object invariants.
		$this->validate_invariants();

		$this->config = $config;
	}

	/**
	 * Initialize properties for existing user source.
	 *
	 * @param User_Selector_Config $config Configuration.
	 */
	private function initialize_existing_user_properties( User_Selector_Config $config ): void {
		// Unique field is required for existing user lookup.
		$this->unique_field       = new User_Selector_Unique_Field( $config->get_unique_field() );
		$this->unique_field_value = $config->get_unique_field_value();

		// Fallback behavior.
		if ( null !== $config->get_fallback() ) {
			$this->fallback = new User_Selector_Fallback( $config->get_fallback() );
		}

		// User data for fallback creation.
		if ( ! empty( $config->get_user_data() ) ) {
			$this->user_data = User_Selector_User_Data::from_array( $config->get_user_data() );
		}
	}

	/**
	 * Initialize properties for new user source.
	 *
	 * @param User_Selector_Config $config Configuration.
	 */
	private function initialize_new_user_properties( User_Selector_Config $config ): void {
		// User data is required for new user creation.
		$this->user_data = User_Selector_User_Data::from_array( $config->get_user_data() );

		// Fallback behavior.
		if ( null !== $config->get_fallback() ) {
			$this->fallback = new User_Selector_Fallback( $config->get_fallback() );
		}

		// Prioritized field for duplicate detection.
		if ( null !== $config->get_prioritized_field() ) {
			$this->prioritized_field = new User_Selector_Prioritized_Field( $config->get_prioritized_field() );
		}
	}

	/**
	 * Validate cross-object business invariants.
	 *
	 * @throws \InvalidArgumentException If invariants are violated.
	 */
	private function validate_invariants(): void {
		if ( $this->source->is_existing_user() ) {
			$this->validate_existing_user_invariants();
		} else {
			$this->validate_new_user_invariants();
		}
	}

	/**
	 * Validate invariants for existing user source.
	 *
	 * @throws \InvalidArgumentException If invariants are violated.
	 */
	private function validate_existing_user_invariants(): void {
		// Unique field is required.
		if ( null === $this->unique_field || null === $this->unique_field->get_value() ) {
			throw new \InvalidArgumentException(
				'Existing user source requires a unique field (email, id, or username)'
			);
		}

		// Unique field value is required.
		if ( empty( $this->unique_field_value ) ) {
			throw new \InvalidArgumentException(
				'Existing user source requires a unique field value'
			);
		}

		// Validate fallback is appropriate for existing user.
		if ( null !== $this->fallback ) {
			$valid_fallbacks = User_Selector_Fallback::get_existing_user_fallbacks();
			if ( ! in_array( $this->fallback->get_value(), $valid_fallbacks, true ) ) {
				throw new \InvalidArgumentException(
					sprintf(
						'Existing user source fallback must be one of: %s',
						implode( ', ', $valid_fallbacks )
					)
				);
			}

			// If fallback creates new user, user data should be provided.
			if ( $this->fallback->creates_new_user() && ( null === $this->user_data || $this->user_data->is_empty() ) ) {
				throw new \InvalidArgumentException(
					'Create new user fallback requires user data with at least email or username'
				);
			}
		}
	}

	/**
	 * Validate invariants for new user source.
	 *
	 * @throws \InvalidArgumentException If invariants are violated.
	 */
	private function validate_new_user_invariants(): void {
		// User data is required.
		if ( null === $this->user_data || $this->user_data->is_empty() ) {
			throw new \InvalidArgumentException(
				'New user source requires user data with at least email or username'
			);
		}

		// Validate fallback is appropriate for new user.
		if ( null !== $this->fallback ) {
			$valid_fallbacks = User_Selector_Fallback::get_new_user_fallbacks();
			if ( ! in_array( $this->fallback->get_value(), $valid_fallbacks, true ) ) {
				throw new \InvalidArgumentException(
					sprintf(
						'New user source fallback must be one of: %s',
						implode( ', ', $valid_fallbacks )
					)
				);
			}

			// If fallback selects existing user, prioritized field is required.
			if ( $this->fallback->selects_existing_user() && ( null === $this->prioritized_field || null === $this->prioritized_field->get_value() ) ) {
				throw new \InvalidArgumentException(
					'Select existing user fallback requires a prioritized field (email or username)'
				);
			}
		}
	}

	/**
	 * Get user selector ID.
	 *
	 * @return User_Selector_Id|null
	 */
	public function get_id(): ?User_Selector_Id {
		return $this->id;
	}

	/**
	 * Get recipe ID.
	 *
	 * @return int|null
	 */
	public function get_recipe_id(): ?int {
		return $this->recipe_id;
	}

	/**
	 * Get source.
	 *
	 * @return User_Selector_Source
	 */
	public function get_source(): User_Selector_Source {
		return $this->source;
	}

	/**
	 * Get unique field.
	 *
	 * @return User_Selector_Unique_Field|null
	 */
	public function get_unique_field(): ?User_Selector_Unique_Field {
		return $this->unique_field;
	}

	/**
	 * Get unique field value.
	 *
	 * @return string|null
	 */
	public function get_unique_field_value(): ?string {
		return $this->unique_field_value;
	}

	/**
	 * Get fallback.
	 *
	 * @return User_Selector_Fallback|null
	 */
	public function get_fallback(): ?User_Selector_Fallback {
		return $this->fallback;
	}

	/**
	 * Get prioritized field.
	 *
	 * @return User_Selector_Prioritized_Field|null
	 */
	public function get_prioritized_field(): ?User_Selector_Prioritized_Field {
		return $this->prioritized_field;
	}

	/**
	 * Get user data.
	 *
	 * @return User_Selector_User_Data|null
	 */
	public function get_user_data(): ?User_Selector_User_Data {
		return $this->user_data;
	}

	/**
	 * Get configuration.
	 *
	 * @return User_Selector_Config|null
	 */
	public function get_config(): ?User_Selector_Config {
		return $this->config;
	}

	/**
	 * Convert to array representation.
	 *
	 * @return array
	 */
	public function to_array(): array {
		$data = array(
			'id'        => $this->id ? $this->id->get_value() : null,
			'recipe_id' => $this->recipe_id,
			'source'    => $this->source->get_value(),
		);

		if ( $this->source->is_existing_user() ) {
			$data['unique_field']       = $this->unique_field ? $this->unique_field->get_value() : null;
			$data['unique_field_value'] = $this->unique_field_value;
			$data['fallback']           = $this->fallback ? $this->fallback->get_value() : null;

			if ( $this->fallback && $this->fallback->creates_new_user() && $this->user_data ) {
				$data['user_data'] = $this->user_data->to_array();
			}
		} else {
			$data['user_data']         = $this->user_data ? $this->user_data->to_array() : array();
			$data['fallback']          = $this->fallback ? $this->fallback->get_value() : null;
			$data['prioritized_field'] = $this->prioritized_field ? $this->prioritized_field->get_value() : null;
		}

		return $data;
	}

	/**
	 * Convert to basic array representation for listing.
	 *
	 * @return array
	 */
	public function to_basic_array(): array {
		return array(
			'id'        => $this->id ? $this->id->get_value() : null,
			'recipe_id' => $this->recipe_id,
			'source'    => $this->source->get_value(),
		);
	}
}
