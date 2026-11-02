<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Integration_Catalog\Integration\Value_Objects;

use InvalidArgumentException;
use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Block_Modifier_Types;

/**
 * Integration Block Modifier Value Object.
 *
 * One catalog row under integration.block_modifiers[block_modifier_type][modifier_code].
 * Mirror of Integration_Item; the type allow-list differs (filter_condition / loop_filter
 * instead of trigger / action) and there are no backend-only fields
 * (no meta_code, no hooks).
 *
 * Per src/app/CLAUDE.md Rule 16, this is an independent class — not a subclass
 * of Integration_Item, not sharing a trait. The 8 wire fields are duplicated
 * deliberately; if/when items and modifiers start co-evolving we extract.
 */
class Integration_Block_Modifier {

	/**
	 * The modifier code.
	 *
	 * @var string
	 */
	private string $code;

	/**
	 * The modifier type (filter_condition or loop_filter).
	 *
	 * @var string
	 */
	private string $type;

	/**
	 * Whether the modifier is deprecated.
	 *
	 * @var bool
	 */
	private bool $is_deprecated;

	/**
	 * The modifier sentence (short and dynamic forms).
	 *
	 * @var array
	 */
	private array $sentence;

	/**
	 * The modifier description.
	 *
	 * @var string|null
	 */
	private ?string $description;

	/**
	 * The minimum plan tier required to use the modifier.
	 *
	 * @var string
	 */
	private string $required_tier;

	/**
	 * Whether the modifier requires user data.
	 *
	 * @var bool
	 */
	private bool $requires_user_data;

	/**
	 * Whether the modifier declares node tests.
	 *
	 * Defaults to false: the FE catalog schema requires the key on every
	 * row, and no modifier declares tests yet.
	 *
	 * @var bool
	 */
	private bool $has_tests;

	/**
	 * The modifier support URL.
	 *
	 * @var string|null
	 */
	private ?string $url_support;

	/**
	 * Allowlist of field-aware ancestor rules; the modifier is offered when at
	 * least one matches. Wire-shaped AncestorRule[]; empty when none.
	 *
	 * @var array<int,array>
	 */
	private array $required_block_ancestors;

	/**
	 * Denylist of field-aware ancestor rules; the modifier is rejected when any
	 * matches (overrides the allowlist). Wire-shaped AncestorRule[]; empty when none.
	 *
	 * @var array<int,array>
	 */
	private array $unsupported_block_ancestors;

	/**
	 * Constructor.
	 *
	 * @param array $modifier Modifier data (the catalog row).
	 *
	 * @throws InvalidArgumentException When a required field is missing or any field is malformed.
	 */
	public function __construct( array $modifier ) {
		$this->validate( $modifier );

		$this->code               = $modifier['code'];
		$this->type               = $modifier['type'];
		$this->is_deprecated      = $modifier['is_deprecated'];
		$this->sentence           = $modifier['sentence'];
		$this->description        = $this->get_value_or_null( $modifier, 'description' );
		$this->required_tier      = $modifier['required_tier'];
		$this->requires_user_data = $modifier['requires_user_data'];
		$this->has_tests          = (bool) ( $modifier['has_tests'] ?? false );
		$this->url_support        = $this->get_value_or_null( $modifier, 'url_support' );

		$this->required_block_ancestors    = $this->build_rules( $modifier['required_block_ancestors'] ?? array() );
		$this->unsupported_block_ancestors = $this->build_rules( $modifier['unsupported_block_ancestors'] ?? array() );
	}

	/**
	 * Convert to array.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return $this->build_array();
	}

	/**
	 * Convert to REST format.
	 *
	 * @return array
	 */
	public function to_rest(): array {
		return $this->build_array();
	}

	/**
	 * Build the wire array. The two ancestor-rule arrays are appended only when
	 * non-empty (absent means "always applies" on the frontend).
	 *
	 * @return array
	 */
	private function build_array(): array {
		$out = array(
			'code'               => $this->code,
			'type'               => $this->type,
			'is_deprecated'      => $this->is_deprecated,
			'sentence'           => $this->sentence,
			'description'        => $this->description,
			'required_tier'      => $this->required_tier,
			'requires_user_data' => $this->requires_user_data,
			'has_tests'          => $this->has_tests,
			'url_support'        => $this->url_support,
		);
		if ( ! empty( $this->required_block_ancestors ) ) {
			$out['required_block_ancestors'] = $this->required_block_ancestors;
		}
		if ( ! empty( $this->unsupported_block_ancestors ) ) {
			$out['unsupported_block_ancestors'] = $this->unsupported_block_ancestors;
		}
		return $out;
	}

