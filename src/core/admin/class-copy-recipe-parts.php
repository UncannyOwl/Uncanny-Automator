<?php

namespace Uncanny_Automator;

/**
 * Class Copy_Recipe_Parts
 *
 * @package Uncanny_Automator
 */
class Copy_Recipe_Parts {
	/**
	 * @var array
	 */
	public $trigger_tokens = array();
	/**
	 * @var array
	 */
	public $action_tokens = array();

	/**
	 * Copy_Recipe_Parts constructor.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'copy_recipe_parts' ) );
		add_filter( 'post_row_actions', array( $this, 'add_copy_recipe_action_rows' ), 10, 2 );
	}

	/**
	 * @param $actions
	 * @param $post
	 *
	 * @return mixed
	 */
	public function add_copy_recipe_action_rows( $actions, $post ) {
		if ( 'uo-recipe' !== $post->post_type ) {
			return $actions;
		}
		$post_type_object = get_post_type_object( $post->post_type );
		$can_edit_post    = current_user_can( $post_type_object->cap->edit_post, $post->ID );
		if ( ! $can_edit_post ) {
			return $actions;
		}
		unset( $actions['inline hide-if-no-js'] );
		unset( $actions['hide-if-no-js'] );
		unset( $actions['view'] );
		$action                 = sprintf( '%s?action=%s&post=%d&return_to_recipe=yes&_wpnonce=%s', admin_url( 'edit.php' ), 'copy_recipe_parts', $post->ID, wp_create_nonce( 'Aut0Mat0R' ) );
		$actions['copy_recipe'] = sprintf( '<a href="%s" title="%s">%s</a>', $action, __( 'Duplicate this recipe', 'uncanny-automator' ), __( 'Duplicate this recipe', 'uncanny-automator' ) );

		return $actions;
	}

	/**
	 *
	 */
	public function copy_recipe_parts() {
		if ( ! automator_filter_has_var( 'action' ) ) {
			return;
		}

		if ( 'copy_recipe_parts' !== automator_filter_input( 'action' ) ) {
			return;
		}

		if ( ! automator_filter_has_var( 'post' ) ) {
			return;
		}

		if ( ! automator_filter_has_var( '_wpnonce' ) ) {
			return;
		}

		if ( ! wp_verify_nonce( automator_filter_input( '_wpnonce' ), 'Aut0Mat0R' ) ) {
			return;
		}

		$recipe_id = absint( automator_filter_input( 'post' ) );

		if ( 'uo-recipe' !== get_post_type( $recipe_id ) ) {
			wp_die( esc_attr( sprintf( '%s %s', __( 'Copy creation failed, could not find original recipe:', 'uncanny-automator' ), htmlspecialchars( $recipe_id ) ) ) );
		}

		// Copy the post and insert it
		$new_recipe_id = $this->copy_this_recipe( $recipe_id );

		do_action( 'automator_recipe_duplicated', $new_recipe_id, $recipe_id );

		if ( automator_filter_has_var( 'return_to_recipe' ) ) {
			wp_safe_redirect( admin_url( 'post.php?post=' . $new_recipe_id . '&action=edit' ) );
		} else {
			if ( false === $new_recipe_id ) {
				wp_safe_redirect( admin_url( 'post.php?post=' . $recipe_id . '&action=edit' ) );
				exit;
			}
			wp_safe_redirect( admin_url( 'edit.php?post_type=' . get_post_type( $recipe_id ) ) );
		}
		exit;
	}

