<?php

namespace Uncanny_Automator\Integrations\Presto;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class PRESTO_VIDEOCOMPLETE
 *
 * @package Uncanny_Automator
 * @method \Uncanny_Automator\Integrations\Presto\Presto_Helpers get_item_helpers()
 */
class PRESTO_VIDEOCOMPLETE extends Trigger {

	/**
	 * Setup trigger
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		$this->set_integration( 'PRESTO' );
		$this->set_trigger_code( 'PRESTOVIDEOCOMPLETE' );
		$this->set_trigger_meta( 'PRESTOVIDEO' );
		$this->set_is_pro( false );

		// Hook into video progress event
		$this->add_action( 'presto_player_progress', 10, 2 );

		// translators: %1$s: is a video
		$this->set_sentence( sprintf( esc_html_x( 'A user completes {{a video:%1$s}}', 'Presto', 'uncanny-automator' ), $this->get_trigger_meta() ) );

		$this->set_readable_sentence(
			esc_html_x( 'A user completes {{a video}}', 'Presto', 'uncanny-automator' )
		);
	}

	/**
	 * Loads available options for the Trigger.
	 *
	 * @return array The available trigger options.
	 */
	public function options() {
		return array(
			array(
				'option_code'     => $this->get_trigger_meta(),
				'label'           => esc_html_x( 'Video', 'Presto', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => $this->get_item_helpers()->get_all_presto_videos( true ),
				'relevant_tokens' => array(),
			),
		);
	}

	/**
	 * Validate the trigger.
	 *
	 * @param array $trigger The trigger data.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return bool True if validation was successful.
	 */
	public function validate( $trigger, $hook_args ) {
		list( $video_id, $percent ) = $hook_args;

		if ( 100 !== $percent ) {
			return false;
		}

		$selected_video_id = $trigger['meta'][ $this->get_trigger_meta() ];

		// If "Any video" is selected (-1), return true
		if ( intval( '-1' ) === intval( $selected_video_id ) ) {
			return true;
		}

		// Check if the completed video matches the selected video
		$normalized_video_id = $this->get_item_helpers()->get_normalized_video_id( $video_id );
		if ( ! $normalized_video_id ) {
			return false;
		}

		return absint( $selected_video_id ) === absint( $normalized_video_id );
	}

	/**
	 * Hydrate tokens.
	 *
	 * @param array $trigger The trigger configuration.
	 * @param array $hook_args The hook arguments.
	 *
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {
		list( $video_id, $percent ) = $hook_args;

		$video = $this->get_item_helpers()->normalize_video_data( $video_id );
		if ( ! $video ) {
			return array();
		}

		return array(
			$this->get_trigger_meta()                 => $video->title,
			$this->get_trigger_meta() . '_ID'         => $video->id,
			$this->get_trigger_meta() . '_POST_TITLE' => ! empty( $video->hub_id ) ? get_the_title( $video->hub_id ) : '-',
		);
	}

	/**
	 * Define tokens.
	 *
	 * @param array $trigger The trigger configuration.
	 * @param array $tokens The existing tokens.
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array(
			$this->get_trigger_meta()                 => array(
				'name'      => esc_html_x( 'Video title', 'Presto', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => $this->get_trigger_meta(),
				'tokenName' => esc_html_x( 'Video title', 'Presto', 'uncanny-automator' ),
			),
			$this->get_trigger_meta() . '_ID'         => array(
				'name'      => esc_html_x( 'Video ID', 'Presto', 'uncanny-automator' ),
				'type'      => 'int',
				'tokenId'   => $this->get_trigger_meta() . '_ID',
				'tokenName' => esc_html_x( 'Video ID', 'Presto', 'uncanny-automator' ),
			),
			$this->get_trigger_meta() . '_POST_TITLE' => array(
				'name'      => esc_html_x( 'Media hub title', 'Presto', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => $this->get_trigger_meta() . '_POST_TITLE',
				'tokenName' => esc_html_x( 'Media hub title', 'Presto', 'uncanny-automator' ),
			),
		);
	}
}
