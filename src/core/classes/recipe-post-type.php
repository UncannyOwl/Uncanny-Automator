<?php

namespace Uncanny_Automator;

/**
 * Class Recipe_Post_Type
 * @package Uncanny_Automator
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

		// Prepopulate recipe from a URL query
		add_action( 'wp_insert_post', array( 'Uncanny_Automator\Populate_From_Query', 'maybe_populate' ), 9, 3 );

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
					$admin_title = sprintf( '%s &mdash; %s', esc_attr__( 'Recipe log', 'uncanny-automator' ), $admin_title );
					break;
				case 'trigger-log':
					$admin_title = sprintf( '%s &mdash; %s', esc_attr__( 'Trigger log', 'uncanny-automator' ), $admin_title );
					break;
				case 'action-log':
					$admin_title = sprintf( '%s &mdash; %s', esc_attr__( 'Action log', 'uncanny-automator' ), $admin_title );
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
			return esc_attr__( 'New recipe', 'uncanny-automator' );
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
			update_post_meta( $post_ID, 'recipe_completions_allowed', '-1' );
		}
	}

	/**
	 *
	 */
	public function automator_post_type() {

		if ( ! post_type_exists( 'uo-recipe' ) ) {
			$icon_url = 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz48c3ZnIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgdmlld0JveD0iMCAwIDI2LjQ1IDI5LjUxIj48cGF0aCBkPSJNMjIuODkgNS41N2MtLjE4LS4xOC0uMzYtLjM1LS41NS0uNTFsMi41NS0yLjU1YTEuNDcgMS40NyAwIDAwLjQzLTEuMDcgMS40OSAxLjQ5IDAgMDAtMi41NS0xbC0yLjk1IDNhMTIuMDkgMTIuMDkgMCAwMC01LjUyLTEuNDNoLTIuMTlhMTIgMTIgMCAwMC01LjUyIDEuMzNMMy42NC40NGExLjUgMS41IDAgMDAtMi4xLjMxIDEuNDkgMS40OSAwIDAwMCAxLjc2bDIuNTMgMi41NUExMi4wNyAxMi4wNyAwIDAwMCAxNC4xN2E0LjM0IDQuMzQgMCAwMDQuMyA0LjM0aDE3LjgxYTQuMzQgNC4zNCAwIDAwNC4zNC00LjM0IDEyLjE1IDEyLjE1IDAgMDAtMy41Ni04LjZ6TTcuMiA3LjUxYTEuNSAxLjUgMCAxMTEuNSAxLjUgMS41IDEuNSAwIDAxLTEuNS0xLjV6bTkuNTQgNS43MWE1IDUgMCAwMS03LjA3IDAgMSAxIDAgMDExLjQxLTEuNDEgMyAzIDAgMDA0LjI0IDAgMSAxIDAgMDExLjQxIDAgMSAxIDAgMDEuMDEgMS40MXptMS00LjIxYTEuNSAxLjUgMCAxMTEuNS0xLjUgMS41IDEuNSAwIDAxLTEuNSAxLjV6TTIyLjcgMjAuNTFoLTE5YTMuNzEgMy43MSAwIDAwLTMuNyAzLjd2MS42YTMuNzEgMy43MSAwIDAwMy43IDMuN2gxOWEzLjcxIDMuNzEgMCAwMDMuNy0zLjd2LTEuNmEzLjcxIDMuNzEgMCAwMC0zLjctMy43em0tNi41IDdoLTMuNXYtMmExIDEgMCAwMC0yIDB2MmgtLjVhMSAxIDAgMDEtMS0xIDQgNCAwIDAxOCAwIDEgMSAwIDAxLTEgMXoiIGZpbGw9IiM4Mjg3OEMiLz48Y2lyY2xlIGN4PSIxNC43IiBjeT0iMjUuNTEiIHI9IjEiIGZpbGw9IiM4Mjg3OEMiLz48L3N2Zz4=';

			$labels = array(
				'name'                  => esc_attr__( 'Recipes', 'uncanny-automator' ),
				'singular_name'         => esc_attr__( 'Recipe', 'uncanny-automator' ),
				'menu_name'             => 'Automator',
				/* translators: 1. Trademarked term */
				'name_admin_bar'        => sprintf( esc_attr__( '%1$s recipe', 'uncanny-automator' ), 'Automator' ),
				'archives'              => 'Recipe Archives',
				'attributes'            => 'Recipe Attributes',
				'parent_item_colon'     => 'Parent Recipe:',
				'all_items'             => esc_attr__( 'All recipes', 'uncanny-automator' ),
				/* translators: Non-personal infinitive verb */
				'add_new_item'          => esc_attr__( 'Add new recipe', 'uncanny-automator' ),
				/* translators: Non-personal infinitive verb */
				'add_new'               => esc_attr_x( 'Add new', 'Recipe', 'uncanny-automator' ),
				'new_item'              => esc_attr__( 'New recipe', 'uncanny-automator' ),
				/* translators: Non-personal infinitive verb */
				'edit_item'             => esc_attr__( 'Edit recipe', 'uncanny-automator' ),
				/* translators: Non-personal infinitive verb */
				'update_item'           => esc_attr__( 'Update recipe', 'uncanny-automator' ),
				/* translators: Non-personal infinitive verb */
				'view_item'             => esc_attr__( 'View recipe', 'uncanny-automator' ),
				/* translators: Non-personal infinitive verb */
				'view_items'            => esc_attr__( 'View recipes', 'uncanny-automator' ),
				/* translators: Non-personal infinitive verb */
				'search_items'          => esc_attr__( 'Search recipes', 'uncanny-automator' ),
				'not_found'             => esc_attr_x( 'Not found', 'Recipe', 'uncanny-automator' ),
				'not_found_in_trash'    => esc_attr_x( 'Not found in trash', 'Recipe', 'uncanny-automator' ),
				'featured_image'        => 'Featured Image',
				'set_featured_image'    => 'Set Featured Image',
				'remove_featured_image' => 'Remove Featured Image',
				'use_featured_image'    => 'Use as Featured Image',
				'insert_into_item'      => 'Insert Into the Recipe',
				'uploaded_to_this_item' => 'Uploaded to This Recipe',
				'items_list'            => 'Recipes List',
				'items_list_navigation' => 'Recipes List Navigation',
				'filter_items_list'     => 'Filter Recipes List',
			);
			$args   = array(
				'label'               => esc_attr__( 'Recipe', 'uncanny-automator' ),
				'description'         => 'Uncanny WordPress Automation',
				'labels'              => $labels,
				'supports'            => array( 'title', 'author' ),
				'hierarchical'        => false,
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'menu_position'       => 40,
				'menu_icon'           => $icon_url,
				'show_in_admin_bar'   => true,
				'show_in_nav_menus'   => false,
				'can_export'          => true,
				'taxonomies'          => array( 'recipe_category', 'recipe_tag' ),
				'has_archive'         => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => true,
				'capabilities'        => array(
					'publish_posts'       => 'manage_options',
					'edit_posts'          => 'manage_options',
					'edit_others_posts'   => 'manage_options',
					'delete_posts'        => 'manage_options',
					'delete_others_posts' => 'manage_options',
					'read_private_posts'  => 'manage_options',
					'edit_post'           => 'manage_options',
					'delete_post'         => 'manage_options',
				),
				'show_in_rest'        => true,
				'rest_base'           => 'uap',
			);

			register_post_type( 'uo-recipe', apply_filters( 'automator_post_type_recipe_args', $args ) );
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
				$q              = "SELECT post_title FROM {$wpdb->posts} WHERE post_parent = {$post_id} AND post_type = 'uo-trigger'";
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
				$q             = "SELECT post_title FROM {$wpdb->posts} WHERE post_parent = {$post_id} AND post_type = 'uo-action'";
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

				$new_columns['type']     = esc_attr__( 'Recipe type', 'uncanny-automator' );
				$new_columns['triggers'] = esc_attr__( 'Triggers', 'uncanny-automator' );
				$new_columns['actions']  = esc_attr__( 'Actions', 'uncanny-automator' );
				/* translators: The number of times a recipe was completed */
				$new_columns['runs'] = esc_attr__( 'Completed runs', 'uncanny-automator' );
				$new_columns[ $key ] = $column;

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
		$recipe_type = get_post_meta( $post->ID, 'uap_recipe_type', true );

		// Create variable to save the title of the triggers metabox,
		// and add the default value (on load value)
		/* translators: Trigger type. Logged-in triggers are triggered only by logged-in users */

		// Check if the user didn't select a recipe type yet
		if ( empty( $recipe_type ) ) {
			$triggers_metabox_title = apply_filters( 'uap_meta_box_title', esc_attr__( 'Triggers', 'uncanny-automator' ), $recipe_type );
		} else {
			$triggers_metabox_title = apply_filters( 'uap_meta_box_title', esc_attr__( 'Logged-in triggers', 'uncanny-automator' ), $recipe_type );
		}

		add_meta_box(
			'uo-recipe-triggers-meta-box-ui',
			$triggers_metabox_title,
			function () {
				ob_start();
				?>
				<div class="uap">
					<div id="recipe-triggers-ui" class="metabox__content uap-clear">

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
			esc_attr__( 'Actions', 'uncanny-automator' ),
			function () {
				ob_start();
				?>
				<div class="uap">
					<div id="recipe-actions-ui" class="metabox__content uap-clear">

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

		add_action(
			'edit_form_after_title',
			function () {
				global $post, $wp_meta_boxes;
				do_meta_boxes( get_current_screen(), 'uap_items', $post );
				unset( $wp_meta_boxes[ get_post_type( $post ) ]['uap_items'] );
			}
		);

		add_meta_box(
			'uo-automator-publish',
			esc_attr__( 'Recipe', 'uncanny-automator' ),
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
									<div class="publish-footer__row uap-clear">
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

		register_rest_route(
			AUTOMATOR_REST_API_END_POINT,
			'/add/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'add' ),
				'permission_callback' => array( $this, 'save_settings_permissions' ),
			)
		);

		register_rest_route(
			AUTOMATOR_REST_API_END_POINT,
			'/delete/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'delete' ),
				'permission_callback' => array( $this, 'save_settings_permissions' ),
			)
		);

		register_rest_route(
			AUTOMATOR_REST_API_END_POINT,
			'/update/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'update' ),
				'permission_callback' => array( $this, 'save_settings_permissions' ),
			)
		);

		register_rest_route(
			AUTOMATOR_REST_API_END_POINT,
			'/get_options/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'get_options' ),
				'permission_callback' => array( $this, 'save_settings_permissions' ),
			)
		);

		register_rest_route(
			AUTOMATOR_REST_API_END_POINT,
			'/change_post_status/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'change_post_status' ),
				'permission_callback' => array( $this, 'save_settings_permissions' ),
			)
		);

		register_rest_route(
			AUTOMATOR_REST_API_END_POINT,
			'/change_post_recipe_type/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'change_post_recipe_type' ),
				'permission_callback' => array( $this, 'save_settings_permissions' ),
			)
		);

		register_rest_route(
			AUTOMATOR_REST_API_END_POINT,
			'/change_post_title/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'change_post_title' ),
				'permission_callback' => array( $this, 'save_settings_permissions' ),
			)
		);

		register_rest_route(
			AUTOMATOR_REST_API_END_POINT,
			'/recipe_completions_allowed/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'recipe_completions_allowed' ),
				'permission_callback' => array( $this, 'save_settings_permissions' ),
			)
		);

		register_rest_route(
			AUTOMATOR_REST_API_END_POINT,
			'/set_recipe_terms/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'set_recipe_terms' ),
				'permission_callback' => array( $this, 'save_settings_permissions' ),
			)
		);

		//Rest APIs for User Selector Automator v2.0
		register_rest_route(
			AUTOMATOR_REST_API_END_POINT,
			'/user-selector/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'user_selector' ),
				'permission_callback' => array( $this, 'save_settings_permissions' ),
			)
		);
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
						update_post_meta( $post_ID, 'uap_trigger_version', Utilities::get_version() );
					}

					if ( 'create_action' === $action ) {
						update_post_meta( $post_ID, 'code', $item_code );
						$action_integration = $uncanny_automator->get->action_integration_from_action_code( $item_code );
						update_post_meta( $post_ID, 'integration', $action_integration );
						update_post_meta( $post_ID, 'uap_action_version', Utilities::get_version() );
					}

					if ( 'create_closure' === $action ) {
						update_post_meta( $post_ID, 'code', $item_code );
						$closure_integration = $uncanny_automator->get->closure_integration_from_closure_code( $item_code );
						update_post_meta( $post_ID, 'integration', $closure_integration );
						update_post_meta( $post_ID, 'uap_closure_version', Utilities::get_version() );
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

			/*
			 * Save human readable sentence that will be stored as trigger and action meta.
			 * Once a trigger is completed, the human readable post meta value will be saved as trigger or action log
			 * meta fr the user to have more detail about it in the logs.
			 */
			if ( isset( $_POST['sentence_human_readable'] ) ) {
				$human_readable = sanitize_text_field( $_POST['sentence_human_readable'] );
				update_post_meta( $item_id, 'sentence_human_readable', $human_readable );
			}

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

				$return['message'] = 'Option updated!';
				$return['success'] = true;
				$return['action']  = 'updated_option';
				$return['data']    = array( $item, $meta_key, $meta_value );

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

					$return['message'] = 'Success!';
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

				/*
				 * Save human readable sentence that will be stored as trigger and action meta.
				 * Once a trigger is completed, the human readable post meta value will be saved as trigger or action log
				 * meta fr the user to have more detail about it in the logs.
				 */
				if ( isset( $_POST['sentence_human_readable'] ) ) {
					$human_readable = sanitize_text_field( $_POST['sentence_human_readable'] );
					update_post_meta( $post_ID, 'sentence_human_readable', $human_readable );
				}

				$post = array(
					'ID'          => $post_ID,
					'post_status' => $post_status,
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
					'post_title' => $post_title,
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
				$all_terms = get_terms(
					array(
						'taxonomy'   => $taxonomy,
						'hide_empty' => false,
					)
				);
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

		$capability = apply_filters( 'uap_roles_modify_recipe', 'edit_posts' );

		// Restrict endpoint to only users who have the edit_posts capability.
		if ( ! current_user_can( $capability ) ) {
			return new \WP_Error( 'rest_forbidden', 'You do not have the capability to save module settings.', array( 'status' => 403 ) );
		}

		// This is a black-listing approach. You could alternatively do this via white-listing, by returning false here and changing the permissions check.
		return apply_filters( 'uap_save_setting_permissions', true );
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
			wp_enqueue_style( 'uap-select2', Utilities::get_vendor_asset( 'select2/css/select2.min.css' ), array(), Utilities::get_version() );
			wp_enqueue_script( 'uap-select2', Utilities::get_vendor_asset( 'select2/js/select2.min.js' ), array( 'jquery' ), Utilities::get_version(), true );

			// Recipe UI scripts
			wp_enqueue_script(
				'automator-recipe-ui-bundle-js',
				Utilities::get_recipe_dist( 'automator-recipe-ui.bundle.js' ),
				array(
					'jquery',
					'uap-select2',
				),
				Utilities::get_version(),
				true
			);

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
			$fields = array(
				'existingUser' => array(),
				'newUser'      => array(),
			);
			// Check if the user defined a valid source
			if ( in_array( $source, array( 'existingUser', 'newUser' ) ) ) {
				// If the user did it, then add the fields
				$fields[ $source ] = get_post_meta( $post_id, 'fields', true );
			}

			$editable_roles = get_editable_roles();
			$roles          = array();
			foreach ( $editable_roles as $role_key => $role_data ) {
				$roles[ $role_key ] = $role_data['name'];
			}

			$api_setup = array(
				'wp'             => false,
				'restURL'        => esc_url_raw( rest_url() . AUTOMATOR_REST_API_END_POINT ),
				'siteURL'        => get_site_url(),
				'nonce'          => \wp_create_nonce( 'wp_rest' ),

				'dev'            => array(
					'debugMode' => WP_DEBUG === true ? true : false,
				),

				// 'recipe_types' => $uncanny_automator->get_recipe_types(),
				'integrations'   => $uncanny_automator->get_integrations(),
				'triggers'       => $uncanny_automator->get_triggers(),
				'actions'        => $uncanny_automator->get_actions(),
				'closures'       => $uncanny_automator->get_closures(),

				'i18n'           => $uncanny_automator->i18n->get_all(),
				'recipes_object' => $uncanny_automator->get_recipes_data( true ),

				'version'        => Utilities::get_version(),

				'proFeatures'    => $this->get_pro_items(),

				'recipe'         => array(
					'id'           => $post_id,
					'author'       => $post->post_author,
					'status'       => $post->post_status,
					'type'         => empty( $recipe_type ) ? null : $recipe_type,
					'isLive'       => ( 'publish' === $post->post_status ) ? true : false,

					'errorMode'    => false,
					'isValid'      => false,

					'userSelector' => array(
						'source'    => $source,
						'fields'    => $fields,
						'isValid'   => false,
						'resources' => array(
							'roles' => $roles,
						),
					),

					'hasLive'      => array(
						'trigger' => false,
						'action'  => false,
						'closure' => false,
					),
					'message'      => array(
						'error'   => '',
						'warning' => '',
					),
					'items'        => array(),
					'publish'      => array(
						'timesPerUser' => empty( $recipe_completions_allowed ) ? 1 : $recipe_completions_allowed,
						'createdOn'    => date_i18n( 'M j, Y @ G:i', get_the_time( 'U', $post_id ) ),
						'moveToTrash'  => get_delete_post_link( $post_id ),
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
	 * List of Pro features to upsell Automator Pro
	 *
	 * @return array
	 */
	private function get_pro_items() {

		$pro_items = array(
			'UOA'            => array(
				'triggers' => array(
					array(
						/* translators: Logged-in trigger - Automator Core */
						'name' => __( 'A user clicks {{a magic button}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - Automator Core */
						'name' => __( 'A user clicks {{a magic link}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Anonymous trigger - Automator Core */
						'name' => __( 'Receive data from a webhook', 'uncanny-automator' ),
						'type' => 'anonymous',
					),
				),
				'actions'  => array(),
			),
			'BB'             => array(
				'triggers' => array(
					array(
						/* translators: Logged-in trigger - bbPress */
						'name' => __( 'A user replies to {{a topic}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
				),
				'actions'  => array(),
			),
			'BDB'            => array(
				'triggers' => array(
					array(
						/* translators: Logged-in trigger - BuddyBoss */
						'name' => __( "A user's connection request is accepted", 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - BuddyBoss */
						'name' => __( 'A user registers with {{a specific value}} in {{a specific field}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - BuddyBoss */
						'name' => __( 'A user registers a new account via an email invitation', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - BuddyBoss */
						'name' => __( 'A user activates a new account via an email invitation', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - BuddyBoss */
						'name' => __( "A user's email invitation results in a new member activation", 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - BuddyBoss */
						'name' => __( "A user's email invitation results in a new member registration", 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - BuddyBoss */
						'name' => __( "A user's member type is set to {{a specific type}}", 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - BuddyBoss */
						'name' => __( 'A user joins {{a public group}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - BuddyBoss */
						'name' => __( 'A user leaves {{a group}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - BuddyBoss */
						'name' => __( 'A user joins {{a private group}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - BuddyBoss */
						'name' => __( 'A user makes a post to the activity stream of {{a group}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - BuddyBoss */
						'name' => __( 'A user updates their profile with {{a specific value}} in {{a specific field}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
				),
				'actions'  => array(
					array(
						/* translators: Action - BuddyBoss */
						'name' => __( "Set the user's member type to {{a specific type}}", 'uncanny-automator' ),
					),
					array(
						/* translators: Action - BuddyBoss */
						'name' => __( "Add a post to the user's activity stream", 'uncanny-automator' ),
					),
					array(
						/* translators: Action - BuddyBoss */
						'name' => __( 'Add a post to the activity stream of {{a group}}', 'uncanny-automator' ),
					),
					array(
						/* translators: Action - BuddyBoss */
						'name' => __( 'Add a post to the sitewide activity stream', 'uncanny-automator' ),
					),
					array(
						/* translators: Action - BuddyBoss */
						'name' => __( 'Send {{a private message}} to the user', 'uncanny-automator' ),
					),
					array(
						/* translators: Action - BuddyBoss */
						'name' => __( 'Create {{a group}}', 'uncanny-automator' ),
					),
					array(
						/* translators: Action - BuddyBoss */
						'name' => __( 'Set {{Xprofile data}}', 'uncanny-automator' ),
					),
				),
			),
			'BP'             => array(
				'triggers' => array(
					array(
						/* translators: Logged-in trigger - BuddyPress */
						'name' => __( 'A user joins {{a public group}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - BuddyPress */
						'name' => __( 'A user leaves {{a group}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - BuddyPress */
						'name' => __( "A user's connection request is accepted", 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - BuddyPress */
						'name' => __( ' A user registers with {{a specific value}} in {{a specific field}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - BuddyPress */
						'name' => __( "A user's member type is set to {{a specific type}}", 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - BuddyPress */
						'name' => __( 'A user joins {{a private group}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - BuddyPress */
						'name' => __( 'A user makes a post to the activity stream of {{a group}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - BuddyPress */
						'name' => __( 'A user updates their profile with {{a specific value}} in {{a specific field}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
				),
				'actions'  => array(
					array(
						/* translators: Action - BuddyPress */
						'name' => __( 'Remove the user from {{a group}}', 'uncanny-automator' ),
					),
					array(
						/* translators: Action - BuddyPress */
						'name' => __( "Set the user's member type to {{a specific type}}", 'uncanny-automator' ),
					),
					array(
						/* translators: Action - BuddyPress */
						'name' => __( "Add a post to the user's {{activity}} stream", 'uncanny-automator' ),
					),
					array(
						/* translators: Action - BuddyPress */
						'name' => __( 'Add a post to the activity stream of {{a group}}', 'uncanny-automator' ),
					),
					array(
						/* translators: Action - BuddyPress */
						'name' => __( 'Add a post to the sitewide {{activity}} stream', 'uncanny-automator' ),
					),
					array(
						/* translators: Action - BuddyPress */
						'name' => __( 'Send {{a private message}} to the user', 'uncanny-automator' ),
					),
					array(
						/* translators: Action - BuddyPress */
						'name' => __( 'Create {{a group}}', 'uncanny-automator' ),
					),
					array(
						/* translators: Action - BuddyPress */
						'name' => __( 'Set {{Xprofile data}}', 'uncanny-automator' ),
					),
				),
			),
			'CF'             => array(
				'triggers' => array(
					array(
						/* translators: Logged-in trigger - Caldera Forms */
						'name' => __( 'A user submits {{a form}} with {{a specific value}} in {{a specific field}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
				),
				'actions'  => array(
					array(
						/* translators: Action - Caldera Forms */
						'name' => __( 'Register a new user', 'uncanny-automator' ),
					),
				),
			),
			'CF7'            => array(
				'triggers' => array(
					array(
						/* translators: Logged-in trigger - Contact Form 7 */
						'name' => __( 'A user submits {{a form}} with {{a specific value}} in {{a specific field}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
				),
				'actions'  => array(
					array(
						/* translators: Action - Contact Form 7 */
						'name' => __( 'Register a new user', 'uncanny-automator' ),
					),
				),
			),
			'ELEM'           => array(
				'triggers' => array(
					array(
						/* translators: Logged-in trigger - Elementor */
						'name' => __( ' A user submits {{a form}} with {{a specific value}} in {{a specific field}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Anonymous trigger - Elementor */
						'name' => __( '{{A form}} is submitted with {{a specific value}} in {{a specific field}}', 'uncanny-automator' ),
						'type' => 'anonymous',
					),
					array(
						/* translators: Anonymous trigger - Elementor */
						'name' => __( 'A user submits {{a form}}', 'uncanny-automator' ),
						'type' => 'anonymous',
					),
				),
				'actions'  => array(
					array(
						/* translators: Action - Elementor */
						'name' => __( 'Show {{a popup}} to the user', 'uncanny-automator' ),
					),
				),
			),
			'EVENTSMANAGER'  => array(
				'triggers' => array(
					array(
						/* translators: Logged-in trigger - Events Manager */
						'name' => __( 'A user unregisters from {{an event}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - Events Manager */
						'name' => __( 'A user registers for {{an event}} with {{a specific ticket}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
				),
				'actions'  => array(
					array(
						/* translators: Action - Events Manager */
						'name' => __( 'Unregister the user from {{an event}}', 'uncanny-automator' ),
					),
				),
			),
			'WPFF'           => array(
				'triggers' => array(
					array(
						/* translators: Logged-in trigger - Fluent Forms */
						'name' => __( 'A user submits {{a form}} with {{a specific value}} in {{a specific field}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - Fluent Forms */
						'name' => __( '{{A form}} is submitted', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - Fluent Forms */
						'name' => __( '{{A form}} is submitted with {{a specific value}} in {{a specific field}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
				),
				'actions'  => array(
					array(
						/* translators: Action - Fluent Forms */
						'name' => __( 'Register a new user', 'uncanny-automator' ),
					),
				),
			),
			'FCRM'           => array(
				'triggers' => array(
					array(
						/* translators: Logged-in trigger - FluentCRM */
						'name' => __( 'A user is removed from {{a list}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - FluentCRM */
						'name' => __( '{{A tag}} is removed from a user', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
				),
				'actions'  => array(
					array(
						/* translators: Action - FluentCRM */
						'name' => __( 'Remove the user from {{a list}}', 'uncanny-automator' ),
					),
					array(
						/* translators: Action - FluentCRM */
						'name' => __( 'Remove {{a tag}} from the user', 'uncanny-automator' ),
					),
				),
			),
			'FI'             => array(
				'triggers' => array(
					array(
						/* translators: Logged-in trigger - Formidable Forms */
						'name' => __( 'A user submits {{a form}} with {{a specific value}} in {{a specific field}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - Formidable Forms */
						'name' => __( 'A user submits {{a form}} with payment', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - Formidable Forms */
						'name' => __( 'A user updates an entry in {{a form}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
				),
				'actions'  => array(
					array(
						/* translators: Action - Formidable Forms */
						'name' => __( 'Register a new user', 'uncanny-automator' ),
					),
				),
			),
			'FR'             => array(
				'triggers' => array(
					array(
						/* translators: Logged-in trigger - Forminator */
						'name' => __( 'A user submits {{a form}} with {{a specific value}} in {{a specific field}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
				),
				'actions'  => array(
					array(
						/* translators: Action - Forminator */
						'name' => __( 'Register a new user', 'uncanny-automator' ),
					),
				),
			),
			'GP'             => array(
				'triggers' => array(
					array(
						/* translators: Logged-in trigger - GamiPress */
						'name' => __( 'A user earns {{an achievement}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - GamiPress */
						'name' => __( 'A user earns {{a number}} {{of a specfic type of}} points', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - GamiPress */
						'name' => __( 'A user attains {{a rank}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
				),
				'actions'  => array(
					array(
						/* translators: Action - GamiPress */
						'name' => __( 'Revoke {{an achievement}} from the user', 'uncanny-automator' ),
					),
					array(
						/* translators: Action - GamiPress */
						'name' => __( 'Revoke {{a rank}} from the user', 'uncanny-automator' ),
					),
					array(
						/* translators: Action - GamiPress */
						'name' => __( 'Revoke {{a number}} {{of a certain type of}} points from the user', 'uncanny-automator' ),
					),
					array(
						/* translators: Action - GamiPress */
						'name' => __( 'Revoke all {{of a certain type of}} points from the user', 'uncanny-automator' ),
					),
				),
			),
			'GIVEWP'         => array(
				'triggers' => array(
					array(
						/* translators: Logged-in trigger - GiveWP */
						'name' => __( 'A user cancels {{a recurring donation}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Anonymous trigger - GiveWP */
						'name' => __( '{{A donation form}} is submitted', 'uncanny-automator' ),
						'type' => 'anonymous',
					),
					array(
						/* translators: Logged-in trigger - GiveWP */
						'name' => __( 'A user makes a donation via {{a form}} with {{a specific value}} in {{a specifc field}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - GiveWP */
						'name' => __( 'A user makes a donation via {{a form}} for an amount {{great than, less than, or equal to}} {{an amount}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - GiveWP */
						'name' => __( 'A user continues {{a recurring donation}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
				),
				'actions'  => array(
					array(
						/* translators: Action - GiveWP */
						'name' => __( 'Add a note to {{a donor}}', 'uncanny-automator' ),
					),
					array(
						/* translators: Action - GiveWP */
						'name' => __( 'Create a donor', 'uncanny-automator' ),
					),
				),
			),
			'GOOGLESHEET'    => array(
				'triggers' => array(),
				'actions'  => array(
					array(
						/* translators: Action - Google Sheets */
						'name' => __( 'Create a row in Google Sheets', 'uncanny-automator' ),
					),
				),
			),
			'GTT'            => array(
				'triggers' => array(),
				'actions'  => array(
					array(
						/* translators: Action - GoToTraining */
						'name' => __( 'Add the user to a {{training session}}', 'uncanny-automator' ),
					),
					array(
						/* translators: Action - GoToTraining */
						'name' => __( 'Remove the user from a {{training session}}', 'uncanny-automator' ),
					),
				),
			),
			'GTM'            => array(
				'triggers' => array(),
				'actions'  => array(
					array(
						/* translators: Action - GoToWebinar */
						'name' => __( 'Add the user to {{a webinar}}', 'uncanny-automator' ),
					),
					array(
						/* translators: Action - GoToWebinar */
						'name' => __( 'Remove the user from {{a webinar}}', 'uncanny-automator' ),
					),
				),
			),
			'GF'             => array(
				'triggers' => array(
					array(
						/* translators: Logged-in trigger - Gravity Forms */
						'name' => __( 'A user submits {{a form}} with {{a specific value}} in {{a specific field}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - Gravity Forms */
						'name' => __( 'A user submits {{a form}} with payment', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
				),
				'actions'  => array(
					array(
						/* translators: Action - Gravity Forms */
						'name' => __( 'Register a new user', 'uncanny-automator' ),
					),
				),
			),
			'GH'             => array(
				'triggers' => array(
					array(
						/* translators: Logged-in trigger - Groundhogg */
						'name' => __( '{{A tag}} is added to a user', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - Groundhogg */
						'name' => __( '{{A tag}} is removed from a user', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
				),
				'actions'  => array(),
			),
			'H5P'            => array(
				'triggers' => array(
					array(
						/* translators: Logged-in trigger - H5P */
						'name' => __( 'A user completes {{H5P content}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - H5P */
						'name' => __( 'A user completes any {{of a specific type of}} H5P content', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - H5P */
						'name' => __( 'A user achieves a score {{greater than, less than or equal to}} {{a value}} on {{H5P content}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
				),
				'actions'  => array(),
			),
			'HF'             => array(
				'triggers' => array(
					array(
						/* translators: Logged-in trigger - HappyForms */
						'name' => __( 'A user submits {{a form}} with {{a specific value}} in {{a specific field}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Anonymous trigger - HappyForms */
						'name' => __( '{{A form}} is submitted', 'uncanny-automator' ),
						'type' => 'anonymous',
					),
					array(
						/* translators: Anonymous trigger - HappyForms */
						'name' => __( '{{A form}} is submitted with {{a specific value}} in {{a specific field}}', 'uncanny-automator' ),
						'type' => 'anonymous',
					),
				),
				'actions'  => array(),
			),
			'INTEGROMAT'     => array(
				'triggers' => array(
					array(
						/* translators: Anonymous trigger - Integromat */
						'name' => __( 'Receive data from {{Integromat webhook}}', 'uncanny-automator' ),
						'type' => 'anonymous',
					),
				),
				'actions'  => array(),
			),
			'LD'             => array(
				'triggers' => array(
					array(
						/* translators: Logged-in trigger - LearnDash */
						'name' => __( 'A user submits an assignment for {{a lesson or topic}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - LearnDash */
						'name' => __( 'A user is enrolled in {{a course}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - LearnDash */
						'name' => __( 'A user is added to {{a group}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
				),
				'actions'  => array(
					array(
						/* translators: Action - LearnDash */
						'name' => __( 'Unenroll the user from {{a course}}', 'uncanny-automator' ),
					),
					array(
						/* translators: Action - LearnDash */
						'name' => __( "Reset the user's progress in {{a course}}", 'uncanny-automator' ),
					),
					array(
						/* translators: Action - LearnDash */
						'name' => __( "Reset the user's attempts for {{a quiz}}", 'uncanny-automator' ),
					),
					array(
						/* translators: Action - LearnDash */
						'name' => __( 'Add the user to {{a group}}', 'uncanny-automator' ),
					),
					array(
						/* translators: Action - LearnDash */
						'name' => __( 'Remove the user from {{a group}}', 'uncanny-automator' ),
					),
					array(
						/* translators: Action - LearnDash */
						'name' => __( "Send an {{email}} to the user's group leader(s)", 'uncanny-automator' ),
					),
					array(
						/* translators: Action - LearnDash */
						'name' => __( 'Mark {{a lesson}} not complete for the user', 'uncanny-automator' ),
					),
					array(
						/* translators: Action - LearnDash */
						'name' => __( 'Mark {{a topic}} not complete for the user', 'uncanny-automator' ),
					),
					array(
						/* translators: Action - LearnDash */
						'name' => __( 'Generate and email a certificate', 'uncanny-automator' ),
					),
				),
			),
			'LP'             => array(
				'triggers' => array(),
				'actions'  => array(
					array(
						/* translators: Action - LearnPress */
						'name' => __( 'Enroll the user in {{a course}}', 'uncanny-automator' ),
					),
					array(
						/* translators: Action - LearnPress */
						'name' => __( 'Mark {{a course}} complete for the user', 'uncanny-automator' ),
					),
					array(
						/* translators: Action - LearnPress */
						'name' => __( 'Remove the user from {{a course}}', 'uncanny-automator' ),
					),
				),
			),
			'LF'             => array(
				'triggers' => array(),
				'actions'  => array(
					array(
						/* translators: Action - LifterLMS */
						'name' => __( 'Remove the user from {{a course}}', 'uncanny-automator' ),
					),
					array(
						/* translators: Action - LifterLMS */
						'name' => __( 'Enroll the user in {{a course}}', 'uncanny-automator' ),
					),
					array(
						/* translators: Action - LifterLMS */
						'name' => __( 'Mark {{a course}} complete for the user', 'uncanny-automator' ),
					),
					array(
						/* translators: Action - LifterLMS */
						'name' => __( 'Remove the user from {{a membership}}', 'uncanny-automator' ),
					),
					array(
						/* translators: Action - LifterLMS */
						'name' => __( 'Enroll the user in {{a membership}}', 'uncanny-automator' ),
					),
					array(
						/* translators: Action - LifterLMS */
						'name' => __( "Reset the user's attempts for {{a quiz}}", 'uncanny-automator' ),
					),
				),
			),
			'MAILCHIMP'      => array(
				'triggers' => array(),
				'actions'  => array(
					array(
						/* translators: Action - Mailchimp */
						'name' => __( 'Add the user to {{an audience}}', 'uncanny-automator' ),
					),
					array(
						/* translators: Action - Mailchimp */
						'name' => __( 'Add {{a tag}} to the user', 'uncanny-automator' ),
					),
					array(
						/* translators: Action - Mailchimp */
						'name' => __( 'Remove {{a tag}} from the user', 'uncanny-automator' ),
					),
					array(
						/* translators: Action - Mailchimp */
						'name' => __( 'Add {{a note}} to the user', 'uncanny-automator' ),
					),
					array(
						/* translators: Action - Mailchimp */
						'name' => __( 'Create and send {{a campaign}}', 'uncanny-automator' ),
					),
					array(
						/* translators: Action - Mailchimp */
						'name' => __( 'Unsubscribe the user from {{an audience}}', 'uncanny-automator' ),
					),
				),
			),
			'MAILPOET'       => array(
				'triggers' => array(),
				'actions'  => array(
					array(
						/* translators: Action - MailPoet */
						'name' => __( 'Remove {{a subscriber}} from {{a list}}', 'uncanny-automator' ),
					),
					array(
						/* translators: Action - MailPoet */
						'name' => __( 'Remove the user from {{a list}}', 'uncanny-automator' ),
					),
				),
			),
			'MSLMS'          => array(
				'triggers' => array(
					array(
						/* translators: Logged-in trigger - MasterStudy LMS */
						'name' => __( 'A user achieves a percentage {{greater than, less than or equal to}} {{a value}} on {{a quiz}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
				),
				'actions'  => array(
					array(
						/* translators: Action - MasterStudy LMS */
						'name' => __( 'Mark {{a quiz}} complete for the user', 'uncanny-automator' ),
					),
					array(
						/* translators: Action - MasterStudy LMS */
						'name' => __( 'Mark {{a lesson}} complete for the user', 'uncanny-automator' ),
					),
					array(
						/* translators: Action - MasterStudy LMS */
						'name' => __( "Reset the user's progress in {{a course}}", 'uncanny-automator' ),
					),
					array(
						/* translators: Action - MasterStudy LMS */
						'name' => __( 'Mark {{a lesson}} not complete for the user', 'uncanny-automator' ),
					),
				),
			),
			'MP'             => array(
				'triggers' => array(
					array(
						/* translators: Logged-in trigger - MemberPress */
						'name' => __( "A user's membership to {{a specific product}} is cancelled", 'uncanny-automator' ),
						'type' => 'logged-in',
					),
				),
				'actions'  => array(
					array(
						/* translators: Action - MemberPress */
						'name' => __( 'Add the user to {{a membership}}', 'uncanny-automator' ),
					),
					array(
						/* translators: Action - MemberPress */
						'name' => __( 'Remove the user from {{a membership}}', 'uncanny-automator' ),
					),
				),
			),
			'MYCRED'         => array(
				'triggers' => array(
					array(
						/* translators: Logged-in trigger - myCred */
						'name' => __( 'A user earns {{a rank}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - myCred */
						'name' => __( "A user's total balance reaches {{a number of}} {{a specific type of}} points", 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - myCred */
						'name' => __( "A user's current balance reaches {{a number of}} {{a specific type of}} points", 'uncanny-automator' ),
						'type' => 'logged-in',
					),
				),
				'actions'  => array(
					array(
						/* translators: Action - myCred */
						'name' => __( 'Revoke {{a number of}} {{a specific type of}} points from the user', 'uncanny-automator' ),
					),
					array(
						/* translators: Action - myCred */
						'name' => __( 'Revoke all {{of a specific type of}} points from the user', 'uncanny-automator' ),
					),
					array(
						/* translators: Action - myCred */
						'name' => __( 'Revoke {{a badge}} from the user', 'uncanny-automator' ),
					),
					array(
						/* translators: Action - myCred */
						'name' => __( "Increase the user's rank for {{a specific type of}} points", 'uncanny-automator' ),
					),
					array(
						/* translators: Action - myCred */
						'name' => __( "Decrease the user's rank for {{a specific type of}} points", 'uncanny-automator' ),
					),
				),
			),
			'NEWSLETTER'     => array(
				'triggers' => array(
					array(
						/* translators: Anonymous trigger - Newsletter */
						'name' => __( '{{A subscription form}} is submitted with {{a specific list}} selected', 'uncanny-automator' ),
						'type' => 'logged-in_and_anonymous',
					),
				),
				'actions'  => array(),
			),
			'NF'             => array(
				'triggers' => array(
					array(
						/* translators: Logged-in trigger - Ninja Forms */
						'name' => __( 'A user submits {{a form}} with {{a specific value}} in {{a specific field}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
				),
				'actions'  => array(
					array(
						/* translators: Action - Ninja Forms */
						'name' => __( 'Register a new user', 'uncanny-automator' ),
					),
				),
			),
			'PMP'            => array(
				'triggers' => array(
					array(
						/* translators: Logged-in trigger - Paid Memberships Pro */
						'name' => __( 'A user renews {{a membership}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
				),
				'actions'  => array(
					array(
						/* translators: Action - Paid Memberships Pro */
						'name' => __( 'Add the user to {{a membership level}}', 'uncanny-automator' ),
					),
					array(
						/* translators: Action - Paid Memberships Pro */
						'name' => __( 'Remove the user from {{a membership level}}', 'uncanny-automator' ),
					),
				),
			),
			'RC'             => array(
				'triggers' => array(
					array(
						/* translators: Logged-in trigger - Restrict Content */
						'name' => __( "A user's membership to {{a specific level}} is cancelled", 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - Restrict Content */
						'name' => __( "A user's membership to {{a specific level}} expires", 'uncanny-automator' ),
						'type' => 'logged-in',
					),
				),
				'actions'  => array(
					array(
						/* translators: Action - Restrict Content */
						'name' => __( 'Remove the user from {{a membership level}}', 'uncanny-automator' ),
					),
				),
			),
			'SLACK'          => array(
				'triggers' => array(),
				'actions'  => array(
					array(
						/* translators: Action - Slack */
						'name' => __( 'Send a message to {{a channel}}', 'uncanny-automator' ),
					),
					array(
						/* translators: Action - Slack */
						'name' => __( 'Send a private message to {{a Slack user}}', 'uncanny-automator' ),
					),
					array(
						/* translators: Action - Slack */
						'name' => __( 'Create {{a channel}}', 'uncanny-automator' ),
					),
				),
			),
			'EC'             => array(
				'triggers' => array(
					array(
						/* translators: Logged-in trigger - The Events Calendar */
						'name' => __( 'A user attends {{an event}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
				),
				'actions'  => array(
					array(
						/* translators: Action - The Events Calendar */
						'name' => __( 'RSVP for {{an event}}', 'uncanny-automator' ),
					),
				),
			),
			'TUTORLMS'       => array(
				'triggers' => array(
					array(
						/* translators: Logged-in trigger - Tutor LMS */
						'name' => __( 'A user achieves a percentage {{greater than, less than or equal to}} {{a value}} on a quiz', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - Tutor LMS */
						'name' => __( 'A user is enrolled in {{a course}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
				),
				'actions'  => array(
					array(
						/* translators: Action - Tutor LMS */
						'name' => __( 'Mark {{a lesson}} complete for the user', 'uncanny-automator' ),
					),
					array(
						/* translators: Action - Tutor LMS */
						'name' => __( 'Mark {{a course}} complete for the user', 'uncanny-automator' ),
					),
					array(
						/* translators: Action - Tutor LMS */
						'name' => __( 'Enroll the user in {{a course}}', 'uncanny-automator' ),
					),
					array(
						/* translators: Action - Tutor LMS */
						'name' => __( "Reset the user's progress in {{a course}}", 'uncanny-automator' ),
					),
				),
			),
			'TWILIO'         => array(
				'triggers' => array(),
				'actions'  => array(
					array(
						/* translators: Action - Twilio */
						'name' => __( 'Send an SMS message to {{a number}}', 'uncanny-automator' ),
					),
				),
			),
			'UM'             => array(
				'triggers' => array(
					array(
						/* translators: Logged-in trigger - Ultimate Member */
						'name' => __( 'A user registers with {{a form}} with {{a specific value}} in {{a specific field}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
				),
				'actions'  => array(
					array(
						/* translators: Action - Ultimate Member */
						'name' => __( "Set the user's role to {{a specific role}}", 'uncanny-automator' ),
					),
				),
			),
			'UPSELL'         => array(
				'triggers' => array(
					array(
						/* translators: Logged-in trigger - Upsell Plugin */
						'name' => __( 'A user subscribes to {{a product}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
				),
				'actions'  => array(),
			),
			'WISHLISTMEMBER' => array(
				'triggers' => array(
					array(
						/* translators: Logged-in trigger - Wishlist Member */
						'name' => __( 'A user submits {{a registration form}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - Wishlist Member */
						'name' => __( 'A user submits a registration form with {{a specific value}} in {{a specific field}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
				),
				'actions'  => array(
					array(
						/* translators: Action - Wishlist Member */
						'name' => __( 'Remove the user from {{a membership level}}', 'uncanny-automator' ),
					),
				),
			),
			'WC'             => array(
				'triggers' => array(
					array(
						/* translators: Logged-in trigger - WooCommerce */
						'name' => __( 'A user completes {{an order}} with a value {{greater than, less than or equal to}} {{an amount}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - WooCommerce */
						'name' => __( "A user's order status changes to {{a specific status}}", 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Anonymous trigger - WooCommerce */
						'name' => __( 'A guest completes an order with {{a product}}', 'uncanny-automator' ),
						'type' => 'anonymous',
					),
					array(
						/* translators: Logged-in trigger - WooCommerce */
						'name' => __( 'A user completes an order with a product with {{a tag}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - WooCommerce */
						'name' => __( 'A user completes an order with a product in {{a category}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - WooCommerce */
						'name' => __( 'A user purchases {{a variable product}} with {{a variation}} selected', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Anonymous trigger - WooCommerce */
						'name' => __( "A guest order's status is changed to {{a specific status}}", 'uncanny-automator' ),
						'type' => 'anonymous',
					),
					array(
						/* translators: Logged-in trigger - WooCommerce */
						'name' => __( 'A user reviews {{a product}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - WooCommerce */
						'name' => __( "A user's review of {{a product}} is approved", 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - WooCommerce */
						'name' => __( 'A user completes an order with a specific quantity of {{a product}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - WooCommerce Memberships */
						'name' => __( "A user's access to {{a membership plan}} is cancelled", 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - WooCommerce Memberships */
						'name' => __( "A user's access to {{a membership plan}} expires", 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - WooCommerce ShipStation */
						'name' => __( 'An order with a total {{greater than, less than or equal to}} {{a specific amount}} is shipped', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - WooCommerce ShipStation */
						'name' => __( 'An order with {{a specific product}} is shipped', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - WooCommerce Subscriptions */
						'name' => __( "A user's subscription to {{a product}} expires", 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - WooCommerce Subscriptions */
						'name' => __( 'A user cancels a subscription to {{a product}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - WooCommerce Subscriptions */
						'name' => __( 'A user renews a subscription to {{a product}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
				),
				'actions'  => array(
					array(
						/* translators: Action - WooCommerce */
						'name' => __( 'Generate and email {{a coupon code}} to the user', 'uncanny-automator' ),
					),
					array(
						/* translators: Action - WooCommerce Memberships */
						'name' => __( 'Remove the user from {{a membership plan}}', 'uncanny-automator' ),
					),
				),
			),
			'WP'             => array(
				'triggers' => array(
					array(
						/* translators: Logged-in trigger - WordPress Core */
						'name' => __( 'A user is created', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - WordPress Core */
						'name' => __( '{{A post}} is updated', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - WordPress Core */
						'name' => __( 'A user resets their password', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Anonymous trigger - WordPress Core */
						'name' => __( 'A guest comment is submitted on {{a post}}', 'uncanny-automator' ),
						'type' => 'anonymous',
					),
					array(
						/* translators: Anonymous trigger - WordPress Core */
						'name' => __( 'A guest comment on {{a post}} is approved', 'uncanny-automator' ),
						'type' => 'anonymous',
					),
					array(
						/* translators: Logged-in trigger - WordPress Core */
						'name' => __( "A user's post is set to {{a specific status}}", 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - WordPress Core */
						'name' => __( 'A post in {{a specific taxonomy}} is updated', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - WordPress Core */
						'name' => __( "A user's comment on {{a post}} is approved", 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - WordPress Core */
						'name' => __( '{{A post}} of {{a specific type}} is moved to the trash', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - WordPress Core */
						'name' => __( "A user's {{profile field}} is updated", 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - WordPress Core */
						'name' => __( "A user's {{specific}} meta key is updated", 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - WordPress Core */
						'name' => __( "A user's role changes from {{a specific role}} to {{a specific role}}", 'uncanny-automator' ),
						'type' => 'logged-in',
					),
				),
				'actions'  => array(
					array(
						/* translators: Action - WordPress Core */
						'name' => __( "Remove {{a role}} from the user's roles", 'uncanny-automator' ),
					),
					array(
						/* translators: Action - WordPress Core */
						'name' => __( 'Set {{post meta}}', 'uncanny-automator' ),
					),
					array(
						/* translators: Action - WordPress Core */
						'name' => __( 'Set {{user meta}}', 'uncanny-automator' ),
					),
					array(
						/* translators: Action - WordPress Core */
						'name' => __( 'Update {{the user}}', 'uncanny-automator' ),
					),
				),
			),
			'WPCW'           => array(
				'triggers' => array(),
				'actions'  => array(
					array(
						/* translators: Action - WP Courseware */
						'name' => __( 'Remove the user from {{a course}}', 'uncanny-automator' ),
					),
					array(
						/* translators: Action - WP Courseware */
						'name' => __( 'Enroll the user in {{a course}}', 'uncanny-automator' ),
					),
				),
			),
			'WF'             => array(
				'triggers' => array(
					array(
						/* translators: Logged-in trigger - WP Fusion */
						'name' => __( '{{A tag}} is added to a user', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - WP Fusion */
						'name' => __( '{{A tag}} is removed from a user', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
				),
				'actions'  => array(
					array(
						/* translators: Action - WP Fusion */
						'name' => __( 'Remove {{a tag}} from the user', 'uncanny-automator' ),
					),
				),
			),
			'WPLMS'          => array(
				'triggers' => array(),
				'actions'  => array(
					array(
						/* translators: Action - WP LMS */
						'name' => __( 'Enroll the user in {{a course}}', 'uncanny-automator' ),
					),
				),
			),
			'WPUSERMANAGER'  => array(
				'triggers' => array(
					array(
						/* translators: Logged-in trigger - WP User Manager */
						'name' => __( 'A user registers using {{a form}} with {{a specific value}} in {{a specific field}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - WP User Manager */
						'name' => __( 'A user is approved', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - WP User Manager */
						'name' => __( 'A user is rejected', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - WP User Manager */
						'name' => __( 'A user verifies their email address', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - WP User Manager */
						'name' => __( 'A user updates their account information', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - WP User Manager */
						'name' => __( 'A user joins {{a group}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - WP User Manager */
						'name' => __( 'A user leaves {{a group}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - WP User Manager */
						'name' => __( 'A user is approved to join {{a private group}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
					array(
						/* translators: Logged-in trigger - WP User Manager */
						'name' => __( 'A user is rejected from joining {{a private group}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
				),
				'actions'  => array(),
			),
			'WPPOLLS'        => array(
				'triggers' => array(
					array(
						/* translators: Logged-in trigger - WP-Polls */
						'name' => __( 'A user submits a poll with {{a specific choice}} selected', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
				),
				'actions'  => array(),
			),
			'WPF'            => array(
				'triggers' => array(
					array(
						/* translators: Logged-in trigger - WPForms */
						'name' => __( 'A user submits {{a form}} with {{a specific value}} in {{a specific field}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
				),
				'actions'  => array(
					array(
						/* translators: Action - WPForms */
						'name' => __( 'Register a new user', 'uncanny-automator' ),
					),
				),
			),
			'WPFORO'         => array(
				'triggers' => array(
					array(
						/* translators: Logged-in trigger - wpForo */
						'name' => __( 'A user replies to {{a topic}} in {{a forum}}', 'uncanny-automator' ),
						'type' => 'logged-in',
					),
				),
				'actions'  => array(
					array(
						/* translators: Action - wpForo */
						'name' => __( 'Remove the user from {{a group}}', 'uncanny-automator' ),
					),
				),
			),
			'ZAPIER'         => array(
				'triggers' => array(
					array(
						/* translators: Anonymous trigger - Zapier */
						'name' => __( 'Receive data from Zapier webhook', 'uncanny-automator' ),
						'type' => 'anonymous',
					),
				),
				'actions'  => array(),
			),
			'ZOOM'           => array(
				'triggers' => array(),
				'actions'  => array(
					array(
						/* translators: Action - Zoom Meetings */
						'name' => __( 'Add the user to {{a meeting}}', 'uncanny-automator' ),
					),
					array(
						/* translators: Action - Zoom Meetings */
						'name' => __( 'Remove the user from {{a meeting}}', 'uncanny-automator' ),
					),
				),
			),
			'ZOOMWEBINAR'    => array(
				'triggers' => array(),
				'actions'  => array(
					array(
						/* translators: Action - Zoom Webinars */
						'name' => __( 'Add the user to {{a webinar}}', 'uncanny-automator' ),
					),
					array(
						/* translators: Action - Zoom Webinars */
						'name' => __( 'Remove the user from {{a webinar}}', 'uncanny-automator' ),
					),
				),
			),
		);

		return $pro_items;
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
			self::delete_recipe_logs( $post_ID );

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

					self::delete_trigger_logs( $child->ID );
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

					self::delete_action_logs( $child->ID );
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

					self::delete_closure_logs( $child->ID );
				}
			}
		} elseif ( $post && 'uo-action' === $post->post_type ) {
			self::delete_action_logs( $post_ID );
		} elseif ( $post && 'uo-trigger' === $post->post_type ) {
			self::delete_trigger_logs( $post_ID );
		} elseif ( $post && 'uo-closure' === $post->post_type ) {
			self::delete_closure_logs( $post_ID );
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
						'post_status' => 'draft',
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
						'post_status' => 'draft',
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
						'post_status' => 'draft',
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
	public static function delete_recipe_logs( $post_ID ) {
		global $wpdb;

		// delete from uap_recipe_log
		$wpdb->delete( $wpdb->prefix . 'uap_recipe_log', array( 'automator_recipe_id' => $post_ID ) );
	}

	/**
	 * Delete all logs and meta for triggers
	 *
	 * @param $post_ID
	 */
	public static function delete_trigger_logs( $post_ID ) {
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
	public static function delete_action_logs( $post_ID ) {
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
	public static function delete_closure_logs( $post_ID ) {
		global $wpdb;

		// delete from uap_closure_log
		$wpdb->delete( $wpdb->prefix . 'uap_closure_log', array( 'automator_closure_id' => $post_ID ) );

		// delete from uap_closure_log_meta
		$wpdb->delete( $wpdb->prefix . 'uap_closure_log_meta', array( 'automator_closure_id' => $post_ID ) );
	}

	/**
	 * Update recipe status to draft and send admin notification email if pro is not active and the recipe has more
	 * than one trigger
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
							'post_status' => 'draft',
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
						'post_status' => 'draft',
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
			Hi,
		</p>
		<p>
			This email is to let you know that the recipe "<?php echo $recipe->post_title; ?>" was automatically set to
			draft status because it contains multiple triggers and the Uncanny Automator Pro plugin is either not
			installed or not activated. To reactivate the recipe, ensure the Uncanny Automator Pro plugin is active,
			then edit the recipe to switch its status to Live.
		</p>
		<p>
			Recipe URL: <a
					href="<?php echo get_edit_post_link( $recipe->ID ); ?>"><?php echo $recipe->post_title; ?></a>
		</p>
		<p>
			The Uncanny Automator Bot
		</p>

		<?php

		$body = ob_get_clean();

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		wp_mail( $to, $subject, $body, $headers );
	}
}


