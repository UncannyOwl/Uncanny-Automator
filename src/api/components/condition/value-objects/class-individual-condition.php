<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Condition\Value_Objects;

use Uncanny_Automator\Api\Components\Condition\Dtos\Condition_Backup_Info;

/**
 * Individual Condition Value Object.
 *
 * Represents a single condition within a condition group.
 * Contains the condition definition (integration, code) and
 * the configured field values.
 *
 * @since 7.0.0
 */
class Individual_Condition {

	private string $integration;
	private string $condition_code;
	private Condition_Id $condition_id;
	private Condition_Fields $fields;
	private Condition_Backup_Info $backup_info;

	/**
	 * Constructor.
	 *
	 * @param Condition_Id          $condition_id Unique condition identifier.
	 * @param string                $integration Integration code (e.g., 'WP', 'GEN', 'LD').
	 * @param string                $condition_code Condition type code (e.g., 'TOKEN_MEETS_CONDITION').
	 * @param Condition_Fields      $fields Field configuration.
	 * @param Condition_Backup_Info $backup_info UI backup information.
	 * @throws \InvalidArgumentException If parameters are invalid.
	 */
	public function __construct(
		Condition_Id $condition_id,
		string $integration,
		string $condition_code,
		Condition_Fields $fields,
		Condition_Backup_Info $backup_info
	) {
		$this->validate_integration( $integration );
		$this->validate_condition_code( $condition_code );

		$this->condition_id   = $condition_id;
		$this->integration    = $integration;
		$this->condition_code = $condition_code;
		$this->fields         = $fields;
		$this->backup_info    = $backup_info;
	}

	/**
	 * Create a new individual condition with generated ID.
	 *
	 * @param string                $integration Integration code.
	 * @param string                $condition_code Condition type code.
	 * @param Condition_Fields      $fields Field configuration.
	 * @param Condition_Backup_Info $backup_info UI backup information.
	 * @param string|null           $condition_id Optional existing condition identifier.
	 * @return self New individual condition.
	 */
	public static function create(
		string $integration,
		string $condition_code,
		Condition_Fields $fields,
		Condition_Backup_Info $backup_info,
		?string $condition_id = null
	): self {

		$condition_id_value = Condition_Id::generate();

		if ( null !== $condition_id && '' !== $condition_id ) {
			$condition_id_value = new Condition_Id( $condition_id );
		}

		return new self(
			$condition_id_value,
			$integration,
			$condition_code,
			$fields,
			$backup_info
		);
	}

	/**
	 * Get the condition ID.
	 *
	 * @return Condition_Id Condition identifier.
	 */
	public function get_condition_id(): Condition_Id {
		return $this->condition_id;
	}

	/**
	 * Get the integration code.
	 *
	 * @return string Integration code.
	 */
	public function get_integration(): string {
		return $this->integration;
	}

	/**
	 * Get the condition code.
	 *
	 * @return string Condition type code.
	 */
	public function get_condition_code(): string {
		return $this->condition_code;
	}

	/**
	 * Get the field configuration.
	 *
	 * @return Condition_Fields Field configuration.
	 */
	public function get_fields(): Condition_Fields {
		return $this->fields;
	}

	/**
	 * Get the backup information.
	 *
	 * @return Condition_Backup_Info UI backup information.
	 */
	public function get_backup_info(): Condition_Backup_Info {
		return $this->backup_info;
	}

	/**
	 * Update field configuration.
	 *
	 * @param Condition_Fields $fields New field configuration.
	 * @return self New instance with updated fields.
	 */
	public function with_fields( Condition_Fields $fields ): self {
		return new self(
			$this->condition_id,
			$this->integration,
			$this->condition_code,
			$fields,
			$this->backup_info
		);
	}

	/**
	 * Update backup information.
	 *
	 * @param Condition_Backup_Info $backup_info New backup information.
	 * @return self New instance with updated backup info.
	 */
	public function with_backup_info( Condition_Backup_Info $backup_info ): self {
		return new self(
			$this->condition_id,
			$this->integration,
			$this->condition_code,
			$this->fields,
			$backup_info
		);
	}

	/**
	 * Convert to array representation for storage.
	 *
	 * @return array Condition as array matching legacy format.
	 */
	public function to_array(): array {
		return array(
			'id'          => $this->condition_id->get_value(),
			'integration' => $this->integration,
			'condition'   => $this->condition_code,
			'fields'      => $this->fields->to_array(),
			'backup'      => $this->backup_info->to_array(),
		);
	}

	/**
	 * Create from array (for hydration from storage).
	 *
	 * @param array $data Array data from storage.
	 * @return self Individual condition instance.
	 * @throws \InvalidArgumentException If required fields are missing or invalid.
	 */
	public static function from_array( array $data ): self {
		$required_fields = array( 'id', 'integration', 'condition', 'fields', 'backup' );

		foreach ( $required_fields as $field ) {
			if ( ! isset( $data[ $field ] ) ) {
				throw new \InvalidArgumentException( sprintf( 'Missing required condition field: %s', $field ) );
			}
		}

		return new self(
			new Condition_Id( $data['id'] ?? '' ),
			$data['integration'] ?? '',
			$data['condition'] ?? '',
			new Condition_Fields( $data['fields'] ?? array() ),
			Condition_Backup_Info::from_array( $data['backup'] ?? array() )
		);
	}

	/**
	 * Check equality with another Individual_Condition.
	 *
	 * @param Individual_Condition $other Other condition to compare.
	 * @return bool True if conditions are equal.
	 */
	public function equals( Individual_Condition $other ): bool {
		return $this->condition_id->equals( $other->get_condition_id() ) &&
				$this->integration === $other->get_integration() &&
				$this->condition_code === $other->get_condition_code() &&
				$this->fields->equals( $other->get_fields() );
	}

	/**
	 * Validate integration code.
	 *
	 * @param string $integration Integration code to validate.
	 * @throws \InvalidArgumentException If integration is invalid.
	 */
	private function validate_integration( string $integration ): void {
		if ( empty( $integration ) ) {
			throw new \InvalidArgumentException( 'Integration code cannot be empty' );
		}

		if ( strlen( $integration ) > 20 ) {
			throw new \InvalidArgumentException( 'Integration code cannot exceed 20 characters' );
		}

		if ( ! preg_match( '/^[A-Z0-9_-]+$/', $integration ) ) {
			throw new \InvalidArgumentException( 'Integration code must contain only uppercase letters, numbers, underscores, and hyphens' );
		}
	}

	/**
	 * Validate condition code.
	 *
	 * @param string $condition_code Condition code to validate.
	 * @throws \InvalidArgumentException If condition code is invalid.
	 */
	private function validate_condition_code( string $condition_code ): void {
		if ( empty( $condition_code ) ) {
			throw new \InvalidArgumentException( 'Condition code cannot be empty' );
		}

		if ( strlen( $condition_code ) > 50 ) {
			throw new \InvalidArgumentException( 'Condition code cannot exceed 50 characters' );
		}

		if ( ! preg_match( '/^[A-Z0-9_-]+$/', $condition_code ) ) {
			throw new \InvalidArgumentException( 'Condition code must contain only uppercase letters, numbers, underscores, and hyphens' );
		}
	}
}
