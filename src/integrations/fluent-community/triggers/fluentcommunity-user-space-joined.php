<?php
namespace Uncanny_Automator\Integrations\Fluent_Community;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class FLUENTCOMMUNITY_USER_SPACE_JOINED
 *
 * @package Uncanny_Automator
 */
class FLUENTCOMMUNITY_USER_SPACE_JOINED extends Trigger {

	protected $prefix = 'FLUENTCOMMUNITY_USER_SPACE_JOINED';

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

		$this->add_action( 'fluent_community/space/joined' );
		$this->set_action_args_count( 3 );

		$this->set_sentence(
			sprintf(
				/* translators: %1$s - Space */
				esc_html_x( 'A user joins {{a space:%1$s}}', 'FluentCommunity', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence(
			esc_html_x( 'A user joins {{a space}}', 'FluentCommunity', 'uncanny-automator' )
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
		$space   = isset( $hook_args[0] ) && is_object( $hook_args[0] ) ? $hook_args[0] : null;
		$user_id = isset( $hook_args[1] ) ? absint( $hook_args[1] ) : null;

		if ( empty( $user_id ) || empty( $space->id ) ) {
			return false;
		}

		$this->set_user_id( $user_id );

		$selected = isset( $trigger['meta'][ $this->get_trigger_meta() ] )
			? (int) $trigger['meta'][ $this->get_trigger_meta() ]
			: -1;

		return ( intval( '-1' ) === intval( $selected ) || absint( $space->id ) === $selected );
	}
	/**
	 * Hydrate tokens.
	 *
	 * @param mixed $trigger The trigger.
	 * @param mixed $hook_args The arguments.
	 * @return mixed
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {
		$space = isset( $hook_args[0] ) ? $hook_args[0] : null;

		if ( ! is_object( $space ) || empty( $space->id ) ) {
			return array();
		}

		return array(
			'SPACE_ID'   => $space->id,
			'SPACE_NAME' => isset( $space->title ) ? $space->title : '',
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
			'SPACE_ID'   => array(
				'name'      => esc_html_x( 'Space ID', 'FluentCommunity', 'uncanny-automator' ),
				'type'      => 'int',
				'tokenId'   => 'SPACE_ID',
				'tokenName' => esc_html_x( 'Space ID', 'FluentCommunity', 'uncanny-automator' ),
			),
			'SPACE_NAME' => array(
				'name'      => esc_html_x( 'Space name', 'FluentCommunity', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'SPACE_NAME',
				'tokenName' => esc_html_x( 'Space name', 'FluentCommunity', 'uncanny-automator' ),
			),
		);
	}
}
