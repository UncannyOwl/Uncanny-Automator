<?php

namespace Uncanny_Automator;

use function Uncanny_Automator\App\Infrastructure\automator_license_manager;

/**
 * Class Recipe_Post_Functions
 *
 * @package Uncanny_Automator
 */
class Recipe_Post_Utilities {

	/**
	 * Post meta holding a trigger's or action's status from before its recipe went to the trash.
	 *
	 * @since 8.0.0
	 *
	 * @var string
	 */
	const STATUS_BEFORE_TRASH_META = '_automator_status_before_recipe_trash';

	/**
	 * Each listed recipe's items, read once per row for both the Triggers and Actions columns.
	 *
	 * @var array<int, array>
	 */
	private $recipe_items = array();

	/**
	 * Recipe_Post_Functions constructor.
	 */
	public function __construct() {
		// Add the custom columns to the uo-recipe.
		add_filter(
			'manage_uo-recipe_posts_columns',
			array(
				$this,
				'set_custom_columns',
			)
		);

		// Add the data to the custom columns for uo-recipe.
		add_action(
			'manage_uo-recipe_posts_custom_column',
			array(
				$this,
				'custom_column',
			),
			10,
			2
		);

		// Add admin post creation scripts.
		add_action(
			'admin_enqueue_scripts',
			array(
				$this,
				'automator_recipe_scripts',
			),
			999
		);

		// Draft every trigger and action when a recipe goes to the trash, and put each back
		// when it leaves the trash. The status change covers every way a recipe is trashed or
		// restored: wp_trash_post() and wp_untrash_post() (All recipes, WP-CLI's `wp post delete`)
		// and a status written with wp_update_post() (the REST and MCP recipe tools,
		// `wp post update`), which never fire wp_trash_post or untrashed_post.
		add_action(
			'transition_post_status',
			array(
				$this,
				'follow_recipe_trash',
			),
			10,
			3
		);

		// Prepopulate recipe from a URL query (only for admins). Must run AFTER
		// change_default_post_status (@10): population goes through the src/app
		// recipe aggregate, whose Recipe_Status VO rejects the transient
		// auto-draft state — the flip to draft has to land first.
		if ( is_admin() ) {
			add_action(
				'wp_insert_post',
				array(
					'Uncanny_Automator\Populate_From_Query',
					'maybe_populate',
				),
				11,
				3
			);
		}

		// Change Default new recipe post from auto-draft to draft.
		add_action(
			'wp_insert_post',
			array(
				$this,
				'change_default_post_status',
			),
			10,
			3
		);

		// Add recipe and redirect to it in edit mode.
		add_filter(
			'replace_editor',
			array(
				$this,
				'redirect_to_recipe',
			),
			20,
			2
		);

		// Remove WordPress default publish box.
		add_action( 'admin_menu', array( $this, 'remove_publish_box' ) );

		add_filter(
			'automator_asset_script_dependencies_uap-admin',
			function ( $dependencies ) {
				if ( ! Automator()->helpers->recipe->is_edit_page() ) {
					return $dependencies;
				}

				$dependencies[] = 'jquery';
				$dependencies[] = 'uap-codemirror';
				$dependencies[] = 'uap-codemirror-autorefresh';
				$dependencies[] = 'uap-codemirror-no-newlines';
				$dependencies[] = 'uap-codemirror-searchcursor';
				$dependencies[] = 'uap-codemirror-search';
				$dependencies[] = 'uap-codemirror-placeholder';
				$dependencies[] = 'uap-codemirror-mode-xml';
				$dependencies[] = 'uap-codemirror-mode-css';
				$dependencies[] = 'uap-codemirror-mode-javascript';
				$dependencies[] = 'uap-codemirror-mode-htmlmixed';
				$dependencies[] = 'uap-tinymce-plugin-fullpage';

				return $dependencies;
			}
		);

		// Delete a recipe's runs when the recipe is deleted for good, before its steps go (priority 10).
		add_action( 'before_delete_post', array( $this, 'purge_deleted_recipe_runs' ), 5, 2 );
	}

