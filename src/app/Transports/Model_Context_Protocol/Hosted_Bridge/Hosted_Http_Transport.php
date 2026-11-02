<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Hosted_Bridge;

use Uncanny_Automator\App\Application\Mcp\Hosted_App;
use WP_HTTP_Proxy;

/**
 * HTTPS calls from this site to the hosted service.
 *
 * The calls carry the site's read credential, so they do not go through
 * wp_remote_*(): http_request_args, https_ssl_verify and http_api_curl all run
 * after the caller's arguments and can switch certificate checks off. Core's
 * bundled Requests library is called directly instead, with its own hooks
 * object, the core CA bundle, no redirects, short timeouts and a size limit.
 * The proxy comes from WP_HTTP_Proxy exactly as core configures it.
 */
final class Hosted_Http_Transport {

	private const TIMEOUT = 8;

	private const CONNECT_TIMEOUT = 3;

	/** Hosted replies are small; a larger one is cut short and then fails JSON decoding. */
	private const MAX_BYTES = 262144;

	private string $base_url;

	private WP_HTTP_Proxy $proxy;

	/** @var callable|null Test seam: receives ( $url, $headers, $body, $method, $options ). */
	private $send;

	/**
	 * Constructor.
	 */
	public function __construct( ?string $base_url = null, ?WP_HTTP_Proxy $proxy = null, ?callable $send = null ) {
		$this->base_url = $base_url ?? Hosted_App::base_url();
		$this->proxy    = $proxy ?? new WP_HTTP_Proxy();
		$this->send     = $send;
	}

	/**
	 * Send one request to the hosted service.
	 *
	 * @param array<string,string> $headers Request headers.
	 *
	 * @return array{status:int,headers:array<string,string>,body:string}|null Null when the hosted address is not HTTPS or no reply came back.
	 */
	public function request( string $method, string $path, array $headers, string $body = '', int $timeout = self::TIMEOUT ): ?array {
		if ( 0 !== strpos( $this->base_url, 'https://' ) || 0 !== strpos( $path, '/' ) ) {
			return null;
		}

		$url = $this->base_url . $path;
		if ( ( new \WP_Http() )->block_request( $url ) ) {
			return null;
		}

		$options = $this->options( $url, max( 1, min( self::TIMEOUT, $timeout ) ) );

		mbstring_binary_safe_encoding();
		try {
			$response = null === $this->send
				? $this->send_with_requests( $url, $headers, $body, $method, $options )
				: ( $this->send )( $url, $headers, $body, $method, $options );
		} catch ( \Throwable $e ) {
			// Dropped whole: callers only need to know nothing came back.
			$response = null;
		} finally {
			reset_mbstring_encoding();
		}

		return is_array( $response ) ? $response : null;
	}

	/**
	 * Requests options that no WordPress filter can change.
	 */
	private function options( string $url, int $timeout ): array {
		$options = array(
			'timeout'          => $timeout,
			'connect_timeout'  => min( self::CONNECT_TIMEOUT, $timeout ),
			'useragent'        => 'UncannyAutomator/' . AUTOMATOR_PLUGIN_VERSION,
			'follow_redirects' => false,
			'max_bytes'        => self::MAX_BYTES,
			'verify'           => ABSPATH . WPINC . '/certificates/ca-bundle.crt',
			'verifyname'       => true,
		);

		if ( $this->proxy->is_enabled() && $this->proxy->send_through_proxy( $url ) ) {
			$proxy_class = class_exists( '\WpOrg\Requests\Proxy\Http' ) ? '\WpOrg\Requests\Proxy\Http' : '\Requests_Proxy_HTTP';
			$proxy       = new $proxy_class( $this->proxy->host() . ':' . $this->proxy->port() );
			if ( $this->proxy->use_authentication() ) {
				$proxy->use_authentication = true;
				$proxy->user               = $this->proxy->username();
				$proxy->pass               = $this->proxy->password();
			}
			$options['proxy'] = $proxy;
		}

		return $options;
	}

	/**
	 * Send with Requests 2 (WordPress 6.2+) or Requests 1 (WordPress 5.8–6.1).
	 *
	 * @return array{status:int,headers:array<string,string>,body:string}
	 */
	private function send_with_requests( string $url, array $headers, string $body, string $method, array $options ): array {
		$response = class_exists( '\WpOrg\Requests\Requests' )
			? \WpOrg\Requests\Requests::request( $url, $headers, $body, $method, $options )
			: \Requests::request( $url, $headers, $body, $method, $options );

		$reply_headers = array();
		foreach ( $response->headers as $name => $value ) {
			$reply_headers[ strtolower( (string) $name ) ] = (string) $value;
		}

		return array(
			'status'  => (int) $response->status_code,
			'headers' => $reply_headers,
			'body'    => (string) $response->body,
		);
	}
}
