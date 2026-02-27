<?php

namespace Uncanny_Automator\Integrations\Seo_By_Rank_Math;

/**
 * Class Rank_Math_Seo_Data_Updated
 *
 * @package Uncanny_Automator
 * @method \Uncanny_Automator\Integrations\Seo_By_Rank_Math\Seo_By_Rank_Math_Helpers get_item_helpers()
 */
class Rank_Math_Seo_Data_Updated extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Setup trigger.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		$this->set_integration( 'SEO_BY_RANK_MATH' );
		$this->set_trigger_code( 'RANK_MATH_SEO_DATA_UPDATED' );
		$this->set_trigger_meta( 'RANK_MATH_POST' );
		$this->set_is_login_required( false );
		$this->set_trigger_type( 'anonymous' );
		// translators: %1$s is the post.
		$this->set_sentence( sprintf( esc_html_x( "{{A post's:%1\$s}} SEO data is updated", 'Rank Math SEO', 'uncanny-automator' ), $this->get_trigger_meta() ) );
		$this->set_readable_sentence( esc_html_x( "{{A post's}} SEO data is updated", 'Rank Math SEO', 'uncanny-automator' ) );
		$this->add_action( 'automator_rank_math_seo_data_saved' );
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
				'tokenName' => esc_html_x( 'Post ID', 'Rank Math SEO', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'POST_TITLE',
				'tokenName' => esc_html_x( 'Post title', 'Rank Math SEO', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'POST_URL',
				'tokenName' => esc_html_x( 'Post URL', 'Rank Math SEO', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => 'POST_TYPE',
				'tokenName' => esc_html_x( 'Post type', 'Rank Math SEO', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'SEO_TITLE',
				'tokenName' => esc_html_x( 'SEO title', 'Rank Math SEO', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'SEO_DESCRIPTION',
				'tokenName' => esc_html_x( 'SEO description', 'Rank Math SEO', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'FOCUS_KEYWORD',
				'tokenName' => esc_html_x( 'Focus keyword', 'Rank Math SEO', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'SEO_SCORE',
				'tokenName' => esc_html_x( 'SEO score', 'Rank Math SEO', 'uncanny-automator' ),
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

		list( $post_id ) = $hook_args;

		$post = get_post( $post_id );

		if ( null === $post ) {
			return false;
		}

		// Check post type matches.
		$selected_post_type = $trigger['meta']['RANK_MATH_POST_TYPE'] ?? '-1';

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

		$helpers         = $this->get_item_helpers();
		$post            = get_post( $post_id );
		$post_title      = null !== $post ? $post->post_title : '';
		$post_type       = null !== $post ? $post->post_type : '';
		$post_type_obj   = ! empty( $post_type ) ? get_post_type_object( $post_type ) : null;
		$post_type_label = null !== $post_type_obj ? $post_type_obj->labels->singular_name : '';

		// The variable Manager hooks its setup() on `wp`, which doesn't fire during REST API
		// requests (rest_api_loaded() exits early). Force-initialize it so replace_vars() can
		// resolve %title%, %sep%, %sitename% etc. setup() is a no-op if already run.
		if ( isset( rank_math()->variables ) && method_exists( rank_math()->variables, 'setup' ) ) {
			rank_math()->variables->setup();
		}

		$raw_title       = $helpers->get_meta_value( $post_id, 'rank_math_title' );
		$raw_description = $helpers->get_meta_value( $post_id, 'rank_math_description' );
		$seo_title       = class_exists( '\RankMath\Helper' ) ? \RankMath\Helper::replace_vars( $raw_title, $post ) : $raw_title;
		$seo_description = class_exists( '\RankMath\Helper' ) ? \RankMath\Helper::replace_vars( $raw_description, $post ) : $raw_description;

		return array(
			'RANK_MATH_POST_TYPE' => $post_type_label,
			'RANK_MATH_POST'      => $post_title,
			'POST_ID'             => $post_id,
			'POST_TITLE'          => $post_title,
			'POST_URL'            => get_permalink( $post_id ),
			'POST_TYPE'           => $post_type,
			'SEO_TITLE'           => $seo_title,
			'SEO_DESCRIPTION'     => $seo_description,
			'FOCUS_KEYWORD'       => $helpers->get_meta_value( $post_id, 'rank_math_focus_keyword' ),
			'SEO_SCORE'           => $helpers->get_meta_value( $post_id, 'rank_math_seo_score' ),
		);
	}
}