	/**
	 * Validate modifier data.
	 *
	 * @param array $modifier Modifier to validate.
	 *
	 * @return void
	 * @throws InvalidArgumentException When a field is missing or invalid.
	 */
	private function validate( array $modifier ): void {
		$required_fields = array( 'code', 'type', 'is_deprecated', 'sentence', 'required_tier', 'requires_user_data' );
		foreach ( $required_fields as $field ) {
			if ( ! isset( $modifier[ $field ] ) ) {
				throw new InvalidArgumentException( sprintf( 'Integration block modifier missing required field: %s', esc_html( $field ) ) );
			}
		}

		if ( empty( $modifier['code'] ) || ! is_string( $modifier['code'] ) ) {
			throw new InvalidArgumentException( 'Integration block modifier code must be a non-empty string' );
		}

		if ( ! Block_Modifier_Types::is_valid( $modifier['type'] ) ) {
			throw new InvalidArgumentException(
				sprintf(
					'Integration block modifier type must be one of: %s. Got: %s',
					esc_html( implode( ', ', Block_Modifier_Types::get_all() ) ),
					esc_html( $modifier['type'] )
				)
			);
		}

		if ( ! is_bool( $modifier['is_deprecated'] ) ) {
			throw new InvalidArgumentException( 'Integration block modifier is_deprecated must be a boolean' );
		}

		if ( ! is_array( $modifier['sentence'] ) ) {
			throw new InvalidArgumentException( 'Integration block modifier sentence must be an array' );
		}

		if ( ! isset( $modifier['sentence']['short'] ) || ! is_string( $modifier['sentence']['short'] ) ) {
			throw new InvalidArgumentException( 'Integration block modifier sentence.short must be a string' );
		}

		if ( ! isset( $modifier['sentence']['dynamic'] ) || ! is_string( $modifier['sentence']['dynamic'] ) ) {
			throw new InvalidArgumentException( 'Integration block modifier sentence.dynamic must be a string' );
		}

		$valid_tiers = array( 'lite', 'pro-basic', 'pro-plus', 'pro-elite' );
		if ( ! in_array( $modifier['required_tier'], $valid_tiers, true ) ) {
			throw new InvalidArgumentException(
				sprintf(
					'Integration block modifier required_tier must be one of: %s. Got: %s',
					esc_html( implode( ', ', $valid_tiers ) ),
					esc_html( $modifier['required_tier'] )
				)
			);
		}

		if ( ! is_bool( $modifier['requires_user_data'] ) ) {
			throw new InvalidArgumentException( 'Integration block modifier requires_user_data must be a boolean' );
		}
	}

	/**
	 * Get a value from the modifier array, or null when unset or empty.
	 *
	 * @param array  $modifier Modifier data.
	 * @param string $key      Key to read.
	 *
	 * @return mixed|null
	 */
	private function get_value_or_null( array $modifier, string $key ) {
		if ( empty( $modifier[ $key ] ) ) {
			return null;
		}
		return $modifier[ $key ];
	}

	/**
	 * Validate and normalize a list of ancestor rules through Ancestor_Rule.
	 *
	 * Untyped parameter on purpose: malformed catalog data (a non-array value
	 * present under the key) is turned into a catchable InvalidArgumentException
	 * rather than an uncatchable TypeError.
	 *
	 * @param mixed $rules Raw rules list, expected to be an array of rule arrays.
	 *
	 * @return array<int,array> Wire-shaped AncestorRule[].
	 * @throws InvalidArgumentException When $rules or any entry is not an array, or a rule is malformed.
	 */
	private function build_rules( $rules ): array {
		if ( ! is_array( $rules ) ) {
			throw new InvalidArgumentException( 'Integration block modifier ancestor rules must be an array' );
		}
		$out = array();
		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				throw new InvalidArgumentException( 'Integration block modifier ancestor rule must be an array' );
			}
			$out[] = ( new Ancestor_Rule( $rule ) )->to_array();
		}
		return $out;
	}
}
