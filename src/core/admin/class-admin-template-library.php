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
			esc_attr__( 'Recipe templates', 'uncanny-automator' ),
			esc_attr__( 'Recipe templates', 'uncanny-automator' ),
			automator_get_capability(), // phpcs:ignore WordPress.WP.Capabilities.Undetermined -- automator_get_capability() returns the capability the Automator menu requires.
			'uncanny-automator-template-library',
			array( $this, 'submenu_page_output' ),
			3
		);

		// Add load hook
		add_action(
			"load-{$this->admin_hook}",
			function () {
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
	 * Load Library class and enqueue scripts.
	 *
	 * @return void
	 */
	public function admin_load() {

		// Strip the WordPress admin chrome around the page. Added here so it only runs on this screen.
		add_action( 'admin_head', array( $this, 'print_screen_css' ) );

		// Add the page slug to the global assets.
		add_filter(
			'automator_enqueue_global_assets',
			function ( $slugs ) {
				$slugs[] = self::PAGE_SLUG;
				return $slugs;
			}
		);
	}

	/**
	 * Print the scoped CSS that strips the WordPress chrome around the template library.
	 *
	 * This is global WordPress-admin chrome CSS (light DOM, outside the component ?inline SCSS pipeline), so it ships as plain CSS in admin_head rather than a component stylesheet. The component owns everything inside `uap-f-recipe-template`; this only resets the surrounding admin frame and paints the page behind the component, mirroring the recipe builder screen. The component fills the screen through the `uap-wp-admin-pinned-app` class it is rendered with, which pins it between the admin bar and the admin menu.
	 *
	 * @return void
	 */
	public function print_screen_css() {
		echo '<style id="uap-template-library-screen-css">
			body.uo-recipe_page_uncanny-automator-template-library #screen-meta,
			body.uo-recipe_page_uncanny-automator-template-library #screen-meta-links,
			body.uo-recipe_page_uncanny-automator-template-library #wpfooter { display: none !important; }

			body.uo-recipe_page_uncanny-automator-template-library #wpcontent { padding-left: 0 !important; }
			body.uo-recipe_page_uncanny-automator-template-library #wpbody-content { padding: 0 !important; }
			body.uo-recipe_page_uncanny-automator-template-library uap-f-recipe-template { background-color: #fff; }
		</style>';
	}
}
