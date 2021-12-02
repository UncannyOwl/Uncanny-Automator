<?php

namespace Uncanny_Automator;

/**
 * Class PRESTO_VIDEOCOMPLETE
 *
 * @package Uncanny_Automator
 */
class PRESTO_VIDEOCOMPLETE {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'PRESTO';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'PRESTOVIDEOCOMPLETE';
		$this->trigger_meta = 'PRESTOVIDEO';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/presto-player/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - Presto Player */
			'sentence'            => sprintf( esc_attr__( 'A user completes {{a video:%1$s}}', 'uncanny-automator' ), $this->trigger_meta ),
			/* translators: Logged-in trigger - Presto Player */
			'select_option_name'  => esc_attr__( 'A user completes {{a video}}', 'uncanny-automator' ),
			'action'              => 'presto_player_progress',
			'priority'            => 20,
			'accepted_args'       => 2,
			'validation_function' => array( $this, 'video_progress' ),
			'options'             => array(
				Automator()->helpers->recipe->presto->options->list_presto_videos( null, $this->trigger_meta ),
			),
		);

		Automator()->register->trigger( $trigger );
	}

	public function video_progress( $video_id, $percent ) {
		if ( $percent == 100 ) {

			$user_id = get_current_user_id();

			$args = array(
				'code'    => $this->trigger_code,
				'meta'    => $this->trigger_meta,
				'post_id' => $video_id,
				'user_id' => $user_id,
			);

			$arr = Automator()->maybe_add_trigger_entry( $args, false );

			if ( $arr ) {
				foreach ( $arr as $result ) {
					if ( true === $result['result'] ) {
						Automator()->maybe_trigger_complete( $result['args'] );
					}
				}
			}
		}
	}
}
