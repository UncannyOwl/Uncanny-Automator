<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Loop\Filter;

use Uncanny_Automator\Api\Components\Loop\Filter\Value_Objects\Id;
use Uncanny_Automator\Api\Components\Loop\Filter\Value_Objects\Code;
use Uncanny_Automator\Api\Components\Loop\Filter\Value_Objects\Integration_Code;
use Uncanny_Automator\Api\Components\Loop\Filter\Value_Objects\User_Type_Value;
use Uncanny_Automator\Api\Components\Loop\Filter\Value_Objects\Fields;
use Uncanny_Automator\Api\Components\Loop\Filter\Value_Objects\Backup;

/**
 * Filter Entity - Rich Domain Model.
 *
 * Root entity representing a filter applied to a loop.
 * Encapsulates business rules and behavior around loop filters.
 *
 * Domain Invariants:
 * - Filter code must not be empty
 * - Integration code must not be empty
 * - User type must be valid ('user' or 'anonymous')
 * - Filters are always pro (premium feature)
 *
 * @since 7.0.0
 */
class Filter {

	private Id $id;
	private Code $code;
	private Integration_Code $integration_code;
	private ?string $integration_name;
	private User_Type_Value $user_type;
	private Fields $fields;
	private Backup $backup;
	private ?string $version;

	/**
	 * Constructor.
	 *
	 * Creates a new Filter entity with invariant validation.
	 * Enforces business rules at construction time (fail-fast principle).
	 *
	 * @param Config $config Filter configuration object.
	 * @throws \InvalidArgumentException If invariants are violated.
	 */
	public function __construct( Config $config ) {
		// Enforce invariants through value object constructors
		$this->id               = new Id( $config->get_id() );
		$this->code             = new Code( $config->get_code() );
		$this->integration_code = new Integration_Code( $config->get_integration_code() );
		$this->integration_name = $config->get_integration_name();
		$this->user_type        = new User_Type_Value( $config->get_user_type() );
		$this->fields           = new Fields( $config->get_fields() );
		$this->backup           = new Backup( $config->get_backup() );
		$this->version          = $config->get_version();
	}
	/**
	 * Get id.
	 *
	 * @return Id
	 */
	public function get_id(): Id {
		return $this->id;
	}
	/**
	 * Get code.
	 *
	 * @return Code
	 */
	public function get_code(): Code {
		return $this->code;
	}
	/**
	 * Get integration code.
	 *
	 * @return Integration_Code
	 */
	public function get_integration_code(): Integration_Code {
		return $this->integration_code;
	}
	/**
	 * Get integration name.
	 *
	 * @return ?
	 */
	public function get_integration_name(): ?string {
		return $this->integration_name;
	}
	/**
	 * Get user type.
	 *
	 * @return User_Type_Value
	 */
	public function get_user_type(): User_Type_Value {
		return $this->user_type;
	}

	/**
	 * Get user type as string (for backward compatibility).
	 *
	 * @deprecated Use get_user_type()->get_value() or requires_logged_in_user() instead.
	 * @return string User type value ('user' or 'anonymous').
	 */
	public function get_user_type_string(): string {
		return $this->user_type->get_value();
	}
	/**
	 * Get fields.
	 *
	 * @return Fields
	 */
	public function get_fields(): Fields {
		return $this->fields;
	}
	/**
	 * Get backup.
	 *
	 * @return Backup
	 */
	public function get_backup(): Backup {
		return $this->backup;
	}
	/**
	 * Get version.
	 *
	 * @return ?
	 */
	public function get_version(): ?string {
		return $this->version;
	}

	/**
	 * Check if this filter is persisted.
	 *
	 * @return bool True if persisted (has ID), false otherwise.
	 */
	public function is_persisted(): bool {
		return $this->id->is_persisted();
	}

	/**
	 * Check if this filter has field configuration.
	 *
	 * Domain Query: Delegates to Fields value object.
	 *
	 * @return bool True if has fields, false if fields are empty.
	 */
	public function has_fields(): bool {
		return ! $this->fields->is_empty();
	}

	/**
	 * Check if this filter has backup data.
	 *
	 * Domain Query: Delegates to Backup value object.
	 *
	 * @return bool True if has backup, false if backup is empty.
	 */
	public function has_backup(): bool {
		return ! $this->backup->is_empty();
	}

	/**
	 * Check if this is a pro filter.
	 *
	 * Domain Rule: All loop filters are pro (premium feature).
	 *
	 * @return bool Always returns true.
	 */
	public function is_pro(): bool {
		return true;  // Filters are always pro
	}

	/**
	 * Check if this filter requires logged-in users.
	 *
	 * Domain Query: Delegates to User_Type_Value for encapsulated behavior.
	 *
	 * @return bool True if requires logged-in users, false if allows anonymous.
	 */
	public function requires_logged_in_user(): bool {
		return $this->user_type->requires_logged_in_user();
	}

	/**
	 * Check if this filter allows anonymous visitors.
	 *
	 * Domain Query: Inverse of requires_logged_in_user().
	 *
	 * @return bool True if allows anonymous, false if requires logged-in user.
	 */
	public function allows_anonymous(): bool {
		return $this->user_type->allows_anonymous();
	}

	/**
	 * Update filter fields.
	 *
	 * Domain Rule: Only persisted filters can be updated.
	 *
	 * @param Fields $new_fields New field values.
	 * @param Backup $new_backup New backup data.
	 * @return self For fluent interface.
	 * @throws \DomainException If filter is not persisted.
	 */
	public function update_fields( Fields $new_fields, Backup $new_backup ): self {
		if ( ! $this->is_persisted() ) {
			throw new \DomainException( 'Cannot update fields on unpersisted filter. Save the filter first.' );
		}

		$this->fields = $new_fields;
		$this->backup = $new_backup;

		return $this;
	}

	/**
	 * Check if this filter is compatible with an iteration type.
	 *
	 * Domain Rule: User filters require user iteration.
	 * Post/Token filters work with any iteration type.
	 *
	 * @param string $iteration_type Iteration type ('users', 'posts', 'token').
	 * @return bool True if compatible, false otherwise.
	 */
	public function is_compatible_with_iteration_type( string $iteration_type ): bool {
		// User filters require user iteration
		if ( $this->requires_logged_in_user() && 'users' !== $iteration_type ) {
			return false;
		}

		return true;
	}

	/**
	 * Convert to array representation.
	 *
	 * Maintains backward compatibility by serializing value objects to primitives.
	 *
	 * @return array Filter data array.
	 */
	public function to_array(): array {
		return array(
			'type'             => 'loop-filter',
			'id'               => $this->id->get_value(),
			'code'             => $this->code->get_value(),
			'integration_code' => $this->integration_code->get_value(),
			'integration_name' => $this->integration_name,
			'filter_type'      => 'pro',  // Always pro (premium feature)
			'user_type'        => $this->user_type->get_value(),  // Serialize to string for BC
			'fields'           => $this->fields->to_array(),
			'backup'           => $this->backup->to_array(),
			'version'          => $this->version,
		);
	}
	/**
	 * From array.
	 *
	 * @param array $data The data.
	 * @return self
	 */
	public static function from_array( array $data ): self {
		return new self( Config::from_array( $data ) );
	}
}
