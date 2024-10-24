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
	 * @var array
	 */
	public $loop_tokens = array();

	/**
	 * @var bool
	 */
	public $is_import = false;

	/**
	 * The meta key for the action_conditions.
	 *
	 * @var string
	 */
	const ACTION_CONDITIONS_META_KEY = 'actions_conditions';

	/**
	 * @var array
	 */
	public $action_conditions = array();

	/**
	 * @var array
	 */
	public $condition_parent_ids = array();

	public $do_not_modify_meta_keys = array(
		'extra_options',
	);

	/**
	 * Copy_Recipe_Parts constructor.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'copy_recipe_parts' ) );
		add_filter( 'post_row_actions', array( $this, 'add_copy_recipe_action_rows' ), 10, 2 );
		add_filter( 'automator_recipe_copy_modify_tokens', array( $this, 'handle_data_loop_tokens' ), 10, 2 );
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

		do_action( 'automator_copy_recipe_complete', $new_recipe_id, $recipe_id );

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

		$this->do_not_modify_meta_keys = apply_filters( 'automator_recipe_do_not_modify_meta_keys', $this->do_not_modify_meta_keys, $recipe_id );

		// Copy recipe post
		$new_recipe_id = $this->copy( $recipe_id );

		if ( is_wp_error( $new_recipe_id ) ) {
			return false;
		}

		// Copy triggers
		$this->copy_recipe_part( $recipe_id, $new_recipe_id, 'uo-trigger' );

		// Copy actions
		$this->copy_recipe_part( $recipe_id, $new_recipe_id, 'uo-action' );

		// Copy loops
		$this->copy_recipe_loops( $recipe_id, $new_recipe_id );

		// Copy closures
		$this->copy_recipe_part( $recipe_id, $new_recipe_id, 'uo-closure' );

		// Fallback to update tokens for Anonymous recipes that is stored in recipe's post meta itself
		$this->copy_recipe_metas( $recipe_id, $new_recipe_id );

		// Copy recipe conditions
		$this->copy_action_conditions( $recipe_id, $new_recipe_id );

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
		$recipe_parts = $this->get_recipe_parts_posts( $type, $recipe_id );

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
	 * @param $recipe_id
	 * @param $new_recipe_id
	 */
	public function copy_recipe_loops( $recipe_id, $new_recipe_id ) {
		// Check if recipe has loops
		$recipe_loops = $this->get_recipe_parts_posts( 'uo-loop', $recipe_id );
		if ( empty( $recipe_loops ) ) {
			return;
		}

		foreach ( $recipe_loops as $loop ) {
			// Copy loop.
			$new_loop_id = $this->copy( $loop->ID, $new_recipe_id, '', $loop );

			// Copy loop filters.
			$loop_filters = $this->get_recipe_parts_posts( 'uo-loop-filter', $loop->ID );
			foreach ( $loop_filters as $filter ) {
				$this->copy( $filter->ID, $new_loop_id, '', $filter );
			}

			// Copy loop actions.
			$loop_actions = $this->get_recipe_parts_posts( 'uo-action', $loop->ID );
			foreach ( $loop_actions as $action ) {
				$this->copy( $action->ID, $new_loop_id, '', $action );
			}
		}
	}

	/**
	 * @param $post_id
	 * @param $post_parent
	 * @param $status
	 * @param $post
	 * @param $post_meta
	 *
	 * @return false|int|void|\WP_Error
	 */
	public function copy( $post_id = 0, $post_parent = 0, $status = '', $post = null, $post_meta = array() ) {
		if ( null === $post && 0 !== $post_id ) {
			$post = get_post( $post_id );
		}

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

		$post_title = $post->post_title;
		if ( 'uo-recipe' === $post->post_type ) {
			$post_title = $this->is_import
				/* translators: Original Post title */
				? sprintf( __( '%1$s (Imported)', 'uncanny-automator' ), $post->post_title )
				/* translators: Original Post title & Post ID */
				: sprintf( __( '%1$s (Duplicated from #%2$d)', 'uncanny-automator' ), $post->post_title, $post_id );
		}

		$new_post = array(
			'menu_order'     => $post->menu_order,
			'comment_status' => $post->comment_status,
			'ping_status'    => $post->ping_status,
			'post_author'    => $new_post_author->ID,
			'post_content'   => $this->modify_tokens( $post->post_content ),
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

			// Store previous => new recipe ID to replace in action conditions
			if ( 'uo-recipe' === $post->post_type ) {
				$this->condition_parent_ids[ $post->ID ] = $new_post_id;
			}

			// Store previous => new trigger ID to replace in trigger tokens
			if ( 'uo-trigger' === $post->post_type ) {
				$this->trigger_tokens[ $post->ID ] = $new_post_id;
			}

			// Store previous => new action ID to replace in action tokens
			if ( 'uo-action' === $post->post_type ) {
				$this->action_tokens[ $post->ID ] = $new_post_id;
			}

			// Store previous => new loop ID to replace in loop tokens
			if ( 'uo-loop' === $post->post_type ) {
				$this->loop_tokens[ $post->ID ]          = $new_post_id;
				$this->condition_parent_ids[ $post->ID ] = $new_post_id;
			}

			$this->copy_recipe_metas( $post_id, $new_post_id, $post_parent, $post_meta );

			// A new recipe part is duplicated
			do_action( 'automator_recipe_part_duplicated', $new_post_id, $post, $new_post );

			return $new_post_id;
		}
	}

	/**
	 * @param $post_id
	 * @param $new_post_id
	 * @param int $post_parent
	 * @param array $post_meta
	 */
	public function copy_recipe_metas( $post_id, $new_post_id, $post_parent = 0, $post_meta = array() ) {

		if ( empty( $post_meta ) ) {
			$post_meta = get_post_meta( $post_id );
		}

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

			// Stash action conditions until end of process.
			if ( self::ACTION_CONDITIONS_META_KEY === $key ) {
				$this->action_conditions[ $new_post_id ] = $val;
				continue;
			}

			// Check if we should be modifying this meta key.
			$skip_meta_key = in_array( $key, $this->do_not_modify_meta_keys, true );
			if ( ! $skip_meta_key ) {
				// Replace IDs in tokens
				$val = $this->modify_tokens( $val, $new_post_id );
			}

			// Pass it thru a filter
			$val = apply_filters( 'automator_recipe_part_meta_value', $val, $post_id, $new_post_id, $key );

			// any remaining meta
			update_post_meta( $new_post_id, $key, $val );

			// Action to hook into meta
			do_action( 'automator_recipe_part_copy_meta_value', $key, $value, $post_id, $new_post_id );
		}
	}

	/**
	 * @param int $post_id
	 * @param int $new_post_id
	 * @param string $conditions
	 *
	 * @return void
	 */
	public function copy_action_conditions( $post_id = 0, $new_post_id = 0, $conditions = '' ) {

		if ( empty( $conditions ) ) {
			if ( ! empty( $this->action_conditions ) && isset( $this->action_conditions[ $new_post_id ] ) ) {
				$conditions = $this->action_conditions[ $new_post_id ];
				unset( $this->action_conditions[ $new_post_id ] );
			}
		}

		if ( empty( $conditions ) ) {
			return;
		}

		$conditions = $this->modify_conditions( $conditions, $post_id, $new_post_id );
		$conditions = apply_filters( 'automator_recipe_copy_action_conditions_meta', $conditions, $post_id, $new_post_id );

		update_post_meta( $new_post_id, self::ACTION_CONDITIONS_META_KEY, $conditions );

		do_action( 'automator_recipe_copy_action_conditions', $conditions, $post_id, $new_post_id );

	}


	/**
	 * @param $content
	 * @param int $new_post_id
	 *
	 * @return mixed
	 */
	public function modify_tokens( $content, $new_post_id = 0 ) {
		//Check if it's a webhook URL
		if ( 0 !== $new_post_id && ! is_array( $content ) && ! is_object( $content ) && preg_match( '/\/wp-json\/uap\/v2\/uap-/', $content ) ) {
			// Only modify webhook URL of the trigger. We are leaving webhook URL of action alone.
			if ( 'uo-trigger' === get_post_type( $new_post_id ) ) {
				$new     = sprintf( 'uap/v2/uap-%d-%d', wp_get_post_parent_id( $new_post_id ), $new_post_id );
				$content = preg_replace( '/uap\/v2\/uap-.+/', $new, $content );
			}
		}

		// Check if it's an object or array : Google sheets etc.
		$encoded      = is_array( $content ) || is_object( $content );
		$decode_param = is_array( $content ); // Decode as array if it was an array
		$content      = $encoded
			? wp_json_encode( $content, JSON_UNESCAPED_UNICODE )
			: $content;

		// Check if any replaceable token exists
		if ( false === $this->token_exists_in_content( $content ) ) {
			// Check if we need to decode.
			return $encoded ? json_decode( $content, $decode_param ) : $content;
		}

		// Replace if trigger token exists
		if ( ! empty( $this->trigger_tokens ) ) {
			$content = $this->replace_trigger_token_ids( $content );
		}

		// Replace if action token exists
		if ( ! empty( $this->action_tokens ) ) {
			$content = $this->replace_action_token_ids( $content );
		}

		// Replace if loop token exists
		if ( ! empty( $this->loop_tokens ) ) {
			$content = $this->replace_loop_token_ids( $content );
		}

		// Add filter for special tokens.
		$content = apply_filters( 'automator_recipe_copy_modify_tokens', $content, $new_post_id );

		// Check if we need to decode.
		return $encoded ? json_decode( $content, $decode_param ) : $content;
	}

	/**
	 * @param $content
	 *
	 * @return bool
	 */
	public function token_exists_in_content( $content ) {
		// check if content contains a replaceable token
		if (
			! is_array( $content )
			&& ! is_object( $content )
			&& false === preg_match_all( '/{{(ACTION_(FIELD|META)\:)?\d+:\w.+?}}/', $content )
			&& false === preg_match_all( '/{{id:(WPMAGICBUTTON|WPMAGICLINK)}}/', $content )
			&& false === preg_match_all( '/{{TOKEN_EXTENDED:([^}]*)}}/', $content )
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

		if ( is_object( $content ) ) {
			return $content;
		}

		// Loop thru multiple triggers and update token's trigger ID based on that.
		foreach ( $this->trigger_tokens as $prev_id => $new_id ) {
			// Sanity check
			if ( is_array( $prev_id ) || is_array( $new_id ) ) {
				continue;
			}

			$pattern = '/(\{\{|\[\[)' . preg_quote( $prev_id, '/' ) . '(:|;)/';

			// Check if content contains a replaceable token by previous ID.
			if ( preg_match_all( $pattern, $content ) ) {
				$content = preg_replace( $pattern, '${1}' . $new_id . '${2}', $content );
			}

			// Check for extended tokens pattern by previous ID.
			$extended_pattern = '/\{\{TOKEN_EXTENDED:([^:]+):' . preg_quote( $prev_id, '/' ) . ':([^}]+)\}\}/';
			if ( preg_match_all( $extended_pattern, $content ) ) {
				$content = preg_replace( $extended_pattern, '{{TOKEN_EXTENDED:${1}:' . $new_id . ':${2}}}', $content );
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

		if ( is_object( $content ) ) {
			return $content;
		}

		// Loop thru multiple actions and update token's action ID based on that.
		foreach ( $this->action_tokens as $prev_id => $new_id ) {
			// Sanity check
			if ( is_array( $prev_id ) || is_array( $new_id ) ) {
				continue;
			}

			// Check if content contains a replaceable token by previous ID.
			if ( preg_match( '/{{(ACTION_(FIELD|META)\:)' . $prev_id . '\:\w.+?}}/', $content ) ) {
				$content = preg_replace( '/{{ACTION_(FIELD|META)\:' . $prev_id . ':/', '{{ACTION_$1\:' . $new_id . ':', $content );
			}
			// Check for extended tokens pattern by previous ID.
			$extended_pattern = '/\{\{TOKEN_EXTENDED:([^:]+):([^:]+):' . preg_quote( $prev_id, '/' ) . ':([^}]+)\}\}/';
			if ( preg_match_all( $extended_pattern, $content ) ) {
				$content = preg_replace( $extended_pattern, '{{TOKEN_EXTENDED:${1}:${2}:' . $new_id . ':${3}}}', $content );
			}
		}

		return $content;
	}

	/**
	 * @param $content
	 *
	 * @return array|mixed|string|string[]|null
	 */
	public function replace_loop_token_ids( $content ) {

		if ( is_object( $content ) ) {
			return $content;
		}

		// Loop thru multiple loops and update token's loop ID based on that.
		foreach ( $this->loop_tokens as $prev_id => $new_id ) {
			// Sanity check
			if ( is_array( $prev_id ) || is_array( $new_id ) ) {
				continue;
			}
			// Check if content contains a replaceable token by previous ID for LOOP_TOKEN or DATA_TOKEN.
			if ( preg_match( '/{{TOKEN_EXTENDED:(LOOP_TOKEN|DATA_TOKEN[^:]*):' . $prev_id . ':/', $content ) ) {
				$content = preg_replace( '/{{TOKEN_EXTENDED:(LOOP_TOKEN|DATA_TOKEN[^:]*):' . $prev_id . ':/', '{{TOKEN_EXTENDED:$1:' . $new_id . ':', $content );
			}
		}

		return $content;
	}

	/**
	 * @param $content
	 * @param $post_id
	 * @param $new_post_id
	 *
	 * @return false|mixed|string
	 */
	public function modify_conditions( $content, $post_id = 0, $new_post_id = 0 ) {

		if ( empty( $content ) ) {
			return $content;
		}

		// decode into array/object
		$content = json_decode( $content );
		foreach ( $content as $k => $condition ) {
			if ( ! isset( $condition->actions ) ) {
				continue;
			}

			// Update the condition parent ID ( recipe or loop )
			$current_parent = isset( $condition->parent_id ) ? $condition->parent_id : null;
			if ( ! $current_parent || ! isset( $this->condition_parent_ids[ $current_parent ] ) ) {
				continue;
			}

			$condition->parent_id = $this->condition_parent_ids[ $current_parent ];

			// Update the action IDs
			foreach ( $condition->actions as $kk => $action_id ) {
				$new_action_id = $this->get_new_action_id( $condition->parent_id, $action_id );
				if ( ! empty( $new_action_id ) ) {
					unset( $condition->actions[ $kk ] ); // Remove old action ID
					$condition->actions[ $kk ] = $new_action_id; // Add new action ID
				}
			}
		}

		// Modify tokens and return encoded json string.
		return $this->modify_tokens( wp_json_encode( $content, JSON_UNESCAPED_UNICODE ), $new_post_id );
	}

	/**
	 * @param $post_type
	 * @param $parent_id
	 *
	 * @return array
	 */
	public function get_recipe_parts_posts( $post_type, $parent_id ) {
		return get_posts(
			array(
				'post_parent'    => $parent_id,
				'post_type'      => $post_type,
				'post_status'    => array( 'draft', 'publish' ),
				'orderby'        => array(
					'menu_order' => 'ASC',
					'ID'         => 'ASC',
				),
				'posts_per_page' => '99999', //phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
			)
		);
	}

	/**
	 * @param int $new_post_id - New post ID
	 * @param int $action_id - Old action ID
	 *
	 * @return bool|int
	 */
	public function get_new_action_id( $new_post_id, $action_id ) {

		// Use `automator_duplicated_from` meta to figure out the new action ID
		// Since the action conditions are stored at the Recipe level by the Action ID
		// We have to do a lookup based on the old Action ID and the new post parent ID

		global $wpdb;
		$new_action_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT pm.post_id
				FROM $wpdb->postmeta pm
				JOIN $wpdb->posts p
				ON p.ID = pm.post_id AND p.post_parent = %d
				WHERE pm.meta_value = %d
				AND pm.meta_key = %s;",
				$new_post_id,
				$action_id,
				'automator_duplicated_from'
			)
		);

		return is_numeric( $new_action_id ) ? $new_action_id : false;
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

	/**
	 * Maybe update the trigger ID in the data loop tokens.
	 *
	 * @param mixed $value
	 * @param int $new_post_id
	 *
	 * @return mixed
	 */
	public function handle_data_loop_tokens( $value, $new_post_id ) {

		if ( ! is_string( $value ) ) {
			return $value;
		}

		// Pattern to match loopable data tokens.
		$trigger_pattern = '/\{\{TOKEN_EXTENDED:DATA_TOKEN_CHILDREN_TRIGGER_LOOPABLE[^}]*\}\}/';
		if ( ! preg_match_all( $trigger_pattern, $value ) ) {
			return $value;
		}

		$trigger_replaced = false;
		if ( ! empty( $this->trigger_tokens ) ) {
			foreach ( $this->trigger_tokens as $prev_trigger_id => $new_trigger_id ) {
				while ( false !== strpos( $value, ":{$prev_trigger_id}:TRIGGER_LOOPABLE" ) ) {
					$before = $value;
					$value  = str_replace( ":{$prev_trigger_id}:TRIGGER_LOOPABLE", ":{$new_trigger_id}:TRIGGER_LOOPABLE", $value );
					if ( $before !== $value ) {
						$trigger_replaced = true;
					}
				}
			}
		}

		// If we didn't replace the tokens, then we should remove them.
		if ( ! $trigger_replaced ) {
			$value = preg_replace( $trigger_pattern, '', $value );
		}

		return $value;
	}
}
