<?php

namespace Uncanny_Automator\Integrations\Wp;

/**
 * Class WP_VIEWCUSTOMPOST
 *
 * @package Uncanny_Automator
 * @property Wp_Helpers $item_helpers
 */
class WP_VIEWCUSTOMPOST extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Static definition — opts the trigger into lazy loading.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'VIEWCUSTOMPOST', 'WP' )
			->trigger_meta( 'WPCUSTOMPOST' )
			->hook( 'template_redirect', 90, 1 );
	}

	/**
	 * Setup trigger configuration.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		// integration / code / trigger_meta / trigger_type are auto-applied from definition().
		$this->set_is_login_required( true );
		// translators: %1$s is a custom post type, %2$s is a number of times.
		$this->set_sentence(
			sprintf(
				esc_html_x( 'A user views {{a custom post type:%1$s}} {{a number of:%2$s}} time(s)', 'WordPress', 'uncanny-automator' ),
				$this->get_trigger_meta(),
				'NUMTIMES:' . $this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'A user views {{a custom post type}} {{a number of}} time(s)', 'WordPress', 'uncanny-automator' ) );
	}

	/**
	 * Define trigger options.
	 *
	 * @return array[]
	 */
	public function options() {
		return array(
			array(
				'option_code'           => 'WPPOSTTYPES',
				'label'                 => esc_html_x( 'Post type', 'WordPress', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'options'               => array(),
				'supports_custom_value' => false,
				'remote_data'           => $this->item_helpers->remote_data_load_config( 'post_types' ),
			),
			array(
				'option_code'           => $this->get_trigger_meta(),
				'label'                 => esc_html_x( 'Post', 'WordPress', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'options'               => array(),
				'supports_custom_value' => false,
				'remote_data'           => $this->item_helpers->remote_data_parent_config( 'posts_by_type', array( 'WPPOSTTYPES' ) ),
			),
			array(
				'option_code'            => 'NUMTIMES',
				'label'                  => esc_html_x( 'Number of times', 'WordPress', 'uncanny-automator' ),
				'show_label_in_sentence' => false,
				'placeholder'            => esc_html_x( 'Example: 1', 'WordPress', 'uncanny-automator' ),
				'input_type'             => 'int',
				'default_value'          => 1,
				'min_number'             => 1,
				'required'               => true,
			),
		);
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
		return array_merge(
			Wp_Shared_Tokens::post_core_tokens(),
			Wp_Shared_Tokens::post_author_tokens(),
			Wp_Shared_Tokens::post_featured_image_tokens(),
			Wp_Shared_Tokens::post_date_tokens(),
			Wp_Shared_Tokens::post_taxonomy_tokens(),
			Wp_Shared_Tokens::numtimes_token()
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

		$post = get_queried_object();

		if ( ! $post instanceof \WP_Post ) {
			return false;
		}

		if ( ! is_singular() ) {
			return false;
		}

		// Match post type (exact or any).
		$selected_post_type = $trigger['meta']['WPPOSTTYPES'] ?? '';
		if ( intval( '-1' ) !== intval( $selected_post_type ) && $post->post_type !== $selected_post_type ) {
			return false;
		}

		// Match specific post (exact or any).
		$selected_post = $trigger['meta'][ $this->get_trigger_meta() ] ?? '';
		if ( intval( '-1' ) !== intval( $selected_post ) && absint( $post->ID ) !== absint( $selected_post ) ) {
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

		$post = get_queried_object();

		if ( ! $post instanceof \WP_Post ) {
			return array();
		}

		$post_id = (int) $post->ID;

		return array_merge(
			Wp_Shared_Tokens::hydrate_post_core_tokens( $post_id ),
			Wp_Shared_Tokens::hydrate_post_author_tokens( $post_id ),
			Wp_Shared_Tokens::hydrate_post_featured_image_tokens( $post_id ),
			Wp_Shared_Tokens::hydrate_post_date_tokens( $post_id ),
			Wp_Shared_Tokens::hydrate_post_taxonomy_tokens( $post_id ),
			Wp_Shared_Tokens::hydrate_numtimes_token( $trigger )
		);
	}
}
