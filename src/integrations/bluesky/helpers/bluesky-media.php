<?php
// phpcs:disable PHPCompatibility.Operators.NewOperators
// phpcs:disable WordPress.PHP.DisallowShortTernary

namespace Uncanny_Automator\Integrations\Bluesky;

use Exception;

/**
 * Class Bluesky_Media
 * - Format the media into a record for the Bluesky API.
 * - Handles upload, external, and website media.
 * - Handles media size limits.
 * - Retrieves dimensions for images and videos.
 * - Retrieves Open Graph data for websites.
 *
 * @package Uncanny_Automator
 */
class Bluesky_Media {

	/**
	 * Image embed type.
	 *
	 * @var string
	 */
	const EMBED_TYPE_IMAGES = 'app.bsky.embed.images';

	/**
	 * External embed type.
	 *
	 * @var string
	 */
	const EMBED_TYPE_EXTERNAL = 'app.bsky.embed.external';

	/**
	 * The media value passed to the constructor.
	 *
	 * @var mixed - string
	 */
	private $media;

	/**
	 * The type of media passed to the constructor.
	 *
	 * @var string - 'upload' | 'external' | 'website'
	 */
	private $type;

	/**
	 * The embed array object.
	 *
	 * @var array
	 */
	private $embed;

	/**
	 * Constructor.
	 *
	 * @param mixed $media - The media.
	 * @param string $type - The type of media.
	 */
	public function __construct( $media, $type ) {
		$this->media = $media;
		$this->type  = $type;
	}

	/**
	 * Get the embed.
	 *
	 * @return array|false
	 */
	public function get_embed() {
		if ( empty( $this->media ) || empty( $this->type ) ) {
			return false;
		}

		switch ( $this->type ) {
			case 'upload':
				$this->embed = $this->get_upload_embed();
				break;
			case 'external':
				$this->embed = $this->get_external_embed();
				break;
			case 'website':
				$this->embed = $this->get_website_embed();
				break;
			default:
				$this->embed = array();
				break;
		}

		return ! empty( $this->embed ) && is_array( $this->embed )
			? $this->embed
			: false;
	}

	/**
	 * Get the upload embed.
	 *
	 * @return array
	 */
	private function get_upload_embed() {
		$embed = array(
			'$type'  => self::EMBED_TYPE_IMAGES,
			'images' => array(),
		);

		foreach ( $this->media as $media ) {
			$image = array(
				'alt'   => $media['alt'] ?? $media['title'] ?? $media['filename'],
				'image' => $media['url'],
			);

			// Only add aspectRatio if both dimensions are valid
			if ( ! empty( $media['width'] ) && ! empty( $media['height'] ) ) {
				$image['aspectRatio'] = array(
					'width'  => $media['width'],
					'height' => $media['height'],
				);
			}

			$embed['images'][] = $image;
		}

		return $embed;
	}

	/**
	 * Get the external embed.
	 *
	 * @return array
	 */
	private function get_external_embed() {
		$url = $this->get_valid_url( $this->media );

		if ( ! $url ) {
			return array();
		}

		// Try oEmbed first
		$oembed_data = $this->get_oembed_data( $url );
		if ( $oembed_data ) {
			return $this->create_external_embed( $oembed_data );
		}

		// If not oEmbed, try as direct media file
		$file_type = wp_check_filetype( $url );
		$extension = strtolower( pathinfo( $url, PATHINFO_EXTENSION ) );

		if ( $this->is_supported_media_type( $extension ) ) {
			return $this->process_direct_media( $url, $file_type );
		}

		return array();
	}

	/**
	 * Get oEmbed data
	 *
	 * @param string $url
	 * @return array|false
	 */
	private function get_oembed_data( $url ) {
		if ( ! function_exists( 'wp_oembed_get' ) ) {
			require_once ABSPATH . 'wp-includes/embed.php';
		}

		$oembed = wp_oembed_get( $url );
		if ( ! $oembed ) {
			return false;
		}

		$provider      = _wp_oembed_get_object();
		$provider_data = $provider->get_data( $url );

		if ( ! $provider_data || empty( $provider_data->thumbnail_url ) ) {
			return false;
		}

		// Build description from available data
		$description = sprintf(
			// translators: 1: Video title 2: Author name 3: Provider name
			esc_html_x( '%1$s by %2$s on %3$s', 'Bluesky', 'uncanny-automator' ),
			esc_attr( $provider_data->title ),
			esc_attr( $provider_data->author_name ),
			esc_attr( $provider_data->provider_name )
		);

		return array(
			'uri'         => $url,
			'title'       => $provider_data->title,
			'description' => $description,
			'thumb'       => $provider_data->thumbnail_url,
		);
	}

