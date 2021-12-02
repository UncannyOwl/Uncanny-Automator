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
		$new_id = $this->copy_this_recipe( $recipe_id );
		if ( automator_filter_has_var( 'return_to_recipe' ) ) {
			wp_safe_redirect( admin_url( 'post.php?post=' . $new_id . '&action=edit' ) );
		} else {
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

		$new_recipe_id = $this->copy( $recipe_id );
		if ( $this->copy_recipe_part( $recipe_id, $new_recipe_id, 'uo-trigger' ) ) {
			$this->copy_recipe_part( $recipe_id, $new_recipe_id, 'uo-action' );
			$this->copy_recipe_part( $recipe_id, $new_recipe_id, 'uo-closure' );

			// Fallback to update tokens for Anonymous recipes that is stored in recipe's post meta itself
			$this->copy_recipe_metas( $recipe_id, $new_recipe_id );
		}
		$recipe_tax = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(object_id) AS total FROM $wpdb->term_relationships WHERE object_id = %d", $recipe_id ) );

		if ( $recipe_tax > 0 ) {
			//Clone tags and categories
			$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->term_relationships WHERE object_id=%d;", $new_recipe_id ) );
			$wpdb->query( $wpdb->prepare( "CREATE TEMPORARY TABLE tmpCopyCats SELECT * FROM $wpdb->term_relationships WHERE object_id=%d;", $recipe_id ) );
			$wpdb->query( $wpdb->prepare( 'UPDATE tmpCopyCats SET object_id=%d WHERE object_id=%d;', $new_recipe_id, $recipe_id ) );
			$wpdb->query( $wpdb->prepare( "INSERT INTO $wpdb->term_relationships SELECT * FROM tmpCopyCats;" ) );
			$wpdb->query( $wpdb->prepare( 'DROP TEMPORARY TABLE IF EXISTS tmpCopyCats;' ) );
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
				'posts_per_page' => '999',
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

			if ( 'uo-trigger' === $recipe_part->post_type ) {
				$this->trigger_tokens[ $recipe_part->ID ] = $new_id;
			}
		}

		return true;
	}

	/**
	 * @param $post_id
	 * @param int $post_parent
	 * @param string $status
	 *
	 * @return false|int|\WP_Error
	 */
	public function copy( $post_id, $post_parent = 0, $status = 'draft' ) {
		$post = get_post( $post_id );
		// We don't want to clone revisions
		if ( 'revision' === $post->post_type ) {
			return false;
		}

		if ( 'attachment' !== $post->post_type ) {
			$status = 'draft';
		}

		$new_post_author = wp_get_current_user();
		$post_title      = 'uo-recipe' === $post->post_type ? $post->post_title . ' ' . __( '(Copy)', 'uncanny-automator' ) : $post->post_title;
		$new_post        = array(
			'menu_order'     => $post->menu_order,
			'comment_status' => $post->comment_status,
			'ping_status'    => $post->ping_status,
			'post_author'    => $new_post_author->ID,
			'post_content'   => $this->modify_tokens( $post->post_content, $post_parent ),
			'post_excerpt'   => $post->post_excerpt,
			'post_mime_type' => $post->post_mime_type,
			'post_parent'    => empty( $post_parent ) ? $post->post_parent : $post_parent,
			'post_password'  => $post->post_password,
			'post_status'    => empty( $status ) ? $post->post_status : $status,
			'post_title'     => $post_title,
			'post_type'      => $post->post_type,
			'post_date'      => current_time( 'mysql' ),
		);

		$new_post_id = wp_insert_post( $new_post );
		if ( is_wp_error( $new_post_id ) ) {
			wp_die( esc_html( $new_post_id->get_error_message() ) );
		}

		$this->copy_recipe_metas( $post_id, $new_post_id, $post_parent );

		return $new_post_id;
	}

	/**
	 * @param $post_id
	 * @param $new_post_id
	 * @param int $post_parent
	 */
	public function copy_recipe_metas( $post_id, $new_post_id, $post_parent = 0 ) {
		$recipe_meta = get_post_meta( $post_id );

		foreach ( $recipe_meta as $key => $value ) {
			$val = maybe_unserialize( $value[0] );
			$val = $this->modify_tokens( $val, $post_parent, $new_post_id );
			update_post_meta( $new_post_id, $key, $val );
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

		if ( empty( $this->trigger_tokens ) ) {
			return $content;
		}

		// Loop thru multiple triggers and update token's trigger ID based on that.
		foreach ( $this->trigger_tokens as $prev_id => $new_id ) {
			$content = preg_replace( '/{{' . $prev_id . ':/', '{{' . $new_id . ':', $content );
		}

		return $content;
	}
}
