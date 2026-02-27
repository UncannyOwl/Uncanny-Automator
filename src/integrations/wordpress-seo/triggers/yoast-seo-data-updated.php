<?php

namespace Uncanny_Automator\Integrations\Wordpress_Seo;

/**
 * Class Yoast_Seo_Data_Updated
 *
 * @package Uncanny_Automator
 * @method \Uncanny_Automator\Integrations\Wordpress_Seo\Wordpress_Seo_Helpers get_item_helpers()
 */
class Yoast_Seo_Data_Updated extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Setup trigger.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		$this->set_integration( 'WORDPRESS_SEO' );
		$this->set_trigger_code( 'YOAST_SEO_DATA_UPDATED' );
		$this->set_trigger_meta( 'YOAST_POST' );
		$this->set_is_login_required( false );
		$this->set_trigger_type( 'anonymous' );
		// translators: %1$s is the post.
		$this->set_sentence( sprintf( esc_html_x( "{{A post's:%1\$s}} SEO data is updated", 'Yoast SEO', 'uncanny-automator' ), $this->get_trigger_meta() ) );
		$this->set_readable_sentence( esc_html_x( "{{A post's}} SEO data is updated", 'Yoast SEO', 'uncanny-automator' ) );
		$this->add_action( 'wpseo_save_indexable', 10, 2 );
	}

	/**
	 * Define trigger options.
	 *
	 * @return array[]
	 */
	public function options() {
		return $this->get_item_helpers()->get_post_type_and_post_options_for_triggers( $this->get_trigger_meta() );
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
				'tokenName' => esc_html_x( 'Post ID', 'Yoast SEO', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'POST_TITLE',
				'tokenName' => esc_html_x( 'Post title', 'Yoast SEO', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'POST_URL',
				'tokenName' => esc_html_x( 'Post URL', 'Yoast SEO', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => 'POST_TYPE',
				'tokenName' => esc_html_x( 'Post type', 'Yoast SEO', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'SEO_TITLE',
				'tokenName' => esc_html_x( 'SEO title', 'Yoast SEO', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'SEO_DESCRIPTION',
				'tokenName' => esc_html_x( 'SEO description', 'Yoast SEO', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'FOCUS_KEYWORD',
				'tokenName' => esc_html_x( 'Focus keyphrase', 'Yoast SEO', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'SEO_SCORE',
				'tokenName' => esc_html_x( 'SEO score', 'Yoast SEO', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'READABILITY_SCORE',
				'tokenName' => esc_html_x( 'Readability score', 'Yoast SEO', 'uncanny-automator' ),
				'tokenType' => 'int',
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

		list( $indexable, $indexable_before ) = $hook_args;

		// Only fire for post indexables.
		if ( ! isset( $indexable->object_type ) || 'post' !== $indexable->object_type ) {
			return false;
		}

		// Skip internal Yoast saves (e.g. link count updates) that don't change SEO data.
		// wpseo_save_indexable fires multiple times per post save; only the one that
		// actually modifies SEO fields should trigger the automation.
		$seo_fields     = array( 'title', 'description', 'primary_focus_keyword', 'primary_focus_keyword_score', 'readability_score' );
		$has_seo_change = false;

		foreach ( $seo_fields as $field ) {
			if ( ( $indexable->{$field} ?? null ) !== ( $indexable_before->{$field} ?? null ) ) {
				$has_seo_change = true;
				break;
			}
		}

		if ( ! $has_seo_change ) {
			return false;
		}

		$post_id = $indexable->object_id ?? 0;
		$post    = get_post( $post_id );

		if ( null === $post ) {
			return false;
		}

		// Check post type matches.
		$selected_post_type = $trigger['meta']['YOAST_POST_TYPE'] ?? '-1';

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

		list( $indexable ) = $hook_args;

		$post_id         = $indexable->object_id ?? 0;
		$post            = get_post( $post_id );
		$post_title      = null !== $post ? $post->post_title : '';
		$post_type       = null !== $post ? $post->post_type : '';
		$post_type_obj   = ! empty( $post_type ) ? get_post_type_object( $post_type ) : null;
		$post_type_label = null !== $post_type_obj ? $post_type_obj->labels->singular_name : '';

		$seo_title       = function_exists( 'wpseo_replace_vars' ) ? wpseo_replace_vars( $indexable->title ?? '', $post ) : ( $indexable->title ?? '' );
		$seo_description = function_exists( 'wpseo_replace_vars' ) ? wpseo_replace_vars( $indexable->description ?? '', $post ) : ( $indexable->description ?? '' );

		return array(
			'YOAST_POST_TYPE'   => $post_type_label,
			'YOAST_POST'        => $post_title,
			'POST_ID'           => $post_id,
			'POST_TITLE'        => $post_title,
			'POST_URL'          => get_permalink( $post_id ),
			'POST_TYPE'         => $post_type,
			'SEO_TITLE'         => $seo_title,
			'SEO_DESCRIPTION'   => $seo_description,
			'FOCUS_KEYWORD'     => $indexable->primary_focus_keyword ?? '',
			'SEO_SCORE'         => $indexable->primary_focus_keyword_score ?? 0,
			'READABILITY_SCORE' => $indexable->readability_score ?? 0,
		);
	}
}
