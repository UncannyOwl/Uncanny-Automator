<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer;

use Uncanny_Automator\App\Recipe_Builder\Block\Value_Objects\Fields_Bag;
use Uncanny_Automator\App\Recipe_Builder\Field\Enums\Field_Types;
use Uncanny_Automator\App\Recipe_Builder\Field\Services\Field_Registry_Service;
use Uncanny_Automator\App\Recipe_Builder\Field\Value_Objects\Field_Context;
use Uncanny_Automator\App\Recipe_Builder\Field\Value_Objects\Field_Value;

/**
 * Project a tree's `fields` map to the wire-shape FieldsOutput.
 *
 * When invoked with a Field_Context, consults Field_Registry_Service for
 * canonical input_type + label per field key. Catalog miss (unregistered
 * code, plugin uninstalled, WP_Error from registry, or field key not
 * present in the registered catalog) → falls back to raw-input read,
 * which itself defaults to type='text' and label='' if nothing supplies
 * them. Recipe-level settings envelopes (recipe_throttle, recipe_user_type,
 * etc.) and run_as fields pass null context — their raw input is the
 * source of truth.
 *
 * Empty fields → stdClass so wp_json_encode produces `{}` (FieldsOutput
 * is a Zod record, not an array).
 */
final class Field_Output_Serializer {

	/**
	 * Supplies each field's catalog type and label, which the envelope carries alongside the value.
	 *
	 * @var Field_Registry_Service
	 */
	private Field_Registry_Service $registry;

	/**
	 * Bind the registry this serializer resolves field definitions against.
	 *
	 * Injectable so tests can supply a fixed registry.
	 *
	 * @param Field_Registry_Service|null $registry Field definition source.
	 */
	public function __construct( ?Field_Registry_Service $registry = null ) {
		$this->registry = $registry ?? Field_Registry_Service::instance();
	}

	/**
	 * Project a field map to its wire envelope. Returns an empty object rather than an empty array so the shape stays an object on the wire.
	 *
	 * @param array $fields Raw tree fields map.
	 * @param Field_Context|null $ctx Optional catalog coordinates.
	 * @return array<string,mixed>|\stdClass
	 */
	public function serialize( array $fields, ?Field_Context $ctx = null ) {
		$normalised = ( new Fields_Bag( $fields ) )->to_array();
		if ( empty( $normalised ) ) {
			return new \stdClass();
		}

		$catalog_map = $this->build_catalog_lookup( $ctx );

		$out = array();
		foreach ( $normalised as $key => $entry ) {
			$raw = $fields[ $key ] ?? array();
			if ( $raw instanceof \stdClass ) {
				$raw = (array) $raw;
			}
			$raw_type = is_array( $raw ) && isset( $raw['type'] ) ? (string) $raw['type'] : '';
			$raw_misc = is_array( $raw ) && isset( $raw['miscellaneous'] ) ? $raw['miscellaneous'] : array();
			if ( $raw_misc instanceof \stdClass ) {
				$raw_misc = (array) $raw_misc;
			}
			$raw_misc   = is_array( $raw_misc ) ? $raw_misc : array();
			$raw_backup = is_array( $raw ) && isset( $raw['backup'] ) && is_array( $raw['backup'] ) ? $raw['backup'] : array();
			$raw_label  = '';
			if ( isset( $raw_misc['label'] ) && '' !== (string) $raw_misc['label'] ) {
				$raw_label = (string) $raw_misc['label'];
			} elseif ( isset( $raw_backup['label'] ) ) {
				$raw_label = (string) $raw_backup['label'];
			}

			$catalog_hit = $catalog_map[ $key ] ?? null;

			// A catalog-resolved input_type is trusted as-is (the recipe builder
			// wire relies on it). Only the raw-type FALLBACK is guarded: the FE
			// FieldType enum is strict (no `.catch`), so a stored/legacy type
			// outside the REST-valid set would abort the whole parse. Validate
			// via the enum (normalize handles the int/integer alias) and coerce
			// anything unknown or internal-only (markdown/html) to text.
			$type = $catalog_hit['input_type'] ?? (
				( '' !== $raw_type && Field_Types::is_valid_rest( Field_Types::normalize( $raw_type ) ) )
					? $raw_type
					: Field_Types::TEXT
			);

			// The FE Zod contract requires `readable` on a SELECTED select or
			// radio, but real-world storage legitimately lacks the `_readable`
			// companion (legacy saves, imported dumps, version drift) — and
			// the server cannot even KNOW selectness for ajax-loaded fields
			// (catalog miss → type falls back to text). So the fallback is
			// untyped (see readable_fallback()). Harmless for true text fields
			// (the FE ignores readable there).
			$readable = $entry['readable'];
			if ( '' === (string) $readable ) {
				$readable = $this->readable_fallback( $entry );
			}

			$out[ $key ] = array(
				'type'          => $type,
				'value'         => $entry['value'],
				'readable'      => $readable,
				'custom'        => $entry['custom'],
				'miscellaneous' => array(
					'label' => $catalog_hit['label'] ?? $raw_label,
				),
			);

			// A field whose definition hides its label in the sentence carries the flag on its input, and the FE sentence reads it. Absent means the label shows.
			if ( isset( $raw_misc['show_label_in_sentence'] ) && is_bool( $raw_misc['show_label_in_sentence'] ) ) {
				$out[ $key ]['miscellaneous']['show_label_in_sentence'] = $raw_misc['show_label_in_sentence'];
			}

			// A TinyMCE textarea's saved content IS HTML: stamp the rendering
			// hints the log-sidebar textarea property renderer keys on (the
			// "HTML" chip, the delegated code view, and the View-preview
			// popup, which mounts only for code_language 'html').
			if ( Field_Types::TEXTAREA === $type && ! empty( $catalog_hit['supports_tinymce'] ) ) {
				$out[ $key ]['miscellaneous']['attributes'] = array(
					'supports_html' => true,
					'code_language' => 'html',
				);
			}
		}
		return $out;
	}

