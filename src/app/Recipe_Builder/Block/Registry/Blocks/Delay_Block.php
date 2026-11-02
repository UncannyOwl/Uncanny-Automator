<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Recipe_Builder\Block\Registry\Blocks;

use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Layout;
use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Type;
use Uncanny_Automator\App\Recipe_Builder\Action\Value_Objects\Delay_Unit;
use Uncanny_Automator\App\Plan\Domain\Plan_Levels;
use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Integration_Item_Types;

/**
 * Delay Block
 *
 * Block definition for the Delay/Schedule block.
 *
 * @package Uncanny_Automator\App\Recipe_Builder\Block\Registry\Blocks
 * @since 7.0.0
 */
class Delay_Block extends Abstract_Block {

	/**
	 * Option code of the mode radio; the runtime reads it by this name (Rule 17).
	 *
	 * @var string
	 */
	public const FIELD_MODE = 'DELAY_MODE';

	/**
	 * Option code of the duration unit; read by this name alongside DELAY_MODE (Rule 17).
	 *
	 * @var string
	 */
	public const FIELD_UNIT = 'DELAY_UNIT';

	/**
	 * Option code of the token-mode expression.
	 *
	 * @var string
	 */
	public const FIELD_TOKEN = 'DELAY_TOKEN';

	/**
	 * Wait a fixed duration from now.
	 *
	 * @var string
	 */
	public const MODE_DELAY = 'delay';

	/**
	 * Wait until a fixed date and time.
	 *
	 * @var string
	 */
	public const MODE_SCHEDULE = 'schedule';

	/**
	 * Wait until a token-resolved expression.
	 *
	 * @var string
	 */
	public const MODE_TOKEN = 'token';

	/**
	 * The amount a Delay waits when the author never saved one, in its unit.
	 * The builder seeds it into the dialog and the tree entry reads it for a
	 * block whose amount was never saved.
	 *
	 * @since 8.0.0
	 *
	 * @var int
	 */
	public const DEFAULT_DELAY_VALUE = 1;

