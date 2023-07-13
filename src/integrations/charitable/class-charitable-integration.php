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

		// Load triggers.
		new ANON_CHARITABLE_MADE_DONATION( $this );
		new CHARITABLE_USER_MADE_DONATION( $this );
		$this->add_wp_hooks();
	}

	/**
	 * Add WP hooks.
	 *
	 * @return void
	 */
	public function add_wp_hooks() {

		// Charitable Hooks fire at different times and with different parameters.
		add_action( 'charitable_after_donation', array( $this, 'charitable_frontend_first_page_after_dontation' ), PHP_INT_MAX, 1 );
		add_action( 'charitable_donation_save', array( $this, 'charitable_admin_updated_donation' ), PHP_INT_MAX, 2 );

	}

	/**
	 * Charitable frontend hook for first page load after a donation is made.
	 *
	 * @param Charitable_Donation_Processor $processor
	 *
	 * @return void
	 */
	public function charitable_frontend_first_page_after_dontation( $processor ) {
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

	/**
	 * Helper Class Instance.
	 *
	 * @return CHARITABLE_HELPERS
	 */
	public function helpers() {
		static $helper = null;
		if ( is_null( $helper ) ) {
			$helper = new CHARITABLE_HELPERS();
		}
		return $helper;
	}

}
