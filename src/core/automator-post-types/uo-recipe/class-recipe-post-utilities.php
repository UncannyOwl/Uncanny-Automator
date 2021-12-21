<?php

namespace Uncanny_Automator;

/**
 * Class Recipe_Post_Functions
 *
 * @package Uncanny_Automator
 */
class Recipe_Post_Utilities {

	/**
	 * Recipe_Post_Functions constructor.
	 */
	public function __construct() {
		// Add the custom columns to the uo-recipe.
		add_filter( 'manage_uo-recipe_posts_columns', array( $this, 'set_custom_columns' ) );

		// Add the data to the custom columns for uo-recipe.
		add_action( 'manage_uo-recipe_posts_custom_column', array( $this, 'custom_column' ), 10, 2 );

		// Add admin post creation scripts.
		add_action( 'admin_enqueue_scripts', array( $this, 'automator_recipe_scripts' ) );

		// Change to before delete post.
		add_action( 'delete_post', array( $this, 'delete_triggers_actions' ), 10, 1 );

		// Draft when recipe moved to trash.
		add_action( 'wp_trash_post', array( $this, 'draft_triggers_actions' ), 10, 1 );

		// Prepopulate recipe from a URL query (only for admins).
		if ( is_admin() ) {
			add_action( 'wp_insert_post', array( 'Uncanny_Automator\Populate_From_Query', 'maybe_populate' ), 9, 3 );
		}

		// Change Default new recipe post from auto-draft to draft.
		add_action( 'wp_insert_post', array( $this, 'change_default_post_status' ), 10, 3 );

		// Add recipe and redirect to it in edit mode.
		add_filter( 'replace_editor', array( $this, 'redirect_to_recipe' ), 20, 2 );

		// Remove WordPress default publish box.
		add_action( 'admin_menu', array( $this, 'remove_publish_box' ) );
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
			wp_safe_redirect( admin_url( 'post.php?post=' . $post->ID . '&action=edit' ) );
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

		if ( 'uo-recipe' !== (string) $post->post_type ) {
			return;
		}
		if ( 'auto-draft' !== (string) $post->post_status ) {
			return;
		}

		// Update post
		$args = array(
			'ID'          => $post_ID,
			'post_status' => 'draft',
			'post_title'  => '',
		);

		// Update the post into the database
		wp_update_post( $args );

		// Save automator version for future use in case
		// something has to be changed for older recipes
		update_post_meta( $post_ID, 'uap_recipe_version', Utilities::automator_get_version() );
		update_post_meta( $post_ID, 'recipe_completions_allowed', '-1' );
		update_post_meta( $post_ID, 'recipe_max_completions_allowed', '-1' );
	}

	/**
	 * Enqueue scripts only on custom post type edit pages
	 *
	 * @param $hook
	 */
	public function automator_recipe_scripts( $hook ) {
		// Add global assets. Load in all admin pages
		// Utilities::legacy_automator_enqueue_global_assets();

		// Add scripts ONLY to recipe custom post type
		if ( 'post-new.php' !== $hook && 'post.php' !== $hook ) {
			return;
		}
		if ( 'uo-recipe' !== (string) get_post_type() ) {
			return;
		}

		// Add CodeMirror
		$this->assets_vendor_codemirror();

		// Add TinyMCE
		$this->assets_vendor_tinymce();

		// Recipe UI scripts
		wp_register_script(
			'uncanny-automator-ui',
			Utilities::automator_get_recipe_dist( 'bundle.min.js' ),
			array(
				'jquery',
				'uap-admin',
				'uap-codemirror',
				'uap-codemirror-autorefresh',
				'uap-codemirror-no-newlines',
				'uap-codemirror-searchcursor',
				'uap-codemirror-search',
				'uap-codemirror-placeholder',
			),
			Utilities::automator_get_version(),
			true
		);

		wp_localize_script(
			'uncanny-automator-ui',
			'UncannyAutomator',
			$this->assets_get_automator_main_object()
		);

		wp_enqueue_script( 'uncanny-automator-ui' );

		wp_enqueue_style(
			'uncanny-automator-ui',
			Utilities::automator_get_recipe_dist( 'bundle.min.css' ),
			array(
				'uap-admin',
				'uap-codemirror',
			),
			Utilities::automator_get_version()
		);
	}

