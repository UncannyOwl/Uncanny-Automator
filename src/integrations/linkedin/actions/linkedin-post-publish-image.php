<?php

namespace Uncanny_Automator\Integrations\Linkedin;

use Uncanny_Automator\Automator;

/**
 * Class LINKEDIN_POST_PUBLISH_IMAGE
 *
 * @package Uncanny_Automator
 *
 * @property Linkedin_App_Helpers $helpers
 * @property Linkedin_Api_Caller $api
 */
class LINKEDIN_POST_PUBLISH_IMAGE extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Setup the action.
	 *
	 * @return void
	 */
	public function setup_action() {

		$this->set_integration( 'LINKEDIN' );
		$this->set_action_code( 'LINKEDIN_POST_PUBLISH_IMAGE' );
		$this->set_action_meta( 'LINKEDIN_POST_PUBLISH_IMAGE_META' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/linkedin/' ) );
		$this->set_requires_user( false );
		$this->set_wpautop( false );
		$this->set_readable_sentence( esc_attr_x( 'Publish a post with an image to {{a LinkedIn page}}', 'LinkedIn', 'uncanny-automator' ) );
		$this->set_sentence(
			sprintf(
				// translators: %1$s is the LinkedIn page name
				esc_attr_x( 'Publish a post with an image to {{a LinkedIn page:%1$s}}', 'LinkedIn', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->helpers->get_page_option_config( $this->get_action_meta() ),
			$this->helpers->get_image_option_config( 'IMAGE' ),
			$this->helpers->get_content_option_config( 'BODY' ),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int   $user_id     The user ID.
	 * @param array $action_data The action data.
	 * @param int   $recipe_id   The recipe ID.
	 * @param array $args        Additional args.
	 * @param array $parsed      Parsed token values.
	 *
	 * @return bool
	 * @throws \Exception If API call fails.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$content = sanitize_textarea_field( $parsed['BODY'] ?? '' );

		// Escape LinkedIn markdown special characters.
		$content = preg_replace_callback(
			'/([\(\)\{\}\[\]])|([@*<>\\\\\_~])/m',
			function ( $matches ) {
				return '\\' . $matches[0];
			},
			$content
		);

		$urn       = sanitize_text_field( $parsed[ $this->get_action_meta() ] ?? '' );
		$image     = sanitize_text_field( $parsed['IMAGE'] ?? '' );
		$image_url = $this->resolve_image_url( $image );

		// Handles invalid Media ID or URL.
		if ( false === $image_url || ! filter_var( $image_url, FILTER_VALIDATE_URL ) ) {
			throw new \Exception(
				esc_html_x( 'Input error: Check token value. The provided image URL is empty or invalid.', 'LinkedIn', 'uncanny-automator' )
			);
		}

		$this->api->publish_post( $content, $urn, $action_data, $image_url );

		return true;
	}

	/**
	 * Resolves the image URL from a Media Library ID or public URL.
	 *
	 * @param mixed $media The public image URL or the Media Library ID.
	 *
	 * @return string|false The URL of the image or false on failure.
	 */
	private function resolve_image_url( $media ) {
		return absint( $media ) > 0
			? wp_get_attachment_url( $media )
			: $media;
	}
}
