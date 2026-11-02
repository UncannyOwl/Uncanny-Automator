<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Remote_File;

/**
 * A remote file the client downloaded: where its bytes are, and the media type
 * the server sent with them.
 *
 * Immutable. The temporary file belongs to the caller, who deletes it.
 *
 * @since 8.0.0
 */
final class Remote_File_Download {

	/**
	 * The temporary file the bytes were written to.
	 *
	 * @var string
	 */
	private $path;

	/**
	 * The Content-Type the server sent, or empty.
	 *
	 * @var string
	 */
	private $content_type;

	/**
	 * Build the download from its temporary file and the raw Content-Type header.
	 *
	 * @param string $path         The temporary file's path.
	 * @param string $content_type The Content-Type header as sent; empty when the server sent none.
	 */
	public function __construct( string $path, string $content_type ) {
		$this->path         = $path;
		$this->content_type = $content_type;
	}

	/**
	 * The temporary file's path.
	 *
	 * @return string
	 */
	public function path(): string {
		return $this->path;
	}

	/**
	 * The file's media type, as the server sent it.
	 *
	 * @return string Empty when the server sent no Content-Type.
	 */
	public function content_type(): string {
		return $this->content_type;
	}
}
