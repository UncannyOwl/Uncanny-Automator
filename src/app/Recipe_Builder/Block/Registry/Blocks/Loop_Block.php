<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Recipe_Builder\Block\Registry\Blocks;

use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Layout;
use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Type;
use Uncanny_Automator\App\Plan\Domain\Plan_Levels;
use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Integration_Item_Types;
use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Block_Modifier_Types;

/**
 * Loop Block
 *
 * Block definition for the Loop block.
 *
 * @package Uncanny_Automator\App\Recipe_Builder\Block\Registry\Blocks
 * @since 7.0.0
 */
class Loop_Block extends Abstract_Block {

	/**
	 * Option code of the iteration-type field.
	 *
	 * @var string
	 */
	const FIELD_LOOP_TYPE = 'LOOP_TYPE';

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

		// Block sentence. Default references `LOOP_TYPE` (radio readable: `Users` / `Posts` / `Token`). When the user picks the `token`option the dedicated `LOOP_TOKEN` field appears below; the variant swaps the rendered template to that field so the user sees the actual token pill instead of the literal label "Token".
		$this->set_sentence(
			array(
				'default'  => esc_html_x( 'Loop through {{the items:LOOP_TYPE}}', 'Recipe Block Loop sentence', 'uncanny-automator' ),
				'variants' => array(
					array(
						'when'     => array(
							array(
								'field'  => 'LOOP_TYPE',
								'equals' => 'token',
							),
						),
						'template' => esc_html_x( 'Loop through {{a token:LOOP_TOKEN}}', 'Recipe Block Loop sentence', 'uncanny-automator' ),
					),
				),
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
					'reason'      => _x( "It's not possible to add a Loop block inside another Loop block.", 'Recipe Block Loop', 'uncanny-automator' ), // phpcs:ignore Uncanny_Automator.Strings.AutoContextTranslation.MissingContext -- Plain text sent as JSON; escaped where it is rendered.
				),
				array(
					'entity_type' => 'item',
					'entity_code' => 'REDIRECT_TO_URL',
					'reason'      => _x( "Loops run in the background, after the visitor has moved on, so a Redirect inside a loop can't reach anyone.", 'Recipe Block Loop', 'uncanny-automator' ), // phpcs:ignore Uncanny_Automator.Strings.AutoContextTranslation.MissingContext -- Plain text sent as JSON; escaped where it is rendered.
				),
			)
		);

		// Modifiers — Loop accepts `loop_filter` modifiers. The catalog of available rows is derived at REST time from the integrations registry; this block declares which kinds it accepts (with each kind's contextual display name). It declares no default modifiers: a loop gets its type's default filter when it is saved (Loop_CRUD_Service::update_loop()).
		$this->set_modifier_types(
			array(
				array(
					'id'   => Block_Modifier_Types::LOOP_FILTER,
					'name' => esc_html_x( 'Filter', 'Recipe Block Loop modifier name', 'uncanny-automator' ),
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

		// Layout. A Loop is a CONTAINER: everything inside it repeats, so the block draws a box around its one lane and the flow passes through it.
		$this->set_layout( Block_Layout::FRAME );

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

		// Fields.
		$this->set_fields(
			array(
				// Loop type radio field
				array(
					'input_type'             => 'radio',
					'option_code'            => self::FIELD_LOOP_TYPE,
					'label'                  => esc_html_x( 'Loop type', 'Recipe Block Loop', 'uncanny-automator' ),
					'description'            => esc_html_x( 'Select the kind of data this loop will repeat through. Each option defines the items that will be processed one by one inside the loop.', 'Recipe Block Loop', 'uncanny-automator' ),
					'required'               => true,
					'show_label_in_sentence' => false,
					'options'                => array(
						array(
							'value' => 'users',
							'text'  => esc_html_x( 'Users', 'Recipe Block Loop', 'uncanny-automator' ),
						),
						array(
							'value' => 'posts',
							'text'  => esc_html_x( 'Posts', 'Recipe Block Loop', 'uncanny-automator' ),
						),
						array(
							'value' => 'token',
							'text'  => esc_html_x( 'Token', 'Recipe Block Loop', 'uncanny-automator' ),
						),
					),
					'relevant_tokens'        => array(),
				),
				// Token field - only visible when "token" is selected
				array(
					'option_code'            => 'LOOP_TOKEN',
					'label'                  => esc_html_x( 'Token', 'Recipe Block Loop', 'uncanny-automator' ),
					'input_type'             => 'text',
					'supports_tokens'        => true,
					'only_loopable_tokens'   => true,
					'required'               => true,
					'show_label_in_sentence' => false,
					'description'            => esc_html_x( 'Enter the loopable token that contains the data you want to iterate through.', 'Recipe Block Loop', 'uncanny-automator' ),
					'dynamic_visibility'     => array(
						'default_state'    => 'hidden',
						'visibility_rules' => array(
							array(
								'operator'             => 'AND',
								'rule_conditions'      => array(
									array(
										'option_code' => self::FIELD_LOOP_TYPE,
										'compare'     => '==',
										'value'       => 'token',
									),
								),
								'resulting_visibility' => 'show',
							),
						),
					),
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

You can also use Loops to handle "housekeeping" and data management, such as:

- Updating roles for **users with specific membership levels**.
- Generating AI content for **posts missing specific meta values**.
- Triggering workflows based on **historical purchase data**.',
				'Recipe Block Loop',
				'uncanny-automator'
			)
		);
	}
}
