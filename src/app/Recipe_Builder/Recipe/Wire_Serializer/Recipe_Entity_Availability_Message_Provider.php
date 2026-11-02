<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer;

use Uncanny_Automator\App\Integration_Catalog\Dependency\Dependency;
use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Block_Modifier_Types;
use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Integration_Item_Types;
use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Type;

/**
 * Composes the per-node `availability.message` markdown for an unavailable
 * recipe entity by REUSING the resolved Dependency's authoritative text —
 * `get_description()` (set per scenario by the catalog resolver and per entity
 * via `set_dependency_description()`) and `get_cta()` for the upgrade link. The
 * only recipe-builder-owned copy is the runtime framing: what the runner does
 * with a node of that kind when the code that runs it is missing (see
 * `framing()`). No `scenario_id` string-matching. The description may span
 * paragraphs and lists, so the framing and the link always start a paragraph
 * of their own.
 */
final class Recipe_Entity_Availability_Message_Provider {

	/**
	 * The message for an item its own requirements hid on this site: its own
	 * sentence, whole (it already says the item does not run), or the generic
	 * sentence when it gives none.
	 *
	 * @since 8.0.0
	 *
	 * @param string $sentence    The item's sentence, '' when it gives none.
	 * @param string $entity_type The node kind, for the generic sentence's noun.
	 *
	 * @return string
	 */
	public function hidden( string $sentence, string $entity_type ): string {
		return '' !== trim( $sentence ) ? trim( $sentence ) : $this->message( null, $entity_type );
	}

	/**
	 * Compose the `availability.message` for an unavailable node from the blocking
	 * dependency's own description, the runtime framing, and the upgrade CTA link.
	 *
	 * @since 8.0.0 The framing says what the run does with the node's kind.
	 *
	 * @param Dependency|null $blocking    First unmet dependency, or null if unresolved.
	 * @param string          $entity_type item_type / block_type / block_modifier_type.
	 * @return string Composed markdown message: the description's paragraphs, then the framing and link as the last paragraph.
	 */
	public function message( ?Dependency $blocking, string $entity_type ): string {
		$reason  = null !== $blocking ? trim( $blocking->get_description() ) : '';
		$framing = $this->framing( $entity_type );

		if ( null !== $blocking ) {
			$cta = $blocking->get_cta();
			$url = $cta->get_url();
			if ( null !== $url && '' !== $url && '' !== $cta->get_label() ) {
				$framing .= ' ' . sprintf( '[%1$s](%2$s)', $cta->get_label(), esc_url( $url ) );
			}
		}

		return implode( "\n\n", array_filter( array( $reason, $framing ) ) );
	}

	/**
	 * What the runner does with an unavailable node of this kind, in one sentence.
	 *
	 * The walker has no processor for a block whose type is missing, so none of
	 * its actions run. An action whose code is missing is completed with an
	 * error. Pro's filter processor counts a condition nothing answers as not
	 * met. Pro's loop counts a loop filter whose plugin is not active as
	 * matching nothing, so the loop runs none of its actions. A trigger whose
	 * integration is inactive never fires, and the recipe does not wait for it.
	 *
	 * @since 8.0.0
	 *
	 * @param string $entity_type item_type / block_type / block_modifier_type.
	 * @return string The translated sentence.
	 */
	private function framing( string $entity_type ): string {
		if ( Block_Type::is_valid( $entity_type ) ) {
			return sprintf(
				// translators: %s is the block kind (loop / filter / queue).
				esc_html_x( 'When the recipe runs, this %s runs none of its actions.', 'Recipe node availability', 'uncanny-automator' ),
				$this->noun( $entity_type )
			);
		}

		if ( Integration_Item_Types::ACTION === $entity_type ) {
			return esc_html_x( 'When the recipe runs, this action does not run, and the run log shows an error.', 'Recipe node availability', 'uncanny-automator' );
		}

		if ( Block_Modifier_Types::FILTER_CONDITION === $entity_type ) {
			return esc_html_x( 'When the recipe runs, this condition counts as not met.', 'Recipe node availability', 'uncanny-automator' );
		}

		if ( Block_Modifier_Types::LOOP_FILTER === $entity_type ) {
			return esc_html_x( 'When the recipe runs, this loop filter matches nothing, so the loop runs none of its actions.', 'Recipe node availability', 'uncanny-automator' );
		}

		return sprintf(
			// translators: %s is the node kind (trigger / item).
			esc_html_x( 'This %s is ignored when the recipe runs.', 'Recipe node availability', 'uncanny-automator' ),
			$this->noun( $entity_type )
		);
	}

	/**
	 * Map an entity type to its user-facing node-kind noun. The provider's only lookup.
	 *
	 * @param string $entity_type item_type / block_type / block_modifier_type.
	 * @return string Display noun (trigger / action / loop filter / …), default "item".
	 */
	private function noun( string $entity_type ): string {
		$map = array(
			'trigger'          => esc_html_x( 'trigger', 'Recipe node kind', 'uncanny-automator' ),
			'action'           => esc_html_x( 'action', 'Recipe node kind', 'uncanny-automator' ),
			'loop'             => esc_html_x( 'loop', 'Recipe node kind', 'uncanny-automator' ),
			'filter'           => esc_html_x( 'filter', 'Recipe node kind', 'uncanny-automator' ),
			'delay_schedule'   => esc_html_x( 'queue', 'Recipe node kind', 'uncanny-automator' ),
			'loop_filter'      => esc_html_x( 'loop filter', 'Recipe node kind', 'uncanny-automator' ),
			'filter_condition' => esc_html_x( 'condition', 'Recipe node kind', 'uncanny-automator' ),
			'run_as'           => esc_html_x( 'run-as setting', 'Recipe node kind', 'uncanny-automator' ),
		);
		return $map[ $entity_type ] ?? esc_html_x( 'item', 'Recipe node kind', 'uncanny-automator' );
	}
}
