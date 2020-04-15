<?php

namespace Uncanny_Automator;

/**
 * Class MP_PURCHASEPRODUCTONETIME
 * @package uncanny_automator
 */
class MP_PURCHASEPRODUCTONETIME {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'MP';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'MPPURCHASEPRODUCTONETIME';
		$this->trigger_meta = 'MPPRODUCT';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		global $uncanny_automator;

		$trigger = array(
			'author'              => $uncanny_automator->get_author_name(),
			'support_link'        => $uncanny_automator->get_author_support_link(),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* Translators: 1:MemberPress Products */
			'sentence'            => sprintf( __( 'Users purchases {{a one-time subscription product:%1$s}} ', 'uncanny-automator' ), $this->trigger_meta ),
			'select_option_name'  => __( 'Users purchases {{a one-time subscription product}}', 'uncanny-automator' ),
			'action'              => 'mepr-event-non-recurring-transaction-completed',
			'priority'            => 10,
			'accepted_args'       => 1,
			'validation_function' => array( $this, 'mp_product_purchased' ),
			'options'             => [
				$uncanny_automator->helpers->recipe->memberpress->options->all_memberpress_products_onetime( null, $this->trigger_meta, [ 'uo_include_any' => true] ),
			],
		);

		$uncanny_automator->register->trigger( $trigger );

		return;
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param object $event transaction object.
	 */
	public function mp_product_purchased( $event ) {

		global $uncanny_automator;

		$subscription = $event->get_data();

		$args = [
			'code'           => $this->trigger_code,
			'meta'           => $this->trigger_meta,
			'post_id'        => intval( $subscription->rec->product_id ),
			'user_id'        => intval( $subscription->rec->user_id ),
		];

		$uncanny_automator->maybe_add_trigger_entry( $args );
	}
}
