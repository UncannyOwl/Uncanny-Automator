<?php

namespace Uncanny_Automator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared Model Context Protocol settings assets.
 *
 * The subtab classes are registered while the settings page renders, which is
 * far too late for admin_enqueue_scripts. This class is included with the other
 * early settings handlers instead, so one enqueue covers every subtab and a new
 * subtab cannot render unstyled by forgetting to add its own.
 *
 * @since 7.6
 * @package Uncanny_Automator
 */
class Admin_Settings_Model_Context_Protocol_Assets {

	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ), 100 );
	}

	/**
	 * Enqueue the shared stylesheet on every Model Context Protocol subtab.
	 *
	 * @param string $hook_suffix Current WordPress admin hook.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		unset( $hook_suffix );

		if ( ! $this->is_model_context_protocol_page() ) {
			return;
		}

		wp_enqueue_style(
			'automator-mcp-settings',
			plugins_url(
				'src/core/admin/admin-settings/tabs/model-context-protocol-assets/model-context-protocol.css',
				AUTOMATOR_BASE_FILE
			),
			array(),
			Utilities::automator_get_version()
		);

	}

	/**
	 * Determine whether the request is any Model Context Protocol subtab.
	 */
	private function is_model_context_protocol_page(): bool {
		return AUTOMATOR_POST_TYPE_RECIPE === automator_filter_input( 'post_type' )
			&& 'uncanny-automator-config' === automator_filter_input( 'page' )
			&& 'model-context-protocol' === automator_filter_input( 'tab' );
	}
}

new Admin_Settings_Model_Context_Protocol_Assets();
