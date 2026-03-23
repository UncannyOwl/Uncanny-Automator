<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Integration\Value_Objects;

use InvalidArgumentException;
use Uncanny_Automator\Api\Components\Integration\Enums\Integration_Item_Types;

/**
 * Integration Item Value Object.
 *
 * Represents a single integration item (trigger, action, filter_condition, or loop_filter).
 * Ensures each item has all required fields and valid structure.
 *
 * @since 7.0.0
 */
class Integration_Item {

	/**
	 * The item code.
	 *
	 * @var string
	 */
	private string $code;

	/**
	 * The item type.
	 *
	 * @var string
	 */
	private string $type;

	/**
	 * The item is deprecated.
	 *
	 * @var bool
	 */
	private bool $is_deprecated;

	/**
	 * The item sentence.
	 *
	 * @var array
	 */
	private array $sentence;

	/**
	 * The item description.
	 *
	 * @var string|null
	 */
	private ?string $description;

	/**
	 * The item required tier.
	 *
	 * @var string
	 */
	private string $required_tier;

	/**
	 * The item requires user data.
	 *
	 * @var bool
	 */
	private bool $requires_user_data;

	/**
	 * The item support URL.
	 *
	 * @var string|null
	 */
	private ?string $url_support;

	/**
	 * The item meta code.
	 *
	 * Used for triggers and actions to identify the primary meta field.
	 * Null for conditions and loop filters.
	 *
	 * @var string|null
	 */
	private ?string $meta_code;

	/**
	 * The WordPress hooks this item listens to.
	 *
	 * Populated for triggers and actions, empty array for other types.
	 *
	 * @var array
	 */
	private array $hooks;

	/**
	 * Constructor.
	 *
	 * @param array $item Item data.
	 *  @property string $code Item code.
	 *  @property string $type Item type.
	 *  @property bool $is_deprecated Item is deprecated.
	 *  @property array $sentence Item sentence.
	 *  @property string|null $description Item description.
	 *  @property string $required_tier Item required tier.
	 *  @property bool $requires_user_data Item requires user data.
	 *  @property string|null $url_support Item support URL.
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid item.
	 */
	public function __construct( array $item ) {
		$this->validate( $item );

		$this->code               = $item['code'];
		$this->type               = $item['type'];
		$this->is_deprecated      = $item['is_deprecated'];
		$this->sentence           = $item['sentence'];
		$this->description        = $this->get_value_or_null( $item, 'description' );
		$this->required_tier      = $item['required_tier'];
		$this->requires_user_data = $item['requires_user_data'];
		$this->url_support        = $this->get_value_or_null( $item, 'url_support' );
		$this->meta_code          = $this->get_value_or_null( $item, 'meta' );
		$this->hooks              = $item['hooks'] ?? array();
	}

	/**
	 * Convert to array.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return array(
			'code'               => $this->code,
			'type'               => $this->type,
			'is_deprecated'      => $this->is_deprecated,
			'sentence'           => $this->sentence,
			'description'        => $this->description,
			'required_tier'      => $this->required_tier,
			'requires_user_data' => $this->requires_user_data,
			'url_support'        => $this->url_support,
			'meta_code'          => $this->meta_code,
			'hooks'              => $this->hooks,
		);
	}

	/**
	 * Convert to REST format.
	 *
	 * Excludes backend-only properties (meta_code, hooks).
	 *
	 * @return array
	 */
	public function to_rest(): array {
		return array(
			'code'               => $this->code,
			'type'               => $this->type,
			'is_deprecated'      => $this->is_deprecated,
			'sentence'           => $this->sentence,
			'description'        => $this->description,
			'required_tier'      => $this->required_tier,
			'requires_user_data' => $this->requires_user_data,
			'url_support'        => $this->url_support,
		);
	}

	/**
	 * Validate item data.
	 *
	 * @param array $item Item to validate.
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid.
	 */
	private function validate( array $item ): void {
		// Validate required fields.
		$required_fields = array( 'code', 'type', 'is_deprecated', 'sentence', 'required_tier', 'requires_user_data' );
		foreach ( $required_fields as $field ) {
			if ( ! isset( $item[ $field ] ) ) {
				throw new InvalidArgumentException( "Integration item missing required field: {$field}" );
			}
		}

		// Validate code.
		if ( empty( $item['code'] ) || ! is_string( $item['code'] ) ) {
			throw new InvalidArgumentException( 'Integration item code must be a non-empty string' );
		}

		// Validate type.
		if ( ! Integration_Item_Types::is_valid( $item['type'] ) ) {
			throw new InvalidArgumentException(
				sprintf(
					'Integration item type must be one of: %s. Got: %s',
					implode( ', ', Integration_Item_Types::get_all() ),
					$item['type']
				)
			);
		}

		// Validate is_deprecated.
		if ( ! is_bool( $item['is_deprecated'] ) ) {
			throw new InvalidArgumentException( 'Integration item is_deprecated must be a boolean' );
		}

		// Validate sentence structure.
		if ( ! is_array( $item['sentence'] ) ) {
			throw new InvalidArgumentException( 'Integration item sentence must be an array' );
		}

		if ( ! isset( $item['sentence']['short'] ) || ! is_string( $item['sentence']['short'] ) ) {
			throw new InvalidArgumentException( 'Integration item sentence.short must be a string' );
		}

		if ( ! isset( $item['sentence']['dynamic'] ) || ! is_string( $item['sentence']['dynamic'] ) ) {
			throw new InvalidArgumentException( 'Integration item sentence.dynamic must be a string' );
		}

		// Validate required_tier.
		$valid_tiers = array( 'lite', 'pro-basic', 'pro-plus', 'pro-elite' );
		if ( ! in_array( $item['required_tier'], $valid_tiers, true ) ) {
			throw new InvalidArgumentException(
				sprintf(
					'Integration item required_tier must be one of: %s. Got: %s',
					implode( ', ', $valid_tiers ),
					$item['required_tier']
				)
			);
		}

		// Validate requires_user_data.
		if ( ! is_bool( $item['requires_user_data'] ) ) {
			throw new InvalidArgumentException( 'Integration item requires_user_data must be a boolean' );
		}
	}

	/**
	 * Get value from array or null if empty.
	 *
	 * @param array $item Array to get value from.
	 * @param string $key Key to get value for.
	 *
	 * @return mixed|null Value if set and not empty, null otherwise.
	 */
	private function get_value_or_null( array $item, string $key ) {
		if ( ! isset( $item[ $key ] ) || empty( $item[ $key ] ) ) {
			return null;
		}

		return $item[ $key ];
	}
}
