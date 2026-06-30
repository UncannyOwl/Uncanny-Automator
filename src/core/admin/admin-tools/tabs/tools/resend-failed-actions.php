<?php
/**
 * Uncanny_Automator\Admin_Tools_Tabs_Resend_Failed
 *
 * Registers the "Resend App actions" sub-tab under Status > Tools and the
 * REST routes that drive it. The client UI is the <uap-resend-failed-actions>
 * Lit component, which ships in the main `uap-admin` bundle already enqueued on
 * Automator admin pages (Status > Tools included).
 */

namespace Uncanny_Automator;

use WP_REST_Request;
use WP_REST_Response;

class Admin_Tools_Tabs_Resend_Failed {

	public function __construct() {
		$this->create_tab();
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	private function create_tab() {
		add_filter(
			'automator_admin_tools_tools_tabs',
			function ( $tabs ) {
				$tabs['resend-failed-actions'] = (object) array(
					'name'     => esc_html__( 'Resend App actions', 'uncanny-automator' ),
					'function' => array( $this, 'tab_output' ),
					'preload'  => true, // panel must be in the DOM for client-side sidebar switching.
				);
				return $tabs;
			},
			10,
			1
		);
	}

	public function tab_output() {
		if ( ! current_user_can( automator_get_capability() ) ) {
			return;
		}
		include Utilities::automator_get_view( 'admin-tools/tab/tools/resend-failed-actions.php' );
	}

	/**
	 * REST routes under the Automator namespace, mirroring resend_api_request.
	 * The wp_rest nonce is sent by the frontend HTTP client (fetchApi); the
	 * permission callback enforces capability.
	 */
	public function register_rest_routes() {
		$perm = function () {
			return current_user_can( automator_get_capability() );
		};
		register_rest_route( AUTOMATOR_REST_API_END_POINT, '/resend_failed_list/', array( 'methods' => 'POST', 'permission_callback' => $perm, 'callback' => array( $this, 'rest_list' ) ) );
		register_rest_route( AUTOMATOR_REST_API_END_POINT, '/resend_failed_run/', array( 'methods' => 'POST', 'permission_callback' => $perm, 'callback' => array( $this, 'rest_run' ) ) );
	}

	private function days( WP_REST_Request $r ) {
		$d = absint( $r->get_param( 'days' ) );
		return $d > 0 ? min( 365, $d ) : 7;
	}

	private function batch( WP_REST_Request $r ) {
		$b = absint( $r->get_param( 'batch' ) );
		return $b > 0 ? min( 200, $b ) : 200;
	}

	private function endpoint( WP_REST_Request $r ) {
		return sanitize_text_field( (string) $r->get_param( 'endpoint' ) );
	}

	public function rest_list( WP_REST_Request $r ) {
		$last_id = absint( $r->get_param( 'last_id' ) );
		return new WP_REST_Response(
			( new Failed_App_Action_Resend() )->list_failed( $this->days( $r ), $last_id, $this->batch( $r ), $this->endpoint( $r ) ),
			200
		);
	}

	public function rest_run( WP_REST_Request $r ) {
		$ids = array_map( 'absint', (array) $r->get_param( 'action_log_ids' ) );
		return new WP_REST_Response( ( new Failed_App_Action_Resend() )->resend_selected( $ids ), 200 );
	}
}

new Admin_Tools_Tabs_Resend_Failed();
