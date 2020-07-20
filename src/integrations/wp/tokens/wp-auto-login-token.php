<?php

namespace Uncanny_Automator;

/**
 * Class WP_Anon_Tokens
 * @package Uncanny_Automator_Pro
 */
class Wp_Tokens {


	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'WP';

	public $error = ['code'=>'hash_error','message'=>'The link used is invalid.'];

	/**
	 * This token is set up in the advanced token drop which is hardcoded in uncanny-automator/src/recipe-ui/src/components/Utilities.js
	 * It is only initialized if Automator Pro is active.
	 * The parser is in uncanny-automator/src/integrations/wp/tokens/wp-auto-login-token.php @see case 'AUTOLOGINLINK':
	 * This class only the auto login process once the user uses the link
	 */
	public function __construct() {

		// Only run if pro is active
		if ( defined( 'AUTOMATOR_PRO_FILE' ) ) {
			add_action( 'login_init', array( $this, 'login_page_init' ) );
		}
	}

	/**
	 *
	 */
	public function login_page_init() {

		if ( isset( $_GET['ua_login'] ) ) {
			$hash = (string) $_GET['ua_login'];

			global $wpdb;

			$results = $wpdb->get_row(
				$wpdb->prepare( "Select user_id, meta_value as expiry FROM $wpdb->usermeta Where meta_key = %s", $hash )
			);

			if ( ! empty( $results ) ) {
				if ( time() < absint( $results->expiry ) ) {
					$user = get_user_by( 'ID', $results->user_id );

					if ( $user ) {
						wp_clear_auth_cookie();
						wp_set_current_user( $user->ID );
						wp_set_auth_cookie( $user->ID );
						delete_user_meta( absint( $user->ID ), $hash );
						do_action( 'uap_auto_login_link_success' );
						wp_redirect( admin_url( 'profile.php' ) );
						exit();
					} else {
						$this->error['code'] = 'user_not_found';
						$this->error['message'] = __('The user you tried to login as does not exist.','uncanny-automator');
						delete_user_meta( absint( $results->user_id ), $hash );
					}
				} else {
					$this->error['code'] = 'hash_expired';
					$this->error['message'] = __('The auto login link has expired.','uncanny-automator');
					delete_user_meta( absint( $results->user_id ), $hash );
				}
			} else {
				$this->error['code'] = 'hash_not_found';
				$this->error['message'] = __('The auto login link is incorrect.','uncanny-automator');
			}

			add_filter( 'wp_login_errors', function ( $errors, $redirect_to ) {
				return new \WP_Error( $this->error['code'], $this->error['message'] );
			}, 20 ,2 );
		}
	}
}
