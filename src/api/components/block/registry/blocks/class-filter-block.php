<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Block\Registry\Blocks;

use Uncanny_Automator\Api\Components\Block\Enums\Block_Type;
use Uncanny_Automator\Api\Components\Plan\Domain\Plan_Levels;
use Uncanny_Automator\Api\Components\Integration\Enums\Integration_Item_Types;

/**
 * Filter Block
 *
 * Block definition for the Filter/Conditional block.
 *
 * @package Uncanny_Automator\Api\Components\Block\Registry\Blocks
 * @since 7.0.0
 */
class Filter_Block extends Abstract_Block {

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
		$this->set_primary_color( 'pink' );
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

		// Supported scopes.
		$this->set_supported_scopes(
			array(
				Integration_Item_Types::ACTION,
			)
		);

		// Unsupported entities.
		$this->set_unsupported_entities(
			array(
				array(
					'entity_type' => 'block',
					'entity_code' => Block_Type::FILTER,
					'reason'      => esc_html_x( "It's not possible to add a Filter block inside another Filter block.", 'Recipe Block Filter', 'uncanny-automator' ),
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
				'Add  **conditional logic**  to your recipes with filters. With the ability to **run actions only if specific conditions are met**, recipes become much more powerful!
			Here are a few examples of what is possible with filters:
			- In recipes with a form submission trigger, **run different actions depending on the options the user selected on the form**.
			- In recipes with a purchase trigger, **run different actions depending on which product the user purchased**.
			- In recipes with a course or group enrollment trigger, **run different actions depending on which course or group the user enrolled in**.
			
			You can also use filters to simply give recipes additional \"intelligence\" about when actions should run, such as:
			- Whether the user has a **specific role or membership level**.
			- Whether the user is **enrolled in a specific course or group**.
            - Whether the user has **specific user meta values**.',
				'Recipe Block Filter',
				'uncanny-automator'
			)
		);
	}
}
