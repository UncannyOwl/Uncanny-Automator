<?php

namespace Uncanny_Automator\Integrations\Seo_By_Rank_Math;

/**
 * Class Rank_Math_Delete_Seo_Title
 *
 * @package Uncanny_Automator
 * @method \Uncanny_Automator\Integrations\Seo_By_Rank_Math\Seo_By_Rank_Math_Helpers get_item_helpers()
 */
class Rank_Math_Delete_Seo_Title extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'SEO_BY_RANK_MATH' );
		$this->set_action_code( 'RANK_MATH_DELETE_SEO_TITLE' );
		$this->set_action_meta( 'RANK_MATH_POST' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_background_processing( true );
		// translators: %1$s is the post.
		$this->set_sentence( sprintf( esc_html_x( "Delete {{a post's:%1\$s}} SEO title", 'Rank Math SEO', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_html_x( "Delete {{a post's}} SEO title", 'Rank Math SEO', 'uncanny-automator' ) );
		$this->set_action_tokens(
			array(
				'POST_ID'    => array(
					'name' => esc_html_x( 'Post ID', 'Rank Math SEO', 'uncanny-automator' ),
					'type' => 'int',
				),
				'POST_TITLE' => array(
					'name' => esc_html_x( 'Post title', 'Rank Math SEO', 'uncanny-automator' ),
					'type' => 'text',
				),
			),
			$this->get_action_code()
		);
	}

	/**
	 * Define action options.
	 *
	 * @return array[]
	 */
	public function options() {
		return $this->get_item_helpers()->get_post_type_and_post_options( $this->get_action_meta() );
	}

	/**
	 * Process the action.
	 *
	 * @param int   $user_id     The user ID.
	 * @param array $action_data The action configuration.
	 * @param int   $recipe_id   The recipe ID.
	 * @param array $args        Additional arguments.
	 * @param array $parsed      Parsed token values.
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$post_id = absint( $parsed[ $this->get_action_meta() ] );
		$post    = get_post( $post_id );

		if ( null === $post ) {
			$this->add_log_error( sprintf( esc_html_x( 'Post with ID %d does not exist.', 'Rank Math SEO', 'uncanny-automator' ), $post_id ) );
			return false;
		}

		delete_post_meta( $post_id, 'rank_math_title' );

		$this->hydrate_tokens(
			array(
				'POST_ID'    => $post_id,
				'POST_TITLE' => $post->post_title,
			)
		);

		return true;
	}
}
