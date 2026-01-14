<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Block\Registry\Blocks;

use Uncanny_Automator\Api\Components\Block\Enums\Block_Type;
use Uncanny_Automator\Api\Components\Plan\Domain\Plan_Levels;
use Uncanny_Automator\Api\Components\Integration\Enums\Integration_Item_Types;

/**
 * Loop Block
 *
 * Block definition for the Loop block.
 *
 * @package Uncanny_Automator\Api\Components\Block\Registry\Blocks
 * @since 7.0.0
 */
class Loop_Block extends Abstract_Block {

	/**
	 * Setup block definition.
	 *
	 * @return void
	 */
	protected function setup_block(): void {
		// Block details.
		$this->set_block_type( Block_Type::LOOP );
		$this->set_block_name( esc_html_x( 'Loop', 'Recipe Block name', 'uncanny-automator' ) );
		$this->set_icon( 'repeat' );
		$this->set_primary_color( 'blue' );
		$this->set_required_tier( Plan_Levels::PRO_BASIC );
		$this->set_external_url( 'https://automatorplugin.com/introducing-loops/' );

		// Block description.
		$this->set_short_description( esc_html_x( 'Add bulk processing capabilities to your recipes with Loops.', 'Recipe Block Loop', 'uncanny-automator' ) );
		$this->set_description(
			esc_html_x(
				'Add bulk processing capabilities to your recipes with Loops. With the ability to run actions on sets of users, posts, or data, you can automate complex site management tasks efficiently.',
				'Recipe Block Loop',
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
					'entity_code' => Block_Type::LOOP,
					'reason'      => esc_html_x( "It's not possible to add a Loop block inside another Loop block.", 'Recipe Block Loop', 'uncanny-automator' ),
				),
			)
		);

		// Taxonomy tags.
		$this->set_taxonomy_tags(
			array(
				'loop',
				'iterate',
				'repeat',
				'cycle',
				'sequence',
			)
		);

		// Paths.
		$this->set_paths(
			array(
				array(
					'path_code'            => 'LOOP-ITERATION-COMPLETED',
					'name'                 => esc_html_x( 'Iteration completed', 'Recipe Block Loop', 'uncanny-automator' ),
					'direction'            => 'bottom',
					'primary_color'        => 'green',
					'unsupported_entities' => array(),
				),
			)
		);

		// Dependency description.
		$this->set_dependency_description(
			esc_html_x(
				'Add **bulk processing** capabilities to your recipes with Loops. With the ability to **run actions on sets of users, posts, or data**, you can automate complex site management tasks efficiently.
			Here are a few examples of what is possible with Loops:
			  - **User Loops:** Perform bulk actions on **every user that matches specific criteria**, such as sending coupons to everyone who purchased a specific product.
			  - **Post Loops:** Update or export **all blog posts, products, or courses** that belong to a specific category or author.
			  - **Token Loops:** Iterate through data arrays to check **a user\'s past orders, completed courses, or active subscriptions**.
			
			  You can also use Loops to handle \"housekeeping\" and data management, such as:
			  - Updating roles for **users with specific membership levels**.
			  - Generating AI content for **posts missing specific meta values**.
			  - Triggering workflows based on **historical purchase data**.',
				'Recipe Block Loop',
				'uncanny-automator'
			)
		);
	}
}
