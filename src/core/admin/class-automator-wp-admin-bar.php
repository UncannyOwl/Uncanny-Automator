<?php

namespace Uncanny_Automator;

/**
 * Owns the Automator entry in the WordPress admin toolbar.
 *
 * Renders independently of the object-cache setting — cache-related sub-items
 * are appended separately by Automator_Cache_Handler.
 *
 * @package Uncanny_Automator
 */
class Automator_WP_Admin_Bar {

	const PARENT_ID = 'automator-bar';

	/**
	 * Hook the menu registration and the brand-icon inline CSS.
	 */
	public function __construct() {
		add_action( 'admin_bar_menu', array( $this, 'register_admin_bar_menu' ), 999 );
		add_action( 'wp_enqueue_scripts', array( $this, 'add_brand_icon_style' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'add_brand_icon_style' ) );
	}

	/**
	 * Entry point on `admin_bar_menu`.
	 *
	 * Renders the Automator brand nodes when visible, and then unconditionally fires
	 * `automator_admin_bar_register` so subscribers (e.g. the Uncanny Agent quicklink)
	 * remain available even when the brand menu is suppressed via filter or capability.
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 *
	 * @return void
	 */
	public function register_admin_bar_menu( $wp_admin_bar ) {

		if ( ! is_user_logged_in() ) {
			return;
		}

		if ( $this->should_render_brand_menu() ) {
			$this->add_brand_nodes( $wp_admin_bar );
		}

		/**
		 * Fires on every admin-bar render after the Automator brand-menu registration phase,
		 * regardless of whether the brand nodes were actually added.
		 *
		 * Subscribers that add adjacent top-level admin-bar nodes (e.g. the Uncanny Agent
		 * quicklink) should hook here instead of `admin_bar_menu`. Top-level node order is
		 * determined by insertion order, so adding here guarantees the new node sits next to
		 * the Automator menu (when present), regardless of what other plugins do at any
		 * `admin_bar_menu` priority. When the brand menu is hidden, subscribers still render
		 * standalone — letting features like the Uncanny Agent quicklink stay always-available.
		 *
		 * @param \WP_Admin_Bar $wp_admin_bar Admin bar instance.
		 */
		do_action( 'automator_admin_bar_register', $wp_admin_bar );
	}