	/**
	 * @param $recipe_id
	 *
	 * @return false|int|\WP_Error
	 */
	public function copy_this_recipe( $recipe_id ) {
		global $wpdb;

		// Copy recipe post
		$new_recipe_id = $this->copy( $recipe_id );

		if ( is_wp_error( $new_recipe_id ) ) {
			return false;
		}

		// Copy triggers
		$this->copy_recipe_part( $recipe_id, $new_recipe_id, 'uo-trigger' );

		// Copy actions
		$this->copy_recipe_part( $recipe_id, $new_recipe_id, 'uo-action' );

		// Copy closures
		$this->copy_recipe_part( $recipe_id, $new_recipe_id, 'uo-closure' );

		// Fallback to update tokens for Anonymous recipes that is stored in recipe's post meta itself
		$this->copy_recipe_metas( $recipe_id, $new_recipe_id );

		$recipe_tax = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(object_id) AS total FROM $wpdb->term_relationships WHERE object_id = %d", $recipe_id ) );

		if ( $recipe_tax > 0 ) {
			//Clone tags and categories
			$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->term_relationships WHERE object_id=%d;", $new_recipe_id ) );
			$wpdb->query( $wpdb->prepare( "CREATE TEMPORARY TABLE tmpCopyCats SELECT * FROM $wpdb->term_relationships WHERE object_id=%d;", $recipe_id ) );
			$wpdb->query( $wpdb->prepare( 'UPDATE tmpCopyCats SET object_id=%d WHERE object_id=%d;', $new_recipe_id, $recipe_id ) );
			$wpdb->query( "INSERT INTO $wpdb->term_relationships SELECT * FROM tmpCopyCats;" );
			$wpdb->query( 'DROP TEMPORARY TABLE IF EXISTS tmpCopyCats;' );
		}

		return $new_recipe_id;
	}


	/**
	 * @param $recipe_id
	 * @param $new_recipe_id
	 * @param $type
	 *
	 * @return bool
	 */
	public function copy_recipe_part( $recipe_id, $new_recipe_id, $type ) {
		$recipe_parts = get_posts(
			array(
				'post_parent'    => $recipe_id,
				'post_type'      => $type,
				'post_status'    => array( 'draft', 'publish' ),
				'order_by'       => 'ID',
				'order'          => 'ASC',
				'posts_per_page' => '99999', //phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
			)
		);

		if ( empty( $recipe_parts ) ) {
			return false;
		}

		foreach ( $recipe_parts as $recipe_part ) {
			if ( $type !== $recipe_part->post_type ) {
				continue;
			}

			$new_id = $this->copy( $recipe_part->ID, $new_recipe_id );

			// Part duplicated
			do_action( 'automator_recipe_part_duplicated', $new_id, $new_recipe_id, $recipe_part, $recipe_id, $type );
		}

		// Parts duplicated
		do_action( 'automator_recipe_parts_duplicated', $new_recipe_id, $recipe_id );

		return true;
	}

	/**
	 * @param $post_id
	 * @param int $post_parent
	 * @param string $status
	 *
	 * @return false|int|\WP_Error
	 */
	public function copy( $post_id, $post_parent = 0, $status = '' ) {
		$post = get_post( $post_id );

		// We don't want to clone revisions
		if ( 'revision' === $post->post_type ) {
			return false;
		}

		$new_post_author = wp_get_current_user();

		// Keep the same status of triggers and actions as original recipe, but draft the recipe
		$status = ! empty( $status ) ? $status : $post->post_status;

		if ( 'uo-recipe' === $post->post_type ) {
			$status = 'draft';
		}

		/* translators: Original Post title & Post ID */
		$post_title = 'uo-recipe' === $post->post_type ? sprintf( __( '%1$s (Duplicated from #%2$d)', 'uncanny-automator' ), $post->post_title, $post_id ) : $post->post_title;

		$new_post = array(
			'menu_order'     => $post->menu_order,
			'comment_status' => $post->comment_status,
			'ping_status'    => $post->ping_status,
			'post_author'    => $new_post_author->ID,
			'post_content'   => $this->modify_tokens( $post->post_content, $post_parent ),
			'post_excerpt'   => $post->post_excerpt,
			'post_mime_type' => $post->post_mime_type,
			'post_parent'    => empty( $post_parent ) ? $post->post_parent : $post_parent,
			'post_password'  => $post->post_password,
			'post_status'    => $status,
			'post_title'     => $post_title,
			'post_type'      => $post->post_type,
			'post_date'      => current_time( 'mysql' ),
		);

		// New ID of the part
		$new_post_id = wp_insert_post( $new_post );

		if ( is_wp_error( $new_post_id ) ) {
			wp_die( esc_html( $new_post_id->get_error_message() ) );
		}

		// Store the original recipe, trigger, action ID
		if ( ! empty( $this->store_duplicated_id( $new_post_id, $post_id ) ) ) {

			// Store previous => new trigger ID to replace in trigger tokens
			if ( 'uo-trigger' === $post->post_type ) {
				$this->trigger_tokens[ $post->ID ] = $new_post_id;
			}

			// Store previous => new trigger ID to replace in action tokens
			if ( 'uo-action' === $post->post_type ) {
				$this->action_tokens[ $post->ID ] = $new_post_id;
			}

			$this->copy_recipe_metas( $post_id, $new_post_id, $post_parent );

			// A new recipe part is duplicated
			do_action( 'automator_recipe_part_duplicated', $new_post_id, $post, $new_post );

			return $new_post_id;
		}
	}

