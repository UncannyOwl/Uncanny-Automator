<?php

namespace Uncanny_Automator;

use Uncanny_Automator\App\Application\Page_Builder\Can_Load_Page_Builder;
use Uncanny_Automator\App\Infrastructure\Page_Builder\Page_Builder_Availability;

/**
 * Class Admin_Settings_Uncanny_Page_Builder
 *
 * @since   7.5
 * @version 1.0
 * @package Uncanny_Automator
 */
class Admin_Settings_Uncanny_Page_Builder {

	/**
	 * Page Builder availability use case.
	 *
	 * @var Can_Load_Page_Builder
	 */
	private $can_load_page_builder;

	/**
	 * Class constructor.
	 *
	 * @param Can_Load_Page_Builder $can_load_page_builder Availability use case.
	 */
	public function __construct( Can_Load_Page_Builder $can_load_page_builder ) {
		$this->can_load_page_builder = $can_load_page_builder;

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
	public function register_tab( $tabs ) {
		if ( ! $this->can_load_page_builder->execute() ) {
			return $tabs;
		}

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
		$uncanny_page_builder_tabs        = apply_filters( 'automator_settings_uncanny_page_builder_tabs', array() );
		$current_uncanny_page_builder_tab = automator_filter_has_var( 'uncanny-page-builder' )
			? sanitize_text_field( automator_filter_input( 'uncanny-page-builder' ) )
			: 'general';
		$layout_version                   = automator_filter_has_var( 'automator_hide_settings_tabs' ) ? 'focus' : 'default';

		foreach ( $uncanny_page_builder_tabs as $tab_key => $tab ) {
			if ( isset( $tab->function ) ) {
				add_action( 'automator_settings_uncanny_page_builder_' . $tab_key . '_tab', $tab->function );
			}

			$tab->is_selected = $tab_key === $current_uncanny_page_builder_tab;
		}

		include Utilities::automator_get_view( 'admin-settings/tab/uncanny-page-builder.php' );
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

new Admin_Settings_Uncanny_Page_Builder( new Can_Load_Page_Builder( new Page_Builder_Availability() ) );
