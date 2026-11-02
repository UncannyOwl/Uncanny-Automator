<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Server;

use Uncanny_Automator\App\Plan\Services\Account\Account_Connect_Url;

/**
 * Resume an administrator's AI approval after the existing Free-account flow.
 * Account linking never grants AI access; the returned request is validated and
 * shown for consent again. Only an opaque, user-bound handle crosses the store.
 *
 * @since 8.0.0
 */
final class OAuth_Account_Link {

	public const RETURN_PARAM = 'automator_mcp_account_link';

	/**
	 * Build a store link for an already-validated authorization request.
	 *
	 * @return string|null Null when the return could not be saved.
	 */
	public function link_url( array $authorization ): ?string {
		if ( ! current_user_can( 'manage_options' ) ) {
			return null;
		}
		$id    = str_replace( '-', '', wp_generate_uuid4() );
		$saved = ( new OAuth_Artifact_Store() )->put(
			'account_link',
			$id,
			array(
				'user_id'    => get_current_user_id(),
				'expires_at' => time() + 15 * MINUTE_IN_SECONDS,
				'parameters' => array(
					'response_type'         => 'code',
					'client_id'             => $authorization['client_id'],
					'redirect_uri'          => $authorization['redirect_uri'],
					'resource'              => $authorization['resource'],
					'scope'                 => implode( ' ', $authorization['scopes'] ),
					'code_challenge'        => $authorization['code_challenge'],
					'code_challenge_method' => 'S256',
					'state'                 => $authorization['state'],
				),
			)
		);
		if ( ! $saved ) {
			return null;
		}
		$return_url = add_query_arg(
			array(
				'post_type'        => AUTOMATOR_POST_TYPE_RECIPE,
				'page'             => 'uncanny-automator-dashboard',
				'state'            => wp_create_nonce( 'automator_setup_wizard_redirect_nonce' ),
				self::RETURN_PARAM => $id,
			),
			admin_url( 'edit.php' )
		);
		return ( new Account_Connect_Url() )->returning_to( $return_url );
	}

	/**
	 * Consume a successful account-link return for this administrator only.
	 * The destination is always this site's authorization endpoint, never a
	 * caller-supplied redirect. Expired returns fall back to the Dashboard.
	 */
	public function resume_url( string $id ): ?string {
		if ( ! preg_match( '/^[a-f0-9]{32}$/D', $id ) || ! current_user_can( 'manage_options' ) ) {
			return null;
		}
		$store  = new OAuth_Artifact_Store();
		$record = $store->get( 'account_link', $id );
		if ( ! is_array( $record ) || (int) ( $record['user_id'] ?? 0 ) !== get_current_user_id() || (int) ( $record['expires_at'] ?? 0 ) <= time() ) {
			return null;
		}
		$record = $store->consume( 'account_link', $id );
		if ( ! is_array( $record ) || ! is_array( $record['parameters'] ?? null ) ) {
			return null;
		}
		// add_query_arg does not encode newly supplied values; OAuth state is opaque.
		$endpoint = ( new OAuth_Server_Configuration() )->get_authorization_endpoint();
		return $endpoint . ( false === strpos( $endpoint, '?' ) ? '?' : '&' )
			. http_build_query( $record['parameters'], '', '&', PHP_QUERY_RFC3986 );
	}
}
