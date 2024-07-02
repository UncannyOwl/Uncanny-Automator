<?php
/**
 * Notion loader.
 *
 * @since 5.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

if ( ! class_exists( 'Uncanny_Automator\Integrations\Notion\Notion_Integration' ) ) {
	return;
}


new Uncanny_Automator\Integrations\Notion\Notion_Integration();
