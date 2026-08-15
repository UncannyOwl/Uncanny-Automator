<?php

namespace Uncanny_Automator;

/**
 * Class Admin_Settings_Uncanny_Page_Builder
 *
 * @since   7.5
 * @version 1.0
 * @package Uncanny_Automator
 */
class Admin_Settings_Uncanny_Page_Builder {

	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_filter( 'automator_settings_sections', array( $this, 'register_tab' ), 21, 1 );

		$this->load_tabs();
	}

	/**
	 * Load the Uncanny Page Builder tab classes.
	 *
	 * @return void
	 */
	private function load_tabs() {
		include_once __DIR__ . DIRECTORY_SEPARATOR . 'uncanny-page-builder-tabs/general.php';
	}

	/**
	 * Register the Uncanny Page Builder settings tab.
	 *
	 * @param array<string,object> $tabs Registered settings tabs.
	 *
	 * @return array<string,object>
	 */
	public function register_tab( $tabs = null ) {
		$tabs = is_array( $tabs ) ? $tabs : array();

		$tabs['uncanny-page-builder'] = (object) array(
			'name'     => esc_html__( 'Uncanny Page Builder', 'uncanny-automator' ),
			'function' => array( $this, 'tab_output' ),
			'preload'  => false,
		);

		return $tabs;
	}

	/**
	 * Output the Uncanny Page Builder settings tab.
	 *
	 * @return void
	 */
	public function tab_output() {
		try {
			$uncanny_page_builder_tabs        = apply_filters( 'automator_settings_uncanny_page_builder_tabs', array() );
			$current_uncanny_page_builder_tab = automator_filter_has_var( 'uncanny-page-builder' )
				? sanitize_text_field( automator_filter_input( 'uncanny-page-builder' ) )
				: 'general';
			$layout_version                   = automator_filter_has_var( 'automator_hide_settings_tabs' ) ? 'focus' : 'default';

			foreach ( $uncanny_page_builder_tabs as $tab_key => $tab ) {
				if ( isset( $tab->function ) && is_callable( $tab->function ) ) {
					$callback = $tab->function;
					add_action(
						'automator_settings_uncanny_page_builder_' . $tab_key . '_tab',
						static function ( ...$ignored ) use ( $callback ): void {
							try {
								unset( $ignored );
								$callback();
							} catch ( \Throwable $throwable ) {
								error_log( sprintf( '[Uncanny Page Builder] Settings subtab callback failed (%s).', get_class( $throwable ) ) );
							}
						}
					);
				}

				$tab->is_selected = $tab_key === $current_uncanny_page_builder_tab;
			}

			include Utilities::automator_get_view( 'admin-settings/tab/uncanny-page-builder.php' );
		} catch ( \Throwable $throwable ) {
			error_log( sprintf( '[Uncanny Page Builder] Settings tab output failed (%s).', get_class( $throwable ) ) );
		}
	}

	/**
	 * Return the link for an Uncanny Page Builder subtab.
	 *
	 * @param string $selected_tab Selected subtab.
	 *
	 * @return string
	 */
	public static function utility_get_uncanny_page_builder_page_link( $selected_tab = '' ) {
		$url_parameters = array(
			'post_type' => AUTOMATOR_POST_TYPE_RECIPE,
			'page'      => 'uncanny-automator-config',
			'tab'       => 'uncanny-page-builder',
		);

		if ( ! empty( $selected_tab ) ) {
			$url_parameters['uncanny-page-builder'] = $selected_tab;
		}

		return add_query_arg(
			$url_parameters,
			admin_url( 'edit.php' )
		);
	}
}

new Admin_Settings_Uncanny_Page_Builder();
