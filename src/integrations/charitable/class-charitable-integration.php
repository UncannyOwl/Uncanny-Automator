<?php

namespace Uncanny_Automator\Integrations\Charitable;

/**
 * Class Charitable_Integration
 *
 * @package Uncanny_Automator
 */
class Charitable_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Setup Automator integration.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new Charitable_Helpers();
		$this->set_integration( 'CHARITABLE' );
		$this->set_name( 'Charitable' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/charitable-icon.svg' );
	}

	/**
	 * Load Integration Classes.
	 *
	 * @return void
	 */
	public function load() {

		// Native Charitable -> Automator hook bridges must register even in
		// targeted mode, so they live in load_shared_hooks(). load() calls it
		// for full-load mode (recipe editor / Automator admin pages).
		$this->load_shared_hooks();

		// Load triggers.
		new ANON_CHARITABLE_MADE_DONATION( $this->helpers );
		new CHARITABLE_USER_MADE_DONATION( $this->helpers );
		new CHARITABLE_DONATION_STATUS_CHANGED( $this->helpers );
		new CHARITABLE_DONATION_COMPLETED( $this->helpers );
		new CHARITABLE_DONATION_REFUNDED( $this->helpers );
		new CHARITABLE_DONOR_CREATED( $this->helpers );
		new CHARITABLE_USER_REGISTERED( $this->helpers );
		new CHARITABLE_CAMPAIGN_ENDED( $this->helpers );
		new CHARITABLE_CAMPAIGN_GOAL_REACHED( $this->helpers );

		// Load actions.
		new CHARITABLE_CREATE_OFFLINE_DONATION( $this->helpers );
		new CHARITABLE_UPDATE_DONATION_STATUS( $this->helpers );
		new CHARITABLE_CREATE_DONOR( $this->helpers );
		new CHARITABLE_UPDATE_DONOR( $this->helpers );
		new CHARITABLE_ADD_DONOR_TAG( $this->helpers );
	}

	/**
	 * Shared hooks required for Charitable execution.
	 *
	 * These native Charitable hooks re-dispatch the internal
	 * automator_charitable_donation_made action that the donation triggers
	 * listen on. They must run whenever the integration is needed — including
	 * targeted (runtime) mode, where the base does NOT call load(). Registering
	 * them in load() alone left them unregistered on live donations, so the
	 * trigger silently never fired.
	 *
	 * @return void
	 */
	protected function load_shared_hooks() {

		// Charitable Hooks fire at different times and with different parameters.
		add_action( 'charitable_after_donation', array( $this, 'charitable_frontend_first_page_after_donation' ), PHP_INT_MAX, 1 );
		add_action( 'charitable_donation_save', array( $this, 'charitable_admin_updated_donation' ), PHP_INT_MAX, 2 );
	}

	/**
	 * Charitable frontend hook for first page load after a donation is made.
	 *
	 * @param Charitable_Donation_Processor $processor
	 *
	 * @return void
	 */
	public function charitable_frontend_first_page_after_donation( $processor ) {
		if ( is_a( $processor, 'Charitable_Donation_Processor' ) ) {
			// Trigger our custom action for triggers
			do_action( 'automator_charitable_donation_made', $processor->get_donation_id() );
		}
	}

	/**
	 * Charitable admin hook for donation update.
	 *
	 * @param int $donation_id
	 * @param WP_Post $post
	 *
	 * @return void
	 */
	public function charitable_admin_updated_donation( $donation_id, $post ) {
		// Check it's an approved status.
		if ( ! is_a( $post, 'WP_Post' ) || ! charitable_is_approved_status( $post->post_status ) ) {
			return;
		}
		// Get the original post status.
		$old_status = ! empty( $_POST['original_post_status'] ) ? wp_unslash( $_POST['original_post_status'] ) : ''; // phpcs:ignore WordPress.Security
		if ( $old_status !== $post->post_status ) {
			// Trigger our custom action for triggers
			do_action( 'automator_charitable_donation_made', $donation_id );
		}
	}

	/**
	 * Check if Plugin is active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'Charitable' );
	}
}