	/**
	 * @param $post_id
	 * @param $new_post_id
	 * @param int $post_parent
	 */
	public function copy_recipe_metas( $post_id, $new_post_id, $post_parent = 0 ) {

		$post_meta = get_post_meta( $post_id );

		foreach ( $post_meta as $key => $value ) {
			if ( 'automator_duplicated_from' === $key ) {
				continue;
			}

			// Update Automator plugin version
			if ( 'uap_recipe_version' === $key || 'uap_trigger_version' === $key || 'uap_action_version' === $key ) {
				update_post_meta( $new_post_id, $key, AUTOMATOR_PLUGIN_VERSION );
				continue;
			}

			// Updating Magic link and Magic button IDs
			if ( 'ANONWPMAGICLINK' === $key || 'WPMAGICLINK' === $key || 'WPMAGICBUTTON' === $key || 'ANONWPMAGICBUTTON' === $key ) {
				update_post_meta( $new_post_id, $key, $new_post_id );
				continue;
			}

			$val = isset( $value[0] ) ? maybe_unserialize( $value[0] ) : '';

			// Modify conditions
			if ( 'actions_conditions' === $key ) {
				$val = $this->modify_conditions( $val, $post_id, $new_post_id );
				$val = apply_filters( 'automator_recipe_copy_action_conditions_value', $val, $post_id, $new_post_id );

				update_post_meta( $new_post_id, $key, $val );

				// Hooked in Pro to fix conditions
				do_action( 'automator_recipe_copy_action_conditions', $val, $post_id, $new_post_id );

				continue;
			}

			// Replace IDs in tokens
			$val = $this->modify_tokens( $val, $post_parent, $new_post_id );

			// Pass it thru a filter
			$val = apply_filters( 'automator_recipe_part_meta_value', $val, $post_id, $new_post_id, $key );

			// any remaining meta
			update_post_meta( $new_post_id, $key, $val );

			// Action to hook into meta
			do_action( 'automator_recipe_part_copy_meta_value', $key, $value, $post_id, $new_post_id );
		}
	}

	/**
	 * @param $content
	 * @param int $post_parent
	 * @param int $new_post_id
	 *
	 * @return mixed
	 */
	public function modify_tokens( $content, $post_parent = 0, $new_post_id = 0 ) {

		//Check if it's a webhook URL
		if ( 0 !== $new_post_id && ! is_array( $content ) && preg_match( '/\/wp-json\/uap\/v2\/uap-/', $content ) ) {
			// Only modify webhook URL of the trigger. We are leaving webhook URL of action alone.
			if ( 'uo-trigger' === get_post_type( $new_post_id ) ) {
				$new     = sprintf( 'uap/v2/uap-%d-%d', wp_get_post_parent_id( $new_post_id ), $new_post_id );
				$content = preg_replace( '/uap\/v2\/uap-.+/', $new, $content );
			}
		}

		// Check if it's an array,
		// i.e., 'extra_content' key
		if ( is_array( $content ) ) {
			return $content;
		}

		// Check if any replaceable token exists
		if ( false === $this->token_exists_in_content( $content ) ) {
			return $content;
		}

		// Replace if trigger token exists
		if ( ! empty( $this->trigger_tokens ) ) {
			$content = $this->replace_trigger_token_ids( $content );
		}

		// Replace if action token exists
		if ( ! empty( $this->action_tokens ) ) {
			$content = $this->replace_action_token_ids( $content );
		}

		return $content;
	}

