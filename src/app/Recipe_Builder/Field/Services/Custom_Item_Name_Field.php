<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Field\Services;

use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Block_Modifier_Types;
use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Integration_Item_Types;

/**
 * The global "custom label" field every configurable recipe entity carries —
 * an optional author-supplied name the builder renders as the entity's main
 * line instead of the catalog sentence.
 *
 * Owns the definition and the trigger-side token resolution.
 * {@see Field_Registry_Service} appends the definition to the materialized
 * field list for items and block modifiers, which is what puts it in front of
 * the FE and what makes it a declared field for validation.
 *
 * Stateless — exposed as static methods. The token filter is wired from
 * `Application_Bootstrap` (this class registers nothing itself).
 */
final class Custom_Item_Name_Field {

	/**
	 * Postmeta key the label is stored under, on the entity's own post.
	 *
	 * Aliases the tier-1 global so `src/app` can read a typed class constant
	 * while one `define()` stays the single source — the same shape as
	 * `WP_Block_Modifier_Store::POST_TYPE` aliasing
	 * `AUTOMATOR_POST_TYPE_BLOCK_MODIFIER`.
	 *
	 * This is the storage contract: recipes in the wild already hold values
	 * under this key, and `WP_Recipe_Trigger_Store::PRESERVE_CASE_KEYS`
	 * exempts it from the uppercasing every other trigger config key goes
	 * through. It cannot be renamed without a migration.
	 *
	 * @var string
	 */
	public const FIELD_CODE = AUTOMATOR_META_KEY_ITEM_CUSTOM_NAME;

	/**
	 * The field definition for one entity type, in the shape the FE
	 * `ModelFieldSchema` parses and the field walkers consume.
	 *
	 * @param string $item_type An `Integration_Item_Types` or `Block_Modifier_Types` literal.
	 *
	 * @return array<string, mixed> Raw field config.
	 */
	public static function definition( string $item_type ): array {
		return array(
			'option_code'     => self::FIELD_CODE,
			'label'           => self::label( $item_type ),
			'input_type'      => 'text',
			'required'        => false,
			'description'     => esc_html_x( "Optionally, name this step to quickly recognize it in your recipe. It won't change functionality but makes automation easier to manage.", 'Custom name field', 'uncanny-automator' ),
			'supports_tokens' => false,
		);
	}

	/**
	 * Resolve a trigger's custom-label token to the saved label.
	 *
	 * Wired to `automator_maybe_parse_token`. Declaring the field means the
	 * token resolver emits one row per named item, and a chip that resolves to
	 * nothing is worse than no chip — so the trigger form is answered here.
	 *
	 * Only the trigger form `{trigger_id}:{CODE}:{FIELD_CODE}` belongs to this
	 * handler. Action field tokens are `ACTION_FIELD:{id}:{CODE}:{FIELD_CODE}`
	 * and resolve through the action-token parser out of the action log's
	 * recorded metas; they pass through untouched. The legacy handler instead
	 * ran `absint()` on the literal `ACTION_FIELD`, got 0, and returned '' —
	 * clobbering whatever the real resolver had produced.
	 *
	 * The meta read is scoped to the trigger store, so a numeric id belonging
	 * to another post type resolves to the empty string rather than leaking
	 * that post's meta.
	 *
	 * @param mixed $value  Token value resolved so far.
	 * @param mixed $pieces Token pieces, normally `[ item_id, item_code, field_code ]`.
	 *
	 * @return mixed The label, '' for a named-but-unset item, or `$value` untouched.
	 */
	public static function parse_token( $value, $pieces ) {
		if ( ! is_array( $pieces ) || ! in_array( self::FIELD_CODE, $pieces, true ) ) {
			return $value;
		}

		$item_id = $pieces[0] ?? null;
		if ( ! is_numeric( $item_id ) ) {
			return $value;
		}

		return (string) Database::get_recipe_trigger_store()->get_meta( (int) $item_id, self::FIELD_CODE, '' );
	}

	/**
	 * The entity-specific label, naming the thing the author is renaming in
	 * the vocabulary the builder already uses for it.
	 *
	 * An unrecognised type still resolves to a usable label rather than an
	 * empty string: the directory routes append this field unconditionally,
	 * and `ModelFieldSchema` requires a non-empty label, so a blank one would
	 * fail the FE parse for the entity's whole field set.
	 *
	 * @param string $item_type An `Integration_Item_Types` or `Block_Modifier_Types` literal.
	 *
	 * @return string Translated label.
	 */
	private static function label( string $item_type ): string {
		switch ( $item_type ) {
			case Integration_Item_Types::TRIGGER:
				return esc_html_x( 'Custom trigger label', 'Custom name field', 'uncanny-automator' );
			case Integration_Item_Types::ACTION:
				return esc_html_x( 'Custom action label', 'Custom name field', 'uncanny-automator' );
			case Block_Modifier_Types::FILTER_CONDITION:
				return esc_html_x( 'Custom condition label', 'Custom name field', 'uncanny-automator' );
			case Block_Modifier_Types::LOOP_FILTER:
				return esc_html_x( 'Custom filter label', 'Custom name field', 'uncanny-automator' );
		}

		return esc_html_x( 'Custom label', 'Custom name field', 'uncanny-automator' );
	}
}
