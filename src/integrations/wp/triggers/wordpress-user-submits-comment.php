<?php

namespace Uncanny_Automator\Integrations\Wp;

/**
 * Class WP_SUBMITCOMMENT
 *
 * Fires when a user submits a comment on a post.
 *
 * @package Uncanny_Automator\Integrations\Wp
 *
 * @property Wp_Helpers $item_helpers
 */
class WP_SUBMITCOMMENT extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Static definition — opts the trigger into lazy loading.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'WPSUBMITCOMMENT', 'WP' )
			->trigger_meta( 'WPPOSTCOMMENTS' )
			->hook( 'comment_post', 90, 3 );
	}

	/**
	 * Sets up the trigger properties and action hook.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		// integration / code / trigger_meta / trigger_type are auto-applied from definition().

		// translators: %1$s is a post, %2$s is a number of times.
		$this->set_sentence(
			sprintf(
				esc_html_x( 'A user submits a comment on {{a post:%1$s}} {{a number of:%2$s}} time(s)', 'WordPress', 'uncanny-automator' ),
				$this->get_trigger_meta(),
				'NUMTIMES:' . $this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence(
			esc_html_x( 'A user submits a comment on {{a post}}', 'WordPress', 'uncanny-automator' )
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
			Wp_Shared_Tokens::comment_author_tokens(),
			Wp_Shared_Tokens::numtimes_token()
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

		// Akismet check.
		if ( true === $this->item_helpers->should_block_comment_by_akismet( $trigger, $comment_approved, $commentdata ) ) {
			return false;
		}

		$post_type     = get_post_type( $commentdata['comment_post_ID'] );
		$selected_type = $trigger['meta']['WPPOSTTYPES'] ?? '';
		$selected_post = $trigger['meta'][ $this->get_trigger_meta() ] ?? '';

		// Match post type if set.
		if ( ! empty( $selected_type ) && intval( '-1' ) !== intval( $selected_type ) && (string) $post_type !== (string) $selected_type ) {
			return false;
		}

		// Match specific post (or "Any").
		if ( intval( '-1' ) !== intval( $selected_post ) && intval( $commentdata['comment_post_ID'] ) !== intval( $selected_post ) ) {
			return false;
		}

		// Bind the run to the commenter explicitly. The framework's fallback to
		// get_current_user_id() is fine for synchronous browser submissions but
		// returns 0 (or the wrong user) for REST/CLI/headless flows. Guest
		// comments have user_id = 0 — this trigger is the logged-in variant
		// (see ANON_WP_SUBMITCOMMENT for guests), so bail out for them.
		$commenter_user_id = absint( $commentdata['user_id'] ?? 0 );
		if ( 0 === $commenter_user_id ) {
			return false;
		}
		$this->set_user_id( $commenter_user_id );

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
			Wp_Shared_Tokens::hydrate_comment_author_tokens( $comment_id ),
			Wp_Shared_Tokens::hydrate_numtimes_token( $trigger )
		);
	}
}