	/**
	 *
	 */
	private function assets_vendor_codemirror() {
		wp_enqueue_style(
			'uap-codemirror',
			Utilities::automator_get_vendor_asset( 'codemirror/css/codemirror.min.css' ),
			array(),
			Utilities::automator_get_version()
		);

		wp_enqueue_script(
			'uap-codemirror',
			Utilities::automator_get_vendor_asset( 'codemirror/js/codemirror.min.js' ),
			array(
				'jquery',
			),
			Utilities::automator_get_version(),
			true
		);

		wp_enqueue_script(
			'uap-codemirror-autorefresh',
			Utilities::automator_get_vendor_asset( 'codemirror/js/autorefresh.js' ),
			array(
				'jquery',
				'uap-codemirror',
			),
			Utilities::automator_get_version(),
			true
		);

		wp_enqueue_script(
			'uap-codemirror-no-newlines',
			Utilities::automator_get_vendor_asset( 'codemirror/js/no-newlines.js' ),
			array(
				'jquery',
				'uap-codemirror',
			),
			Utilities::automator_get_version(),
			true
		);

		wp_enqueue_script(
			'uap-codemirror-placeholder',
			Utilities::automator_get_vendor_asset( 'codemirror/js/placeholder.js' ),
			array(
				'jquery',
				'uap-codemirror',
			),
			Utilities::automator_get_version(),
			true
		);

		wp_enqueue_script(
			'uap-codemirror-searchcursor',
			Utilities::automator_get_vendor_asset( 'codemirror/js/searchcursor.js' ),
			array(
				'jquery',
				'uap-codemirror',
			),
			Utilities::automator_get_version(),
			true
		);

		wp_enqueue_script(
			'uap-codemirror-search',
			Utilities::automator_get_vendor_asset( 'codemirror/js/search.js' ),
			array(
				'jquery',
				'uap-codemirror',
				'uap-codemirror-searchcursor',
			),
			Utilities::automator_get_version(),
			true
		);
	}

	/**
	 *
	 */
	private function assets_vendor_tinymce() {
		wp_enqueue_editor();
		wp_enqueue_media();
	}

	/**
	 * @return mixed|void
	 */
	private function assets_get_automator_main_object() {
		global $post;

		// $post return $post->ID as a string, Our JS expects an int... change it
		$post_id = (int) $post->ID;

		// API data
		$completions_allowed     = get_post_meta( $post_id, 'recipe_completions_allowed', true );
		$max_completions_allowed = get_post_meta( $post_id, 'recipe_max_completions_allowed', true );
		$recipe_type             = get_post_meta( $post_id, 'uap_recipe_type', true );

		// Get source
		$source = get_post_meta( $post_id, 'source', true );
		// Create fields array
		$fields = array(
			'existingUser' => array(),
			'newUser'      => array(),
		);
		// Check if the user defined a valid source
		if ( in_array( $source, array( 'existingUser', 'newUser' ), false ) ) {
			// If the user did it, then add the fields
			$fields[ $source ] = get_post_meta( $post_id, 'fields', true );
		}

		$editable_roles = get_editable_roles();
		$roles          = array();
		foreach ( $editable_roles as $role_key => $role_data ) {
			$roles[ $role_key ] = $role_data['name'];
		}

		// Remove any cached extra options
		delete_post_meta( $post_id, 'extra_options' );
		$count     = Automator()->get->recipe_completed_times( $post_id );
		$url       = add_query_arg(
			array(
				'post_type' => 'uo-recipe',
				'page'      => 'uncanny-automator-recipe-log',
				'recipe_id' => $post_id,
			),
			admin_url( 'edit.php' )
		);
		$api_setup = array(
			'wp'                  => false,
			'restURL'             => esc_url_raw( rest_url() . AUTOMATOR_REST_API_END_POINT ),
			'siteURL'             => get_site_url(),
			'nonce'               => \wp_create_nonce( 'wp_rest' ),
			'dev'                 => array(
				'developerMode'  => (bool) AUTOMATOR_DEBUG_MODE,
				'recipesUrl'     => admin_url( 'edit.php?post_type=uo-recipe' ),
				'debuggingURL'   => 'https://automatorplugin.com/knowledge-base/troubleshooting-plugin-errors/?utm_source=uncanny_automator&utm_medium=recipe-wizard-error-modal&utm_content=learn-more-debugging',
				'supportPage'    => 'https://automatorplugin.com/automator-support/',
				'permalinksURL'  => esc_url( admin_url( 'options-permalink.php' ) ),
				'automatorTools' => admin_url( 'edit.php?post_type=uo-recipe&page=uncanny-automator-tools' ),
			),
			'integrations'        => Automator()->get_integrations(),
			'triggers'            => Automator()->get_triggers(),
			'actions'             => Automator()->get_actions(),
			'closures'            => Automator()->get_closures(),
			'i18n'                => Automator()->i18n->get_all(),
			'recipes_object'      => Automator()->get_recipes_data( true, $post_id ),
			'version'             => Utilities::automator_get_version(),
			'proVersion'          => defined( 'AUTOMATOR_PRO_FILE' ) ? \Uncanny_Automator_Pro\InitializePlugin::PLUGIN_VERSION : '',
			'proFeatures'         => $this->get_pro_items(),
			'recipe'              => array(
				'id'               => $post_id,
				'author'           => $post->post_author,
				'status'           => $post->post_status,
				'type'             => empty( $recipe_type ) ? null : $recipe_type,
				'isLive'           => 'publish' === $post->post_status,
				'requiresUserData' => Automator()->get->get_recipe_requires_user( $post_id ),
				'errorMode'        => false,
				'isValid'          => false,
				'userSelector'     => array(
					'source'    => $source,
					'data'      => $fields,
					'isValid'   => false,
					'resources' => array(
						'roles' => $roles,
					),
				),
				'hasLive'          => array(
					'trigger' => false,
					'action'  => false,
					'closure' => false,
				),
				'message'          => array(
					'error'   => '',
					'warning' => '',
				),
				'items'            => array(),
				'publish'          => array(
					'timesPerUser'           => empty( $completions_allowed ) ? 1 : $completions_allowed,
					'timesPerRecipe'         => empty( $max_completions_allowed ) ? '-1' : $max_completions_allowed,
					'recipeRunTimes'         => $count,
					'recipeRunTimesUrl'      => $url,
					'recipeRunTimesViewLogs' => __( 'View logs', 'uncanny-automator' ),
					'createdOn'              => date_i18n( 'M j, Y @ G:i', get_the_time( 'U', $post_id ) ),
					'moveToTrash'            => get_delete_post_link( $post_id ),
					'copyToDraft'            => sprintf( '%s?action=%s&post=%d&return_to_recipe=yes&_wpnonce=%s', admin_url( 'edit.php' ), 'copy_recipe_parts', $post_id, wp_create_nonce( 'Aut0Mat0R' ) ),
				),
			),
			'format'              => array(
				'date' => get_option( 'date_format' ),
			),
			'connectApiUrl'       => sprintf( '%s%s?redirect_url=%s', AUTOMATOR_FREE_STORE_URL, AUTOMATOR_FREE_STORE_CONNECT_URL, rawurlencode( admin_url( 'admin.php?page=uncanny-automator-dashboard' ) ) ),
			'dashboardUrl'        => admin_url( 'admin.php?page=uncanny-automator-dashboard' ),
			'hasAccountConnected' => ( ! Admin_Menu::is_automator_connected() ? false : true ),
			'hasValidProLicense'  => ( defined( 'AUTOMATOR_PRO_FILE' ) && 'valid' === get_option( 'uap_automator_pro_license_status' ) ),
			'licenseUrl'          => site_url( 'wp-admin/edit.php?post_type=uo-recipe&page=uncanny-automator-license-activation' ),
			'marketing'           => array(
				'utmR' => get_option( 'uncannyautomator_source', '' ),
			),
		);

		$api_setup = apply_filters_deprecated( 'uap_api_setup', array( $api_setup ), '3.0', 'automator_api_setup' ); // deprecate
		return apply_filters( 'automator_api_setup', $api_setup );
	}

