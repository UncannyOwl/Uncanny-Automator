<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Loop\Iterable_Expression;

use Uncanny_Automator\Api\Components\Loop\Iterable_Expression\Enums\Iteration_Type;
use Uncanny_Automator\Api\Components\Loop\Iterable_Expression\Value_Objects\Type;
use Uncanny_Automator\Api\Components\Loop\Iterable_Expression\Value_Objects\Fields;

/**
 * Expression Value Object.
 *
 * Root object of the Iterable_Expression bounded context.
 * Represents the complete iteration configuration for a loop.
 * Composes the iteration type, field configuration, and backup data.
 *
 * Encapsulates what a loop iterates over (users, posts, or tokens).
 *
 * @since 7.0.0
 */
class Expression {

	/**
	 * The iteration type.
	 *
	 * @var Type
	 */
	private Type $type;

	/**
	 * The field configuration.
	 *
	 * @var Fields|null
	 */
	private ?Fields $fields;

	/**
	 * The backup data.
	 *
	 * @var array|null
	 */
	private ?array $backup;

	/**
	 * Constructor.
	 *
	 * @param Type        $type   The iteration type.
	 * @param Fields|null $fields The field configuration.
	 * @param array|null  $backup The backup data.
	 */
	public function __construct(
		Type $type,
		?Fields $fields = null,
		?array $backup = null
	) {
		$this->type   = $type;
		$this->fields = $fields;
		$this->backup = $backup;
	}

	/**
	 * Create from array.
	 *
	 * Handles fields/backup as both arrays and JSON strings (Pro UI format).
	 *
	 * @param array $data Array data (typically from post meta).
	 * @return self
	 */
	public static function from_array( array $data ): self {
		$type_value = $data['type'] ?? Iteration_Type::USERS;
		$type       = new Type( $type_value );

		// Handle fields as array or JSON string.
		$fields = null;
		if ( isset( $data['fields'] ) ) {
			if ( is_string( $data['fields'] ) ) {
				$fields = Fields::from_json( $data['fields'] );
			} elseif ( is_array( $data['fields'] ) ) {
				$fields = new Fields( $data['fields'] );
			}
		}

		// Handle backup as array or JSON string.
		$backup = null;
		if ( isset( $data['backup'] ) ) {
			if ( is_string( $data['backup'] ) ) {
				$decoded = json_decode( $data['backup'], true );
				$backup  = is_array( $decoded ) ? $decoded : null;
			} elseif ( is_array( $data['backup'] ) ) {
				$backup = $data['backup'];
			}
		}

		return new self( $type, $fields, $backup );
	}

	/**
	 * Get iteration type.
	 *
	 * @return Type
	 */
	public function get_type(): Type {
		return $this->type;
	}

	/**
	 * Get field configuration.
	 *
	 * @return Fields|null
	 */
	public function get_fields(): ?Fields {
		return $this->fields;
	}

	/**
	 * Get backup data.
	 *
	 * @return array|null
	 */
	public function get_backup(): ?array {
		return $this->backup;
	}

	/**
	 * Check if this is a users iteration.
	 *
	 * @return bool
	 */
	public function is_users(): bool {
		return $this->type->is_users();
	}

	/**
	 * Check if this is a posts iteration.
	 *
	 * @return bool
	 */
	public function is_posts(): bool {
		return $this->type->is_posts();
	}

	/**
	 * Check if this is a token iteration.
	 *
	 * @return bool
	 */
	public function is_token(): bool {
		return $this->type->is_token();
	}

	/**
	 * Check if fields are configured.
	 *
	 * @return bool
	 */
	public function has_fields(): bool {
		return null !== $this->fields && ! $this->fields->is_empty();
	}

	/**
	 * Check if backup data exists.
	 *
	 * @return bool
	 */
	public function has_backup(): bool {
		return null !== $this->backup && ! empty( $this->backup );
	}

	/**
	 * Convert to array.
	 *
	 * Fields and backup are JSON-encoded to match Pro UI storage format.
	 *
	 * @return array
	 */
	public function to_array(): array {
		$result = array(
			'type' => $this->type->get_value(),
		);

		if ( null !== $this->fields && ! $this->fields->is_empty() ) {
			$result['fields'] = wp_json_encode( $this->fields->to_array() );
		}

		if ( null !== $this->backup && ! empty( $this->backup ) ) {
			$result['backup'] = wp_json_encode( $this->backup );
		}

		return $result;
	}

	/**
	 * Create a default users iteration expression.
	 *
	 * @return self
	 */
	public static function default_users(): self {
		return new self( new Type( Iteration_Type::USERS ) );
	}

	/**
	 * Create a default posts iteration expression.
	 *
	 * @return self
	 */
	public static function default_posts(): self {
		return new self( new Type( Iteration_Type::POSTS ) );
	}

	/**
	 * Create a default token iteration expression.
	 *
	 * @return self
	 */
	public static function default_token(): self {
		return new self( new Type( Iteration_Type::TOKEN ) );
	}
}