	/**
	 * @param $value
	 * @param $post
	 *
	 * @return mixed
	 */
	public function redirect_to_recipe( $value, $post ) {

		global $current_screen;

		if ( $current_screen && 'add' === $current_screen->action && AUTOMATOR_POST_TYPE_RECIPE === $current_screen->post_type ) {
			wp_safe_redirect( admin_url( 'post.php?post=' . $post->ID . '&action=edit' ) );
			die();
		}

		return $value;
	}

	/**
	 * Remove the WP standard Post publish metabox
	 */
	public function remove_publish_box() {
		remove_meta_box( 'submitdiv', AUTOMATOR_POST_TYPE_RECIPE, 'side' );
	}

	/**
	 * @param $post_ID
	 * @param $post
	 * @param $update
	 */
	public function change_default_post_status( $post_ID, $post, $update ) {

		if ( AUTOMATOR_POST_TYPE_RECIPE !== (string) $post->post_type ) {
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
		// Add scripts ONLY to recipe custom post type
		if ( 'post-new.php' !== $hook && 'post.php' !== $hook ) {
			return;
		}
		if ( AUTOMATOR_POST_TYPE_RECIPE !== (string) get_post_type() ) {
			return;
		}

		// Add TinyMCE plugins
		$this->assets_vendor_tinymce_plugins();

		// Add CodeMirror
		$this->assets_vendor_codemirror();

		// Add TinyMCE
		$this->assets_vendor_tinymce();

		// Remove conflictive assets
		// These shouldn't load in the recipe builder
		self::dequeue_conflictive_assets();
	}

	/**
	 * Enqueue additional TinyMCE plugins for WordPress' editor
	 */
	private function assets_vendor_tinymce_plugins() {
		Utilities::enqueue_legacy_vendor_asset(
			'uap-tinymce-plugin-fullpage',
			'tinymce/plugins/fullpage/plugin.min.js',
			array(
				'wp-tinymce',
			)
		);
	}

	/**
	 * Enqueue CodeMirror assets
	 */
	private function assets_vendor_codemirror() {
		// Base handle for the codemirror assets
		$handle = 'uap-codemirror';

		Utilities::enqueue_legacy_vendor_asset(
			$handle,
			'codemirror/css/codemirror.min.css'
		);

		Utilities::enqueue_legacy_vendor_asset(
			$handle,
			'codemirror/js/codemirror.min.js'
		);

		Utilities::enqueue_legacy_vendor_asset(
			$handle . '-autorefresh',
			'codemirror/js/autorefresh.js',
			array( 'jquery', $handle )
		);

		Utilities::enqueue_legacy_vendor_asset(
			$handle . '-no-newlines',
			'codemirror/js/no-newlines.js',
			array( 'jquery', $handle )
		);

		Utilities::enqueue_legacy_vendor_asset(
			$handle . '-placeholder',
			'codemirror/js/placeholder.js',
			array( 'jquery', $handle )
		);

		Utilities::enqueue_legacy_vendor_asset(
			$handle . '-searchcursor',
			'codemirror/js/searchcursor.js',
			array( 'jquery', $handle )
		);

		Utilities::enqueue_legacy_vendor_asset(
			$handle . '-search',
			'codemirror/js/search.js',
			array( 'jquery', $handle, $handle . '-searchcursor' )
		);

		Utilities::enqueue_legacy_vendor_asset(
			$handle . '-mode-xml',
			'codemirror/js/modes/xml/xml.min.js',
			array( 'jquery', $handle )
		);

		Utilities::enqueue_legacy_vendor_asset(
			$handle . '-mode-css',
			'codemirror/js/modes/css/css.min.js',
			array( 'jquery', $handle )
		);

		Utilities::enqueue_legacy_vendor_asset(
			$handle . '-mode-javascript',
			'codemirror/js/modes/javascript/javascript.min.js',
			array( 'jquery', $handle )
		);

		Utilities::enqueue_legacy_vendor_asset(
			$handle . '-mode-htmlmixed',
			'codemirror/js/modes/htmlmixed/htmlmixed.min.js',
			array( 'jquery', $handle )
		);
	}

	/**
	 * Dequeues conflictive assets that shouldn't be loading in the recipe builder
	 */
	public static function dequeue_conflictive_assets() {

		// Set conflictive scripts
		$conflictive_scripts = array(
			// General
			'select2',

			// WooCommerce
			'selectWoo',
			'wc-enhanced-select',

			// LearnDash
			'learndash-select2-jquery-script',

			// The Events Calendar
			'tribe-select2',

			// Studiocart
			'sc-select2_js',

			// JW Player 6 for WordPress
			'jquerySelect2',
			'jwp6media',

			// YouTube Embed Plus
			'__ytprefs_admin__',

			/**
			 * Groundhogg
			 * Creates a conflict with select2 dropdowns
			 */
			'groundhogg-select2',

			// Divi Supreme Pro
			'dsm-select-two',
		);

		$conflictive_styles = array(
			// General
			'select2',

			// LearnDash
			'learndash-select2-jquery-style',

			// Studiocart
			'sc-select2_css',

			// JW Player 6 for WordPress
			'jquerySelect2Style',

			// Advanced Custom Fields Pro
			'acf-input',

			/**
			 * Groundhogg
			 * Creates a conflict with select2 dropdowns
			 */
			'groundhogg-select2',

			/**
			 * MemberPress for LearnDash
			 * Creates a conflict with select2 dropdowns
			 */
			'learndash-memberpress-edit-membership',

			// Divi Supreme Pro
			'dsm-select-two',
		);

		$conflictive_assets = array(
			'scripts' => $conflictive_scripts,
			'styles'  => $conflictive_styles,
		);

		$conflictive_assets = apply_filters( 'automator_conflictive_assets', $conflictive_assets );

		// Check if the array is valid
		if ( empty( $conflictive_assets ) || ! isset( $conflictive_assets['scripts'] ) || ! isset( $conflictive_assets['styles'] ) ) {
			// Someone made a mess and this is empty now. Bail
			return;
		}
		foreach ( $conflictive_assets['scripts'] as $conflictive_script ) {
			wp_deregister_script( $conflictive_script );
		}

		foreach ( $conflictive_assets['styles'] as $conflictive_style ) {
			wp_deregister_style( $conflictive_style );
		}
	}

	/**
	 *
	 */
	private function assets_vendor_tinymce() {
		wp_enqueue_editor();
		wp_enqueue_media();
	}

	/**
	 * Draft every trigger and action of a recipe going to the trash, and put each back when
	 * the recipe leaves the trash, however its status is written.
	 *
	 * @since 8.0.0
	 *
	 * @param string        $new_status The post's new status.
	 * @param string        $old_status The post's status before.
	 * @param \WP_Post|null $post       The post whose status changed.
	 *
	 * @return void
	 */
	public function follow_recipe_trash( $new_status, $old_status, $post ) {

		if ( ! $post instanceof \WP_Post || AUTOMATOR_POST_TYPE_RECIPE !== $post->post_type || $new_status === $old_status ) {
			return;
		}

		if ( 'trash' === $new_status ) {
			$this->draft_triggers_actions( $post->ID );
			return;
		}

		if ( 'trash' === $old_status ) {
			$this->restore_triggers_actions( $post->ID );
		}
	}

	/**
	 * Draft every trigger and action of a recipe going to the trash, including the
	 * actions inside its filter and loop blocks, and remember each one's status so
	 * restoring the recipe can put it back.
	 *
	 * @param int $post_ID The post going to the trash.
	 *
	 * @return void
	 */
	public function draft_triggers_actions( $post_ID ) {

		$post = get_post( $post_ID );

		if ( ! $post || AUTOMATOR_POST_TYPE_RECIPE !== $post->post_type ) {
			return;
		}

		foreach ( $this->get_recipe_item_ids( (int) $post->ID ) as $item_id ) {

			$status = (string) get_post_status( $item_id );

			// Recorded for a draft too, so an older record never outlives this trash.
			update_post_meta( $item_id, self::STATUS_BEFORE_TRASH_META, $status );

			if ( 'draft' !== $status ) {
				wp_update_post(
					array(
						'ID'          => $item_id,
						'post_status' => 'draft',
					)
				);
			}
		}
	}

	/**
	 * Put every trigger and action of a restored recipe back to the status it had
	 * before the recipe went to the trash.
	 *
	 * @since 8.0.0
	 *
	 * @param int $post_ID The restored post.
	 *
	 * @return void
	 */
	public function restore_triggers_actions( $post_ID ) {

		$post = get_post( $post_ID );

		if ( ! $post || AUTOMATOR_POST_TYPE_RECIPE !== $post->post_type ) {
			return;
		}

		foreach ( $this->get_recipe_item_ids( (int) $post->ID ) as $item_id ) {

			$status = (string) get_post_meta( $item_id, self::STATUS_BEFORE_TRASH_META, true );

			// Drafted before the status was recorded: it stays a draft.
			if ( '' === $status ) {
				continue;
			}

			if ( get_post_status( $item_id ) !== $status ) {
				wp_update_post(
					array(
						'ID'          => $item_id,
						'post_status' => $status,
					)
				);
			}

			if ( get_post_status( $item_id ) === $status ) {
				delete_post_meta( $item_id, self::STATUS_BEFORE_TRASH_META );
			}
		}
	}

	/**
	 * The recipe's triggers, its own actions, and the actions inside its blocks at
	 * any depth (each block carries the recipe's ID, as the recipe manifest reads it).
	 *
	 * @since 8.0.0
	 *
	 * @param int $recipe_id The recipe ID.
	 *
	 * @return int[] Trigger and action post IDs.
	 */
	private function get_recipe_item_ids( $recipe_id ) {

		$triggers = get_posts(
			array(
				'post_parent'    => $recipe_id,
				'post_status'    => 'any',
				'post_type'      => AUTOMATOR_POST_TYPE_TRIGGER,
				'posts_per_page' => -1, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Every trigger of one recipe.
				'fields'         => 'ids',
			)
		);

		$actions = get_posts(
			array(
				'post_parent__in' => array_merge( array( $recipe_id ), automator_get_block_store()->get_recipe_container_ids( $recipe_id ) ),
				'post_status'     => 'any',
				'post_type'       => AUTOMATOR_POST_TYPE_ACTION,
				'posts_per_page'  => -1, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Every action of one recipe.
				'fields'          => 'ids',
			)
		);

		return array_map( 'absint', array_merge( $triggers, $actions ) );
	}

	/**
	 * Add data to custom columns in the recipe list
	 *
	 * @param $column
	 * @param $post_id
	 */
	public function custom_column( $column, $post_id ) {

		switch ( $column ) {
			case 'triggers':
			case 'actions':
				$entity  = 'triggers' === $column ? 'trigger' : 'action';
				$post_id = (int) $post_id;
				if ( ! isset( $this->recipe_items[ $post_id ] ) ) {
					$this->recipe_items[ $post_id ] = automator_get_recipe_items( $post_id );
				}
				?>
				<div class="uap">
					<div class="uo-post-column__list">
						<?php
						foreach ( $this->recipe_items[ $post_id ] as $item ) {
							if ( $entity !== $item['entity'] ) {
								continue;
							}
							?>
							<div class="uo-post-column__item">
								<?php echo $item['is_on'] ? '<span class="dashicons dashicons-yes-alt recipe-ui-dash" title="Live"></span>' : '<span class="dashicons dashicons-warning recipe-ui-dash" title="Draft"></span>'; ?>
								<?php echo esc_html( $item['label'] ); ?>
							</div>
						<?php } ?>
					</div>
				</div>
				<?php
				break;
			case 'runs':
				//$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(run_number) FROM {$wpdb->prefix}uap_recipe_log WHERE automator_recipe_id=%d AND completed = %d", $post_id, 1 ) );
				$count = Automator()->utilities->get_recipe_total_runs( $post_id );

				$url = add_query_arg(
					array(
						'post_type' => AUTOMATOR_POST_TYPE_RECIPE,
						'page'      => 'uncanny-automator-admin-logs',
						'recipe_id' => $post_id,
					),
					admin_url( 'edit.php' )
				);
				printf( '<a href="%s">%s</a>', $url, $count ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				break;
			case 'type':
				$type = get_post_meta( $post_id, 'uap_recipe_type', true );
				echo empty( $type ) ? esc_html__( 'User', 'uncanny-automator' ) : esc_html( ucfirst( $type ) );
				break;
			case 'recipe_status':
				$post_status = get_post_status( $post_id );
				echo 'publish' === $post_status ? '<span class="dashicons dashicons-yes-alt recipe-ui-dash" title="Live"></span>' . esc_html__( 'Live', 'uncanny-automator' ) : '<span class="dashicons dashicons-warning recipe-ui-dash" title="Draft"></span>' . esc_html__( 'Draft', 'uncanny-automator' );
				break;
			case 'recipe_notes':
				$note_meta = get_post_meta( $post_id, 'uap_recipe_notes', true );
				$notes     = '';
				if ( ! empty( $note_meta ) ) {
					// Limit the text 10 words.
					$limited_text = wp_trim_words( $note_meta, 10, '...' );
					$show_full    = strlen( $note_meta ) > strlen( $limited_text );
					$limited_aria = $show_full ? 'false' : 'true';

					// Create a wrapper.
					$notes = '<div class="recipe-ui-list-notes__wrapper">';

					// Add a span with the limited text.
					$notes .= sprintf(
						'<span class="recipe-ui-list-notes__limited" aria-hidden="%s">%s</span>',
						esc_attr( $limited_aria ),
						esc_html( $limited_text )
					);

					// Add another span with the full text (hidden by default).
					if ( $show_full ) {
						$notes .= sprintf(
							// translators: %s: The full note text.
							'<span class="recipe-ui-list-notes__full" aria-hidden="false">%s</span>',
							nl2br( esc_textarea( $note_meta ) )
						);
					}

					$notes .= '</div>';
				}
				echo wp_kses(
					$notes,
					array(
						'div'  => array(
							'class' => array(),
						),
						'span' => array(
							'class'       => array(),
							'aria-hidden' => array(),
						),
						'br'   => array(), // For nl2br output
					)
				);
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
				$new_columns['recipe_notes']  = esc_attr__( 'Notes', 'uncanny-automator' );
				$new_columns[ $key ]          = $column;

			} else {
				$new_columns[ $key ] = $column;
			}
		}

		return $new_columns;
	}

	/**
	 * Delete the runs of a recipe that is deleted for good, as "Clear activity logs" does.
	 *
	 * Once the recipe is gone no screen can remove its runs: Logs > Recipe lists only
	 * runs of recipes that exist, and Logs > Action and Logs > Trigger have no delete.
	 * clear_recipe_logs() fires `automator_before_recipe_logs_cleared` first, so Pro
	 * cancels the recipe's waiting delays. This runs before the recipe's blocks are
	 * deleted, so deleting a delay block finds no waiting run to settle as Cancelled.
	 * A recipe moved to the trash keeps its runs.
	 *
	 * @since 8.0.0
	 *
	 * @param int           $post_id The post being deleted.
	 * @param \WP_Post|null $post    The post, when WordPress passes it.
	 *
	 * @return void
	 */
	public function purge_deleted_recipe_runs( $post_id, $post = null ) {

		$post = $post instanceof \WP_Post ? $post : get_post( $post_id );

		if ( ! $post instanceof \WP_Post || AUTOMATOR_POST_TYPE_RECIPE !== $post->post_type ) {
			return;
		}

		\clear_recipe_logs( (int) $post->ID );
	}
}
