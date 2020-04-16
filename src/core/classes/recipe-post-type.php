<?php

namespace Uncanny_Automator;

/**
 * Class Recipe_Post_Type
 * @package uncanny_automator
 */
class Recipe_Post_Type {

	/**
	 * RecipePostType constructor.
	 */
	public function __construct() {

		// Create and register custom post type
		add_action( 'init', array( $this, 'automator_post_type' ), 0 );

		// Add the custom columns to the uo-recipe
		add_filter( 'manage_uo-recipe_posts_columns', array( $this, 'set_custom_columns' ) );

		// Add the data to the custom columns for uo-recipe
		add_action( 'manage_uo-recipe_posts_custom_column', array( $this, 'custom_column' ), 10, 2 );

		// Add to parent menu
		//add_action( 'admin_menu', array( $this, 'admin_menu' ) );

		// Adding entry point for JS based triggers and actions UI into Meta Boxes
		add_action( 'add_meta_boxes', array( $this, 'recipe_add_meta_box_ui' ), 11 );

		// Register API class
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );

		// Add admin post creation scripts
		add_action( 'admin_enqueue_scripts', array( $this, 'scripts' ) );

		// Change to before delete post
		add_action( 'delete_post', array( $this, 'delete_triggers_actions' ), 10, 1 );
		// Draft when recipe moved to trash
		add_action( 'wp_trash_post', array( $this, 'draft_triggers_actions' ), 10, 1 );

		// Change Default new recipe post from auto-draft to draft
		add_action( 'wp_insert_post', array( $this, 'change_default_post_status' ), 10, 3 );
		add_filter( 'default_title', array( $this, 'default_recipe_title' ), 20, 2 );
		add_filter( 'replace_editor', array( $this, 'redirect_to_recipe' ), 20, 2 );

		add_action( 'admin_menu', array( $this, 'remove_publish_box' ) );
		add_filter( 'admin_title', array( $this, 'modify_report_titles' ), 40, 2 );