	/**
	 * Whether the Automator brand menu (parent + sub-items) should render.
	 *
	 * Independent of the Uncanny Agent quicklink, which has its own gates.
	 *
	 * @return bool
	 */
	private function should_render_brand_menu(): bool {

		if ( ! current_user_can( automator_get_capability() ) ) {
			return false;
		}

		if ( true === apply_filters( 'automator_hide_wp_admin_header_menu', false ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Add the Automator parent node and its brand sub-items.
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 *
	 * @return void
	 */
	private function add_brand_nodes( $wp_admin_bar ) {

		$wp_admin_bar->add_node(
			array(
				'id'    => self::PARENT_ID,
				'title' => '<span class="ab-icon" aria-hidden="true"></span><span class="ab-label">' . esc_html__( 'Automator', 'uncanny-automator' ) . '</span>',
				'href'  => admin_url( 'admin.php?page=uncanny-automator-dashboard' ),
				'meta'  => array(
					'class' => 'automator',
					'title' => esc_html__( 'Automator', 'uncanny-automator' ),
				),
			)
		);

		$wp_admin_bar->add_node(
			array(
				'id'     => 'automator-all-recipes',
				'parent' => self::PARENT_ID,
				'title'  => esc_html__( 'Recipes', 'uncanny-automator' ),
				'group'  => false,
				'href'   => admin_url( 'edit.php?post_type=uo-recipe' ),
			)
		);

		$wp_admin_bar->add_node(
			array(
				'id'     => 'automator-add-recipe',
				'parent' => self::PARENT_ID,
				'title'  => esc_html__( 'Add new recipe', 'uncanny-automator' ),
				'group'  => false,
				'href'   => admin_url( 'post-new.php?post_type=uo-recipe' ),
			)
		);

		$wp_admin_bar->add_node(
			array(
				'id'     => 'automator-recipe-logs',
				'parent' => self::PARENT_ID,
				'title'  => esc_html__( 'Logs', 'uncanny-automator' ),
				'group'  => false,
				'href'   => admin_url( 'edit.php?post_type=uo-recipe&page=uncanny-automator-admin-logs' ),
			)
		);

		$wp_admin_bar->add_node(
			array(
				'id'     => 'automator-settings',
				'parent' => self::PARENT_ID,
				'title'  => esc_html__( 'Settings', 'uncanny-automator' ),
				'group'  => false,
				'href'   => admin_url( 'edit.php?post_type=uo-recipe&page=uncanny-automator-config' ),
			)
		);
	}

	/**
	 * Attach the brand-icon CSS to core's `admin-bar` stylesheet on admin + frontend.
	 *
	 * Core enqueues the `admin-bar` style handle whenever the toolbar renders (WP_Admin_Bar::initialize() runs on template_redirect / admin_init, before this hook), so the icon CSS ships inline with it: no extra asset request, gated by the same checks as the brand markup itself.
	 *
	 * @return void
	 */
	public function add_brand_icon_style() {

		if ( ! is_admin_bar_showing() ) {
			return;
		}

		if ( ! $this->should_render_brand_menu() ) {
			return;
		}

		wp_add_inline_style( 'admin-bar', $this->get_brand_icon_css() );
	}

	/**
	 * CSS painting the Automator logo into the brand node's `.ab-icon`.
	 *
	 * The logo is a mask over `currentColor` on the `.ab-icon:before` pseudo-element: core and every admin color scheme set the rest, hover, and focus `color` of `.ab-icon:before`, so the icon recolors exactly like a native dashicon. Sized in `em` because core scales `.ab-icon` typography up on small screens.
	 *
	 * @return string
	 */
	private function get_brand_icon_css(): string {

		$icon_url = 'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><path d="m42.63 28.37c-0.27 2.32-0.27 4.67 0 6.99 0.4 3.44 6.7 3.44 7.27 0 0.37-2.31 0.37-4.65 0-6.96-0.56-3.44-6.9-3.44-7.27-0.03z"/><path d="m14.04 28.41c-0.37 2.31-0.37 4.65 0 6.96 0.57 3.44 6.88 3.44 7.28 0 0.27-2.32 0.27-4.67 0-6.99-0.37-3.42-6.67-3.42-7.28 0.03z"/><path d="m37.07 39.61c-3.13 0.82-6.41 0.82-9.54 0 0.29 5.9 9.25 5.9 9.54 0z"/><path d="m63.5 19.04c-0.34-3.07-2.25-4.75-5.15-5.4-8.67-1.82-17.51-2.69-26.38-2.58-8.84-0.08-17.66 0.78-26.32 2.58-2.96 0.65-4.86 2.33-5.2 5.4-0.39 4-0.53 8.01-0.4 12.02 0.09 4.27 0.61 8.52 1.56 12.69 0.65 2.73 3.6 4.49 6.05 5.52 11.5 4.91 37.1 4.91 48.65 0 2.45-1.04 5.36-2.79 6.05-5.52 0.96-4.16 1.5-8.42 1.6-12.69 0.13-4.01-0.02-8.03-0.46-12.02zm-5.42 23.64c-0.25 0.99-2.75 2.24-3.52 2.53-10.4 4.48-34.86 4.48-45.25 0-0.78-0.32-3.24-1.51-3.52-2.53-1.46-6.05-1.84-15.43-1.01-23.2 0.12-1.19 0.82-1.36 1.73-1.56 5.08-1.18 10.25-1.89 15.46-2.16 0.18-0.01 0.36 0.06 0.5 0.18 0.13 0.12 0.21 0.29 0.24 0.47 0.37 2.86 0.99 5.67 1.84 8.43 0.62 1.23 4.58 1.76 7.42 1.76 2.87 0 6.84-0.53 7.36-1.76 0.85-2.75 1.48-5.57 1.85-8.43 0.02-0.18 0.11-0.35 0.25-0.47 0.13-0.11 0.31-0.18 0.49-0.18 5.2 0.24 10.35 0.96 15.42 2.16 0.94 0.2 1.64 0.37 1.76 1.56 0.82 7.74 0.45 17.11-1.02 23.2z"/></svg>' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		return '#wpadminbar #wp-admin-bar-automator-bar .ab-icon:before {
			content: "";
			display: block;
			width: 1em;
			height: 1em;
			margin: 0 auto;
			top: 2px;
			background-color: currentColor;
			-webkit-mask: url("' . $icon_url . '") center / contain no-repeat;
			mask: url("' . $icon_url . '") center / contain no-repeat;
		}';
	}
}
