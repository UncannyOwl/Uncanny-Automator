<?php

namespace Uncanny_Automator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Admin_Settings_Model_Context_Protocol
 *
 * @since 7.6
 * @package Uncanny_Automator
 */
class Admin_Settings_Model_Context_Protocol {

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->create_tab();
		$this->load_tabs();
	}

	/**
	 * Load the Model Context Protocol subtab classes.
	 *
	 * @return void
	 */
	private function load_tabs() {
		foreach ( Admin_Settings::get_model_context_protocol_subtabs() as $subtab ) {
			$this->load_tab( $subtab );
		}
	}

	/**
	 * Load a Model Context Protocol subtab class.
	 *
	 * @param string $tab_key The subtab ID.
	 *
	 * @return void
	 */
	private function load_tab( $tab_key ) {
		include_once __DIR__ . DIRECTORY_SEPARATOR . 'model-context-protocol-tabs/' . $tab_key . '.php';
	}

	/**
	 * Register the top-level settings tab.
	 *
	 * @return void
	 */
	private function create_tab() {
		add_filter(
			'automator_settings_sections',
			function ( $tabs ) {
				$tabs['model-context-protocol'] = (object) array(
					'name'     => esc_html__( 'MCP settings', 'uncanny-automator' ),
					'function' => array( $this, 'tab_output' ),
					'preload'  => false,
				);

				return $tabs;
			},
			15,
			1
		);
	}

	/**
	 * Output the Model Context Protocol settings tab.
	 *
	 * @return void
	 */
	public function tab_output() {
		$model_context_protocol_tabs = $this->get_model_context_protocol_tabs();
		$default_tab                 = 'general';
		$requested_tab               = automator_filter_has_var( 'model-context-protocol' )
			? sanitize_text_field( automator_filter_input( 'model-context-protocol' ) )
			: $default_tab;
		$current_tab                 = isset( $model_context_protocol_tabs[ $requested_tab ] ) ? $requested_tab : $default_tab;
		$layout_version              = automator_filter_has_var( 'automator_hide_settings_tabs' ) ? 'focus' : 'default';

		foreach ( $model_context_protocol_tabs as $tab_key => $tab ) {
			if ( isset( $tab->function ) ) {
				add_action( 'automator_settings_model_context_protocol_' . $tab_key . '_tab', $tab->function );
			}

			$tab->is_selected = $tab_key === $current_tab;
		}

		include Utilities::automator_get_view( 'admin-settings/tab/model-context-protocol.php' );
	}

	/**
	 * Return the registered Model Context Protocol subtabs.
	 *
	 * @return array<string,object>
	 */
	public function get_model_context_protocol_tabs() {
		return apply_filters( 'automator_settings_model_context_protocol_tabs', array() );
	}

	/**
	 * Return the URL for a Model Context Protocol subtab.
	 *
	 * @param string $selected_tab Optional. The subtab ID.
	 *
	 * @return string
	 */
	public static function utility_get_model_context_protocol_page_link( $selected_tab = '' ) {
		$url_parameters = array(
			'post_type' => AUTOMATOR_POST_TYPE_RECIPE,
			'page'      => 'uncanny-automator-config',
			'tab'       => 'model-context-protocol',
		);

		if ( ! empty( $selected_tab ) ) {
			$url_parameters['model-context-protocol'] = $selected_tab;
		}

		return add_query_arg(
			$url_parameters,
			admin_url( 'edit.php' )
		);
	}
}

new Admin_Settings_Model_Context_Protocol();
