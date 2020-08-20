<?php

namespace Uncanny_Automator;

/**
 * Class EDD_PRODUCTPURCHASE
 * @package Uncanny_Automator
 */
class EDD_PRODUCTPURCHASE {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'EDD';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'EDD_PRODUCTPURCHASE';
		$this->trigger_meta = 'EDDPRODUCT';
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
			/* translators: Logged-in trigger - Easy Digital Downloads */
			'sentence'            => sprintf(  esc_attr__( 'A user purchases {{a product:%1$s}}', 'uncanny-automator' ), $this->trigger_meta ),
			/* translators: Logged-in trigger - Easy Digital Downloads */
			'select_option_name'  =>  esc_attr__( 'A user purchases {{a product}}', 'uncanny-automator' ),
			'action'              => 'edd_complete_purchase',
			'priority'            => 10,
			'accepted_args'       => 1,
			'validation_function' => array( $this, 'edd_product_purchase' ),
			'options'             => [
				$uncanny_automator->helpers->recipe->edd->options->all_edd_downloads(  esc_attr__( 'Product', 'uncanny-automator' ), $this->trigger_meta ),
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
	public function edd_product_purchase( $payment_id ) {

		global $uncanny_automator;

		$cart_items = edd_get_payment_meta_cart_details( $payment_id );
		if ( empty( $cart_items ) ) {
			return;
		}

		foreach ( $cart_items as $item ) {
			$post_id = $item['id'];
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
}