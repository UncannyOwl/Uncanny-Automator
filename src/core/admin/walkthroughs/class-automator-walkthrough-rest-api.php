<?php

namespace Uncanny_Automator;

use WP_REST_Request;
use WP_REST_Response;

/**
 * Class Automator_Walkthrough_Rest_Api
 *
 * REST controller for the admin walkthroughs. Exposes the active walkthrough plus the current user's resume pointer for a surface, and persists that user's progress. The endpoints are surface-scoped and current-user-scoped, not recipe-specific, so they live with the walkthrough feature.
 *
 * @package Uncanny_Automator
 */
class Automator_Walkthrough_Rest_Api {

	/**
	 * Registers the REST routes on rest_api_init.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Registers the walkthrough REST routes.
	 *
	 * @return void
	 */
	public function register_routes() {

		register_rest_route(
			AUTOMATOR_REST_API_END_POINT,
			'/walkthroughs/',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_walkthroughs' ),
				'permission_callback' => array( $this, 'permissions' ),
			)
		);

		register_rest_route(
			AUTOMATOR_REST_API_END_POINT,
			'/set_walkthrough_progress/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'set_walkthrough_progress' ),
				'permission_callback' => array( $this, 'permissions' ),
			)
		);
	}

	/**
	 * Allows a logged-in user who holds the Automator capability and sends a valid REST nonce.
	 *
	 * @return bool
	 */
	public function permissions() {

		$nonce = isset( $_SERVER['HTTP_X_WP_NONCE'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) )
			: '';

		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return false;
		}

		return current_user_can( automator_get_capability() ); // phpcs:ignore WordPress.WP.Capabilities.Undetermined -- Dynamic capability from filter.
	}

	/**
	 * Returns the active walkthrough spec and the user's resume pointer for a surface.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function get_walkthroughs( WP_REST_Request $request ) {

		$surface  = sanitize_text_field( (string) $request->get_param( 'surface' ) );
		$resolved = Automator_Walkthrough_Specs::get_active( $surface, get_current_user_id() );

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => array(
					'active'   => $resolved['active'],
					'progress' => $resolved['progress'],
				),
			),
			200
		);
	}

	/**
	 * Persists the current user's progress for a walkthrough.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function set_walkthrough_progress( WP_REST_Request $request ) {

		if ( $request->has_param( 'id' ) && $request->has_param( 'progress_percentage' ) && $request->has_param( 'step_id' ) && $request->has_param( 'close_requested' ) ) {

			$walkthrough_id  = sanitize_text_field( $request->get_param( 'id' ) );
			$step            = sanitize_text_field( $request->get_param( 'step_id' ) );
			$percent         = $request->has_param( 'progress_percentage' ) ? absint( $request->get_param( 'progress_percentage' ) ) : 0;
			$close_requested = $request->has_param( 'close_requested' ) ? filter_var( $request->get_param( 'close_requested' ), FILTER_VALIDATE_BOOLEAN ) : false;
			$show            = ! $close_requested && $percent < 100;

			$updated = Automator()->utilities->set_user_walkthrough_progress(
				get_current_user_id(),
				$walkthrough_id,
				array(
					'show'      => $show ? 1 : 0,
					'step'      => $step,
					'progress'  => $percent,
					'dismissed' => $close_requested,
				)
			);

			if ( false !== $updated ) {
				$return = array(
					'message' => 'Updated!',
					'success' => true,
					'action'  => 'updated_option',
				);

				/**
				 * Fires when a walkthrough mode is updated.
				 *
				 * @since 5.8
				 */
				do_action( 'automator_user_walkthrough_progress_updated', $updated, $return );

				return new WP_REST_Response( $return, 200 );
			}
		}

		$return = array(
			'message' => 'Failed to update',
			'success' => false,
			'action'  => 'show_error',
		);

		return new WP_REST_Response( $return, 200 );
	}
}
