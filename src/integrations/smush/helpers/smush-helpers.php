<?php

namespace Uncanny_Automator\Integrations\Smush;

use Uncanny_Automator\Recipe\Abstract_Helpers;

/**
 * Class Smush_Helpers
 *
 * @package Uncanny_Automator\Integrations\Smush
 */
class Smush_Helpers extends Abstract_Helpers {

	/**
	 * Post meta key holding the per-image Smush stats.
	 *
	 * @var string
	 */
	const SMUSH_META_KEY = 'wp-smpro-smush-data';

	/**
	 * Maximum number of images returned to the image picker.
	 *
	 * @var int
	 */
	const IMAGE_QUERY_LIMIT = 50;

	/**
	 * Remote-data handler: search the media library for images.
	 *
	 * Media libraries are routinely large, so the picker is search-driven — the
	 * builder renders a `search_options` select empty and only fetches once the
	 * user types (it suppresses the "No results found" placeholder for this
	 * event and merely reconstructs any previously-saved value). The empty-query
	 * branch below is therefore not what the builder shows on first open; it is
	 * a sensible default for direct/REST callers.
	 *
	 * Reachable via `POST /wp-json/uap/v2/remote-data/smush/images_strict`.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_images_strict( $request ): array {

		return $this->remote_data_success( $this->get_image_options( $request->get_search_query() ) );
	}

	/**
	 * Query the media library for image attachments.
	 *
	 * @param string $search Optional search term.
	 *
	 * @return array Value/text option pairs.
	 */
	public function get_image_options( string $search = '' ): array {

		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_mime_type' => 'image',
			'posts_per_page' => self::IMAGE_QUERY_LIMIT, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
			'include_any'    => false,
		);

		if ( '' !== $search ) {
			$args['s'] = $search;

			return automator_wp_query( $args );
		}

		// No search term — show the newest images instead of an empty dropdown.
		$args['orderby'] = 'date';
		$args['order']   = 'DESC';

		return automator_wp_query( $args );
	}

	/**
	 * Check whether an attachment exists and is an image.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	public function is_image_attachment( $attachment_id ): bool {

		$attachment_id = absint( $attachment_id );

		if ( 0 === $attachment_id ) {
			return false;
		}

		if ( 'attachment' !== get_post_type( $attachment_id ) ) {
			return false;
		}

		return wp_attachment_is_image( $attachment_id );
	}

	/**
	 * Token definitions for an image attachment, shared by every Smush item.
	 *
	 * Returned in the trigger token format (a list of tokenId/tokenName/tokenType
	 * maps). Actions take the same set through
	 * `get_image_action_token_definitions()` so the two never drift apart.
	 *
	 * @param bool $include_file_path Triggers expose the server file path;
	 *                                actions deliberately do not.
	 *
	 * @return array
	 */
	public function get_image_token_definitions( bool $include_file_path = true ): array {

		$tokens = array(
			array(
				'tokenId'   => 'IMAGE_ID',
				'tokenName' => esc_html_x( 'Image ID', 'Smush', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'IMAGE_TITLE',
				'tokenName' => esc_html_x( 'Image title', 'Smush', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'IMAGE_URL',
				'tokenName' => esc_html_x( 'Image URL', 'Smush', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
		);

		if ( $include_file_path ) {
			$tokens[] = array(
				'tokenId'   => 'IMAGE_FILE_PATH',
				'tokenName' => esc_html_x( 'Image file path', 'Smush', 'uncanny-automator' ),
				'tokenType' => 'text',
			);
		}

		return $tokens;
	}

	/**
	 * The shared image token set in the action token format (keyed by token ID).
	 *
	 * @return array
	 */
	public function get_image_action_token_definitions(): array {

		$tokens = array();

		foreach ( $this->get_image_token_definitions( false ) as $token ) {
			$tokens[ $token['tokenId'] ] = array(
				'name' => $token['tokenName'],
				'type' => $token['tokenType'],
			);
		}

		return $tokens;
	}

	/**
	 * Hydrate the shared image tokens.
	 *
	 * Always returns the full keyset — empty strings for an attachment that no
	 * longer exists — so a recipe never resolves a partial map.
	 *
	 * @param int  $attachment_id     The attachment ID.
	 * @param bool $include_file_path Must match the flag passed to
	 *                                `get_image_token_definitions()`.
	 *
	 * @return array
	 */
	public function hydrate_image_tokens( $attachment_id, bool $include_file_path = true ): array {

		$attachment_id = absint( $attachment_id );

		$tokens = array(
			'IMAGE_ID'    => $attachment_id,
			'IMAGE_TITLE' => (string) get_the_title( $attachment_id ),
			'IMAGE_URL'   => (string) wp_get_attachment_url( $attachment_id ),
		);

		if ( $include_file_path ) {
			$file_path                 = get_attached_file( $attachment_id );
			$tokens['IMAGE_FILE_PATH'] = false !== $file_path ? $file_path : '';
		}

		return $tokens;
	}

	/**
	 * Read the stored Smush stats for an attachment.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return array Normalized stats.
	 */
	public function get_smush_stats( $attachment_id ): array {

		$smush_meta = get_post_meta( absint( $attachment_id ), self::SMUSH_META_KEY, true );
		$stats      = is_array( $smush_meta ) && isset( $smush_meta['stats'] ) ? $smush_meta['stats'] : array();

		return $this->normalize_stats( $stats );
	}

	/**
	 * Normalize a Smush stats array into a predictable keyset.
	 *
	 * Smush's stats arrays come from `Media_Item_Stats::to_array()` — the keys
	 * are always present on a successful optimization but may be absent when a
	 * partial or empty stats array is stored.
	 *
	 * @param mixed $stats The raw stats array.
	 *
	 * @return array
	 */
	public function normalize_stats( $stats ): array {

		$stats = is_array( $stats ) ? $stats : array();

		return array(
			'size_before' => isset( $stats['size_before'] ) ? (int) $stats['size_before'] : 0,
			'size_after'  => isset( $stats['size_after'] ) ? (int) $stats['size_after'] : 0,
			'bytes'       => isset( $stats['bytes'] ) ? (int) $stats['bytes'] : 0,
			'percent'     => isset( $stats['percent'] ) ? round( (float) $stats['percent'], 2 ) : 0,
			'is_lossy'    => empty( $stats['lossy'] ) ? 0 : 1,
		);
	}

	/**
	 * Count the image sizes recorded in a Smush meta array.
	 *
	 * @param mixed $smush_meta The smush meta array carried by the hook.
	 *
	 * @return int
	 */
	public function count_optimized_sizes( $smush_meta ): int {

		if ( ! is_array( $smush_meta ) || ! isset( $smush_meta['sizes'] ) || ! is_array( $smush_meta['sizes'] ) ) {
			return 0;
		}

		return count( $smush_meta['sizes'] );
	}

	/**
	 * Extract a readable message from a Smush error bag.
	 *
	 * @param mixed  $errors   A WP_Error instance, or anything else.
	 * @param string $fallback Message used when no error text is available.
	 *
	 * @return string
	 */
	public function get_error_message( $errors, string $fallback ): string {

		if ( $errors instanceof \WP_Error && $errors->has_errors() ) {
			return $errors->get_error_message();
		}

		return $fallback;
	}
}