	/**
	 * List of Pro features to upsell Automator Pro
	 *
	 * @return array
	 */
	private function get_pro_items() {

		return Utilities::get_pro_items_list();
	}

	/**
	 * Delete all children triggers and actions of recipe
	 *
	 * @param $post_ID
	 */
	public function delete_triggers_actions( $post_ID ) {

		$post = get_post( $post_ID );
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		if ( 'uo-recipe' === $post->post_type ) {

			// delete recipe logs
			self::delete_recipe_logs( $post_ID );
		}

		if ( 'uo-action' === (string) $post->post_type ) {
			Automator()->db->action->delete( $post_ID );
		}

		if ( 'uo-trigger' === (string) $post->post_type ) {
			Automator()->db->trigger->delete( $post_ID );
		}

		if ( 'uo-closure' === (string) $post->post_type ) {
			Automator()->db->closure->delete( $post_ID );
		}
	}

	/**
	 * Delete all logs and meta for triggers
	 *
	 * @param $post_ID
	 */
	public static function delete_recipe_logs( $post_ID ) {
		Automator()->db->recipe->delete( $post_ID );

		$args = array(
			'post_parent'    => $post_ID,
			'post_status'    => 'any',
			'post_type'      => 'uo-trigger',
			'posts_per_page' => 999, //phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
		);

		$children = get_children( $args );

		if ( is_array( $children ) && count( $children ) > 0 ) {

			// Delete all the Children of the Parent Page
			foreach ( $children as $child ) {

				wp_delete_post( $child->ID, true );

				Automator()->db->trigger->delete( $post_ID );
			}
		}

		$args = array(
			'post_parent'    => $post_ID,
			'post_status'    => 'any',
			'post_type'      => 'uo-action',
			'posts_per_page' => 999, //phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
		);

		$children = get_children( $args );

		if ( is_array( $children ) && count( $children ) > 0 ) {

			// Delete all the Children of the Parent Page
			foreach ( $children as $child ) {

				wp_delete_post( $child->ID, true );

				Automator()->db->action->delete( $post_ID );
			}
		}

		$args = array(
			'post_parent'    => $post_ID,
			'post_status'    => 'any',
			'post_type'      => 'uo-closure',
			'posts_per_page' => 999, //phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
		);

		$children = get_children( $args );

		if ( is_array( $children ) && count( $children ) > 0 ) {

			// Delete all the Children of the Parent Page
			foreach ( $children as $child ) {

				wp_delete_post( $child->ID, true );

				Automator()->db->closure->delete( $post_ID );
			}
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
				'post_parent'    => $post->ID,
				'post_status'    => 'any',
				'post_type'      => 'uo-trigger',
				'posts_per_page' => 999, //phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
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
				'post_parent'    => $post->ID,
				'post_status'    => 'any',
				'post_type'      => 'uo-action',
				'posts_per_page' => 999, //phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
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
				'post_parent'    => $post->ID,
				'post_status'    => 'any',
				'post_type'      => 'uo-closure',
				'posts_per_page' => 999, //phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
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
	 * Add data to custom columns in the recipe list
	 *
	 * @param $column
	 * @param $post_id
	 */
	public function custom_column( $column, $post_id ) {

		global $wpdb;

		switch ( $column ) {
			case 'triggers':
				$trigger_titles = $wpdb->get_results( $wpdb->prepare( "SELECT post_status, post_title FROM {$wpdb->posts} WHERE post_parent = %d AND post_type = %s", $post_id, 'uo-trigger' ) );
				?>
				<div class="uap">
					<div class="uo-post-column__list">
						<?php
						foreach ( $trigger_titles as $title ) {
							?>
							<div class="uo-post-column__item">
								<?php echo 'publish' === $title->post_status ? '<span class="dashicons dashicons-yes-alt recipe-ui-dash" title="Live"></span>' : '<span class="dashicons dashicons-warning recipe-ui-dash" title="Draft"></span>'; ?>
								<?php echo esc_html( $title->post_title ); ?>
							</div>
						<?php } ?>
					</div>
				</div>
				<?php

				break;
			case 'actions':
				$action_titles = $wpdb->get_results( $wpdb->prepare( "SELECT post_status, post_title FROM {$wpdb->posts} WHERE post_parent=%d AND post_type=%s", $post_id, 'uo-action' ) );
				?>
				<div class="uap">
					<div class="uo-post-column__list">
						<?php foreach ( $action_titles as $title ) { ?>
							<div class="uo-post-column__item">
								<?php echo 'publish' === $title->post_status ? '<span class="dashicons dashicons-yes-alt recipe-ui-dash" title="Live"></span>' : '<span class="dashicons dashicons-warning recipe-ui-dash" title="Draft"></span>'; ?>
								<?php echo esc_html( $title->post_title ); ?>
							</div>
						<?php } ?>
					</div>
				</div>
				<?php
				break;
			case 'runs':
				$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(run_number) FROM {$wpdb->prefix}uap_recipe_log WHERE automator_recipe_id=%d AND completed = %d", $post_id, 1 ) );
				$url   = add_query_arg(
					array(
						'post_type' => 'uo-recipe',
						'page'      => 'uncanny-automator-recipe-log',
						'recipe_id' => $post_id,
					),
					admin_url( 'edit.php' )
				);
				echo sprintf( '<a href="%s">%s</a>', $url, $count ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				break;
			case 'type':
				$type = get_post_meta( $post_id, 'uap_recipe_type', true );
				echo empty( $type ) ? esc_html__( 'User', 'uncanny-automator' ) : esc_html( ucfirst( $type ) );
				break;
			case 'recipe_status':
				$post_status = get_post_status( $post_id );
				echo 'publish' === $post_status ? '<span class="dashicons dashicons-yes-alt recipe-ui-dash" title="Live"></span>' . esc_html__( 'Live', 'uncanny-automator' ) : '<span class="dashicons dashicons-warning recipe-ui-dash" title="Draft"></span>' . esc_html__( 'Draft', 'uncanny-automator' );

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

				//$new_columns['type']     = esc_attr__( 'Recipe type', 'uncanny-automator' );
				$new_columns['triggers'] = esc_attr__( 'Triggers', 'uncanny-automator' );
				$new_columns['actions']  = esc_attr__( 'Actions', 'uncanny-automator' );
				/* translators: The number of times a recipe was completed */
				$new_columns['runs']          = esc_attr__( 'Completed runs', 'uncanny-automator' );
				$new_columns['recipe_status'] = esc_attr__( 'Recipe status', 'uncanny-automator' );
				$new_columns[ $key ]          = $column;

			} else {
				$new_columns[ $key ] = $column;
			}
		}

		return $new_columns;
	}
}
