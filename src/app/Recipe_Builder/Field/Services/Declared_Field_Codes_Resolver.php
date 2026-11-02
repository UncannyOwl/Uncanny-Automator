<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Field\Services;

/**
 * Resolves an item's declared field codes for write-time invalidation
 * (node-tests spec Stage 3, AMENDED 2026-08-12).
 *
 * The declared set is the allow-list a store's update loop checks written
 * meta keys against before counting a change toward
 * `automator_node_fields_changed`: the item's DECLARED top-level option
 * codes from {@see Field_Registry_Service::get_item_fields()}. Keys outside
 * the set — identity metas, sentence snapshots, `_readable` companions,
 * side-effect caches, run-state storage, third-party postmeta — can never
 * invalidate, by construction rather than by deny-list vigilance.
 *
 * The stores resolve this themselves, lazily, only when a save actually
 * produced candidate edits — no-op saves, first saves and replay imports
 * never pay the definition lookup (`options_callback` execution).
 */
final class Declared_Field_Codes_Resolver {

	/**
	 * Keys the degraded set must exclude: display state the registry never
	 * declares (`Field_Validator` whitelists the first two separately) plus
	 * the identity/bookkeeping metas every update path writes alongside the
	 * field values — none of these may masquerade as a field edit when the
	 * registry cannot answer.
	 *
	 * @var array<string>
	 */
	private const DEGRADED_EXCLUDED_KEYS = array(
		Custom_Item_Name_Field::FIELD_CODE,
		'added_by_llm',
		'integration',
		'code',
		'user_type',
		'status',
		'recipe_id',
		'integration_name',
		'add_action',
		'type',
		'sentence',
		'sentence_human_readable',
		'sentence_human_readable_html',
	);

	/**
	 * Field definition source.
	 *
	 * @var Field_Registry_Service
	 */
	private Field_Registry_Service $registry;

	/**
	 * Build the resolver, defaulting to the production registry.
	 *
	 * @param Field_Registry_Service|null $registry Definition source (test seam).
	 */
	public function __construct( ?Field_Registry_Service $registry = null ) {
		$this->registry = $registry ?? Field_Registry_Service::instance();
	}

	/**
	 * The declared field codes for one item save.
	 *
	 * Declared top-level `option_code`s when the registry resolves the item
	 * (repeater sub-field codes are not meta keys — they live inside the
	 * parent's serialized value and are not descended into). When the
	 * registry returns `WP_Error`, degrades to {@see self::degraded_set()}
	 * — a lost result beats a stale green.
	 *
	 * {@see Custom_Item_Name_Field::FIELD_CODE} is subtracted: the registry
	 * declares it on every item so the builder can render it, but a rename is
	 * display state and must never clear a node-test run.
	 *
	 * @param string $integration_code Integration code (trigger/action codes are globally unique).
	 * @param string $item_type        Integration_Item_Types literal ('trigger' | 'action').
	 * @param string $item_code        Item code.
	 * @param array  $incoming_meta    Meta map being written — the degraded fallback source.
	 * @return array<string> Declared option codes.
	 */
	public function resolve( string $integration_code, string $item_type, string $item_code, array $incoming_meta ): array {
		$fields = $this->registry->get_item_fields( $integration_code, $item_type, $item_code );

		if ( is_wp_error( $fields ) ) {
			return self::degraded_set( $incoming_meta );
		}

		$codes = array();
		foreach ( $fields as $field ) {
			$code = (string) $field['option_code'];
			if ( Custom_Item_Name_Field::FIELD_CODE === $code ) {
				continue;
			}
			$codes[] = $code;
		}

		return array_values( array_unique( $codes ) );
	}

	/**
	 * The degraded declared set: every incoming meta key except
	 * `_readable`-suffixed display companions and the
	 * {@see self::DEGRADED_EXCLUDED_KEYS} non-field metas. The single
	 * derivation site for registry-unresolvable saves.
	 *
	 * @param array $incoming_meta Meta map being written.
	 * @return array<string> Comparable keys.
	 */
	public static function degraded_set( array $incoming_meta ): array {
		$codes = array();

		foreach ( array_keys( $incoming_meta ) as $key ) {
			$key = (string) $key;
			if ( '_readable' === substr( $key, -9 ) ) {
				continue;
			}
			if ( in_array( $key, self::DEGRADED_EXCLUDED_KEYS, true ) ) {
				continue;
			}
			$codes[] = $key;
		}

		return $codes;
	}
}
