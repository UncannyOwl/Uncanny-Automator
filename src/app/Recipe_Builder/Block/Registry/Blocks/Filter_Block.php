<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Recipe_Builder\Block\Registry\Blocks;

use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Layout;
use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Type;
use Uncanny_Automator\App\Plan\Domain\Plan_Levels;
use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Integration_Item_Types;
use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Block_Modifier_Types;

/**
 * Filter Block
 *
 * Block definition for the Filter/Conditional block. It declares no unsupported entities: a Filter nests inside
 * any block, Filters included. Delay and Loop carry the product's only two nesting limits.
 *
 * @package Uncanny_Automator\App\Recipe_Builder\Block\Registry\Blocks
 * @since 7.0.0
 */
class Filter_Block extends Abstract_Block {

	/**
	 * Option code of the match-mode radio; the runtime reads it by this name (Rule 17).
	 *
	 * @var string
	 */
	public const FIELD_MATCH_MODE = 'CONDITION_MATCH_MODE';

	/**
	 * Continue when any condition is met.
	 *
	 * @var string
	 */
	public const MATCH_MODE_ANY = 'any';

	/**
	 * Continue only when every condition is met.
	 *
	 * @var string
	 */
	public const MATCH_MODE_ALL = 'all';

	/**
	 * Setup block definition.
	 *
	 * @return void
	 */
	protected function setup_block(): void {
		// Block details.
		$this->set_block_type( Block_Type::FILTER );
		$this->set_block_name( esc_html_x( 'Filter', 'Recipe Block name', 'uncanny-automator' ) );
		$this->set_icon( 'code-merge' );
		$this->set_primary_color( 'indigo' );
		$this->set_required_tier( Plan_Levels::PRO_BASIC );
		$this->set_external_url( 'https://automatorplugin.com/knowledge-base/action-filters-conditions/' );

		// Block description.
		$this->set_short_description( esc_html_x( 'Add conditional logic to your recipes with filters.', 'Recipe Block Filter', 'uncanny-automator' ) );
		$this->set_description(
			esc_html_x(
				'Add conditional logic to your recipes with filters. With the ability to run actions only if specific conditions are met, recipes become much more powerful!',
				'Recipe Block Filter',
				'uncanny-automator'
			)
		);

		// Block sentence. The `default` template references the `CONDITION_MATCH_MODE` field; the frontend substitutes its `readable` value (`Any` / `All`) at render time.
		$this->set_sentence(
			array(
				'default' => esc_html_x( 'Continue when {{any:CONDITION_MATCH_MODE}} of the conditions match', 'Recipe Block Filter sentence', 'uncanny-automator' ),
			)
		);

		// Supported scopes.
		$this->set_supported_scopes(
			array(
				Integration_Item_Types::ACTION,
			)
		);

		// Modifiers — Filter accepts `filter_condition` modifiers. The catalog of available rows is derived at REST time from the integrations registry (integration items of the matching types); the block only declares which kinds it accepts (with each kind's contextual display name) and which skeletons to auto-attach on creation.
		$this->set_modifier_types(
			array(
				array(
					'id'   => Block_Modifier_Types::FILTER_CONDITION,
					'name' => esc_html_x( 'Condition', 'Recipe Block Filter modifier name', 'uncanny-automator' ),
				),
			)
		);

		// Taxonomy tags.
		$this->set_taxonomy_tags(
			array(
				'filter',
				'conditional',
				'logic',
				'decision',
				'branching',
			)
		);

		// Fields.
		$this->set_fields(
			array(
				// Condition match mode radio field
				array(
					'input_type'             => 'radio',
					'option_code'            => self::FIELD_MATCH_MODE,
					'label'                  => esc_html_x( 'Condition match mode', 'Recipe Block Filter', 'uncanny-automator' ),
					'description'            => esc_html_x( 'Choose whether all conditions must be met, or if meeting any single condition is sufficient.', 'Recipe Block Filter', 'uncanny-automator' ),
					'required'               => true,
					'default_value'          => self::MATCH_MODE_ANY,
					'show_label_in_sentence' => false,
					'options'                => array(
						array(
							'value' => self::MATCH_MODE_ANY,
							'text'  => esc_html_x( 'Any', 'Recipe Block Filter', 'uncanny-automator' ),
						),
						array(
							'value' => self::MATCH_MODE_ALL,
							'text'  => esc_html_x( 'All', 'Recipe Block Filter', 'uncanny-automator' ),
						),
					),
				),
			)
		);

		// Layout. A Filter BRANCHES: its two paths are alternatives rather than one lane, so a surface with room draws them side by side, splitting above and coming back together below.
		$this->set_layout( Block_Layout::FAN_OUT );

		// Paths.
		$this->set_paths(
			array(
				array(
					'path_code'            => 'FILTER-CONDITIONS-MET',
					'name'                 => esc_html_x( 'Conditions met', 'Recipe Block Filter', 'uncanny-automator' ),
					'direction'            => 'bottom',
					'primary_color'        => 'green',
					'unsupported_entities' => array(),
				),
				array(
					'path_code'            => 'FILTER-CONDITIONS-NOT-MET',
					'name'                 => esc_html_x( 'Conditions not met', 'Recipe Block Filter', 'uncanny-automator' ),
					'direction'            => 'right',
					'primary_color'        => 'red',
					'unsupported_entities' => array(),
				),
			)
		);

		// Dependency description.
		$this->set_dependency_description(
			esc_html_x(
				'Add **conditional logic** to your recipes with filters. With the ability to **run actions only if specific conditions are met**, recipes become much more powerful!

Here are a few examples of what is possible with filters:

- In recipes with a form submission trigger, **run different actions depending on the options the user selected on the form**.
- In recipes with a purchase trigger, **run different actions depending on which product the user purchased**.
- In recipes with a course or group enrollment trigger, **run different actions depending on which course or group the user enrolled in**.

You can also use filters to give recipes additional "intelligence" about when actions should run, such as:

- Whether the user has a **specific role or membership level**.
- Whether the user is **enrolled in a specific course or group**.
- Whether the user has **specific user meta values**.',
				'Recipe Block Filter',
				'uncanny-automator'
			)
		);
	}
}
