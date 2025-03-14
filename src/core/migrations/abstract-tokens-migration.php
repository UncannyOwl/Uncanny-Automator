<?php

namespace Uncanny_Automator\Migrations;

/**
 * Tokens_Migration.
 *
 * @package Uncanny_Automator
 */
abstract class Tokens_Migration extends Migration {

	/**
	 * strings_to_replace
	 *
	 * Override this method and return an array of string pairs to replace.
	 *
	 * @return array
	 */
	public function strings_to_replace() {

		return array(
			'{{EXAMPLE' => '{{UT:ADVANCED:EXAMPLE',
		);
	}


	/**
	 * migrate
	 *
	 * @return mixed
	 */
	public function migrate() {

		$actions = $this->get_all_posts( 'uo-action' );

		foreach ( $actions as $action ) {
			$this->migrate_tokens_in_post( $action );
		}

		$recipes = $this->get_all_posts( 'uo-recipe' );

		foreach ( $recipes as $recipe ) {
			$this->migrate_tokens_in_post( $recipe );
		}

		$this->complete();
	}

	/**
	 * get_all_actions
	 *
	 * @return mixed
	 */
	public function get_all_posts( $post_type ) {

		$args = array(
			'post_type'   => $post_type,
			'numberposts' => -1,
			'post_status' => 'any',
		);

		$actions = get_posts( $args );

		return $actions;
	}

	/**
	 * migrate_tokens_in_post
	 *
	 * @param  mixed $post
	 * @return void
	 */
	public function migrate_tokens_in_post( $post ) {

		$post_metas = get_post_meta( $post->ID );

		foreach ( $post_metas as $meta_key => $meta_values ) {
			$meta_value = array_shift( $meta_values );
			$this->maybe_update_meta( $post->ID, $meta_key, $meta_value );
		}

		$this->migrate_tokens_in_content( $post );
	}

	/**
	 * migrate_tokens_in_content
	 *
	 * @param  mixed $post
	 * @return void
	 */
	public function migrate_tokens_in_content( $post ) {

		$initial_content = $post->post_content;
		$updated_content = $this->replace_strings( $initial_content );

		// If nothing changed in the value, move on
		if ( $updated_content === $initial_content ) {
			return;
		}

		$post->post_content = $updated_content;
		wp_update_post( $post );
	}

	/**
	 * maybe_update_meta
	 *
	 * @param  mixed $post_id
	 * @param  mixed $meta_key
	 * @param  mixed $initial_value
	 * @return void
	 */
	public function maybe_update_meta( $post_id, $meta_key, $initial_value ) {

		$updated_value = $this->replace_strings( $initial_value );

		// If nothing changed in the value, move on
		if ( $updated_value === $initial_value ) {
			return;
		}

		update_post_meta( $post_id, $meta_key, $updated_value );
	}

	/**
	 * replace_strings
	 *
	 * @param  string $initial_value
	 * @return string
	 */
	public function replace_strings( $initial_value ) {

		$updated_value = strtr( $initial_value, $this->strings_to_replace() );

		return $updated_value;
	}
}
