<?php

namespace Uncanny_Automator;

/**
 * Class ADVADS_AD_SET_TO_STATUS
 *
 * @package Uncanny_Automator
 */
class ADVADS_AD_SET_TO_STATUS {

	use Recipe\Triggers;

	public $helpers;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->helpers = new Advanced_Ads_Helpers();
		$this->setup_trigger();

	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function setup_trigger() {
		$this->set_integration( 'ADVADS' );
		$this->set_trigger_code( 'AD_STATUS_SET_CODE' );
		$this->set_trigger_meta( 'ALL_ADS_META' );
		$this->set_is_login_required( true );
		$this->set_action_args_count( 1 );

		/* Translators: Trigger sentence */
		$this->set_sentence( sprintf( esc_html__( '{{An ad:%1$s}} is set to {{a specific status:%2$s}}', 'uncanny-automator' ), $this->get_trigger_meta(), 'AD_STATUS' ) );

		/* Translators: Trigger sentence */
		$this->set_readable_sentence( esc_html__( '{{An ad}} is set to {{a specific status}}', 'uncanny-automator' ) ); // Non-active state sentence to show

		$this->set_action_hook(
			array(
				'advanced-ads-ad-status-draft-to-pending',
				'advanced-ads-ad-status-draft-to-publish',
				'advanced-ads-ad-status-draft-to-advanced_ads_expired',
				'advanced-ads-ad-status-pending-to-draft',
				'advanced-ads-ad-status-pending-to-publish',
				'advanced-ads-ad-status-pending-to-advanced_ads_expired',
				'advanced-ads-ad-status-publish-to-draft',
				'advanced-ads-ad-status-publish-to-pending',
				'advanced-ads-ad-status-publish-to-advanced_ads_expired',
				'advanced-ads-ad-status-advanced_ads_expired-to-publish',
				'advanced-ads-ad-status-advanced_ads_expired-to-pending',
				'advanced-ads-ad-status-advanced_ads_expired-to-draft',
			)
		);
		$this->set_options_callback( array( $this, 'load_options' ) );
		$this->register_trigger();
	}

	/**
	 * @return array
	 */
	public function load_options() {

		return Automator()->utilities->keep_order_of_options(
			array(
				'options' => array(
					$this->helpers->get_all_ads( $this->get_trigger_meta(), true ),
					$this->helpers->ad_statuses( 'AD_STATUS', true ),
				),
			)
		);

	}

	/**
	 * @param ...$args
	 *
	 * @return bool
	 */
	public function validate_trigger( ...$args ) {

		$is_valid   = false;
		list( $ad ) = $args[0];
		if ( isset( $ad ) ) {
			$is_valid = true;
		}

		return $is_valid;

	}

	/**
	 * @param $data
	 *
	 * @return void
	 */
	public function prepare_to_run( $data ) {
		$this->set_conditional_trigger( true );
	}

	/**
	 * Check email subject against the trigger meta
	 *
	 * @param $args
	 */
	public function validate_conditions( ...$args ) {
		list( $ad )                = $args[0];
		$this->actual_where_values = array(); // Fix for when not using the latest Trigger_Recipe_Filters version. Newer integration can omit this line.
		// Get ad ID and status.
		$status = $ad->status;
		$ad_id  = $ad->id;

		// Find ad ID and status
		return $this->find_all( $this->trigger_recipes() )
					->where( array( $this->get_trigger_meta(), 'AD_STATUS' ) )
					->match( array( $ad_id, $status ) )
					->format( array( 'intval', 'trim' ) )
					->get();
	}

}