	/**
	 * Process direct media files
	 *
	 * @param string $url
	 * @param array $file_type
	 * @return array
	 */
	private function process_direct_media( $url, $file_type ) {

		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$tmp = download_url( $url );

		if ( is_wp_error( $tmp ) ) {
			return array();
		}

		try {
			$media = array(
				'alt'   => pathinfo( wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_FILENAME ),
				'image' => $url,
			);

			// Get mime type
			if ( ! empty( $file_type['type'] ) ) {
				$media['mimeType'] = $file_type['type'];
			} elseif ( pathinfo( $url, PATHINFO_EXTENSION ) === 'svg' ) {
				$media['mimeType'] = 'image/svg+xml';
			}

			// Get file size
			$size = filesize( $tmp );
			if ( $size ) {
				$media['size'] = $size;
			}

			// Handle dimensions
			if ( ! empty( $media['mimeType'] ) ) {
				$dimensions = $this->get_media_dimensions( $media['mimeType'], $tmp );
				if ( $dimensions ) {
					$media['aspectRatio'] = $dimensions;
				}
			}

			return array(
				'$type'  => self::EMBED_TYPE_IMAGES,
				'images' => array( $media ),
			);

		} catch ( Exception $e ) {
			return array();
		} finally {
			// Delete the temporary file.
			wp_delete_file( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
	}

	/**
	 * Get dimensions based on mime type
	 *
	 * @param string $mime_type
	 * @param string $tmp_file
	 * @return array|null
	 */
	private function get_media_dimensions( $mime_type, $tmp_file ) {
		if ( empty( $mime_type ) ) {
			return null;
		}

		if ( strpos( $mime_type, 'image/svg' ) === 0 ) {
			return null; // Skip dimensions for SVG
		}

		if ( strpos( $mime_type, 'image/' ) === 0 ) {
			return $this->get_image_dimensions( $tmp_file );
		}

		if ( strpos( $mime_type, 'video/' ) === 0 ) {
			return $this->get_video_dimensions( $tmp_file );
		}

		return null;
	}

	/**
	 * Get image dimensions
	 *
	 * @param string $tmp_file
	 * @return array|null
	 */
	private function get_image_dimensions( $tmp_file ) {
		$image_size = getimagesize( $tmp_file );

		if ( ! $image_size || empty( $image_size[0] ) || empty( $image_size[1] ) ) {
			return null;
		}

		return array(
			'width'  => $image_size[0],
			'height' => $image_size[1],
		);
	}

	/**
	 * Get video dimensions
	 *
	 * @param string $tmp_file
	 * @return array|null
	 */
	private function get_video_dimensions( $tmp_file ) {
		if ( ! class_exists( 'getID3' ) ) {
			require_once ABSPATH . 'wp-includes/ID3/getid3.php';
		}

		$get_id3   = new \getID3();
		$file_info = $get_id3->analyze( $tmp_file );
		$video     = isset( $file_info['video'] ) ? $file_info['video'] : array();

		if ( empty( $video['resolution_x'] ) || empty( $video['resolution_y'] ) ) {
			return null;
		}

		return array(
			'width'  => $video['resolution_x'],
			'height' => $video['resolution_y'],
		);
	}

	/**
	 * Get the website embed.
	 *
	 * @return array
	 */
	private function get_website_embed() {
		$url = $this->get_valid_url( $this->media );

		if ( ! $url ) {
			return array();
		}

		// Get HTML content
		$response = wp_remote_get( $url );
		if ( is_wp_error( $response ) ) {
			return array();
		}

		$html = wp_remote_retrieve_body( $response );
		if ( empty( $html ) ) {
			return array();
		}

		// Create a DOMDocument to parse the HTML
		$doc = new \DOMDocument();
		@$doc->loadHTML( $html, LIBXML_NOERROR ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		// Create XPath object to query meta tags
		$xpath = new \DOMXPath( $doc );

		// Get OpenGraph data
		$thumb_url = $this->get_meta_content( $xpath, 'og:image' );

		// Bail early if no thumbnail
		if ( ! $thumb_url ) {
			return array();
		}

		$data = array(
			'uri'         => $url,
			'title'       => $this->get_meta_content( $xpath, 'og:title' ) ?: $this->get_title( $xpath ), // phpcs:ignore Universal.Operators.DisallowShortTernary.Found
			'description' => $this->get_meta_content( $xpath, 'og:description' ) ?: $this->get_meta_content( $xpath, 'description' ), // phpcs:ignore Universal.Operators.DisallowShortTernary.Found
			'thumb'       => $thumb_url,
		);

		return $this->create_external_embed( $data );
	}

	/**
	 * Get meta tag content
	 *
	 * @param \DOMXPath $xpath
	 * @param string $property
	 * @return string|null
	 */
	private function get_meta_content( $xpath, $property ) {
		$meta = $xpath->query( "//meta[@property='$property' or @name='$property']" )->item( 0 );
		return $meta ? $meta->getAttribute( 'content' ) : null;
	}

	/**
	 * Get page title
	 *
	 * @param \DOMXPath $xpath
	 * @return string|null
	 */
	private function get_title( $xpath ) {
		$title = $xpath->query( '//title' )->item( 0 );
		return $title ? $title->textContent : null; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}

	/**
	 * Validate URL.
	 *
	 * @param string $url
	 * @return string|false
	 */
	private function get_valid_url( $url ) {
		if ( filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return $url;
		}
		return false;
	}

	/**
	 * Create external embed structure
	 *
	 * @param array $data
	 * @return array
	 */
	private function create_external_embed( $data ) {
		if ( empty( $data['title'] ) || empty( $data['thumb'] ) ) {
			return array();
		}

		return array(
			'$type'    => self::EMBED_TYPE_EXTERNAL,
			'external' => $data,
		);
	}

	/**
	 * Check if media type is supported
	 *
	 * @param string $extension
	 * @return bool
	 */
	private function is_supported_media_type( $extension ) {
		$supported_images = array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg' );
		$supported_videos = array( 'mp4', 'mov', 'quicktime' );
		return in_array( $extension, array_merge( $supported_images, $supported_videos ), true );
	}
}
