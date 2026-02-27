<?php

namespace Uncanny_Automator\Integrations\Seo_By_Rank_Math;

/**
 * Class Rank_Math_Seo_Score_Reached
 *
 * @package Uncanny_Automator
 * @method \Uncanny_Automator\Integrations\Seo_By_Rank_Math\Seo_By_Rank_Math_Helpers get_item_helpers()
 */
class Rank_Math_Seo_Score_Reached extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Setup trigger.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		$this->set_integration( 'SEO_BY_RANK_MATH' );
		$this->set_trigger_code( 'RANK_MATH_SEO_SCORE_REACHED' );
		$this->set_trigger_meta( 'RANK_MATH_POST' );
		$this->set_is_login_required( false );
		$this->set_trigger_type( 'anonymous' );
		// translators: %1$s is the post, %2$s is the condition, %3$s is the score threshold.
		$this->set_sentence(
			sprintf(
				esc_html_x( "{{A post's:%1\$s}} SEO score is {{greater than, less than, or equal to:%2\$s}} {{a value:%3\$s}}", 'Rank Math SEO', 'uncanny-automator' ),
				$this->get_trigger_meta(),
				'NUMBERCOND:' . $this->get_trigger_meta(),
				'SCORE_THRESHOLD:' . $this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( "{{A post's}} SEO score is {{greater than, less than, or equal to}} {{a value}}", 'Rank Math SEO', 'uncanny-automator' ) );
		$this->add_action( 'automator_rank_math_seo_data_saved' );
	}

	/**
	 * Define trigger options.
	 *
	 * @return array[]
	 */
	public function options() {

		$options = $this->get_item_helpers()->get_post_type_and_post_options_for_triggers( $this->get_trigger_meta() );

		// Convert legacy less_or_greater_than options to modern format.
		$number_conditions = Automator()->helpers->recipe->field->less_or_greater_than();
		$condition_options = array();

		foreach ( $number_conditions['options'] as $key => $label ) {
			$condition_options[] = array(
				'text'  => $label,
				'value' => $key,
			);
		}

		$options[] = array(
			'option_code'           => 'NUMBERCOND',
			'label'                 => esc_html_x( 'Condition', 'Rank Math SEO', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => true,
			'options'               => $condition_options,
			'supports_custom_value' => false,
		);

		$options[] = array(
			'option_code' => 'SCORE_THRESHOLD',
			'label'       => esc_html_x( 'Score threshold', 'Rank Math SEO', 'uncanny-automator' ),
			'input_type'  => 'int',
			'required'    => true,
			'description' => esc_html_x( 'Enter a value between 0 and 100.', 'Rank Math SEO', 'uncanny-automator' ),
		);

		return $options;
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
				'tokenId'   => 'SEO_SCORE',
				'tokenName' => esc_html_x( 'SEO score', 'Rank Math SEO', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'NUMBERCOND',
				'tokenName' => esc_html_x( 'Condition', 'Rank Math SEO', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'SCORE_THRESHOLD',
				'tokenName' => esc_html_x( 'Score threshold', 'Rank Math SEO', 'uncanny-automator' ),
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

		// Check SEO score against condition.
		$condition     = $trigger['meta']['NUMBERCOND'] ?? '>=';
		$threshold     = isset( $trigger['meta']['SCORE_THRESHOLD'] ) ? (int) $trigger['meta']['SCORE_THRESHOLD'] : 0;
		$current_score = (int) $this->get_item_helpers()->get_meta_value( $post_id, 'rank_math_seo_score' );

		return Automator()->utilities->match_condition_vs_number( $condition, $threshold, $current_score );
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

		$post            = get_post( $post_id );
		$post_title      = null !== $post ? $post->post_title : '';
		$post_type       = null !== $post ? $post->post_type : '';
		$post_type_obj   = ! empty( $post_type ) ? get_post_type_object( $post_type ) : null;
		$post_type_label = null !== $post_type_obj ? $post_type_obj->labels->singular_name : '';

		return array(
			'RANK_MATH_POST_TYPE' => $post_type_label,
			'RANK_MATH_POST'      => $post_title,
			'POST_ID'             => $post_id,
			'POST_TITLE'          => $post_title,
			'POST_URL'            => get_permalink( $post_id ),
			'POST_TYPE'           => $post_type,
			'SEO_SCORE'           => (int) $this->get_item_helpers()->get_meta_value( $post_id, 'rank_math_seo_score' ),
			'NUMBERCOND'          => $trigger['meta']['NUMBERCOND_readable'] ?? '',
			'SCORE_THRESHOLD'     => isset( $trigger['meta']['SCORE_THRESHOLD'] ) ? (int) $trigger['meta']['SCORE_THRESHOLD'] : 0,
		);
	}
}
