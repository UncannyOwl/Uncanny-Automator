<?php

namespace Uncanny_Automator;

/**
 * Class EDD_ORDERDONE
 * @package Uncanny_Automator
 */
class EDD_ORDERDONE {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'EDD';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * SetAutomatorTriggers constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'EDDORDERDONE';
		$this->trigger_meta = 'EDDORDERTOTAL';
		//$this->define_trigger();
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
			/* translators: Logged-in trigger - Easy Digital Downloads */
			'sentence'            => sprintf(  esc_attr__( 'Order total is {{equals to:%1$s}} ${{0:%2$s}} and placed {{a number of:%3$s}} time(s)', 'uncanny-automator' ), 'NUMBERCOND', $this->trigger_meta, 'NUMTIMES' ),
			/* translators: Logged-in trigger - Easy Digital Downloads */
			'select_option_name'  =>  esc_attr__( 'User completes {{an order}}', 'uncanny-automator' ),
			'action'              => 'edd_complete_purchase',
			'priority'            => 10,
			'accepted_args'       => 1,
			'validation_function' => array( $this, 'edd_complete_purchase' ),
			'options'             => [
				$uncanny_automator->helpers->recipe->field->integer_field( $this->trigger_meta ),
				$uncanny_automator->helpers->recipe->field->less_or_greater_than(),
				$uncanny_automator->helpers->recipe->options->number_of_times(),
			],
		);

		$uncanny_automator->register->trigger( $trigger );

		return;
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $payment_id
	 */
	public function edd_complete_purchase( $payment_id ) {

		//TODO:: Complete this function
		global $uncanny_automator;

		$post_id = 0;
		$user_id = get_current_user_id();
		$args    = [
			'code'    => $this->trigger_code,
			'meta'    => $this->trigger_meta,
			'post_id' => $post_id,
			'user_id' => $user_id,
		];

		$uncanny_automator->maybe_add_trigger_entry( $args );
	}
}
