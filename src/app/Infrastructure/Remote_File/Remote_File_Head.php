<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Remote_File;

/**
 * What a server answered to a HEAD request for a remote file.
 *
 * Immutable. A redirect is not followed, so a 3xx status is the answer as it
 * came, never the status of wherever it pointed.
 *
 * @since 8.0.0
 */
final class Remote_File_Head {

	/**
	 * The HTTP status the server answered.
	 *
	 * @var int
	 */
	private $status;

	/**
	 * The file's size in bytes, or null when the server did not report one.
	 *
	 * @var int|null
	 */
	private $size;

	/**
	 * The Content-Type the server reported, or empty.
	 *
	 * @var string
	 */
	private $content_type;

	/**
	 * Build the answer from the status and the raw Content-Length and Content-Type headers.
	 *
	 * @param int    $status         The HTTP status.
	 * @param string $content_length The Content-Length header as sent; empty when the server sent none.
	 * @param string $content_type   The Content-Type header as sent; empty when the server sent none.
	 */
	public function __construct( int $status, string $content_length, string $content_type ) {
		$this->status       = $status;
		$this->size         = '' === $content_length ? null : absint( $content_length );
		$this->content_type = $content_type;
	}

	/**
	 * The HTTP status the server answered.
	 *
	 * @return int
	 */
	public function status(): int {
		return $this->status;
	}

	/**
	 * The file's size in bytes, as the server reported it.
	 *
	 * @return int|null Null when the server sent no Content-Length.
	 */
	public function size(): ?int {
		return $this->size;
	}

	/**
	 * The file's media type, as the server reported it.
	 *
	 * @since 8.0.0
	 *
	 * @return string Empty when the server sent no Content-Type.
	 */
	public function content_type(): string {
		return $this->content_type;
	}
}