	/**
	 * Setup block definition.
	 *
	 * @return void
	 */
	protected function setup_block(): void {
		// Block details.
		$this->set_block_type( Block_Type::DELAY_SCHEDULE );
		$this->set_block_name( esc_html_x( 'Queue', 'Recipe Block name', 'uncanny-automator' ) );
		$this->set_icon( 'stopwatch' );
		$this->set_primary_color( 'purple' );
		$this->set_required_tier( Plan_Levels::PRO_BASIC );
		$this->set_external_url( 'https://automatorplugin.com/knowledge-base/scheduled-actions/' );

		// Block description.
		$this->set_short_description( esc_html_x( 'Queue actions to run after a wait, while the rest of the recipe carries on.', 'Recipe Block Delay', 'uncanny-automator' ) );
		$this->set_description(
			esc_html_x(
				'The actions inside a Queue run after a wait that counts from when the triggers complete. Actions outside it are not held back: they run right away.',
				'Recipe Block Delay',
				'uncanny-automator'
			)
		);

		// Block sentence. The block exposes three modes via `DELAY_MODE` (`delay` / `schedule` / `token`) each with its own field arrangement. The `default` template is a graceful fallback for when no mode is selected yet; each variant renders the matching field combination as pills so the user reads back exactly what they configured. `delay` is also the radio's default_value, so a freshly created Queue block matches the delay variant immediately.
		$this->set_sentence(
			array(
				'default'  => esc_html_x( 'Run the actions inside later', 'Recipe Block Delay sentence', 'uncanny-automator' ),
				'variants' => array(
					array(
						'when'     => array(
							array(
								'field'  => 'DELAY_MODE',
								'equals' => 'delay',
							),
						),
						'template' => esc_html_x( 'Wait {{0:DELAY_VALUE}} {{minutes:DELAY_UNIT}} after triggers complete', 'Recipe Block Delay sentence', 'uncanny-automator' ),
					),
					array(
						'when'     => array(
							array(
								'field'  => 'DELAY_MODE',
								'equals' => 'schedule',
							),
						),
						'template' => esc_html_x( 'Wait until {{a date:SCHEDULE_DATE}} at {{a time:SCHEDULE_TIME}}', 'Recipe Block Delay sentence', 'uncanny-automator' ),
					),
					array(
						'when'     => array(
							array(
								'field'  => 'DELAY_MODE',
								'equals' => 'token',
							),
						),
						'template' => esc_html_x( 'Wait for {{some time:DELAY_TOKEN}}', 'Recipe Block Delay sentence', 'uncanny-automator' ),
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
					'entity_code' => Block_Type::DELAY_SCHEDULE,
					'reason'      => _x( "It's not possible to add a Queue block inside another Queue block.", 'Recipe Block Delay', 'uncanny-automator' ), // phpcs:ignore Uncanny_Automator.Strings.AutoContextTranslation.MissingContext -- Plain text sent as JSON; escaped where it is rendered.
				),
				array(
					'entity_type' => 'item',
					'entity_code' => 'REDIRECT_TO_URL',
					'reason'      => _x( "Queued steps run later in the background, after the visitor has moved on, so a Redirect inside a Queue can't reach anyone.", 'Recipe Block Delay', 'uncanny-automator' ), // phpcs:ignore Uncanny_Automator.Strings.AutoContextTranslation.MissingContext -- Plain text sent as JSON; escaped where it is rendered.
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

		// Layout. A Delay is DEFERRED: the actions placed in it run later while the steps outside it run right away, so the block is drawn apart from the flow instead of as a box the flow passes through. That it accepts no modifiers says nothing about its shape - only this line does.
		$this->set_layout( Block_Layout::DEFERRED );

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

		// Fields. The block has three modes — `delay`, `schedule`,
		// `token` — each surfacing a different pair of fields via
		// `dynamic_visibility`. The radio's `default_value` matches the
		// `delay` variant so a freshly created block reads naturally.
		$this->set_fields(
			array(
				array(
					'input_type'             => 'radio',
					'option_code'            => self::FIELD_MODE,
					'label'                  => esc_html_x( 'Wait mode', 'Recipe Block Delay', 'uncanny-automator' ),
					'description'            => esc_html_x( 'Choose what the actions inside wait for: a set time after the triggers complete, a date and time, or a token or custom value.', 'Recipe Block Delay', 'uncanny-automator' ),
					'required'               => true,
					'default_value'          => self::MODE_DELAY,
					'show_label_in_sentence' => false,
					'options'                => array(
						array(
							'value' => self::MODE_DELAY,
							'text'  => esc_html_x( 'Set time', 'Recipe Block Delay', 'uncanny-automator' ),
						),
						array(
							'value' => self::MODE_SCHEDULE,
							'text'  => esc_html_x( 'Schedule', 'Recipe Block Delay', 'uncanny-automator' ),
						),
						array(
							'value' => self::MODE_TOKEN,
							'text'  => esc_html_x( 'Use a token / custom value', 'Recipe Block Delay', 'uncanny-automator' ),
						),
					),
				),

				// Delay mode — number + unit.
				array(
					'option_code'            => 'DELAY_VALUE',
					'label'                  => esc_html_x( 'Amount', 'Recipe Block Delay', 'uncanny-automator' ),
					'input_type'             => 'int',
					'required'               => true,
					'default_value'          => self::DEFAULT_DELAY_VALUE,
					'show_label_in_sentence' => false,
					'description'            => esc_html_x( 'How long the actions inside wait after the triggers complete.', 'Recipe Block Delay', 'uncanny-automator' ),
					'dynamic_visibility'     => array(
						'default_state'    => 'hidden',
						'visibility_rules' => array(
							array(
								'operator'             => 'AND',
								'rule_conditions'      => array(
									array(
										'option_code' => self::FIELD_MODE,
										'compare'     => '==',
										'value'       => self::MODE_DELAY,
									),
								),
								'resulting_visibility' => 'show',
							),
						),
					),
				),
				array(
					'option_code'            => 'DELAY_UNIT',
					'label'                  => esc_html_x( 'Unit', 'Recipe Block Delay', 'uncanny-automator' ),
					'input_type'             => 'select',
					'required'               => true,
					'default_value'          => 'minutes',
					'show_label_in_sentence' => false,
					'description'            => esc_html_x( 'The time unit applied to the amount above.', 'Recipe Block Delay', 'uncanny-automator' ),
					'options'                => $this->delay_unit_options(),
					'dynamic_visibility'     => array(
						'default_state'    => 'hidden',
						'visibility_rules' => array(
							array(
								'operator'             => 'AND',
								'rule_conditions'      => array(
									array(
										'option_code' => self::FIELD_MODE,
										'compare'     => '==',
										'value'       => self::MODE_DELAY,
									),
								),
								'resulting_visibility' => 'show',
							),
						),
					),
				),

				// Schedule mode: date + time. Descriptions embed the live site timezone so users don't have to guess.
				array(
					'option_code'            => 'SCHEDULE_DATE',
					'label'                  => esc_html_x( 'Date', 'Recipe Block Delay', 'uncanny-automator' ),
					'input_type'             => 'date',
					'required'               => true,
					'show_label_in_sentence' => false,
					'description'            => sprintf(
						/* translators: %s: site timezone string (e.g. "America/New_York" or "+00:00") */
						esc_html_x( 'The date the actions inside run on, in your site\'s timezone (**%s**).', 'Recipe Block Delay', 'uncanny-automator' ),
						wp_timezone_string()
					),
					'dynamic_visibility'     => array(
						'default_state'    => 'hidden',
						'visibility_rules' => array(
							array(
								'operator'             => 'AND',
								'rule_conditions'      => array(
									array(
										'option_code' => self::FIELD_MODE,
										'compare'     => '==',
										'value'       => self::MODE_SCHEDULE,
									),
								),
								'resulting_visibility' => 'show',
							),
						),
					),
				),
				array(
					'option_code'            => 'SCHEDULE_TIME',
					'label'                  => esc_html_x( 'Time', 'Recipe Block Delay', 'uncanny-automator' ),
					'input_type'             => 'time',
					'required'               => true,
					'show_label_in_sentence' => false,
					'description'            => sprintf(
						/* translators: %s: site timezone string (e.g. "America/New_York" or "+00:00") */
						esc_html_x( 'The time of day, in your site\'s timezone (**%s**).', 'Recipe Block Delay', 'uncanny-automator' ),
						wp_timezone_string()
					),
					'dynamic_visibility'     => array(
						'default_state'    => 'hidden',
						'visibility_rules' => array(
							array(
								'operator'             => 'AND',
								'rule_conditions'      => array(
									array(
										'option_code' => self::FIELD_MODE,
										'compare'     => '==',
										'value'       => self::MODE_SCHEDULE,
									),
								),
								'resulting_visibility' => 'show',
							),
						),
					),
				),

				// Token mode: single text field with token selector.
				// Description authored as markdown.
				array(
					'option_code'            => self::FIELD_TOKEN,
					'label'                  => esc_html_x( 'Token or custom value', 'Recipe Block Delay', 'uncanny-automator' ),
					'input_type'             => 'text',
					'supports_tokens'        => true,
					'required'               => true,
					'show_label_in_sentence' => false,
					'description'            => sprintf(
						/* translators: %s: URL of the knowledge base article on custom scheduling. */
						esc_html_x( 'Use **tokens** and/or text to enter any supported format. Examples: "Friday at 1pm", "November 9", "2 hours", Unix timestamps and [more](%s).', 'Recipe Block Delay', 'uncanny-automator' ),
						'https://automatorplugin.com/knowledge-base/how-to-use-custom-scheduling-in-wordpress-automations/?utm_source=uncanny-automator&utm_medium=in-plugin&utm_content=queue-block-token-more'
					),
					'dynamic_visibility'     => array(
						'default_state'    => 'hidden',
						'visibility_rules' => array(
							array(
								'operator'             => 'AND',
								'rule_conditions'      => array(
									array(
										'option_code' => self::FIELD_MODE,
										'compare'     => '==',
										'value'       => self::MODE_TOKEN,
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
				"Add **delayed** or **scheduled actions** to your recipes. With the ability to delay or schedule actions, you can run an action a specific number of **minutes, hours, or days after a user completes the recipe's triggers**.

Here are a few examples of what is possible with delayed and scheduled actions:

- When a new user registers, **send them an onboarding drip campaign over a period of days or weeks** to help them get up to speed with minimal support.
- When a user purchases a product, **send them an email a week later to ask them to leave a review**.
- When a user registers for an event, **send them a reminder before the event**.
- When a user enrolls in a course or event, **send them supporting materials at regular intervals** during the course or before or after the event.

You can also use the scheduled recipe feature to run recipes at regular intervals to do things like:

- Send **email reminders** to users that haven't completed a key activity.
- **Log data for a report** in Google Sheets, Airtable or Notion.
- Perform **scheduled maintenance** on a site.",
				'Recipe Block Delay',
				'uncanny-automator'
			)
		);
	}

	/**
	 * The DELAY_UNIT select's options, built from the one list that decides
	 * which units a picker offers.
	 *
	 * The set is read from {@see Delay_Unit::offered()} rather than written
	 * out here, because a picker offering a unit the value object rejects is
	 * exactly the drift this arrangement exists to prevent: `Delay_Unit`
	 * throws, `Action_Meta` catches, and the delay disappears without a word.
	 * Labels stay local — they are translated strings, not vocabulary.
	 *
	 * @return array<int,array<string,string>>
	 */
	private function delay_unit_options(): array {
		$labels = array(
			'seconds' => esc_html_x( 'Seconds', 'Recipe Block Delay', 'uncanny-automator' ),
			'minutes' => esc_html_x( 'Minutes', 'Recipe Block Delay', 'uncanny-automator' ),
			'hours'   => esc_html_x( 'Hours', 'Recipe Block Delay', 'uncanny-automator' ),
			'days'    => esc_html_x( 'Days', 'Recipe Block Delay', 'uncanny-automator' ),
			'weeks'   => esc_html_x( 'Weeks', 'Recipe Block Delay', 'uncanny-automator' ),
			'months'  => esc_html_x( 'Months', 'Recipe Block Delay', 'uncanny-automator' ),
			'years'   => esc_html_x( 'Years', 'Recipe Block Delay', 'uncanny-automator' ),
		);

		$options = array();
		foreach ( Delay_Unit::offered() as $unit ) {
			$options[] = array(
				'value' => $unit,
				'text'  => $labels[ $unit ] ?? ucfirst( $unit ),
			);
		}

		return $options;
	}
}
