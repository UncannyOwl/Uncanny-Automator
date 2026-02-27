<?php

namespace Uncanny_Automator\Integrations\Aioseo;

/**
 * Class Aioseo_Seo_Data_Updated
 *
 * @package Uncanny_Automator
 * @method \Uncanny_Automator\Integrations\Aioseo\Aioseo_Helpers get_item_helpers()
 */
class Aioseo_Seo_Data_Updated extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Setup trigger configuration.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		$this->set_integration( 'AIOSEO' );
		$this->set_trigger_code( 'AIOSEO_SEO_DATA_UPDATED' );
		$this->set_trigger_meta( 'AIOSEO_POST' );
		$this->set_is_pro( false );
		$this->set_is_login_required( false );
		$this->set_trigger_type( 'anonymous' );
		$this->set_uses_api( false );
		// translators: %1$s is the post.
		$this->set_sentence( sprintf( esc_html_x( "{{A post's:%1\$s}} SEO data is updated", 'All in One SEO', 'uncanny-automator' ), $this->get_trigger_meta() ) );
		$this->set_readable_sentence( esc_html_x( "{{A post's}} SEO data is updated", 'All in One SEO', 'uncanny-automator' ) );
		$this->add_action( 'aioseo_insert_post', 10, 1 );
	}

	/**
	 * Define trigger options.
	 *
	 * @return array[]
	 */
	public function options() {
		return $this->get_item_helpers()->get_trigger_post_type_and_post_options( $this->get_trigger_meta() );
	}

	/**
	 * Define available tokens.
	 *
	 * @param array $trigger The trigger settings.
	 * @param array $tokens  Existing tokens.
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array(
			array(
				'tokenId'   => 'POST_ID',
				'tokenName' => esc_html_x( 'Post ID', 'All in One SEO', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'POST_TITLE',
				'tokenName' => esc_html_x( 'Post title', 'All in One SEO', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'POST_TYPE',
				'tokenName' => esc_html_x( 'Post type', 'All in One SEO', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'POST_URL',
				'tokenName' => esc_html_x( 'Post URL', 'All in One SEO', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => 'SEO_TITLE',
				'tokenName' => esc_html_x( 'SEO title', 'All in One SEO', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'SEO_DESCRIPTION',
				'tokenName' => esc_html_x( 'SEO description', 'All in One SEO', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'SEO_SCORE',
				'tokenName' => esc_html_x( 'SEO score', 'All in One SEO', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'CANONICAL_URL',
				'tokenName' => esc_html_x( 'Canonical URL', 'All in One SEO', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => 'OG_TITLE',
				'tokenName' => esc_html_x( 'Open Graph title', 'All in One SEO', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'OG_DESCRIPTION',
				'tokenName' => esc_html_x( 'Open Graph description', 'All in One SEO', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * Validate trigger against hook arguments.
	 *
	 * @param array $trigger   The trigger settings.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		if ( ! isset( $trigger['meta'][ $this->get_trigger_meta() ] ) ) {
			return false;
		}

		list( $post_id ) = $hook_args;

		$post = get_post( $post_id );

		if ( null === $post ) {
			return false;
		}

		// Check post type matches.
		$selected_post_type = $trigger['meta']['AIOSEO_POST_TYPE'] ?? '-1';

		if ( '-1' !== $selected_post_type && $post->post_type !== $selected_post_type ) {
			return false;
		}

		// Check specific post matches.
		$selected_post = $trigger['meta'][ $this->get_trigger_meta() ];

		if ( '-1' !== $selected_post && (int) $post_id !== (int) $selected_post ) {
			return false;
		}

		return true;
	}

	/**
	 * Hydrate token values from hook arguments.
	 *
	 * @param array $trigger   The completed trigger settings.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		list( $post_id ) = $hook_args;

		$post        = get_post( $post_id );
		$aioseo_post = $this->get_item_helpers()->get_aioseo_post( $post_id );

		$post_title      = null !== $post ? $post->post_title : '';
		$post_type       = null !== $post ? $post->post_type : '';
		$post_type_obj   = ! empty( $post_type ) ? get_post_type_object( $post_type ) : null;
		$post_type_label = null !== $post_type_obj ? $post_type_obj->labels->singular_name : '';

		$can_replace     = function_exists( 'aioseo' ) && isset( aioseo()->tags );
		$seo_title       = $can_replace ? aioseo()->tags->replaceTags( $aioseo_post->title ?? '', $post_id ) : ( $aioseo_post->title ?? '' );
		$seo_description = $can_replace ? aioseo()->tags->replaceTags( $aioseo_post->description ?? '', $post_id ) : ( $aioseo_post->description ?? '' );
		$og_title        = $can_replace ? aioseo()->tags->replaceTags( $aioseo_post->og_title ?? '', $post_id ) : ( $aioseo_post->og_title ?? '' );
		$og_description  = $can_replace ? aioseo()->tags->replaceTags( $aioseo_post->og_description ?? '', $post_id ) : ( $aioseo_post->og_description ?? '' );

		return array(
			'AIOSEO_POST_TYPE' => $post_type_label,
			'AIOSEO_POST'      => $post_title,
			'POST_ID'          => $post_id,
			'POST_TITLE'       => $post_title,
			'POST_TYPE'        => $post_type,
			'POST_URL'         => get_permalink( $post_id ),
			'SEO_TITLE'        => $seo_title,
			'SEO_DESCRIPTION'  => $seo_description,
			'SEO_SCORE'        => $aioseo_post->seo_score ?? 0,
			'CANONICAL_URL'    => $aioseo_post->canonical_url ?? '',
			'OG_TITLE'         => $og_title,
			'OG_DESCRIPTION'   => $og_description,
		);
	}
}
