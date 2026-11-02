<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Schema\Value_Objects;

use Uncanny_Automator\App\Recipe_Builder\Field\Services\Custom_Item_Name_Field;
use Uncanny_Automator\App\Recipe_Builder\Field\Services\Item_Field_Filter;
use Uncanny_Automator\App\Recipe_Builder\Trigger\Enums\Trigger_Status;
use Uncanny_Automator\App\Recipe_Builder\Trigger\Trigger;
use WP_Post;

/**
 * Tree-entry projection for a trigger item.
 *
 * Provides {@see self::unhydrated()} for triggers that fail strict
 * aggregate hydration (e.g. invariant violation like missing hook name)
 * — the tree surfaces what the trigger stores, so it isn't silently dropped
 * from the recipe's trigger list.
 */
final class Trigger_Tree_Entry extends Item_Tree_Entry {


	/**
	 * Private by design: build through the named constructors below.
	 */
	private function __construct() {}


	/**
	 * Project a trigger aggregate plus its post row into a tree entry.
	 *
	 * @param Trigger $trigger Hydrated trigger aggregate.
	 * @param WP_Post $post    Backing trigger post.
	 *
	 * @return self
	 */
	public static function from_aggregate( Trigger $trigger, WP_Post $post ): self {
		$instance                   = new self();
		$instance->id               = (int) $post->ID;
		$instance->kind             = 'trigger';
		$instance->code             = $trigger->get_trigger_code()->get_value();
		$instance->integration_code = $trigger->get_integration()->get_value();
		$instance->is_item_on       = Trigger_Status::is_item_on( $post->post_status );
		$instance->custom_name      = $trigger->get_custom_name();
		$instance->fields           = $trigger->get_fields();
		$instance->menu_order       = (int) $post->menu_order;
		$instance->presentation     = array(
			'hook'                    => $trigger->get_trigger_hook()->get_name(),
			'sentence'                => $trigger->get_sentence(),
			'sentence_human_readable' => $trigger->get_sentence_human_readable(),
		);

		return $instance;
	}

	/**
	 * Build an entry from what a trigger post stores, for a trigger the aggregate refuses.
	 *
	 * Used when `Recipe_Trigger_Store::hydrate_trigger_from_post()` returns
	 * null (e.g. the `Trigger_Hook` VO rejected an empty hook name). The entry
	 * has a hydrated entry's shape, so the builder can place the trigger, the
	 * violation checks read its real field values and an export carries it whole.
	 *
	 * @since 8.0.0 Carries what the trigger stores instead of blank values.
	 *
	 * @param WP_Post             $post   Backing trigger post.
	 * @param array<string,mixed> $stored `Recipe_Trigger_Store::parse_trigger_meta()` output.
	 *
	 * @return self
	 */
	public static function unhydrated( WP_Post $post, array $stored ): self {
		$custom_name = $stored['configuration'][ Custom_Item_Name_Field::FIELD_CODE ] ?? null;

		$instance                   = new self();
		$instance->id               = (int) $post->ID;
		$instance->kind             = 'trigger';
		$instance->code             = $stored['trigger_code'];
		$instance->integration_code = $stored['integration'];
		$instance->is_item_on       = Trigger_Status::is_item_on( $post->post_status );
		$instance->custom_name      = is_string( $custom_name ) && '' !== $custom_name ? $custom_name : null;
		$instance->fields           = Item_Field_Filter::omit_structural_keys( $stored['configuration'] );
		$instance->menu_order       = (int) $post->menu_order;
		$instance->presentation     = array(
			// `add_action` can store a list of hooks; an entry presents one, as `Trigger_Hook::get_name()` does.
			'hook'                    => (string) ( ( (array) $stored['trigger_hook']['name'] )[0] ?? '' ),
			'sentence'                => $stored['sentence'],
			'sentence_human_readable' => $stored['sentence_human_readable'],
		);

		return $instance;
	}
}
