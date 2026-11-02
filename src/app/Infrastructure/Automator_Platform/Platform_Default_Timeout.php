<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform;

use Uncanny_Automator\App\Events\Dispatcher;

/**
 * The timeout of a platform-bound request whose caller named none.
 *
 * WordPress gives a request with no `timeout` argument the value of
 * `http_request_timeout`, five seconds. The platform answers well inside the
 * {@see Client::DEFAULT_TIMEOUT_SECONDS} this returns for its own URLs, and no
 * further than that: other hosts keep what WordPress chose, so third-party
 * integration traffic inherits nothing from here. A caller that names its own
 * timeout never reaches this filter.
 *
 * The legacy `Api_Server` class used to register this, and only once it had
 * first loaded; the bootstrap does it at plugin load now. The
 * `automator_api_timeout` filter keeps tuning both this default and the one
 * {@see Client::send()} applies.
 *
 * @since 8.0.0
 */
final class Platform_Default_Timeout {

	/**
	 * @var Platform_Url_Matcher
	 */
	private $urls;

	/**
	 * @param Platform_Url_Matcher|null $urls Optional injection for unit tests;
	 *        production passes null and gets the configured platform.
	 */
	public function __construct( ?Platform_Url_Matcher $urls = null ) {

		$this->urls = $urls ?? Platform_Url_Matcher::from_constants();
	}

	/**
	 * Register the `http_request_timeout` filter. Called once at bootstrap.
	 *
	 * @return void
	 */
	public function register(): void {

		add_filter( 'http_request_timeout', array( $this, 'default_timeout' ), 10, 2 );
	}

	/**
	 * The default timeout for a request, in seconds.
	 *
	 * @param mixed  $timeout     The timeout WordPress would use.
	 * @param string $request_url The request URL.
	 *
	 * @return mixed The platform's default for a platform URL, else `$timeout`.
	 */
	public function default_timeout( $timeout, $request_url = '' ) {

		if ( ! is_string( $request_url ) || ! $this->urls->is_platform( $request_url ) ) {
			return $timeout;
		}

		/**
		 * Filter the platform request timeout in seconds.
		 *
		 * @param int    $seconds     The timeout.
		 * @param string $request_url The full request URL.
		 */
		return Dispatcher::filter( 'automator_api_timeout', Client::DEFAULT_TIMEOUT_SECONDS, $request_url );
	}
}
