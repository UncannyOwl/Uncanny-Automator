<?php
/**
 * OpenAI Setup Requirements Trait
 *
 * The one setup step the OpenAI actions built on GPT-4 need: a connected account with GPT-4 access.
 *
 * @package Uncanny_Automator
 * @author  Uncanny Owl
 */

namespace Uncanny_Automator\Integrations\OpenAI;

/**
 * Trait OpenAI_Setup_Requirements
 *
 * @property OpenAI_App_Helpers $helpers
 *
 * @since 8.1.0
 */
trait OpenAI_Setup_Requirements {

	/**
	 * The setup step a GPT-4 action needs: GPT-4 access on the connected account, named after the settings page
	 * where it is rechecked.
	 *
	 * @since 8.1.0
	 *
	 * @return array The steps, each with its answer.
	 */
	protected function setup_requirements(): array {
		return array(
			array(
				'key'         => 'gpt-4-access',
				'description' => sprintf(
					/* translators: %s: The app's name */
					esc_html_x( 'Recheck GPT-4 access on the %s settings page.', 'OpenAI', 'uncanny-automator' ),
					'OpenAI'
				),
				'is_met'      => $this->helpers->has_gpt4_access(),
			),
		);
	}
}
