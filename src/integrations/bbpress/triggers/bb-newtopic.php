<?php

namespace Uncanny_Automator;

/**
 * Class BB_NEWTOPIC
 * @package Uncanny_Automator
 */
class BB_NEWTOPIC {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'BB';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'BBNEWTOPIC';
		$this->trigger_meta = 'BBFORUMS';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {



		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/bbpress/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - bbPress */
			'sentence'            => sprintf( esc_attr__( 'A user creates a topic in {{a forum:%1$s}} {{a number of:%2$s}} time(s)', 'uncanny-automator' ), $this->trigger_meta, 'NUMTIMES' ),
			/* translators: Logged-in trigger - bbPress */
			'select_option_name'  => esc_attr__( 'A user creates a topic in {{a forum}}', 'uncanny-automator' ),
			'action'              => 'bbp_new_topic',
			'priority'            => 10,
			'accepted_args'       => 4,
			'validation_function' => array( $this, 'bbp_new_topic' ),
			'options'             => [
				Automator()->helpers->recipe->bbpress->options->list_bbpress_forums( null, $this->trigger_meta, true ),
				Automator()->helpers->recipe->options->number_of_times(),
			],
		);

		Automator()->register->trigger( $trigger );

		return;
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $topic_id
	 * @param $forum_id
	 * @param $anonymous_data
	 * @param $topic_author
	 */
	public function bbp_new_topic( $topic_id, $forum_id, $anonymous_data, $topic_author ) {


		$user_id = get_current_user_id();

		$args = [
			'code'    => $this->trigger_code,
			'meta'    => $this->trigger_meta,
			'post_id' => $forum_id,
			'user_id' => $user_id,
		];

		Automator()->maybe_add_trigger_entry( $args );
	}
}
