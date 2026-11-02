<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Integration_Catalog\Integration\Value_Objects;

use InvalidArgumentException;
use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Integration_Item_Types;

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
	 * Whether the item declares node tests.
	 *
	 * Defaults to false: the FE catalog schema requires the key on every
	 * row, and most sources (feed data, legacy registries)
	 * never declare tests.
	 *
	 * @var bool
	 */
	private bool $has_tests;

	/**
	 * Whether a recipe step built from the item can be switched on and off.
	 *
	 * Defaults to true, as the FE reads an absent key: only the always-live
	 * triggers (Run now and the import triggers it fires) are stamped false.
	 *
	 * @var bool
	 */
	private bool $has_status_toggle;

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
	 * Allowlist of ancestor rules: the item may be placed only inside a block
	 * matching at least one. Wire-shaped AncestorRule[]; empty when unrestricted.
	 *
	 * @var array<int,array>
	 */
	private array $required_block_ancestors;

	/**
	 * Denylist of ancestor rules: the item is rejected inside a block matching
	 * any. Wire-shaped AncestorRule[]; empty when none.
	 *
	 * @var array<int,array>
	 */
	private array $unsupported_block_ancestors;

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
		$this->sentence           = $this->guard_short_sentence( $item['sentence'] );
		$this->description        = $this->get_value_or_null( $item, 'description' );
		$this->required_tier      = $item['required_tier'];
		$this->requires_user_data = $item['requires_user_data'];
		$this->has_tests          = (bool) ( $item['has_tests'] ?? false );
		$this->has_status_toggle  = (bool) ( $item['has_status_toggle'] ?? true );
		$this->url_support        = $this->get_value_or_null( $item, 'url_support' );
		$this->meta_code          = $this->get_value_or_null( $item, 'meta' );
		$this->hooks              = $item['hooks'] ?? array();

		$this->required_block_ancestors    = $this->build_rules( $item['required_block_ancestors'] ?? array() );
		$this->unsupported_block_ancestors = $this->build_rules( $item['unsupported_block_ancestors'] ?? array() );
	}

	/**
	 * Convert to array. The ancestor-rule arrays are appended only when non-empty.
	 *
	 * @return array
	 */
	public function to_array(): array {
		$out = array(
			'code'               => $this->code,
			'type'               => $this->type,
			'is_deprecated'      => $this->is_deprecated,
			'sentence'           => $this->sentence,
			'description'        => $this->description,
			'required_tier'      => $this->required_tier,
			'requires_user_data' => $this->requires_user_data,
			'has_tests'          => $this->has_tests,
			'has_status_toggle'  => $this->has_status_toggle,
			'url_support'        => $this->url_support,
			'meta_code'          => $this->meta_code,
			'hooks'              => $this->hooks,
		);

		return $this->with_ancestor_rules( $out );
	}

	/**
	 * Convert to REST format.
	 *
	 * Excludes backend-only properties (meta_code, hooks). The ancestor-rule
	 * arrays are appended only when non-empty.
	 *
	 * @return array
	 */
	public function to_rest(): array {
		$out = array(
			'code'               => $this->code,
			'type'               => $this->type,
			'is_deprecated'      => $this->is_deprecated,
			'sentence'           => $this->sentence,
			'description'        => $this->description,
			'required_tier'      => $this->required_tier,
			'requires_user_data' => $this->requires_user_data,
			'has_tests'          => $this->has_tests,
			'has_status_toggle'  => $this->has_status_toggle,
			'url_support'        => $this->url_support,
		);

		return $this->with_ancestor_rules( $out );
	}

	/**
	 * Append the ancestor-rule arrays, each only when non-empty — absent means
	 * "no restriction" on the frontend, and keeps every unrestricted item's row
	 * byte-for-byte what it was.
	 *
	 * @param array $out Item wire array.
	 *
	 * @return array
	 */
	private function with_ancestor_rules( array $out ): array {
		if ( ! empty( $this->required_block_ancestors ) ) {
			$out['required_block_ancestors'] = $this->required_block_ancestors;
		}
		if ( ! empty( $this->unsupported_block_ancestors ) ) {
			$out['unsupported_block_ancestors'] = $this->unsupported_block_ancestors;
		}

		return $out;
	}

	/**
	 * Validate and normalize a list of ancestor rules through Ancestor_Rule.
	 *
	 * Untyped parameter on purpose: a non-array value under the key becomes a
	 * catchable InvalidArgumentException rather than an uncatchable TypeError.
	 *
	 * @param mixed $rules Raw rules list, expected to be an array of rule arrays.
	 *
	 * @return array<int,array> Wire-shaped AncestorRule[].
	 * @throws InvalidArgumentException When $rules or any entry is not an array, or a rule is malformed.
	 */
	private function build_rules( $rules ): array {
		if ( ! is_array( $rules ) ) {
			throw new InvalidArgumentException( 'Integration item ancestor rules must be an array' );
		}
		$out = array();
		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				throw new InvalidArgumentException( 'Integration item ancestor rule must be an array' );
			}
			$out[] = ( new Ancestor_Rule( $rule ) )->to_array();
		}

		return $out;
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
				throw new InvalidArgumentException( sprintf( 'Integration item missing required field: %s', esc_html( $field ) ) );
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
					esc_html( implode( ', ', Integration_Item_Types::get_all() ) ),
					esc_html( $item['type'] )
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
					esc_html( implode( ', ', $valid_tiers ) ),
					esc_html( $item['required_tier'] )
				)
			);
		}

		// Validate requires_user_data.
		if ( ! is_bool( $item['requires_user_data'] ) ) {
			throw new InvalidArgumentException( 'Integration item requires_user_data must be a boolean' );
		}
	}

	/**
	 * Guarantee sentence.short satisfies the frontend catalog contract.
	 *
	 * The frontend integrations-catalog schema rejects any colon in
	 * `sentence.short` and validates the whole payload with a strict parser,
	 * so a single colon (e.g. an `Action: <CODE>` default that leaks into the
	 * external `complete.json` feed) fails the ENTIRE catalog response and
	 * blanks every catalog consumer — recipe builder, recipes list, template
	 * library — on every site. The feed is external and untrusted, so this VO
	 * is the boundary that must not hand the frontend a contract-violating
	 * value: strip colons from `short` so one bad upstream row can never take
	 * the catalog down. `dynamic` is intentionally left untouched — it
	 * legitimately carries `{{token:FIELD}}` sprintf specifiers.
	 *
	 * @param array $sentence Raw sentence array (short + dynamic).
	 *
	 * @return array Sentence array with a colon-free short.
	 */
	private function guard_short_sentence( array $sentence ): array {
		if ( isset( $sentence['short'] ) && is_string( $sentence['short'] ) && false !== strpos( $sentence['short'], ':' ) ) {
			$sentence['short'] = trim( str_replace( ':', '', $sentence['short'] ) );
		}

		return $sentence;
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
