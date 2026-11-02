<?php

namespace Uncanny_Automator;

/**
 * Class Copy_Recipe_Parts
 *
 * Admin wiring for recipe duplication: the "Duplicate this recipe" row action
 * and its admin_init handler, which delegate whole-recipe duplication to the
 * portability engine via {@see Copy_Recipe_Parts::duplicate_recipe_via_engine()}
 * (`automator_duplicate_recipe()`). The legacy part-copy / token-rewrite
 * machinery that used to live here is replaced by
 * {@see \Uncanny_Automator\App\Recipe_Builder\Recipe\Portability\Recipe_Duplication_Service},
 * {@see \Uncanny_Automator\App\Recipe_Builder\Recipe\Portability\Recipe_Exporter} and
 * {@see \Uncanny_Automator\App\Recipe_Builder\Recipe\Portability\Recipe_Importer}.
 *
 * @package Uncanny_Automator
 */
class Copy_Recipe_Parts {

	/**
	 * Copy_Recipe_Parts constructor.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'copy_recipe_parts' ) );
		add_filter( 'post_row_actions', array( $this, 'add_copy_recipe_action_rows' ), 10, 2 );
	}

	/**
	 * Add the "Duplicate this recipe" link to the recipe row actions.
	 *
	 * @param array    $actions Row actions.
	 * @param \WP_Post $post    Current list-table post.
	 *
	 * @return array Row actions with the duplicate link added.
	 */
	public function add_copy_recipe_action_rows( $actions, $post ) {
		if ( AUTOMATOR_POST_TYPE_RECIPE !== $post->post_type ) {
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
		$actions['copy_recipe'] = sprintf( '<a href="%s" title="%s">%s</a>', $action, esc_html__( 'Duplicate this recipe', 'uncanny-automator' ), __( 'Duplicate this recipe', 'uncanny-automator' ) );

		return $actions;
	}

	/**
	 * Handle the duplicate row action: nonce + capability checks, duplicate
	 * through the engine, fire `automator_copy_recipe_complete`, redirect to
	 * the new recipe's edit screen (or back on failure).
	 *
	 * @return void
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

		if ( ! current_user_can( 'edit_post', $recipe_id ) ) {
			return;
		}

		if ( AUTOMATOR_POST_TYPE_RECIPE !== get_post_type( $recipe_id ) ) {
			wp_die( esc_attr( sprintf( '%s %s', esc_html__( 'Copy creation failed, could not find original recipe:', 'uncanny-automator' ), htmlspecialchars( $recipe_id ) ) ) );
		}

		// Deep-duplicate through the portability engine.
		$new_recipe_id = $this->duplicate_recipe_via_engine( $recipe_id );

		do_action( 'automator_copy_recipe_complete', $new_recipe_id, $recipe_id );

		// On failure, return to the recipes list (a false id must never reach
		// post.php — `post.php?post=&action=edit` bounces to the Posts list).
		if ( false === $new_recipe_id ) {
			wp_safe_redirect( admin_url( 'edit.php?post_type=' . get_post_type( $recipe_id ) ) );
			exit;
		}

		if ( automator_filter_has_var( 'return_to_recipe' ) ) {
			wp_safe_redirect( admin_url( 'post.php?post=' . $new_recipe_id . '&action=edit' ) );
		} else {
			wp_safe_redirect( admin_url( 'edit.php?post_type=' . get_post_type( $recipe_id ) ) );
		}
		exit;
	}

	/**
	 * Duplicate a whole recipe through the portability engine, preserving the
	 * legacy admin title format and the handler's int|false return contract.
	 *
	 * @param int $recipe_id Source recipe id.
	 *
	 * @return int|false New recipe id, or false on failure.
	 */
	public function duplicate_recipe_via_engine( $recipe_id ) {
		$recipe_id = absint( $recipe_id );

		$title = sprintf(
			/* translators: 1. Original recipe title, 2. Original recipe ID */
			esc_html__( '%1$s (Duplicated from #%2$d)', 'uncanny-automator' ),
			get_post_field( 'post_title', $recipe_id, 'raw' ),
			$recipe_id
		);

		$result = automator_duplicate_recipe( $recipe_id, $title );

		if ( is_wp_error( $result ) || empty( $result['recipe_id'] ) ) {
			return false;
		}

		return (int) $result['recipe_id'];
	}
}
