<?php
namespace Uncanny_Automator\Services\Loopable\Data_Integrations;

use WP_Error;

/**
 * The single source-content fetch path for the loopable data integrations.
 *
 * Remote reads route through automator_remote_get_ssrf_safe(): private and
 * reserved hosts are refused at every redirect hop, and redirects are walked
 * manually up to MAX_REDIRECT_HOPS. Loopable source URLs are user-configured
 * and commonly sit behind HTTP→HTTPS rewrites, CDNs, and shorteners, so the
 * hop allowance mirrors the Bluesky website-embed ruling — validation per hop
 * means more hops widen convenience, not the attack surface.
 */
class Source_Fetch {

	/**
	 * Maximum validated redirect hops for a loopable source URL.
	 *
	 * @var int
	 */
	const MAX_REDIRECT_HOPS = 3;

	/**
	 * Fetches the contents of a remote source URL.
	 *
	 * @param string $url The user-configured source URL.
	 *
	 * @return string|WP_Error Response body, or WP_Error (invalid_url,
	 *                         automator_ssrf_blocked, automator_too_many_redirects,
	 *                         invalid_response, or transport error).
	 */
	public static function remote_contents( $url ) {

		if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return new WP_Error( 'invalid_url', 'The URL provided is not valid.' );
		}

		$response = automator_remote_get_ssrf_safe( $url, array( 'redirection' => self::MAX_REDIRECT_HOPS ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error( 'invalid_response', 'The request did not return a valid response.' );
		}

		return wp_remote_retrieve_body( $response );
	}

	/**
	 * Resolves a media-library attachment URL to its filesystem path.
	 *
	 * @param string $file_url The attachment URL (upload-source loopables).
	 *
	 * @return string|false Absolute path, or false when the URL is not a
	 *                      known attachment.
	 */
	public static function attachment_path_from_url( $file_url ) {

		$attachment_id = attachment_url_to_postid( $file_url );

		if ( $attachment_id ) {
			return get_attached_file( $attachment_id );
		}

		return false;
	}
}