		// send email notice and draft recipe with multiple triggers if pro in not active
		// Removing for multiple trigger support in lite
		//add_action( 'current_screen', array( $this, 'maybe_draft_live_recipe' ) );
		//add_action( 'uap_before_trigger_completed', array( $this, 'uap_before_trigger_completed' ), 10, 4 );
	}

	/**
	 * @param $admin_title
	 * @param $title
	 *
	 * @return string
	 */
	public function modify_report_titles( $admin_title, $title ) {

		if ( isset( $_GET['tab'] ) ) {
			switch ( sanitize_text_field( $_GET['tab'] ) ) {
				case 'recipe-log':
					$admin_title = sprintf( '%s &mdash; %s', __( 'Recipe Report', 'uncanny-automator' ), $admin_title );
					break;
				case 'trigger-log':
					$admin_title = sprintf( '%s &mdash; %s', __( 'Trigger Report', 'uncanny-automator' ), $admin_title );
					break;
				case 'action-log':
					$admin_title = sprintf( '%s &mdash; %s', __( 'Action Report', 'uncanny-automator' ), $admin_title );
					break;
			}
		}

		return $admin_title;
	}

	/**
	 * @param $post_title
	 * @param $post
	 *
	 * @return string
	 */
	public function default_recipe_title( $post_title, $post ) {

		if ( 'uo-recipe' === (string) $post->post_type ) {
			return __( 'New Recipe', 'uncanny-automator' );
		}

		return $post_title;
	}

	/**
	 * @param $value
	 * @param $post
	 *
	 * @return mixed
	 */
	public function redirect_to_recipe( $value, $post ) {

		global $current_screen;

		if ( $current_screen && 'add' === $current_screen->action && 'uo-recipe' === $current_screen->post_type ) {
			wp_redirect( admin_url( 'post.php?post=' . $post->ID . '&action=edit' ) );
			die();
		}

		return $value;
	}

	/**
	 * Remove the WP standard Post publish metabox
	 */
	public function remove_publish_box() {
		remove_meta_box( 'submitdiv', 'uo-recipe', 'side' );
	}

	/**
	 * @param $post_ID
	 * @param $post
	 * @param $update
	 */
	public function change_default_post_status( $post_ID, $post, $update ) {

		if ( 'uo-recipe' === (string) $post->post_type && 'auto-draft' === (string) $post->post_status ) {

			// Update post
			$args = array(
				'ID'          => $post_ID,
				'post_status' => 'draft',
				'post_title'  => '',
			);

			// Update the post into the database
			wp_update_post( $args );

			//Save "user" recipe type as the default IF pro is not active
			if ( ! defined( 'AUTOMATOR_PRO_FILE' ) ) {
				update_post_meta( $post_ID, 'uap_recipe_type', 'user' );
			}

			// Save automator version for future use in case
			// something has to be changed for older recipes
			update_post_meta( $post_ID, 'uap_recipe_version', Utilities::get_version() );
		}
	}

	/**
	 *
	 */
	public function automator_post_type() {

		if ( ! post_type_exists( 'uo-recipe' ) ) {
			$icon_url = 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz48c3ZnIGVuYWJsZS1iYWNrZ3JvdW5kPSJuZXcgMCAwIDU4MSA2NDAiIHZlcnNpb249IjEuMSIgdmlld0JveD0iMCAwIDU4MSA2NDAiIHhtbDpzcGFjZT0icHJlc2VydmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHBhdGggZD0ibTUyNi40IDM0LjFjMC42IDUgMSAxMC4xIDEuMyAxNS4xIDAuNSAxMC4zIDEuMiAyMC42IDAuOCAzMC45LTAuNSAxMS41LTEgMjMtMi4xIDM0LjQtMi42IDI2LjctNy44IDUzLjMtMTYuNSA3OC43LTcuMyAyMS4zLTE3LjEgNDEuOC0yOS45IDYwLjQtMTIgMTcuNS0yNi44IDMzLTQzLjggNDUuOS0xNy4yIDEzLTM2LjcgMjMtNTcuMSAyOS45LTI1LjEgOC41LTUxLjUgMTIuNy03Ny45IDEzLjggNzAuMyAyNS4zIDEwNi45IDEwMi44IDgxLjYgMTczLjEtMTguOSA1Mi42LTY4LjEgODguMS0xMjQgODkuNWgtNi4xYy0xMS4xLTAuMi0yMi4xLTEuOC0zMi45LTQuNy0yOS40LTcuOS01NS45LTI2LjMtNzMuNy01MC45LTI5LjItNDAuMi0zNC4xLTkzLjEtMTIuNi0xMzgtMjUgMjUuMS00NC41IDU1LjMtNTkuMSA4Ny40LTguOCAxOS43LTE2LjEgNDAuMS0yMC44IDYxLjEtMS4yLTE0LjMtMS4yLTI4LjYtMC42LTQyLjkgMS4zLTI2LjYgNS4xLTUzLjIgMTIuMi03OC45IDUuOC0yMS4yIDEzLjktNDEuOCAyNC43LTYwLjlzMjQuNC0zNi42IDQwLjYtNTEuM2MxNy4zLTE1LjcgMzcuMy0yOC4xIDU5LjEtMzYuOCAyNC41LTkuOSA1MC42LTE1LjIgNzYuOC0xNy4yIDEzLjMtMS4xIDI2LjctMC44IDQwLjEtMi4zIDI0LjUtMi40IDQ4LjgtOC40IDcxLjMtMTguMyAyMS05LjIgNDAuNC0yMS44IDU3LjUtMzcuMiAxNi41LTE0LjkgMzAuOC0zMi4xIDQyLjgtNTAuOCAxMy0yMC4yIDIzLjQtNDIuMSAzMS42LTY0LjcgNy42LTIxLjEgMTMuNC00Mi45IDE2LjctNjUuM3ptLTI3OS40IDMyOS41Yy0xOC42IDEuOC0zNi4yIDguOC01MC45IDIwLjQtMTcuMSAxMy40LTI5LjggMzIuMi0zNi4yIDUyLjktNy40IDIzLjktNi44IDQ5LjUgMS43IDczIDcuMSAxOS42IDE5LjkgMzcuMiAzNi44IDQ5LjYgMTQuMSAxMC41IDMwLjkgMTYuOSA0OC40IDE4LjZzMzUuMi0xLjYgNTEtOS40YzEzLjUtNi43IDI1LjQtMTYuMyAzNC44LTI4LjEgMTAuNi0xMy40IDE3LjktMjkgMjEuNS00NS43IDQuOC0yMi40IDIuOC00NS43LTUuOC02Ni45LTguMS0yMC0yMi4yLTM3LjYtNDAuMy00OS4zLTE4LTExLjctMzkuNS0xNy02MS0xNS4xeiIgZmlsbD0iIzgyODc4QyIvPjxwYXRoIGQ9Im0yNDIuNiA0MDIuNmM2LjItMS4zIDEyLjYtMS44IDE4LjktMS41LTExLjQgMTEuNC0xMi4yIDI5LjctMS44IDQyIDExLjIgMTMuMyAzMS4xIDE1LjEgNDQuNCAzLjkgNS4zLTQuNCA4LjktMTAuNCAxMC41LTE3LjEgMTIuNCAxNi44IDE2LjYgMzkuNCAxMSA1OS41LTUgMTguNS0xOCAzNC42LTM1IDQzLjUtMzQuNSAxOC4yLTc3LjMgNS4xLTk1LjUtMjkuNS0xLTItMi00LTIuOS02LjEtOC4xLTE5LjYtNi41LTQzIDQuMi02MS4zIDEwLTE3IDI2LjgtMjkuMiA0Ni4yLTMzLjR6IiBmaWxsPSIjODI4NzhDIi8+PC9zdmc+';
			$labels = array(
				'name'                  => __( 'Recipes', 'uncanny-automator' ),
				'singular_name'         => __( 'Recipe', 'uncanny-automator' ),
				'menu_name'             => __( 'Automator', 'uncanny-automator' ),
				'name_admin_bar'        => __( 'Automator Recipe', 'uncanny-automator' ),
				'archives'              => __( 'Recipe Archives', 'uncanny-automator' ),
				'attributes'            => __( 'Recipe Attributes', 'uncanny-automator' ),
				'parent_item_colon'     => __( 'Parent Recipe:', 'uncanny-automator' ),
				'all_items'             => __( 'All Recipes', 'uncanny-automator' ),
				'add_new_item'          => __( 'Add New Recipe', 'uncanny-automator' ),
				'add_new'               => _x( 'Add New', 'Recipe', 'uncanny-automator' ),
				'new_item'              => __( 'New Recipe', 'uncanny-automator' ),
				'edit_item'             => __( 'Edit Recipe', 'uncanny-automator' ),
				'update_item'           => __( 'Update Recipe', 'uncanny-automator' ),
				'view_item'             => __( 'View Recipe', 'uncanny-automator' ),
				'view_items'            => __( 'View Recipes', 'uncanny-automator' ),
				'search_items'          => __( 'Search Recipes', 'uncanny-automator' ),
				'not_found'             => _x( 'Not Found', 'Recipe', 'uncanny-automator' ),
				'not_found_in_trash'    => _x( 'Not Found in Trash', 'Recipe', 'uncanny-automator' ),
				'featured_image'        => __( 'Featured Image', 'uncanny-automator' ),
				'set_featured_image'    => __( 'Set Featured Image', 'uncanny-automator' ),
				'remove_featured_image' => __( 'Remove Featured Image', 'uncanny-automator' ),
				'use_featured_image'    => __( 'Use as Featured Image', 'uncanny-automator' ),
				'insert_into_item'      => __( 'Insert Into the Recipe', 'uncanny-automator' ),
				'uploaded_to_this_item' => __( 'Uploaded to This Recipe', 'uncanny-automator' ),
				'items_list'            => __( 'Recipes List', 'uncanny-automator' ),
				'items_list_navigation' => __( 'Recipes List Navigation', 'uncanny-automator' ),
				'filter_items_list'     => __( 'Filter Recipes List', 'uncanny-automator' ),
			);
			$args   = array(
				'label'               => __( 'Recipe', 'uncanny-automator' ),
				'description'         => __( 'Uncanny WordPress Automation', 'uncanny-automator' ),
				'labels'              => $labels,
				'supports'            => array( 'title', 'author' ),
				'hierarchical'        => false,
				'public'              => false,
				'show_ui'             => true,
				//'show_in_menu'        => false,
				'menu_position'       => 40,
				'menu_icon'           => $icon_url,
				'show_in_admin_bar'   => true,
				'show_in_nav_menus'   => true,
				'can_export'          => true,
				'taxonomies'          => array( 'recipe_category', 'recipe_tag' ),
				'has_archive'         => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => true,
				'capability_type'     => 'post',
				'show_in_rest'        => true,
				'rest_base'           => 'uap',
			);

			register_post_type( 'uo-recipe', $args );
		}
	}

	/**
	 * Add data to custom columns in the recipe list
	 *
	 * @param $column
	 * @param $post_id
	 */
	public function custom_column( $column, $post_id ) {

		global $wpdb;

		switch ( $column ) {
			case 'triggers':
				$q = "SELECT post_title FROM {$wpdb->posts} WHERE post_parent = {$post_id} AND post_type = 'uo-trigger'";
				$trigger_titles = $wpdb->get_results( $q );

				?>

                <div class="uap">
                    <div class="uo-post-column__list">

						<?php foreach ( $trigger_titles as $title ) { ?>

                            <div class="uo-post-column__item"><?php echo $title->post_title; ?></div>

						<?php } ?>
                    </div>
                </div>

				<?php

				break;
			case 'actions':
				$q = "SELECT post_title FROM {$wpdb->posts} WHERE post_parent = {$post_id} AND post_type = 'uo-action'";
				$action_titles = $wpdb->get_results( $q );

				?>

                <div class="uap">
                    <div class="uo-post-column__list">

						<?php foreach ( $action_titles as $title ) { ?>

                            <div class="uo-post-column__item"><?php echo $title->post_title; ?></div>

						<?php } ?>
                    </div>
                </div>

				<?php

				break;
			case 'runs':
				$q     = "SELECT count(automator_recipe_id) FROM {$wpdb->prefix}uap_recipe_log WHERE automator_recipe_id = {$post_id} AND ( completed = 1 OR completed = 2 ) ";
				$count = $wpdb->get_var( $q );
				echo $count;

				break;
			case 'type':
				$type = get_post_meta( $post_id, 'uap_recipe_type', true );
				echo empty( $type ) ? 'User' : ucfirst( $type );

				break;
		}
	}

	/**
	 * Create custom columns in the recipe list
	 *
	 * @param $columns
	 *
	 * @return mixed
	 */
	public function set_custom_columns( $columns ) {

		$new_columns = array();

		foreach ( $columns as $key => $column ) {


			if ( 'author' === $key ) {

				$new_columns['type']     = __( 'Recipe Type', 'uncanny-automator' );
				$new_columns['triggers'] = __( 'Triggers', 'uncanny-automator' );
				$new_columns['actions']  = __( 'Actions', 'uncanny-automator' );
				$new_columns['runs']     = _x( 'Completed Runs', 'The number of times a recipe was completed', 'uncanny-automator' );
				$new_columns[ $key ]     = $column;

			} else {
				$new_columns[ $key ] = $column;
			}
		}

		return $new_columns;
	}

	/**
	 * Creates an entry point with in a metabox to add JS / Rest-Api based UI
	 */
	public function recipe_add_meta_box_ui() {
		// Get global $post
		global $post, $uncanny_automator;

		// Get recipe type
		$recipe_type = $uncanny_automator->utilities->get_recipe_type( $post->ID );

		// Create variable to save the title of the triggers metabox,
		// and add the default value (on load value)
		$triggers_metabox_title = apply_filters( 'uap_meta_box_title', __( 'Logged-in triggers', 'uncanny-automator' ), $recipe_type );

		add_meta_box(
			'uo-recipe-triggers-meta-box-ui',
			$triggers_metabox_title,
			function () {
				ob_start();
				?>
                <div class="uap">
                    <div id="recipe-triggers-ui" class="metabox__content clear">

                        <!-- Placeholder content -->
                        <div class="uap-placeholder">
                            <div class="item item--trigger">
                                <div>
                                    <div class="item-actions">
                                        <div class="item-actions__btn">
                                            <i class="uo-icon uo-icon--ellipsis-h"></i>
                                        </div>
                                    </div>
                                    <div class="item-icon"></div>
                                    <div class="item-title"></div>
                                </div>
                                <div class="item__content">
                                    <div class="item-integrations">
                                        <div class="item-integration">
                                            <div class="item-integration__logo"></div>
                                            <div class="item-integration__name"></div>
                                        </div>
                                        <div class="item-integration">
                                            <div class="item-integration__logo"></div>
                                            <div class="item-integration__name"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- End of placeholder content -->

                    </div>
                </div>
				<?php
				echo ob_get_clean();
			},
			'uo-recipe',
			'uap_items',
			'high'
		);

		add_meta_box(
			'uo-recipe-actions-meta-box-ui',
			__( 'Actions', 'uncanny-automator' ),
			function () {
				ob_start();
				?>
                <div class="uap">
                    <div id="recipe-actions-ui" class="metabox__content clear">

                        <!-- Placeholder content -->
                        <div class="uap-placeholder">
                            <div class="item item--action">
                                <div>
                                    <div class="item-actions">
                                        <div class="item-actions__btn">
                                            <i class="uo-icon uo-icon--ellipsis-h"></i>
                                        </div>
                                    </div>
                                    <div class="item-icon"></div>
                                    <div class="item-title"></div>
                                </div>
                            </div>
                            <div class="metabox__footer">
                                <div class="uap-placeholder-checkbox">
                                    <div class="uap-placeholder-checkbox__field"></div>
                                    <div class="uap-placeholder-checkbox__label"></div>
                                </div>
                            </div>
                        </div>
                        <!-- End of placeholder content -->

                    </div>
                </div>
				<?php
				echo ob_get_clean();
			},
			'uo-recipe',
			'uap_items',
			'high'
		);

		add_action( 'edit_form_after_title', function () {
			global $post, $wp_meta_boxes;
			do_meta_boxes( get_current_screen(), 'uap_items', $post );
			unset( $wp_meta_boxes[ get_post_type( $post ) ]['uap_items'] );
		} );

		add_meta_box(
			'uo-automator-publish',
			__( 'Recipe', 'uncanny-automator' ),
			function () {
				ob_start();
				?>
                <div id="uo-automator-publish-metabox" class="uap">

                    <!-- Placeholder content -->
                    <div class="uap-placeholder">
                        <div id="uap-publish-metabox">
                            <div class="metabox__content">
                                <div class="publish-row">
                                    <div class="publish-row__visible">
                                        <span class="publish-row__icon"></span>
                                        <span class="publish-row__name"></span>
                                        <span class="publish-row__value"></span>
                                        <span class="publish-row__edit"></span>
                                    </div>
                                </div>
                                <div class="publish-row">
                                    <div class="publish-row__visible">
                                        <span class="publish-row__icon"></span>
                                        <span class="publish-row__name"></span>
                                        <span class="publish-row__value"></span>
                                        <span class="publish-row__edit"></span>
                                    </div>
                                </div>
                                <div class="publish-row">
                                    <div class="publish-row__visible">
                                        <span class="publish-row__icon"></span>
                                        <span class="publish-row__name"></span>
                                        <span class="publish-row__value"></span>
                                        <span class="publish-row__edit"></span>
                                    </div>
                                </div>
                            </div>
                            <div class="metabox__footer">
                                <div class="publish-footer">
                                    <div class="publish-footer__row clear">
                                        <div class="publish-footer__left">
                                            <a class="publish-footer__move-to-draft"></a>
                                        </div>
                                        <div class="publish-footer__right"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- End of placeholder content -->

                </div>
				<?php
				echo ob_get_clean();
			},
			'uo-recipe',
			'side',
			'high'
		);
	}

	/**
	 * Rest API Custom Endpoints
	 *
	 * @since 1.0
	 */
	public function register_routes() {

		register_rest_route( AUTOMATOR_REST_API_END_POINT, '/add/', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'add' ),
			'permission_callback' => array( $this, 'save_settings_permissions' ),
		) );

		register_rest_route( AUTOMATOR_REST_API_END_POINT, '/delete/', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'delete' ),
			'permission_callback' => array( $this, 'save_settings_permissions' ),
		) );

		register_rest_route( AUTOMATOR_REST_API_END_POINT, '/update/', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'update' ),
			'permission_callback' => array( $this, 'save_settings_permissions' ),
		) );

		register_rest_route( AUTOMATOR_REST_API_END_POINT, '/get_options/', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'get_options' ),
			'permission_callback' => array( $this, 'save_settings_permissions' ),
		) );

		register_rest_route( AUTOMATOR_REST_API_END_POINT, '/change_post_status/', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'change_post_status' ),
			'permission_callback' => array( $this, 'save_settings_permissions' ),
		) );

		register_rest_route( AUTOMATOR_REST_API_END_POINT, '/change_post_recipe_type/', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'change_post_recipe_type' ),
			'permission_callback' => array( $this, 'save_settings_permissions' ),
		) );

		register_rest_route( AUTOMATOR_REST_API_END_POINT, '/change_post_title/', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'change_post_title' ),
			'permission_callback' => array( $this, 'save_settings_permissions' ),
		) );

		register_rest_route( AUTOMATOR_REST_API_END_POINT, '/recipe_completions_allowed/', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'recipe_completions_allowed' ),
			'permission_callback' => array( $this, 'save_settings_permissions' ),
		) );

		register_rest_route( AUTOMATOR_REST_API_END_POINT, '/set_recipe_terms/', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'set_recipe_terms' ),
			'permission_callback' => array( $this, 'save_settings_permissions' ),
		) );

		//Rest APIs for User Selector Automator v2.0
		register_rest_route( AUTOMATOR_REST_API_END_POINT, '/user-selector/', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'user_selector' ),
			'permission_callback' => array( $this, 'save_settings_permissions' ),
		) );
	}

	/**
	 * Add trigger or action to recipe
	 *
	 * @param $request
	 *
	 * @return \WP_REST_Response
	 */
	public function add( $request ) {

		// Make sure we have a parent post ID
		if ( isset( $_POST['recipePostID'] ) && is_numeric( $_POST['recipePostID'] ) && ( ( isset( $_POST['trigger_code'] ) || isset( $_POST['item_code'] ) ) ) ) {

			// Make sure the parent post exists
			$recipe = get_post( absint( $_POST['recipePostID'] ) );
			global $uncanny_automator;

			if ( $recipe ) {

				$post_type   = false;
				$sentence    = '';
				$post_action = sanitize_text_field( $_POST['action'] );
				// Make sure we have the post type ( trigger OR action )
				if ( isset( $_POST['action'] ) && ( 'add-new-trigger' === (string) $post_action || 'add-new-action' === (string) $post_action || 'add-new-closure' === (string) $post_action ) ) {


					if ( 'add-new-trigger' === (string) $post_action ) {
						$post_type = 'uo-trigger';
						$action    = 'create_trigger';
						$sentence  = $uncanny_automator->get->trigger_title_from_trigger_code( sanitize_text_field( $_POST['item_code'] ) );
					}

					if ( 'add-new-action' === (string) $post_action ) {
						$post_type = 'uo-action';
						$action    = 'create_action';
						$sentence  = $uncanny_automator->get->action_title_from_action_code( sanitize_text_field( $_POST['item_code'] ) );
					}

					if ( 'add-new-closure' === (string) $post_action ) {
						$post_type = 'uo-closure';
						$action    = 'create_closure';

					}
				}

				if ( $post_type ) {

					$create_post = apply_filters( 'add_recipe_child', true, $post_type, $action, $recipe );

					if ( true !== $create_post ) {
						return $create_post;
					}

					// Create post object
					$post = array(
						'post_title'        => $sentence,
						'post_content'      => '',
						'post_status'       => 'draft',
						'post_type'         => $post_type,
						'post_date'         => $recipe->post_date,
						'post_date_gmt'     => $recipe->post_date_gmt,
						'post_modified'     => $recipe->post_modified,
						'post_modified_gmt' => $recipe->post_modified_gmt,
						'post_parent'       => $recipe->ID,

					);

					// Insert the post into the database
					$post_ID = wp_insert_post( $post );

					/** Sanitize @var $item_code */
					$item_code = $uncanny_automator->uap_sanitize( $_POST['item_code'] );

					if ( 'create_trigger' === $action ) {
						update_post_meta( $post_ID, 'code', $item_code );
						$trigger_integration = $uncanny_automator->get->trigger_integration_from_trigger_code( $item_code );
						update_post_meta( $post_ID, 'integration', $trigger_integration );
					}

					if ( 'create_action' === $action ) {
						update_post_meta( $post_ID, 'code', $item_code );
						$action_integration = $uncanny_automator->get->action_integration_from_action_code( $item_code );
						update_post_meta( $post_ID, 'integration', $action_integration );
					}

					if ( 'create_closure' === $action ) {
						update_post_meta( $post_ID, 'code', $item_code );
						$closure_integration = $uncanny_automator->get->closure_integration_from_closure_code( $item_code );
						update_post_meta( $post_ID, 'integration', $closure_integration );
					}

					if ( isset( $_POST['default_meta'] ) ) {
						if ( is_array( $_POST['default_meta'] ) ) {
							$meta_values = (array) $uncanny_automator->uap_sanitize( $_POST['default_meta'], 'mixed' );
							foreach ( $meta_values as $meta_key => $meta_value ) {
								update_post_meta( $post_ID, $uncanny_automator->uap_sanitize( $meta_key ), $uncanny_automator->uap_sanitize( $meta_value ) );
							}
						}
					}

					if ( $post_ID ) {
						$return['success']        = true;
						$return['post_ID']        = $post_ID;
						$return['action']         = $action;
						$return['recipes_object'] = $uncanny_automator->get_recipes_data( true );
					} else {
						$return['message'] = 'Post was not successfully created';
						$return['success'] = false;
						$return['data']    = $request;
						$return['post']    = '';
					}

					return new \WP_REST_Response( $return, 200 );
				}
			}
		}

		$return['message'] = 'The data that was sent was malformed. Please reload the page and trying again.';
		$return['success'] = false;
		$return['data']    = $request;
		$return['post']    = '';

		return new \WP_REST_Response( $return, 200 );
	}

	/**
	 * Delete trigger or action to recipe
	 *
	 * @param $request
	 *
	 * @return \WP_REST_Response
	 */
	public function delete( $request ) {

		// Make sure we have a parent post ID
		if ( isset( $_POST['ID'] ) && is_numeric( $_POST['ID'] ) ) {

			// Delete the post
			$delete_posts = wp_delete_post( absint( $_POST['ID'] ), true );

			if ( $delete_posts ) {

				$return['message']      = 'Deleted!';
				$return['success']      = true;
				$return['delete_posts'] = $delete_posts;
				$return['action']       = 'deleted-' . $delete_posts->post_type;

				global $uncanny_automator;
				$return['recipes_object'] = $uncanny_automator->get_recipes_data( true );

				return new \WP_REST_Response( $return, 200 );
			}
		}

		$return['message'] = 'The data that was sent was malformed. Please reload the page and trying again.';
		$return['success'] = false;
		$return['data']    = $request;
		$return['post']    = '';
		$return['action']  = 'show_error';

		return new \WP_REST_Response( $return, 200 );
	}

	/**
	 * Add trigger or action to recipe
	 *
	 * @param $request
	 *
	 * @return \WP_REST_Response
	 */
	public function update( $request ) {

		if ( isset( $_POST['itemId'] ) && is_numeric( $_POST['itemId'] ) && isset( $_POST['optionCode'] ) && isset( $_POST['optionValue'] ) ) {

			global $uncanny_automator;
			$item_id    = absint( $_POST['itemId'] );
			$meta_key   = (string) $uncanny_automator->uap_sanitize( $_POST['optionCode'] );
			$meta_value = $uncanny_automator->uap_sanitize( $_POST['optionValue'], 'mixed' );

			// Make sure the parent post exists
			$item = get_post( $item_id );

			if ( $item ) {
				if ( is_array( $meta_value ) ) {
					foreach ( $meta_value as $meta_key => $meta_val ) {
						update_post_meta( $item_id, $meta_key, $meta_val );
					}
				} else {
					update_post_meta( $item_id, $meta_key, $meta_value );
				}

				$return['message'] = __( 'Option Updated!', 'uncanny-automator' );
				$return['success'] = true;
				$return['action']  = 'updated_option';
				$return['data']    = [ $item, $meta_key, $meta_value ];

				$return['recipes_object'] = $uncanny_automator->get_recipes_data( true );

				$return = apply_filters( 'automator_option_updated', $return, $item, $meta_key, $meta_value );

				return new \WP_REST_Response( $return, 200 );
			} else {
				$return['message'] = 'You are trying to update trigger meta for a trigger that does not exist. Please reload the page and trying again.';
				$return['success'] = false;
				$return['data']    = $request;
				$return['post']    = '';

				return new \WP_REST_Response( $return, 200 );
			}
		}

		$return['message'] = 'The data that was sent was malformed. Please reload the page and trying again.';
		$return['success'] = false;
		$return['data']    = $request;
		$return['post']    = '';

		return new \WP_REST_Response( $return, 200 );
	}

	/**
	 * Get Option for trigger
	 *
	 * @param $request
	 *
	 * @return \WP_REST_Response
	 */
	public function get_options( $request ) {

		$options = array();

		// Make sure we have a trigger code
		if ( isset( $_POST['triggerCode'] ) ) {

			$trigger_code = sanitize_text_field( $_POST['triggerCode'] );

			global $uncanny_automator;

			$triggers = $uncanny_automator->get_triggers();

			// Loop through all trigger
			foreach ( $triggers as $trigger ) {

				// Locate the trigger the our trigger code
				if ( isset( $trigger['code'] ) && $trigger_code === $trigger['code'] ) {

					$options = $trigger['options'];

					$return['message'] = __( 'Success!', 'uncanny-automator' );
					$return['success'] = true;
					$return['options'] = $options;
					$return['action']  = 'show_success';

					return new \WP_REST_Response( $return, 200 );
				}
			}

			$return['message'] = 'No trigger code match';
			$return['success'] = false;
			$return['options'] = $options;
			$return['action']  = 'show_error';
			//$return['$trigger']      = $trigger['code'];
			//$return['$trigger_code'] = $trigger_code;
			///$return['$_POST']        = $_POST;

			return new \WP_REST_Response( $return, 200 );

		} elseif ( isset( $_POST['actionCode'] ) ) {

			$trigger_code = sanitize_text_field( $_POST['actionCode'] );

			global $uncanny_automator;

			$actions = $uncanny_automator->get_actions();

			// Loop through all trigger
			foreach ( $actions as $action ) {

				// Locate the trigger the our trigger code
				if ( isset( $action['code'] ) && $trigger_code === $action['code'] ) {

					$options = $action['options'];

					$return['message'] = 'Success!';
					$return['success'] = true;
					$return['options'] = $options;
					$return['action']  = 'show_success';

					return new \WP_REST_Response( $return, 200 );
				}
			}

			$return['message'] = 'No action code match';
			$return['success'] = false;
			$return['options'] = $options;
			$return['action']  = 'show_error';

			return new \WP_REST_Response( $return, 200 );
		}

		$return['message'] = 'The data that was sent was malformed. Please reload the page and trying again.';
		$return['success'] = false;
		$return['options'] = $options;
		$return['action']  = 'show_error';

		return new \WP_REST_Response( $return, 200 );
	}

	/**
	 * @param $request
	 *
	 * @return \WP_REST_Response
	 */
	public function change_post_status( $request ) {

		// Make sure we have a post ID and a post status
		if ( isset( $_POST['post_ID'] ) && isset( $_POST['post_status'] ) ) {

			$status_types = array( 'draft', 'publish' );

			$post_status = sanitize_text_field( $_POST['post_status'] );
			$post_ID     = absint( $_POST['post_ID'] );

			if ( in_array( $post_status, $status_types ) && $post_ID ) {

				$post = array(
					'ID'          => $post_ID,
					'post_status' => $post_status
				);

				$updated = wp_update_post( $post );

				if ( $updated ) {
					$return['message'] = 'Updated!';
					$return['success'] = true;
					$return['action']  = 'updated_post';
					//$return['$_POST']  = '';

					global $uncanny_automator;
					$return['recipes_object'] = $uncanny_automator->get_recipes_data( true );

					return new \WP_REST_Response( $return, 200 );
				}
			}
		}

		$return['message'] = 'Failed to update';
		$return['success'] = false;
		$return['action']  = 'show_error';

		return new \WP_REST_Response( $return, 200 );
	}

	/**
	 * @param $request
	 *
	 * @return \WP_REST_Response
	 */
	public function change_post_recipe_type() {

		// Make sure we have a post ID and a post status
		if ( isset( $_POST['post_ID'] ) && isset( $_POST['recipe_type'] ) ) {
			global $uncanny_automator;

			$recipe_types = apply_filters( 'uap_recipe_types', $uncanny_automator->get_recipe_types() );

			$recipe_type = sanitize_text_field( $_POST['recipe_type'] );
			$post_ID     = absint( $_POST['post_ID'] );

			if ( in_array( $recipe_type, $recipe_types ) && $post_ID ) {


				$updated = $uncanny_automator->utilities->set_recipe_type( $post_ID, $recipe_type );

				if ( false !== $updated ) {
					$return['message'] = 'Updated!';
					$return['success'] = true;
					$return['action']  = 'updated_post';
					//$return['$_POST']  = '';

					global $uncanny_automator;
					$return['recipes_object'] = $uncanny_automator->get_recipes_data( true );

					return new \WP_REST_Response( $return, 200 );
				}
			}
		}

		$return['message'] = 'Failed to update';
		$return['success'] = false;
		$return['action']  = 'show_error';

		return new \WP_REST_Response( $return, 200 );
	}

	/**
	 * @param $request
	 *
	 * @return \WP_REST_Response
	 */
	public function change_post_title( $request ) {

		// Make sure we have a post ID and a post status
		if ( isset( $_POST['post_ID'] ) && isset( $_POST['post_title'] ) ) {

			$post_title = sanitize_text_field( $_POST['post_title'] );
			$post_ID    = absint( $_POST['post_ID'] );

			if ( $post_ID ) {

				$post = array(
					'ID'         => $post_ID,
					'post_title' => $post_title
				);

				$updated = wp_update_post( $post );

				if ( $updated ) {
					$return['message'] = 'Updated!';
					$return['success'] = true;
					$return['action']  = 'updated_post';
					//$return['$_POST']  = $_POST;

					global $uncanny_automator;
					$return['recipes_object'] = $uncanny_automator->get_recipes_data( true );

					return new \WP_REST_Response( $return, 200 );
				}
			}
		}

		$return['message'] = 'Failed to update';
		$return['success'] = false;
		$return['action']  = 'show_error';

		return new \WP_REST_Response( $return, 200 );
	}

	/**
	 * Add trigger or action to recipe
	 *
	 * @param $request
	 *
	 * @return \WP_REST_Response
	 */
	public function recipe_completions_allowed( $request ) {

		// Make sure we have a post ID and a post status
		if ( isset( $_POST['post_ID'] ) && absint( $_POST['post_ID'] ) && isset( $_POST['recipe_completions_allowed'] ) ) {

			$recipe_completions_allowed = sanitize_text_field( $_POST['recipe_completions_allowed'] );
			$post_ID                    = absint( $_POST['post_ID'] );

			if ( '-1' === $recipe_completions_allowed ) {
				$recipe_completions_allowed = - 1;
			} elseif ( is_numeric( $recipe_completions_allowed ) ) {
				$recipe_completions_allowed = absint( $recipe_completions_allowed );
			} else {
				$recipe_completions_allowed = 1;
			}

			update_post_meta( $post_ID, 'recipe_completions_allowed', $recipe_completions_allowed );

			$return['message'] = 'Updated!';
			$return['success'] = true;
			$return['action']  = 'updated_recipe_completions_allowed';

			global $uncanny_automator;
			$return['recipes_object'] = $uncanny_automator->get_recipes_data( true );

			return new \WP_REST_Response( $return, 200 );
		}

		$return['message'] = 'Failed to update';
		$return['success'] = false;
		$return['action']  = 'show_error';

		return new \WP_REST_Response( $return, 200 );
	}

	/**
	 * Set recipe terms & tags
	 *
	 * @param $request
	 *
	 * @return \WP_REST_Response
	 */
	public function set_recipe_terms( \WP_REST_Request $request ) {
		// Make sure we have a post ID and a post status
		$params = $request->get_body_params();
		if ( isset( $params['recipe_id'] ) && isset( $params['term_id'] ) ) {
			$update_count = false;
			$recipe_id    = absint( $params['recipe_id'] );
			$taxonomy     = (string) sanitize_text_field( $params['term_id'] );
			if ( 'recipe_category' === $taxonomy && isset( $params['category_id'] ) && ! empty( $params['category_id'] ) ) {
				$term_id = absint( $params['category_id'] );
				$set_cat = 'true' === sanitize_text_field( $params['set_category'] ) ? true : false;
				if ( true === $set_cat ) {
					wp_add_object_terms( $recipe_id, $term_id, $taxonomy );
				} elseif ( ! $set_cat ) {
					wp_remove_object_terms( $recipe_id, $term_id, $taxonomy );
				}
				//$update_count = true;
			} elseif ( 'recipe_tag' === $taxonomy && isset( $params['tags']['commaSeparated'] ) && ! empty( $params['tags']['commaSeparated'] ) ) {
				$tags_sanitized = sanitize_text_field( $params['tags']['commaSeparated'] );
				$tags           = explode( ',', $tags_sanitized );
				wp_set_object_terms( $recipe_id, $tags, $taxonomy );
				//$update_count = true;
			}

			if ( $update_count ) {
				$all_terms = get_terms( [ 'taxonomy' => $taxonomy, 'hide_empty' => false ] );
				if ( $all_terms ) {
					$term_ids = array_column( $all_terms, 'term_id' );
					wp_update_term_count_now( $term_ids, $taxonomy );
				}
			}

			$return['message'] = 'Updated!';
			$return['success'] = true;
			$return['action']  = 'set_recipe_terms';

			return new \WP_REST_Response( $return, 200 );
		}

		$return['message'] = 'Failed to update';
		$return['success'] = false;
		$return['action']  = 'show_error';

		return new \WP_REST_Response( $return, 200 );
	}

	/**
	 * Permission callback function that let the rest API allow or disallow access
	 */
	/**
	 * @return bool|\WP_Error
	 */
	public function save_settings_permissions() {

		$capability = apply_filters( 'modify_recipe', 'edit_posts' );

		// Restrict endpoint to only users who have the edit_posts capability.
		if ( ! current_user_can( $capability ) ) {
			return new \WP_Error( 'rest_forbidden', esc_html__( 'You do not have the capability to save module settings.', 'uncanny-automator' ), array( 'status' => 401 ) );
		}

		// This is a black-listing approach. You could alternatively do this via white-listing, by returning false here and changing the permissions check.
		return true;
	}


	/**
	 * Enqueue scripts only on custom post type edit pages
	 *
	 * @param $hook
	 */
	function scripts( $hook ) {

		// Add global assets. Load in all admin pages
		Utilities::enqueue_global_assets();

		// Add scripts ONLY to recipe custom post type
		if ( ( 'post-new.php' === $hook || 'post.php' === $hook ) && get_post_type() === 'uo-recipe' ) {

			global $post;

			// $post return $post->ID as a string, Our JS expects an int... change it
			$post_id = (int) $post->ID;

			//Added select2 option for the dropdowns
			wp_enqueue_style( 'select2', Utilities::get_vendor_asset( 'select2/css/select2.min.css' ), array(), Utilities::get_version() );
			wp_enqueue_script( 'select2', Utilities::get_vendor_asset( 'select2/js/select2.min.js' ), array( 'jquery' ), Utilities::get_version(), true );

			// Recipe UI scripts
			wp_enqueue_script( 'automator-recipe-ui-bundle-js', Utilities::get_recipe_dist( 'automator-recipe-ui.bundle.js' ), array( 'jquery' ), Utilities::get_version(), true );

			// Enqueue editor assets
			wp_enqueue_editor();
			wp_enqueue_media();

			global $uncanny_automator;

			// API data
			$recipe_completions_allowed = get_post_meta( $post_id, 'recipe_completions_allowed', true );
			$recipe_type                = get_post_meta( $post_id, 'uap_recipe_type', true );

			// Get source
			$source = get_post_meta( $post_id, 'source', true );
			// Create fields array
			$fields = [
				'existingUser' => [],
				'newUser'      => []
			];
			// Check if the user defined a valid source
			if ( in_array( $source, [ 'existingUser', 'newUser' ] ) ) {
				// If the user did it, then add the fields
				$fields[ $source ] = get_post_meta( $post_id, 'fields', true );
			}

			$editable_roles = get_editable_roles();
			$roles          = [];
			foreach ( $editable_roles as $role_key => $role_data ) {
				$roles[ $role_key ] = $role_data['name'];
			}

			$api_setup = array(
				'wp'      => false,
				'restURL' => esc_url_raw( rest_url() . AUTOMATOR_REST_API_END_POINT ),
				'siteURL' => get_site_url(),
				'nonce'   => \wp_create_nonce( 'wp_rest' ),

				'dev'          => array(
					'debugMode' => WP_DEBUG === true ? true : false,
				),

				// 'recipe_types' => $uncanny_automator->get_recipe_types(),
				'integrations' => $uncanny_automator->get_integrations(),
				'triggers'     => $uncanny_automator->get_triggers(),
				'actions'      => $uncanny_automator->get_actions(),
				'closures'     => $uncanny_automator->get_closures(),

				'i18n'           => $uncanny_automator->i18n->get_all(),
				'recipes_object' => $uncanny_automator->get_recipes_data( true ),

				'version' => Utilities::get_version(),

				'recipe' => array(
					'id'     => $post_id,
					'author' => $post->post_author,
					'status' => $post->post_status,
					'type'   => empty( $recipe_type ) ? null : $recipe_type,
					'isLive' => ( 'publish' === $post->post_status ) ? true : false,

					'errorMode' => false,
					'isValid'   => false,

					'userSelector' => [
						'source'    => $source,
						'fields'    => $fields,
						'isValid'   => false,
						'resources' => [
							'roles' => $roles
						]
					],

					'hasLive' => array(
						'trigger' => false,
						'action'  => false,
						'closure' => false
					),
					'message' => array(
						'error'   => '',
						'warning' => ''
					),
					'items'   => [],
					'publish' => array(
						'timesPerUser' => empty( $recipe_completions_allowed ) ? 1 : $recipe_completions_allowed,
						'createdOn'    => get_the_time( 'M j, Y @ G:i', $post_id ),
						'moveToTrash'  => get_delete_post_link( $post_id )
					),
				),
			);

			$api_setup = apply_filters( 'uap_api_setup', $api_setup );

			wp_localize_script( 'automator-recipe-ui-bundle-js', 'UncannyAutomator', $api_setup );

			wp_enqueue_script( 'automator-recipe-ui-bundle-js' );

			wp_enqueue_style( 'automator-recipe-ui-bundle-css', Utilities::get_recipe_dist( 'automator-recipe-ui.bundle.css' ), array(), Utilities::get_version() );
		}
	}

	/**
	 * Delete all children triggers and actions of recipe
	 *
	 * @param $post_ID
	 */
	public function delete_triggers_actions( $post_ID ) {

		$post = get_post( $post_ID );

		if ( $post && 'uo-recipe' === $post->post_type ) {

			// delete recipe logs
			$this->delete_recipe_logs( $post_ID );

			$args = array(
				'post_parent' => $post->ID,
				'post_status' => 'any',
				'post_type'   => 'uo-trigger',
				'numberposts' => 99,
			);

			$children = get_children( $args );

			if ( is_array( $children ) && count( $children ) > 0 ) {

				// Delete all the Children of the Parent Page
				foreach ( $children as $child ) {

					wp_delete_post( $child->ID, true );

					$this->delete_trigger_logs( $child->ID );
				}
			}

			$args = array(
				'post_parent' => $post->ID,
				'post_status' => 'any',
				'post_type'   => 'uo-action',
				'numberposts' => 99,
			);

			$children = get_children( $args );

			if ( is_array( $children ) && count( $children ) > 0 ) {

				// Delete all the Children of the Parent Page
				foreach ( $children as $child ) {

					wp_delete_post( $child->ID, true );

					$this->delete_action_logs( $child->ID );
				}
			}

			$args = array(
				'post_parent' => $post->ID,
				'post_status' => 'any',
				'post_type'   => 'uo-closure',
				'numberposts' => 99,
			);

			$children = get_children( $args );

			if ( is_array( $children ) && count( $children ) > 0 ) {

				// Delete all the Children of the Parent Page
				foreach ( $children as $child ) {

					wp_delete_post( $child->ID, true );

					$this->delete_closure_logs( $child->ID );
				}
			}

		} elseif ( $post && 'uo-action' === $post->post_type ) {
			$this->delete_action_logs( $post_ID );
		} elseif ( $post && 'uo-trigger' === $post->post_type ) {
			$this->delete_trigger_logs( $post_ID );
		} elseif ( $post && 'uo-closure' === $post->post_type ) {
			$this->delete_closure_logs( $post_ID );
		}
	}

	/**
	 * Draft all children triggers and actions of recipe
	 *
	 * @param $post_ID
	 */
	public function draft_triggers_actions( $post_ID ) {

		$post = get_post( $post_ID );

		if ( $post && 'uo-recipe' === $post->post_type ) {

			$args = array(
				'post_parent' => $post->ID,
				'post_status' => 'any',
				'post_type'   => 'uo-trigger',
				'numberposts' => 99,
			);

			$children = get_children( $args );

			if ( is_array( $children ) && count( $children ) > 0 ) {

				// Delete all the Children of the Parent Page
				foreach ( $children as $child ) {

					$child_update = array(
						'ID'          => $child->ID,
						'post_status' => 'draft'
					);

					wp_update_post( $child_update );
				}
			}

			$args = array(
				'post_parent' => $post->ID,
				'post_status' => 'any',
				'post_type'   => 'uo-action',
				'numberposts' => 99,
			);

			$children = get_children( $args );

			if ( is_array( $children ) && count( $children ) > 0 ) {

				// Delete all the Children of the Parent Page
				foreach ( $children as $child ) {

					$child_update = array(
						'ID'          => $child->ID,
						'post_status' => 'draft'
					);

					wp_update_post( $child_update );
				}
			}

			$args = array(
				'post_parent' => $post->ID,
				'post_status' => 'any',
				'post_type'   => 'uo-closure',
				'numberposts' => 99,
			);

			$children = get_children( $args );

			if ( is_array( $children ) && count( $children ) > 0 ) {

				// Delete all the Children of the Parent Page
				foreach ( $children as $child ) {

					$child_update = array(
						'ID'          => $child->ID,
						'post_status' => 'draft'
					);

					wp_update_post( $child_update );
				}
			}

		}
	}

	/**
	 * Delete all logs and meta for triggers
	 *
	 * @param $post_ID
	 */
	public function delete_recipe_logs( $post_ID ) {
		global $wpdb;

		// delete from uap_recipe_log
		$wpdb->delete( $wpdb->prefix . 'uap_recipe_log', array( 'automator_recipe_id' => $post_ID ) );
	}

	/**
	 * Delete all logs and meta for triggers
	 *
	 * @param $post_ID
	 */
	public function delete_trigger_logs( $post_ID ) {
		global $wpdb;

		// delete from uap_trigger_log
		$wpdb->delete( $wpdb->prefix . 'uap_trigger_log', array( 'automator_trigger_id' => $post_ID ) );

		// delete from uap_trigger_log_meta
		$wpdb->delete( $wpdb->prefix . 'uap_trigger_log_meta', array( 'automator_trigger_id' => $post_ID ) );
	}

	/**
	 * Delete all logs and meta for actions
	 *
	 * @param $post_ID
	 */
	public function delete_action_logs( $post_ID ) {
		global $wpdb;

		// delete from uap_action_log
		$wpdb->delete( $wpdb->prefix . 'uap_action_log', array( 'automator_action_id' => $post_ID ) );

		// delete from uap_action_log_meta
		$wpdb->delete( $wpdb->prefix . 'uap_action_log_meta', array( 'automator_action_id' => $post_ID ) );
	}

	/**
	 * Delete all logs and meta for closures
	 *
	 * @param $post_ID
	 */
	public function delete_closure_logs( $post_ID ) {
		global $wpdb;

		// delete from uap_closure_log
		$wpdb->delete( $wpdb->prefix . 'uap_closure_log', array( 'automator_closure_id' => $post_ID ) );

		// delete from uap_closure_log_meta
		$wpdb->delete( $wpdb->prefix . 'uap_closure_log_meta', array( 'automator_closure_id' => $post_ID ) );
	}

	/**
	 * Update recipe status to draft and send admin notification email if pro is not active and the recipe has more than one trigger
	 *
	 * @param $current_screen
	 */
	public function maybe_draft_live_recipe( $current_screen ) {

		if ( is_admin() && 'uo-recipe' === $current_screen->post_type && ! defined( 'UAPRO_PLUGIN_NAME' ) ) {

			global $uncanny_automator;

			if ( isset( $_GET['post'] ) && isset( $_GET['action'] ) && 'edit' === sanitize_text_field( $_GET['action'] ) ) {
				$recipe_ID = absint( $_GET['post'] );
				if ( $recipe_ID ) {

					$recipe = get_post( $recipe_ID );

					if ( 1 < count( $uncanny_automator->get_recipe_data( 'uo-trigger', $recipe_ID ) ) && 'publish' === $recipe->post_status ) {

						$recipe_update = array(
							'ID'          => $recipe_ID,
							'post_status' => 'draft'
						);

						wp_update_post( $recipe_update );

						$this->send_email_notice( $recipe );
					}
				}
			}
		}
	}

	/**
	 *
	 *
	 * @param int $user_ID
	 * @param int $trigger_ID
	 * @param int $recipe_ID
	 * @param int $trigger_log_id
	 */
	function uap_before_trigger_completed( $user_ID, $trigger_ID, $recipe_ID, $trigger_log_id ) {

		if ( ! defined( 'UAPRO_PLUGIN_NAME' ) ) {
			if ( $recipe_ID ) {

				$recipe = get_post( $recipe_ID );

				global $uncanny_automator;
				if ( 1 < count( $uncanny_automator->get_recipe_data( 'uo-trigger', $recipe_ID ) ) && 'publish' === $recipe->post_status ) {

					$recipe_update = array(
						'ID'          => $recipe_ID,
						'post_status' => 'draft'
					);

					wp_update_post( $recipe_update );

					$this->send_email_notice( $recipe );
				}
			}
		}
	}

	/**
	 * @param $request
	 *
	 * @return \WP_REST_Response
	 */
	public function user_selector( $request ) {

		// Make sure we have a post ID and a post status
		if ( isset( $_POST['source'] ) && isset( $_POST['source'] ) ) {

			global $uncanny_automator;
			$source    = $uncanny_automator->uap_sanitize( $_POST['source'] );
			$fields    = $uncanny_automator->uap_sanitize( $_POST['fields'], 'mixed' );
			$recipe_id = (int) $_POST['recipeId'];
			//get recipe post id or action post id
			update_post_meta( $recipe_id, 'source', $source );
			update_post_meta( $recipe_id, 'fields', $fields );

			$return['message'] = 'Updated!';
			$return['success'] = true;
			$return['action']  = 'user_selector';

			$return['recipes_object'] = $uncanny_automator->get_recipes_data( true );

			return new \WP_REST_Response( $return, 200 );
		}

		$return['message'] = 'Failed to update';
		$return['success'] = false;
		$return['action']  = 'show_error';

		return new \WP_REST_Response( $return, 200 );
	}

	/**
	 * @param $recipe
	 */
	public function send_email_notice( $recipe ) {

		$to = get_bloginfo( 'admin_email' );

		$subject = sprintf( 'Recipe "%s" was automatically set to draft', $recipe->post_title );

		// Email content
		ob_start();
		?>

        <p>
			<?php _x( 'Hi,', 'Email notice', 'uncanny-automator' ) ?>
        </p>
        <p>
			<?php printf( _x( 'This email is to let you know that the recipe "%s" was automatically set to draft status because it contains multiple triggers and the Uncanny Automator Pro plugin is either not installed or not activated.  To reactivate the recipe, ensure the Uncanny Automator Pro plugin is active, then edit the recipe to switch its status to Live.', 'Email notice', 'uncanny-automator' ), $recipe->post_title ); ?>
        </p>
        <p>
			<?php printf( _x( 'Recipe link: %s', 'Email notice', 'uncanny-automator' ), sprintf( '<a href="%s">%s</a>', get_edit_post_link( $recipe->ID ), $recipe->post_title ) ) ?>
        </p>
        <p>
			<?php printf( _x( 'The %s Bot', 'Email notice', 'uncanny-automator' ), 'Uncanny Automator' ) ?>
        </p>

		<?php

		$body = ob_get_clean();

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		wp_mail( $to, $subject, $body, $headers );
	}
}
