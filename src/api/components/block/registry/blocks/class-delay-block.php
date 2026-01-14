<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Block\Registry\Blocks;

use Uncanny_Automator\Api\Components\Block\Enums\Block_Type;
use Uncanny_Automator\Api\Components\Plan\Domain\Plan_Levels;
use Uncanny_Automator\Api\Components\Integration\Enums\Integration_Item_Types;

/**
 * Delay Block
 *
 * Block definition for the Delay/Schedule block.
 *
 * @package Uncanny_Automator\Api\Components\Block\Registry\Blocks
 * @since 7.0.0
 */
class Delay_Block extends Abstract_Block {

	/**
	 * Setup block definition.
	 *
	 * @return void
	 */
	protected function setup_block(): void {
		// Block details.
		$this->set_block_type( Block_Type::DELAY_SCHEDULE );
		$this->set_block_name( esc_html_x( 'Delay', 'Recipe Block name', 'uncanny-automator' ) );
		$this->set_icon( 'stopwatch' );
		$this->set_primary_color( 'purple' );
		$this->set_required_tier( Plan_Levels::PRO_BASIC );
		$this->set_external_url( 'https://automatorplugin.com/knowledge-base/scheduled-actions/' );

		// Block description.
		$this->set_short_description( esc_html_x( 'Pause actions, scheduling them to run after a set delay.', 'Recipe Block Delay', 'uncanny-automator' ) );
		$this->set_description(
			esc_html_x(
				'Pause the execution of subsequent actions for a specified interval so you can schedule them to run only after that time has passed.',
				'Recipe Block Delay',
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
					'entity_code' => Block_Type::DELAY_SCHEDULE,
					'reason'      => esc_html_x( "It's not possible to add a Delay block inside another Delay block.", 'Recipe Block Delay', 'uncanny-automator' ),
				),
			)
		);

		// Taxonomy tags.
		$this->set_taxonomy_tags(
			array(
				'delay',
				'schedule',
				'defer',
				'queue',
				'wait',
			)
		);

		// Paths.
		$this->set_paths(
			array(
				array(
					'path_code'            => 'DELAY-COMPLETED',
					'name'                 => esc_html_x( 'Completed', 'Recipe Block Delay', 'uncanny-automator' ),
					'direction'            => 'bottom',
					'primary_color'        => 'green',
					'unsupported_entities' => array(),
				),
			)
		);

		// Dependency description.
		$this->set_dependency_description(
			esc_html_x(
				"Add **delayed** or **scheduled actions** to your recipes. With the ability to delay or schedule actions, you can run an action a specific number of **minutes, hours, or days after a user completes the recipe's triggers!**
				Here are a few examples of what is possible with delayed and scheduled actions:
				- When a new user registers, **send them an onboarding drip campaign over a period of days or weeks** to help them get up to speed with minimal support
				- When a user purchases a product, **send them an email a week later to ask them to leave a review**.
				- When a user registers for an event, **send them a reminder before the event**.
				- When a user enrolls in a course or event, **send them supporting materials at regular intervals**  during the course or before or after the event.
				
				You can also use the scheduled recipe feature to run recipes at regular intervals to do things like:
				- Send **email reminders** to users that haven't completed a key activity.
				- **Log data for a report** in Google Sheets, AirTable or Notion.
				- Perform **scheduled maintenance** on a site.",
				'Recipe Block Delay',
				'uncanny-automator'
			)
		);
	}
}