	/**
	 * @param $content
	 *
	 * @return bool
	 */
	public function token_exists_in_content( $content ) {
		// check if content contains a replaceable token
		if (
			false === preg_match_all( '/{{(ACTION_(FIELD|META)\:)?\d+:\w.+?}}/', $content )
			&& false === preg_match_all( '/{{id:(WPMAGICBUTTON|WPMAGICLINK)}}/', $content )
		) {
			return false;
		}

		return true;
	}

	/**
	 * @param $content
	 *
	 * @return array|mixed|string|string[]|null
	 */
	public function replace_trigger_token_ids( $content ) {
		// Loop thru multiple triggers and update token's trigger ID based on that.
		foreach ( $this->trigger_tokens as $prev_id => $new_id ) {
			// Sanity check
			if ( is_array( $prev_id ) || is_array( $new_id ) ) {
				continue;
			}
			// check if content contains a replaceable token by previous ID
			if ( preg_match( '/{{' . $prev_id . '\:\w.+?}}/', $content ) ) {
				$content = preg_replace( '/{{' . $prev_id . ':/', '{{' . $new_id . ':', $content );
			}
		}

		return $content;
	}

	/**
	 * @param $content
	 *
	 * @return array|mixed|string|string[]|null
	 */
	public function replace_action_token_ids( $content ) {
		// Loop thru multiple actions and update token's action ID based on that.
		foreach ( $this->action_tokens as $prev_id => $new_id ) {
			// Sanity check
			if ( is_array( $prev_id ) || is_array( $new_id ) ) {
				continue;
			}
			// check if content contains a replaceable token by previous ID
			if ( preg_match( '/{{(ACTION_(FIELD|META)\:)' . $prev_id . '\:\w.+?}}/', $content ) ) {
				$content = preg_replace( '/{{ACTION_(FIELD|META)\:' . $prev_id . ':/', '{{ACTION_$1\:' . $new_id . ':', $content );
			}
		}

		return $content;
	}

	/**
	 * @param $content
	 * @param $post_id
	 * @param $new_post_id
	 *
	 * @depreacated v5.1 - See do_action in Pro 5.1
	 * @return false|mixed|string
	 */
	public function modify_conditions( $content, $post_id = 0, $new_post_id = 0 ) {

		if ( empty( $content ) ) {
			return $content;
		}
		// decode into array/object
		$content = json_decode( $content );
		global $wpdb;
		foreach ( $content as $k => $condition ) {
			if ( ! isset( $condition->actions ) ) {
				continue;
			}

			foreach ( $condition->actions as $kk => $action_id ) {
				// Use `automator_duplicated_from` meta to figure out the new action ID
				// Since the action conditions are stored at the Recipe level by the Action ID
				// We have to do a lookup based on the old Action ID and the new recipe post parent ID
				$qry = $wpdb->prepare(
					"SELECT pm.post_id
FROM $wpdb->postmeta pm
JOIN $wpdb->posts p
ON p.ID = pm.post_id AND p.post_parent = %d
WHERE pm.meta_value = %d
AND pm.meta_key = %s;",
					$new_post_id,
					$action_id,
					'automator_duplicated_from'
				);

				$new_action_id = $wpdb->get_var( $qry ); //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				if ( is_numeric( $new_action_id ) ) {
					unset( $condition->actions[ $kk ] ); // Remove old action ID
					$condition->actions[ $kk ] = $new_action_id; // Add new action ID
				}
			}
		}

		// return encoded string
		return wp_json_encode( $content );
	}

	/**
	 * @param $new_post_id
	 * @param $post_id
	 *
	 * @return bool|int|\mysqli_result|resource|null
	 */
	public function store_duplicated_id( $new_post_id, $post_id ) {
		global $wpdb;

		return $wpdb->insert(
			$wpdb->postmeta,
			array(
				'post_id'    => $new_post_id,
				'meta_key'   => 'automator_duplicated_from',
				'meta_value' => $post_id,
			),
			array(
				'%d',
				'%s',
				'%d',
			)
		);
	}
}
