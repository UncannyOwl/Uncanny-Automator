<?php

namespace Uncanny_Automator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Admin_Settings_General_Taxonomy
 *
 * Registers a "General" settings sub-tab that embeds the native WordPress
 * term management screen for a recipe taxonomy.
 *
 * @since   8.0
 * @package Uncanny_Automator
 */
class Admin_Settings_General_Taxonomy {

	/**
	 * The sub-tab key used in the "general" URL parameter.
	 *
	 * @var string
	 */
	private $tab_key;

	/**
	 * The taxonomy managed by this sub-tab.
	 *
	 * @var string
	 */
	private $taxonomy;

	/**
	 * The translated sidebar label.
	 *
	 * @var string
	 */
	private $name;

	/**
	 * The sidebar icon code.
	 *
	 * @var string
	 */
	private $icon;

	/**
	 * Class constructor
	 *
	 * @param string $tab_key  The sub-tab key used in the "general" URL parameter.
	 * @param string $taxonomy The taxonomy managed by this sub-tab.
	 * @param string $name     The translated sidebar label.
	 * @param string $icon     The sidebar icon code.
	 */
	public function __construct( $tab_key, $taxonomy, $name, $icon ) {
		$this->tab_key  = $tab_key;
		$this->taxonomy = $taxonomy;
		$this->name     = $name;
		$this->icon     = $icon;

		// Define the tab
		$this->create_tab();
	}

	/**
	 * Adds the tab using the automator_settings_general_tabs filter
	 */
	private function create_tab() {
		// Add the tab using the filter
		add_filter(
			'automator_settings_general_tabs',
			function ( $tabs ) {
				$taxonomy_object = get_taxonomy( $this->taxonomy );

				// The filter runs at settings-page render time, so unauthorized users never get a sidebar item whose panel would render empty.
				if ( ! $taxonomy_object || ! current_user_can( $taxonomy_object->cap->manage_terms ) ) { // phpcs:ignore WordPress.WP.Capabilities.Undetermined -- Capability comes from the taxonomy registration.
					return $tabs;
				}

				$tabs[ $this->tab_key ] = (object) array(
					'name'     => $this->name,
					'function' => array( $this, 'tab_output' ),
					'preload'  => false, // Determines if the content should be loaded even if the tab is not selected
					'icon'     => $this->icon,
				);

				return $tabs;
			},
			10,
			1
		);
	}

	/**
	 * Outputs the content of the sub-tab
	 */
	public function tab_output() {
		$taxonomy_object = get_taxonomy( $this->taxonomy );

		if ( ! $taxonomy_object || ! current_user_can( $taxonomy_object->cap->manage_terms ) ) { // phpcs:ignore WordPress.WP.Capabilities.Undetermined -- Capability comes from the taxonomy registration.
			return;
		}

		$screen_id      = 'edit-' . $this->taxonomy;
		$can_edit_terms = current_user_can( $taxonomy_object->cap->edit_terms ); // phpcs:ignore WordPress.WP.Capabilities.Undetermined -- Capability comes from the taxonomy registration.

		$terms_table = $this->prepare_terms_table( $screen_id );

		$this->enqueue_screen_scripts( $can_edit_terms );

		$message = $this->resolve_result_message();

		// In scope for the view.
		$taxonomy     = $this->taxonomy;
		$tab_key      = $this->tab_key;
		$current_page = $terms_table->get_pagenum();
		$notice_type  = automator_filter_has_var( 'error' ) ? 'error' : 'success';

		include Utilities::automator_get_view( 'admin-settings/tab/general/recipe-taxonomy.php' );
	}

	/**
	 * Builds the prepared WP_Terms_List_Table for the sub-tab screen
	 *
	 * @param string $screen_id The wp-admin screen id for the taxonomy.
	 *
	 * @return \WP_Terms_List_Table
	 */
	private function prepare_terms_table( $screen_id ) {
		// The screen id alone does not carry a post type, and column_posts() fatals on an empty one; prime it before the table derives its screen.
		$screen            = \WP_Screen::get( $screen_id );
		$screen->post_type = AUTOMATOR_POST_TYPE_RECIPE;

		$terms_table = _get_list_table( 'WP_Terms_List_Table', array( 'screen' => $screen ) );

		// The table builds pagination and sorting links from the request URI; strip one-time result params so they do not stick.
		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$_SERVER['REQUEST_URI'] = remove_query_arg( array( 'message', 'error' ), $_SERVER['REQUEST_URI'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Mirrors wp-admin/edit-tags.php; strips known one-time args, escaping happens at output.
		}

		$terms_table->prepare_items();

		// The panel renders mid-page, so a past-the-end page number is clamped in place instead of redirected like wp-admin/edit-tags.php does.
		$total_pages = (int) $terms_table->get_pagination_arg( 'total_pages' );

		if ( $total_pages > 0 && $terms_table->get_pagenum() > $total_pages ) {
			$_REQUEST['paged'] = $total_pages;

			$terms_table->prepare_items();
		}

		return $terms_table;
	}

	/**
	 * Enqueues the core admin scripts that power AJAX add, AJAX delete and quick edit
	 *
	 * Enqueued mid-page on purpose: the sub-tab renders after admin_enqueue_scripts has fired, and WordPress prints these in the admin footer.
	 *
	 * @param bool $can_edit_terms Whether the current user can edit terms.
	 */
	private function enqueue_screen_scripts( $can_edit_terms ) {
		wp_enqueue_script( 'admin-tags' );

		if ( $can_edit_terms ) {
			wp_enqueue_script( 'inline-edit-tax' );
		}
	}

	/**
	 * Resolves the "message" query code set by the edit-tags.php redirect into a display string
	 *
	 * @return string|false The message, or false when the request carries no valid code.
	 */
	private function resolve_result_message() {
		$taxonomy = $this->taxonomy;

		// The core messages file resolves the code against $taxonomy in scope, with a generic fallback for custom taxonomies.
		require ABSPATH . 'wp-admin/includes/edit-tag-messages.php';

		return $message;
	}
}

new Admin_Settings_General_Taxonomy(
	'recipe-categories',
	'recipe_category',
	esc_html__( 'Recipe categories', 'uncanny-automator' ),
	'folder'
);

new Admin_Settings_General_Taxonomy(
	'recipe-tags',
	'recipe_tag',
	esc_html__( 'Recipe tags', 'uncanny-automator' ),
	'tags'
);
