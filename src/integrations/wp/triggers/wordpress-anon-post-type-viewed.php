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
			Wp_Shared_Tokens::post_taxonomy_tokens(),
			$this->legacy_alias_tokens()
		);
	}

	/**
	 * Legacy token aliases (<= 7.2.5).
	 *
	 * The legacy framework auto-generated meta-named field tokens for the
	 * post selector (WPPOST / _ID / _URL). The migration replaced them with
	 * the generic Wp_Shared_Tokens set (POSTTITLE, POSTID, ...), orphaning
	 * every saved {id}:WPVIEWPOSTTYPE:WPPOST* pill — red in the builder,
	 * unresolved at parse. Both sets are defined and hydrated so pills from
	 * either era resolve. Names and types are the legacy ones verbatim
	 * (tests/wpunit/migration snapshot contract).
	 *
	 * @return array[]
	 */
	private function legacy_alias_tokens() {
		return array(
			array(
				'tokenId'   => 'WPPOST',
				'tokenName' => esc_html_x( 'Post title (legacy)', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'WPPOST_ID',
				'tokenName' => esc_html_x( 'Post ID (legacy)', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'WPPOST_URL',
				'tokenName' => esc_html_x( 'Post URL (legacy)', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'url',
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

		$values = array_merge(
			Wp_Shared_Tokens::hydrate_post_core_tokens( $post_id ),
			Wp_Shared_Tokens::hydrate_post_author_tokens( $post_id ),
			Wp_Shared_Tokens::hydrate_post_featured_image_tokens( $post_id ),
			Wp_Shared_Tokens::hydrate_post_date_tokens( $post_id ),
			Wp_Shared_Tokens::hydrate_post_taxonomy_tokens( $post_id )
		);

		// Legacy aliases mirror the generic values bit-for-bit — see legacy_alias_tokens().
		$values['WPPOST']     = $values['POSTTITLE'] ?? '';
		$values['WPPOST_ID']  = $values['POSTID'] ?? 0;
		$values['WPPOST_URL'] = $values['POSTURL'] ?? '';

		return $values;
	}
}
