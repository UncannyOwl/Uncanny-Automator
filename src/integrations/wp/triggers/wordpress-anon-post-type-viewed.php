<?php

namespace Uncanny_Automator\Integrations\Wp;

/**
 * Class ANON_WP_VIEWPOSTTYPE
 *
 * @package Uncanny_Automator
 * @property Wp_Helpers $item_helpers
 */
class ANON_WP_VIEWPOSTTYPE extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Static definition — opts the trigger into lazy loading.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'WPVIEWPOSTTYPE', 'WP' )
			->trigger_meta( 'WPPOSTTYPES' )
			->trigger_type( 'anonymous' )
			->hook( 'template_redirect', 90, 1 );
	}

	/**
	 * Setup trigger configuration.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		// integration / code / trigger_meta / trigger_type are auto-applied from definition().
		$this->set_is_login_required( false );
		// translators: %1$s Post type.
		$this->set_sentence(
			sprintf(
				esc_html_x( 'A {{specific type of post:%1$s}} is viewed', 'WordPress', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'A {{specific type of post}} is viewed', 'WordPress', 'uncanny-automator' ) );
	}

	/**
	 * Define trigger options.
	 *
	 * @return array[]
	 */
	public function options() {
		return array(
			array(
				'option_code'           => $this->get_trigger_meta(),
				'label'                 => esc_html_x( 'Post type', 'WordPress', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'options'               => array(),
				'supports_custom_value' => false,
				'relevant_tokens'       => array(),
				'remote_data'           => $this->item_helpers->remote_data_load_config( 'post_types' ),
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
			Wp_Shared_Tokens::post_taxonomy_tokens()
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

		if ( ! is_singular() ) {
			return false;
		}

		global $post;

		if ( ! $post instanceof \WP_Post ) {
			return false;
		}

		if ( ! is_post_type_viewable( $post->post_type ) ) {
			return false;
		}

		$selected_post_type = $trigger['meta'][ $this->get_trigger_meta() ] ?? '-1';

		if ( intval( '-1' ) !== intval( $selected_post_type ) && (string) $post->post_type !== (string) $selected_post_type ) {
			return false;
		}

		return true;
	}

	/**
	 * Hydrate token values from hook arguments.
	 *
	 * @param array $trigger   The trigger settings.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		global $post;

		if ( ! $post instanceof \WP_Post ) {
			return array();
		}

		$post_id = (int) $post->ID;

		return array_merge(
			Wp_Shared_Tokens::hydrate_post_core_tokens( $post_id ),
			Wp_Shared_Tokens::hydrate_post_author_tokens( $post_id ),
			Wp_Shared_Tokens::hydrate_post_featured_image_tokens( $post_id ),
			Wp_Shared_Tokens::hydrate_post_date_tokens( $post_id ),
			Wp_Shared_Tokens::hydrate_post_taxonomy_tokens( $post_id )
		);
	}
}
