<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Server;

use Uncanny_Automator\App\Application\Mcp\Hosted_App;
use Uncanny_Automator\App\Plan\Services\License\License_Service;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Authenticated_Token_Context;

/** The hosted Agent reuses standalone permissions, with OAuth-managed lifetime. */
final class Hosted_Agent_Access {

	/** Identify requests that need the first-party Agent consent boundary. */
	public static function requested( array $scopes ): bool {
		return in_array( Authenticated_Token_Context::SCOPE_AGENT_READ, $scopes, true )
			|| in_array( Authenticated_Token_Context::SCOPE_AGENT_WRITE, $scopes, true );
	}

	/**
	 * Stop a new hosted connection before chat reaches a catalog it cannot use.
	 * A connected free account is enough; this does not require a Pro plan.
	 */
	public static function site_license_error( array $scopes ): ?\WP_Error {
		if ( ! self::requested( $scopes ) ) {
			return null;
		}

		// Use the same license source as the component catalog on this site.
		$key = sanitize_text_field( ( new License_Service() )->get_license_key() );
		if ( '' !== $key ) {
			return null;
		}

		return new \WP_Error(
			'automator_site_license_required',
			// phpcs:ignore Uncanny_Automator.Strings.AutoContextTranslation.MissingContext -- Plain-text WP_Error; the consent page escapes it when rendering.
			_x( 'This site needs an Automator license before it can connect to Uncanny Automator. Connect a free account or enter your Pro license key in Automator → Settings → General → License.', 'MCP hosted connection license requirement', 'uncanny-automator' )
		);
	}

	/** Bind Agent access to the callback recipient, never its display name. */
	public static function allows_client( array $client ): bool {
		// A registrant can choose any display name. Only the exact product
		// callback may receive this grant, and code redemption still needs PKCE.
		// A site trusts exactly one hosted app: production, or the wp-config
		// UNCANNY_AGENT_APP_URL override for local installations.
		$redirects = $client['redirect_uris'] ?? array();

		return is_array( $redirects ) && 1 === count( $redirects )
			&& Hosted_App::base_url() . '/api/connection-approvals/callback' === reset( $redirects );
	}

	/** Use the product name only for the site's configured hosted callback. */
	public static function client_name( array $client ): string {
		return self::allows_client( $client ) ? 'Uncanny Automator' : (string) ( $client['name'] ?? '' );
	}

	/** Recheck the client at issuance and renewal, after browser consent. */
	public static function require_client( array $scopes, array $client ): void {
		if ( self::requested( $scopes ) && ! self::allows_client( $client ) ) {
			throw new \InvalidArgumentException( 'Uncanny Automator access was refused because the app callback does not match the hosted app trusted by this site (hosted_app_untrusted). Ask the site administrator to check UNCANNY_AGENT_APP_URL in wp-config.php, then connect again from the trusted Uncanny Automator app.' );
		}
	}
}
