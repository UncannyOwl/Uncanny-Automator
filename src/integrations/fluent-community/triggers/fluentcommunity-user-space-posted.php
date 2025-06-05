<?php
namespace Uncanny_Automator\Integrations\Fluent_Community;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class FLUENTCOMMUNITY_USER_SPACE_POSTED
 *
 * @package Uncanny_Automator
 */
class FLUENTCOMMUNITY_USER_SPACE_POSTED extends Trigger {

	protected $prefix = 'FLUENTCOMMUNITY_USER_SPACE_POSTED';

	protected $helpers;
	/**
	 * Setup trigger.
	 */
	protected function setup_trigger() {
		$this->helpers = array_shift( $this->dependencies );

		$this->set_integration( 'FLUENT_COMMUNITY' );
		$this->set_trigger_code( $this->prefix . '_CODE' );
		$this->set_trigger_meta( $this->prefix . '_META' );
		$this->set_is_pro( false );

		$this->add_action( 'fluent_community/space_feed/created' );
		$this->set_action_args_count( 1 );

		$this->set_sentence(
			sprintf(
				/* translators: %1$s - Space */
				esc_html_x( 'A user posts to {{a space:%1$s}}', 'FluentCommunity', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence(
			esc_html_x( 'A user posts to {{a space}}', 'FluentCommunity', 'uncanny-automator' )
		);
	}
	/**
	 * Options.
	 *
	 * @return mixed
	 */
	public function options() {
		return array(
			array(
				'option_code'           => $this->get_trigger_meta(),
				'label'                 => esc_html_x( 'Space', 'FluentCommunity', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'options'               => $this->helpers->all_spaces( true ),
				'relevant_tokens'       => array(),
				'supports_custom_value' => false,
			),
		);
	}
	/**
	 * Validate.
	 *
	 * @param mixed $trigger The trigger.
	 * @param mixed $hook_args The arguments.
	 * @return mixed
	 */
	public function validate( $trigger, $hook_args ) {
		$feed    = is_object( $hook_args[0] ) ? $hook_args[0] : null;
		$user_id = absint( $feed->user_id ?? 0 );

		if ( ! $user_id || ! isset( $feed->space_id ) ) {
			return false;
		}

		$this->set_user_id( $user_id );

		$selected = isset( $trigger['meta'][ $this->get_trigger_meta() ] )
			? (int) $trigger['meta'][ $this->get_trigger_meta() ]
			: -1;

		return ( intval( '-1' ) === intval( $selected ) || absint( $feed->space_id ) === $selected );
	}


	/**
	 * Hydrate tokens.
	 *
	 * @param mixed $trigger The trigger.
	 * @param mixed $hook_args The arguments.
	 * @return mixed
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {
		$feed = isset( $hook_args[0] ) && is_object( $hook_args[0] ) ? $hook_args[0] : null;

		if ( ! $feed ) {
			return array();
		}

		return array(
			'POST_ID'      => $feed->id,
			'POST_TITLE'   => $feed->title ?? '',
			'POST_CONTENT' => $feed->message ?? '',
			'SPACE_ID'     => $feed->space_id ?? '',
			'SPACE_NAME'   => isset( $feed->space ) ? $feed->space->title : '',
		);
	}
	/**
	 * Define tokens.
	 *
	 * @param mixed $trigger The trigger.
	 * @param mixed $tokens The destination.
	 * @return mixed
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array(
			'POST_ID'      => array(
				'name'      => esc_html_x( 'Post ID', 'Fluent Community', 'uncanny-automator' ),
				'type'      => 'int',
				'tokenId'   => 'POST_ID',
				'tokenName' => esc_html_x( 'Post ID', 'Fluent Community', 'uncanny-automator' ),
			),
			'POST_TITLE'   => array(
				'name'      => esc_html_x( 'Post title', 'Fluent Community', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'POST_TITLE',
				'tokenName' => esc_html_x( 'Post title', 'Fluent Community', 'uncanny-automator' ),
			),
			'POST_CONTENT' => array(
				'name'      => esc_html_x( 'Post content', 'Fluent Community', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'POST_CONTENT',
				'tokenName' => esc_html_x( 'Post content', 'Fluent Community', 'uncanny-automator' ),
			),
			'SPACE_ID'     => array(
				'name'      => esc_html_x( 'Space ID', 'Fluent Community', 'uncanny-automator' ),
				'type'      => 'int',
				'tokenId'   => 'SPACE_ID',
				'tokenName' => esc_html_x( 'Space ID', 'Fluent Community', 'uncanny-automator' ),
			),
			'SPACE_NAME'   => array(
				'name'      => esc_html_x( 'Space name', 'Fluent Community', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'SPACE_NAME',
				'tokenName' => esc_html_x( 'Space name', 'Fluent Community', 'uncanny-automator' ),
			),
		);
	}
}