	/**
	 * What a field saved without a readable reads as. A select left on the
	 * custom-value placeholder reads as the owner's custom value; any other
	 * non-empty scalar value reads as itself. A placeholder with no custom
	 * value keeps reading as the placeholder, so a selected select never
	 * ships an empty readable.
	 *
	 * @since 8.0.0
	 *
	 * @param array{value: mixed, readable: string, custom: string} $entry Normalised field entry.
	 * @return string The readable, or '' when the value is empty or not a scalar.
	 */
	private function readable_fallback( array $entry ): string {
		if ( ! is_scalar( $entry['value'] ) ) {
			return '';
		}

		if ( ( new Field_Value( $entry['value'] ) )->is_custom_value_sentinel() && '' !== (string) $entry['custom'] ) {
			return (string) $entry['custom'];
		}

		return (string) $entry['value'];
	}

	/**
	 * Like serialize(), but ships ONLY the fields the catalog defines for the
	 * context — input keys with no catalog entry are dropped instead of
	 * riding the raw-input fallback. For value sources that mix configured
	 * fields with system meta (e.g. the per-iteration tokens_snapshot blob,
	 * a flattened action-postmeta dump), the catalog is the filter as well
	 * as the label source. A catalog miss (unregistered code, integration
	 * uninstalled) drops everything; callers treat the empty object as
	 * "omit the fields key".
	 *
	 * @param array         $fields Raw fields map (possibly polluted with system meta).
	 * @param Field_Context $ctx    Catalog coordinates — required; filtering is meaningless without them.
	 * @return array<string,mixed>|\stdClass The FieldsOutput map, or stdClass when nothing survives the filter.
	 */
	public function serialize_defined( array $fields, Field_Context $ctx ) {
		$catalog = $this->build_catalog_lookup( $ctx );
		$defined = array_intersect_key( $fields, $catalog );
		if ( empty( $defined ) ) {
			return new \stdClass();
		}
		return $this->serialize( $defined, $ctx );
	}

	/**
	 * Build a `[option_code => ['input_type', 'label']]` lookup map from the
	 * registry. Null/invalid ctx → empty map → every key falls back.
	 *
	 * Walks both shapes the registry returns:
	 * - get_item_fields_grouped: `{option_code|group_code => [field,…]}`
	 * - get_block_modifier_fields: `[field, field, …]`
	 *
	 * First field wins on `option_code` duplicates (option_code may repeat
	 * inside a single materialization; the wire's FieldsOutput is a
	 * `Record<string, FieldOutput>` so single-entry-per-key is the
	 * downstream contract anyway).
	 *
	 * @return array<string, array{input_type: ?string, label: ?string}>
	 */
	private function build_catalog_lookup( ?Field_Context $ctx ): array {
		if ( null === $ctx ) {
			return array();
		}

		if ( $ctx->is_item() ) {
			$catalog = $this->registry->get_item_fields_grouped(
				$ctx->get_integration_code(),
				$ctx->get_owner_type(),
				$ctx->get_code()
			);
		} elseif ( $ctx->is_block_modifier() ) {
			$catalog = $this->registry->get_block_modifier_fields(
				$ctx->get_integration_code(),
				$ctx->get_owner_type(),
				$ctx->get_code()
			);
		} else {
			return array();
		}

		if ( is_wp_error( $catalog ) || ! is_array( $catalog ) ) {
			return array();
		}

		$map = array();

		// Grouped item shape: `{key => [field, …]}` — each value is an array
		// of fields. Flat modifier shape: `[field, field, …]` — fields at
		// the top level. Detect by inspecting the first row: if its first
		// value is an array containing `option_code`, it's flat.
		$first         = reset( $catalog );
		$is_flat_shape = is_array( $first ) && isset( $first['option_code'] );

		if ( $is_flat_shape ) {
			foreach ( $catalog as $field ) {
				$this->harvest_field_into_map( $field, $map );
			}
			return $map;
		}

		foreach ( $catalog as $group ) {
			if ( ! is_array( $group ) ) {
				continue;
			}
			foreach ( $group as $field ) {
				$this->harvest_field_into_map( $field, $map );
			}
		}

		return $map;
	}

	/**
	 * Fold one raw field into the option-code keyed map.
	 *
	 * Anything without an `option_code` is skipped rather than rejected —
	 * the caller sweeps mixed field shapes and only wants the addressable ones.
	 *
	 * @param mixed                $field Raw field entry; ignored unless it carries an option_code.
	 * @param array<string, mixed> $map   Accumulator, keyed by option code.
	 *
	 * @return void
	 */
	private function harvest_field_into_map( $field, array &$map ): void {
		if ( ! is_array( $field ) || ! isset( $field['option_code'] ) ) {
			return;
		}
		$option_code = (string) $field['option_code'];
		if ( '' === $option_code || isset( $map[ $option_code ] ) ) {
			return;
		}
		$input_type          = $field['input_type'] ?? ( $field['type'] ?? null );
		$label               = $field['label'] ?? null;
		$map[ $option_code ] = array(
			'input_type'       => is_string( $input_type ) && '' !== $input_type ? $input_type : null,
			'label'            => is_string( $label ) ? $label : null,
			'supports_tinymce' => ! empty( $field['supports_tinymce'] ),
		);
	}
}
