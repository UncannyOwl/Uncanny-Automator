<?php

namespace Uncanny_Automator\Integrations\Wp;

/**
 * Class WP_POSTRECEIVESCOMMENT
 *
 * Fires when a user's post receives a comment.
 *
 * @package Uncanny_Automator\Integrations\Wp
 *
 * @property Wp_Helpers $item_helpers
 */
class WP_POSTRECEIVESCOMMENT extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Static definition — opts the trigger into lazy loading.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'WPCOMMENTRECEIVED', 'WP' )
			->trigger_meta( 'USERSPOSTCOMMENT' )
			->hook( 'comment_post', 90, 3 );
	}

	/**
	 * Sets up the trigger properties and action hook.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		// integration / code / trigger_meta / trigger_type are auto-applied from definition().

		// translators: %1$s is a post.
		$this->set_sentence(
			sprintf(
				esc_html_x( "{{A user's post:%1\$s}} receives a comment", 'WordPress', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence(
			esc_html_x( "{{A user's post}} receives a comment", 'WordPress', 'uncanny-automator' )
		);
	}

	/**
	 * Define the trigger options.
	 *
	 * @return array
	 */
	public function options() {

		$fields = array(
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
		);

		// Add Akismet checkbox if plugin is active.
		if ( defined( 'AKISMET_VERSION' ) ) {
			$fields[] = array(
				'input_type'    => 'checkbox',
				'label'         => esc_html_x( 'Trigger only if the comment passes Akismet spam filtering', 'WordPress', 'uncanny-automator' ),
				'option_code'   => 'AKISMET_CHECK',
				'is_toggle'     => true,
				'default_value' => false,
			);
		}

		return $fields;
	}

	/**
	 * Define trigger tokens.
	 *
	 * @param array $trigger The trigger data.
	 * @param array $tokens  Existing tokens.
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array_merge(
			Wp_Shared_Tokens::post_core_tokens(),
			Wp_Shared_Tokens::post_author_tokens(),
			Wp_Shared_Tokens::post_featured_image_tokens(),
			Wp_Shared_Tokens::comment_core_tokens(),
			Wp_Shared_Tokens::comment_author_tokens()
		);
	}

	/**
	 * Validate the trigger.
	 *
	 * @param array $trigger   The trigger data.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		list( $comment_id, $comment_approved, $commentdata ) = $hook_args;

		// Skip comments posted by Automator.
		if ( isset( $commentdata['posted_by_automator'] ) ) {
			return false;
		}

		// Set user_id to the post author.
		$post_author = get_post_field( 'post_author', (int) $commentdata['comment_post_ID'] );
		$this->set_user_id( absint( $post_author ) );

		// Akismet check.
		if ( true === $this->item_helpers->should_block_comment_by_akismet( $trigger, $comment_approved, $commentdata ) ) {
			return false;
		}

		// Match selected post (or "Any").
		$selected_post = $trigger['meta'][ $this->get_trigger_meta() ] ?? '';

		if ( intval( '-1' ) !== intval( $selected_post ) && intval( $commentdata['comment_post_ID'] ) !== intval( $selected_post ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Hydrate trigger tokens with runtime values.
	 *
	 * @param array $trigger   The trigger data.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		list( $comment_id, $comment_approved, $commentdata ) = $hook_args;

		$post_id    = (int) $commentdata['comment_post_ID'];
		$comment_id = (int) $comment_id;

		return array_merge(
			Wp_Shared_Tokens::hydrate_post_core_tokens( $post_id ),
			Wp_Shared_Tokens::hydrate_post_author_tokens( $post_id ),
			Wp_Shared_Tokens::hydrate_post_featured_image_tokens( $post_id ),
			Wp_Shared_Tokens::hydrate_comment_core_tokens( $comment_id ),
			Wp_Shared_Tokens::hydrate_comment_author_tokens( $comment_id )
		);
	}
}
