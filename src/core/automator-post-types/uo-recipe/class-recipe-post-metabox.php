<?php

namespace Uncanny_Automator;

/**
 * Class Recipe_Post_Metabox
 *
 * Takes over the uo-recipe edit screen: renders <uap-f-recipe-builder> pinned between the admin bar and the admin menu and strips all WordPress chrome (metaboxes, page heading, Screen Options, Help, admin notices).
 *
 * The class name is fixed by its classmap key in src/class-automator-load.php.
 *
 * @package Uncanny_Automator
 */
class Recipe_Post_Metabox {

	/**
	 * Recipe_Post_Metabox constructor.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'remove_default_metaboxes' ), PHP_INT_MAX, 2 );
		add_action( 'edit_form_after_title', array( $this, 'render_builder' ) );

		add_filter( 'screen_options_show_screen', array( $this, 'maybe_hide_screen_options' ) );
		add_action( 'current_screen', array( $this, 'maybe_remove_help_tabs' ) );
		add_action( 'in_admin_header', array( $this, 'maybe_suppress_notices' ), PHP_INT_MAX );
		add_filter( 'admin_body_class', array( $this, 'maybe_add_body_class' ) );
		add_action( 'admin_head', array( $this, 'maybe_print_screen_css' ) );
	}

	/**
	 * Determine whether the current request is the classic uo-recipe edit screen.
	 *
	 * True only on post.php for a uo-recipe.
	 * post-new.php redirects to post.php via Recipe_Post_Utilities::redirect_to_recipe(), so the WP_Screen base of 'post' covers both entry points.
	 *
	 * @return bool
	 */
	private function is_recipe_edit_screen() {
		$screen = get_current_screen();

		return $screen instanceof \WP_Screen
			&& 'post' === $screen->base
			&& AUTOMATOR_POST_TYPE_RECIPE === $screen->post_type;
	}

	/**
	 * Render the recipe builder into the edit form, below the (hidden) title field.
	 *
	 * Mounts <uap-f-recipe-builder> and its type selector inside <uap-d-site-auth>, passing the REST endpoint and a wp_rest nonce.
	 *
	 * @return void
	 */
	public function render_builder() {
		global $post;

		if ( ! $post instanceof \WP_Post || AUTOMATOR_POST_TYPE_RECIPE !== $post->post_type ) {
			return;
		}

		$recipe_id = absint( $post->ID );

		if ( $recipe_id <= 0 ) {
			return;
		}

		printf(
			'<uap-d-site-auth site-api-url="%1$s" site-api-nonce="%2$s">
				<uap-f-recipe-builder class="uap-wp-admin-pinned-app" recipe-id="%3$d"></uap-f-recipe-builder>
				<uap-f-recipe-builder-edit-node-dialog recipe-id="%3$d"></uap-f-recipe-builder-edit-node-dialog>
				<uap-f-recipe-builder-type-selector recipe-id="%3$d"></uap-f-recipe-builder-type-selector>
			</uap-d-site-auth>',
			esc_attr( rest_url() . AUTOMATOR_REST_API_END_POINT ),
			esc_attr( wp_create_nonce( 'wp_rest' ) ),
			esc_attr( $recipe_id )
		);
	}

	/**
	 * Remove the default WordPress metaboxes from the uo-recipe edit screen.
	 *
	 * The $post parameter is unused but kept to match the add_meta_boxes callback signature.
	 *
	 * @param string   $post_type The current post type.
	 * @param \WP_Post $post      The current post object.
	 *
	 * @return void
	 */
	public function remove_default_metaboxes( $post_type, $post ) {
		if ( AUTOMATOR_POST_TYPE_RECIPE !== $post_type ) {
			return;
		}

		remove_meta_box( 'submitdiv', $post_type, 'side' );
		remove_meta_box( 'authordiv', $post_type, 'normal' );
		remove_meta_box( 'slugdiv', $post_type, 'normal' );
		remove_meta_box( 'recipe_categorydiv', $post_type, 'side' );
		remove_meta_box( 'tagsdiv-recipe_tag', $post_type, 'side' );
	}

	/**
	 * Hide the Screen Options tab on the recipe edit screen.
	 *
	 * @param bool $show Whether to show the Screen Options tab.
	 *
	 * @return bool
	 */
	public function maybe_hide_screen_options( $show ) {
		return $this->is_recipe_edit_screen() ? false : $show;
	}

	/**
	 * Remove the Help tabs on the recipe edit screen.
	 *
	 * @param \WP_Screen $screen The current screen.
	 *
	 * @return void
	 */
	public function maybe_remove_help_tabs( $screen ) {
		if ( $screen instanceof \WP_Screen && 'post' === $screen->base && AUTOMATOR_POST_TYPE_RECIPE === $screen->post_type ) {
			$screen->remove_help_tabs();
		}
	}

	/**
	 * Suppress admin notices on the recipe edit screen.
	 *
	 * @return void
	 */
	public function maybe_suppress_notices() {
		if ( ! $this->is_recipe_edit_screen() ) {
			return;
		}

		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'all_admin_notices' );
		remove_all_actions( 'user_admin_notices' );
		remove_all_actions( 'network_admin_notices' );
	}

	/**
	 * Add the recipe-builder body class on the recipe edit screen.
	 *
	 * The admin menu keeps the user's own preference (the `mfold` user setting) here as on every other admin screen; the pinned builder sizes itself to whichever width the menu has.
	 *
	 * @param string $classes Space-separated list of body classes.
	 *
	 * @return string
	 */
	public function maybe_add_body_class( $classes ) {
		return $this->is_recipe_edit_screen() ? $classes . ' uap-recipe-builder-screen' : $classes;
	}

	/**
	 * Print the scoped CSS that strips the WordPress chrome on the recipe edit screen.
	 *
	 * This is global WordPress-admin chrome CSS (light DOM, outside the component ?inline SCSS pipeline), so it ships as plain CSS in admin_head rather than a component stylesheet. The builder fills the screen through the `uap-wp-admin-pinned-app` class it is rendered with, which pins it between the admin bar and the admin menu.
	 *
	 * @return void
	 */
	public function maybe_print_screen_css() {
		if ( ! $this->is_recipe_edit_screen() ) {
			return;
		}

		echo '<style id="uap-recipe-builder-screen-css">
			body.uap-recipe-builder-screen #wpbody-content > .wrap > h1.wp-heading-inline,
			body.uap-recipe-builder-screen #wpbody-content > .wrap > .page-title-action,
			body.uap-recipe-builder-screen #wpbody-content > .wrap > hr.wp-header-end,
			body.uap-recipe-builder-screen #screen-meta,
			body.uap-recipe-builder-screen #screen-meta-links,
			body.uap-recipe-builder-screen #titlediv,
			body.uap-recipe-builder-screen #postdivrich,
			body.uap-recipe-builder-screen #postbox-container-1,
			body.uap-recipe-builder-screen #postbox-container-2,
			body.uap-recipe-builder-screen #wpfooter { display: none !important; }

			body.uap-recipe-builder-screen #wpbody-content { padding: 0 !important; margin: 0 !important; }
			body.uap-recipe-builder-screen #post-body-content, .edit-form-section { padding: 0 !important; margin: 0 !important; }
			body.uap-recipe-builder-screen #wpcontent { padding-left: 0 !important }
			body.uap-recipe-builder-screen .wrap { padding: 0 !important; margin: 0 !important; }
			body.uap-recipe-builder-screen #poststuff { padding: 0 !important; }
			body.uap-recipe-builder-screen #post-body { margin: 0 !important; }
		</style>';
	}
}
