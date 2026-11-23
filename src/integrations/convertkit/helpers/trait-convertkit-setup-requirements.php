<?php
/**
 * Kit Setup Requirements Trait
 *
 * The one setup step the Kit actions built on the V4 API need: a connection made with a V4 API key.
 *
 * @package Uncanny_Automator
 * @author  Uncanny Owl
 */

namespace Uncanny_Automator\Integrations\ConvertKit;

/**
 * Trait ConvertKit_Setup_Requirements
 *
 * @property ConvertKit_App_Helpers $helpers
 *
 * @since 8.1.0
 */
trait ConvertKit_Setup_Requirements {

	/**
	 * The setup step a V4-only Kit action needs: Kit connected with a V4 API key, not a legacy V3 key, named after
	 * the settings page it is done on.
	 *
	 * @since 8.1.0
	 *
	 * @return array The steps, each with its answer.
	 */
	protected function setup_requirements(): array {
		return array(
			array(
				'key'         => 'v4-api-key',
				'description' => sprintf(
					/* translators: %s: The app's name */
					esc_html_x( 'Reconnect with a V4 API key on the %s settings page.', 'ConvertKit', 'uncanny-automator' ),
					'Kit'
				),
				'is_met'      => ! $this->helpers->is_v3(),
			),
		);
	}
}
