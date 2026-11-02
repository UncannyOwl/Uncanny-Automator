<?php

namespace Uncanny_Automator;

/**
 * Class Automator_Walkthrough_Host
 *
 * Renders the `<uap-d-walkthrough-host>` element, wrapped in its own `<uap-d-site-auth>` provider, in the admin footer of every screen that loads the Automator app bundle. Mounting on every Automator screen (rather than a single page) is what makes a walkthrough resumable across pages: each screen reports its own surface, and the frontend engine resolves the active walkthrough for that surface on load. The provider configures the fetch client, so the host element carries no credentials of its own.
 *
 * @package Uncanny_Automator
 */
class Automator_Walkthrough_Host {

	/**
	 * Registered handle of the Automator admin app bundle. The host is mounted only where this script is enqueued, so it tracks the bundle exactly without duplicating the screen list.
	 */
	const SCRIPT_HANDLE = 'uap-admin';

	/**
	 * Registers the footer mount.
	 */
	public function __construct() {
		add_action( 'admin_footer', array( $this, 'render' ) );
	}

	/**
	 * Outputs the host on every admin screen that has the app bundle enqueued.
	 *
	 * @return void
	 */
	public function render() {

		if ( ! wp_script_is( self::SCRIPT_HANDLE, 'enqueued' ) ) {
			return;
		}

		$surface = $this->current_surface();

		if ( '' === $surface ) {
			return;
		}

		printf(
			'<uap-d-site-auth site-api-url="%1$s" site-api-nonce="%2$s"><uap-d-walkthrough-host current-surface="%3$s"></uap-d-walkthrough-host></uap-d-site-auth>',
			esc_attr( rest_url() . AUTOMATOR_REST_API_END_POINT ),
			esc_attr( wp_create_nonce( 'wp_rest' ) ),
			esc_attr( $surface )
		);
	}

	/**
	 * Resolves the walkthrough surface for the current admin screen.
	 *
	 * @return string The surface id, or an empty string when the screen cannot be identified or is a recipe that already has a type.
	 */
	private function current_surface() {

		$screen = get_current_screen();

		if ( ! $screen instanceof \WP_Screen || ! isset( $screen->id ) ) {
			return '';
		}

		if ( AUTOMATOR_POST_TYPE_RECIPE === $screen->id ) {
			$recipe = get_post();

			// As in 7.x, the create-recipe tour runs only on a recipe with no type yet: a new one, with the type selector showing.
			return $recipe instanceof \WP_Post && null === automator_get_recipe_type_or_null( (int) $recipe->ID ) ? 'recipe-edit' : '';
		}

		$known = array(
			'edit-uo-recipe' => 'recipes',
		);

		if ( isset( $known[ $screen->id ] ) ) {
			return $known[ $screen->id ];
		}

		// Derive a surface from the menu screen id, dropping the WordPress submenu prefixes, so 'uo-recipe_page_uncanny-automator-dashboard' becomes 'dashboard'.
		$surface = str_replace(
			array( 'uo-recipe_page_uncanny-automator-', 'admin_page_uncanny-automator-' ),
			'',
			$screen->id
		);

		return sanitize_key( $surface );
	}
}
