<?php

namespace Uncanny_Automator;

/**
 * Class WP_SUBMITPOST
 *
 * @package Uncanny_Automator
 */
class WP_SUBMITPOST {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'WP';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'SUBMITPOST';
		$this->trigger_meta = 'WPPOST';
		// TODO trigger is not finshed
		//$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/wordpress-core/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			'action'              => 'shutdown',
			'validation_function' => array( $this, 'submit_post' ),
			'options_callback'    => array( $this, 'load_options' ),
		);

		Automator()->register->trigger( $trigger );

		return;
	}

	/**
	 * load_options
	 *
	 * @return void
	 */
	public function load_options() {
		$options = array(
			'options' => array(
				array(
					'option_code'        => 'NUMTIMES',
					'label'              => 'Number of Times',
					'input_type'         => 'text',
					// to setup example, lets define the value the child will be based on
					'current_value'      => false,
					'default_value'      => false,
					'validation_type'    => 'integer',
					'validation_message' => 'Please add how many times the page must be submitted.',
				),
			),
		);
		return $options;
	}
}
