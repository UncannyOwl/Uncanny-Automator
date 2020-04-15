<?php

namespace Uncanny_Automator;

/**
 * Class BB_NEWTOPIC
 * @package uncanny_automator
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

		global $uncanny_automator;

		$trigger = array(
			'author'              => $uncanny_automator->get_author_name( $this->trigger_code ),
			'support_link'        => $uncanny_automator->get_author_support_link( $this->trigger_code ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* Translators: 1:bbPress Forums 2:Number of times*/
			'sentence'            => sprintf( __( 'User creates a topic in {{forum:%1$s}} {{a number of:%2$s}} times', 'uncanny-automator' ), $this->trigger_meta, 'NUMTIMES' ),
			'select_option_name'  => __( 'User creates a topic in {{a forum}}', 'uncanny-automator' ),
			'action'              => 'bbp_new_topic',
			'priority'            => 10,
			'accepted_args'       => 4,
			'validation_function' => array( $this, 'bbp_new_topic' ),
			'options'             => [
				$uncanny_automator->helpers->recipe->bbpress->options->list_bbpress_forums(),
				$uncanny_automator->helpers->recipe->options->number_of_times(),
			],
		);

		$uncanny_automator->register->trigger( $trigger );

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

		global $uncanny_automator;
		$user_id = get_current_user_id();

		$args = [
			'code'    => $this->trigger_code,
			'meta'    => $this->trigger_meta,
			'post_id' => $forum_id,
			'user_id' => $user_id,
		];

		$uncanny_automator->maybe_add_trigger_entry( $args );
	}
}
