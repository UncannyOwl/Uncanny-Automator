<?php

namespace Uncanny_Automator\Integrations\Wordpress_Seo;

/**
 * Class Yoast_Set_Seo_Description
 *
 * @package Uncanny_Automator
 * @method \Uncanny_Automator\Integrations\Wordpress_Seo\Wordpress_Seo_Helpers get_item_helpers()
 */
class Yoast_Set_Seo_Description extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'WORDPRESS_SEO' );
		$this->set_action_code( 'YOAST_SET_SEO_DESCRIPTION' );
		$this->set_action_meta( 'YOAST_POST' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_background_processing( true );
		// translators: %1$s is the post, %2$s is the SEO description value.
		$this->set_sentence( sprintf( esc_html_x( "Set {{a post's:%1\$s}} SEO description to {{a specific value:%2\$s}}", 'Yoast SEO', 'uncanny-automator' ), $this->get_action_meta(), 'SEO_DESCRIPTION_VALUE:' . $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_html_x( "Set {{a post's}} SEO description to {{a specific value}}", 'Yoast SEO', 'uncanny-automator' ) );
		$this->set_action_tokens(
			array(
				'POST_ID'         => array(
					'name' => esc_html_x( 'Post ID', 'Yoast SEO', 'uncanny-automator' ),
					'type' => 'int',
				),
				'POST_TITLE'      => array(
					'name' => esc_html_x( 'Post title', 'Yoast SEO', 'uncanny-automator' ),
					'type' => 'text',
				),
				'SEO_DESCRIPTION' => array(
					'name' => esc_html_x( 'SEO description', 'Yoast SEO', 'uncanny-automator' ),
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

		$options = $this->get_item_helpers()->get_post_type_and_post_options( $this->get_action_meta() );

		$options[] = array(
			'option_code' => 'SEO_DESCRIPTION_VALUE',
			'label'       => esc_html_x( 'SEO description', 'Yoast SEO', 'uncanny-automator' ),
			'input_type'  => 'textarea',
			'required'    => true,
		);

		return $options;
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

		$post_id         = absint( $parsed[ $this->get_action_meta() ] );
		$seo_description = sanitize_textarea_field( $parsed['SEO_DESCRIPTION_VALUE'] ?? '' );

		$post = get_post( $post_id );

		if ( null === $post ) {
			$this->add_log_error( sprintf( esc_html_x( 'Post with ID %d does not exist.', 'Yoast SEO', 'uncanny-automator' ), $post_id ) );
			return false;
		}

		$this->get_item_helpers()->update_yoast_meta( $post_id, 'metadesc', $seo_description );

		$this->hydrate_tokens(
			array(
				'POST_ID'         => $post_id,
				'POST_TITLE'      => $post->post_title,
				'SEO_DESCRIPTION' => $seo_description,
			)
		);

		return true;
	}
}
