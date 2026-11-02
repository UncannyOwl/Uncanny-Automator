<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Server;

use WP_REST_Response;

/**
 * Explicit HTML response for the administrator consent screen.
 */
final class OAuth_Html_Response extends WP_REST_Response {

	private string $html;

	private ?string $content_security_policy;

	/**
	 * Constructor.
	 *
	 * @param string      $html                    Fully escaped document.
	 * @param int         $status                  HTTP status.
	 * @param string|null $content_security_policy Per-response CSP, or null for the consent default.
	 */
	public function __construct( string $html, int $status = 200, ?string $content_security_policy = null ) {
		$this->html                    = $html;
		$this->content_security_policy = $content_security_policy;
		parent::__construct( null, $status, array( 'Content-Type' => 'text/html; charset=utf-8' ) );
	}

	/**
	 * Return the escaped, server-generated document.
	 */
	public function get_html(): string {
		return $this->html;
	}

	/**
	 * Return the CSP this document must be served with, or null for the default.
	 */
	public function get_content_security_policy(): ?string {
		return $this->content_security_policy;
	}
}
