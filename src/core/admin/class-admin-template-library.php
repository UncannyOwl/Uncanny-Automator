<?php

namespace Uncanny_Automator;

/**
 * Class Admin_Template_Library
 *
 * @since   0.0
 * @version 0.0
 * @package Uncanny_Automator
 */
class Admin_Template_Library {

	/**
	 * Admin hook
	 *
	 * @var string
	 */
	public $admin_hook;

	/**
	 * Library
	 *
	 * @var Recipe_Template_Library
	 */
	public $library = null;

	/**
	 * Page slug
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'uncanny-automator-template-library';

	/**
	 * Class constructor
	 */
	public function __construct() {

		add_action( 'admin_menu', array( $this, 'submenu_page' ) );
		add_action( 'automator_template_library_admin_load', array( $this, 'admin_load' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_endpoint' ) );

	}

	/**
	 * Adds the "Template library" submenu page
	 *
	 * @return void
	 */
	public function submenu_page() {

		// Add submenu
		$this->admin_hook = add_submenu_page(
			'edit.php?post_type=uo-recipe',
			/* translators: 1. Trademarked term */
			sprintf( esc_attr__( '%1$s settings', 'uncanny-automator' ), 'Uncanny Automator' ),
			esc_attr__( 'Recipe templates', 'uncanny-automator' ),
			'manage_options',
			'uncanny-automator-template-library',
			array( $this, 'submenu_page_output' ),
			3
		);

		// Add load hook
		add_action(
			"load-{$this->admin_hook}",
			function() {
				do_action( 'automator_template_library_admin_load', $this );
			}
		);
	}

	/**
	 * Creates the layout of the "Library" page
	 *
	 * @return string - HTML
	 */
	public function submenu_page_output() {
		include Utilities::automator_get_view( 'admin-recipe-template-library.php' );
	}

	/**
	 * Register REST endpoints.
	 *
	 * @return void
	 */
	public function register_rest_endpoint() {
		$this->library()->register_rest_routes();
	}

	/**
	 * Load Library class and enqueue scripts.
	 *
	 * @return void
	 */
	public function admin_load() {

		// Add the page slug to the global assets.
		add_filter(
			'automator_enqueue_global_assets',
			function( $slugs ) {
				$slugs[] = self::PAGE_SLUG;
				return $slugs;
			}
		);

		// Dequeue conflicting Select2 scripts.
		add_filter(
			'automator_conflictive_assets',
			function( $assets ) {
				// Remove the default Select2 scripts and styles.
				foreach ( $assets as $type => $group ) {
					foreach ( $group as $key => $handle ) {
						if ( 'select2' === $handle ) {
							unset( $assets[ $type ][ $key ] );
						}
					}
				}
				return $assets;
			}
		);
		Recipe_Post_Utilities::dequeue_conflictive_assets();

		// Populate the template data.
		add_filter(
			'automator_assets_backend_js_data',
			array( $this, 'localize_template_data' ),
			999,
			2
		);

		$this->library()->load();
	}

	/**
	 * Localize the template data.
	 *
	 * @param array  $data - The data to localize.
	 * @param string $hook - The current hook.
	 *
	 * @return array
	 */
	public function localize_template_data( $data, $hook ) {

		if ( $hook !== $this->admin_hook ) {
			return $data;
		}

		// Add the template library data.
		$data['templateLibrary'] = array(
			'baseURL'            => $this->get_url(),
			'categoryURLsprintf' => $this->get_url( array( 'category' => '%s' ) ),
			'endpoint'           => '/template-library',
			'templates'          => $this->library()->templates,
			'categories'         => $this->library()->categories,
			'integrations'       => $this->library()->integrations,
			'addNewTemplate'     => array(
				'id'  => 'add-new-template',
				'url' => add_query_arg( 'post_type', 'uo-recipe', admin_url( 'post-new.php' ) ),
			),
			// Search keys for fuse.js
			'searchKeys'         => array(
				'title',
				'integrations.name',
			),
			'activeFilter'       => $this->get_active_filter(),
			'itemsPerPage'       => $this->library()->per_page,
			'pluginInstallURL'   => add_query_arg(
				array(
					'type' => 'term',
					'tab'  => 'search',
				),
				admin_url( 'plugin-install.php' )
			),
		);

		// Ensure all integration data is made available for icons.
		$data['components']['icon']['integrations'] = $this->populate_component_integrations( $data['components']['icon']['integrations'] );

		return $data;
	}

	/**
	 * Populate any missing items in the component integrations.
	 *
	 * @param array $integrations - The component integrations.
	 *
	 * @return array
	 */
	private function populate_component_integrations( $integrations ) {
		if ( empty( $integrations ) || empty( $this->library()->integrations ) ) {
			return $integrations;
		}

		foreach ( $this->library()->integrations as $integration ) {
			if ( ! isset( $integrations[ $integration['code'] ] ) ) {
				$integrations[ $integration['code'] ] = array(
					'id'   => $integration['code'],
					'icon' => $integration['icon'],
					'name' => $integration['name'],
				);
			}
		}

		return $integrations;
	}

	/**
	 * Get the active filter
	 *
	 * @return array
	 */
	public function get_active_filter() {
		$filter = array(
			'filter' => 'all',
			'type'   => 'all',
			'page'   => 1,
		);

		// Check for page.
		if ( automator_filter_has_var( 'paged' ) ) {
			$filter['page'] = absint( automator_filter_input( 'paged' ) );
		}

		// Check for search string.
		if ( automator_filter_has_var( 'search' ) ) {
			$filter['filter'] = automator_filter_input( 'search' );
			$filter['type']   = 'search';
			// If no search string, check for category.
		} elseif ( automator_filter_has_var( 'category' ) ) {
			$filter['filter'] = automator_filter_input( 'category' );
			$filter['type']   = 'category';
			// If no search || category, check for integration.
		} elseif ( automator_filter_has_var( 'integration' ) ) {
			$filter['filter'] = automator_filter_input( 'integration' );
			$filter['type']   = 'integration';
		}

		return $filter;
	}

	/**
	 * Get the URL
	 *
	 * @param array $args - Optional arguments for the URL
	 *
	 * @return string
	 */
	public function get_url( $args = array() ) {

		$defaults = array(
			'post_type' => 'uo-recipe',
			'page'      => self::PAGE_SLUG,
		);

		$args = ! empty( $args ) && is_array( $args ) ? wp_parse_args( $args, $defaults ) : $defaults;
		return add_query_arg( $args, admin_url( 'edit.php' ) );
	}

	/**
	 * Get the instance of the library.
	 *
	 * @return Recipe_Template_Library
	 */
	public function library() {
		if ( is_null( $this->library ) ) {
			$this->library = new Recipe_Template_Library();
		}
		return $this->library;
	}

}
