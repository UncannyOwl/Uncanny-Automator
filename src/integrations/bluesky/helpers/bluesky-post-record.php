<?php

namespace Uncanny_Automator\Integrations\Bluesky;

use Exception;

/**
 * Bluesky Post Record
 * - Format the post text into a record for the Bluesky API.
 * - Handles text length limits.
 * - Handles facets for links, mentions, and hashtags.
 * - Add media to the record if provided.
 */
class Bluesky_Post_Record {

	/**
	 * Text.
	 *
	 * @var string
	 */
	private $text;

	/**
	 * Media.
	 *
	 * @var array
	 */
	private $media;

	/**
	 * Minimum text length.
	 *
	 * @var int
	 */
	const MIN_TEXT_LENGTH = 3;

	/**
	 * Maximum text length.
	 *
	 * @var int
	 */
	const MAX_TEXT_LENGTH = 300;

	/**
	 * Mention regex pattern - matches Bluesky handle format
	 * Captures handles like: @user.bsky.social, @username, @user-name.bsky
	 *
	 * @var string
	 */
	const MENTION_REGEX = '/(\s|^)(@[a-zA-Z0-9](?:[a-zA-Z0-9-]*[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]*[a-zA-Z0-9])?)*)/';

	/**
	 * URL regex pattern
	 *
	 * @var string
	 */
	const URL_REGEX = '/(https?:\/\/[^\s,)\.]+(?:\.[^\s,)\.]+)*)(?<![\.,:;!?])/i';

	/**
	 * Hashtag regex pattern
	 *
	 * @var string
	 */
	const TAG_REGEX = '/(^|[\\s\\r\\n])[#＃]((?!\\x{fe0f})[^\s\\x{00AD}\\x{2060}\\x{200A}\\x{200B}\\x{200C}\\x{200D}\\x{20e2}]*[^\d\s\p{P}\\x{00AD}\\x{2060}\\x{200A}\\x{200B}\\x{200C}\\x{200D}\\x{20e2}]+[^\s\\x{00AD}\\x{2060}\\x{200A}\\x{200B}\\x{200C}\\x{200D}\\x{20e2}]*)/u';

	/**
	 * Constructor.
	 *
	 * @param string $text - The text of the post.
	 * @param array $media - The media of the post.
	 */
	public function __construct( $text, $media = array() ) {
		$this->text  = sanitize_textarea_field( $text );
		$this->media = $media;
	}

	/**
	 * Get the record.
	 *
	 * @return array
	 * @throws Exception
	 */
	public function get_record() {

		// Validate post min and max length.
		$this->validate_post_length();

		// Prepare the base record.
		$record = array(
			'$type' => 'app.bsky.feed.post',
			'text'  => $this->text,
		);

		// Process all text features.
		$facets = array_merge(
			$this->get_links(),
			$this->get_mentions(),
			$this->get_hashtags()
		);

		// Only add facets if we found any.
		if ( ! empty( $facets ) ) {
			$record['facets'] = $facets;
		}

		// Add media to the record if it exists.
		if ( ! empty( $this->media ) ) {
			$media = new Bluesky_Media( $this->media['media'], $this->media['type'] );
			$embed = $media->get_embed();

			// Throw an error if the embed is not an array.
			if ( empty( $embed ) || ! is_array( $embed ) ) {
				throw new Exception(
					esc_attr_x( 'Invalid media embed', 'Bluesky', 'uncanny-automator' )
				);
			}

			$record['embed'] = $embed;
		}

		return $record;
	}

	/**
	 * Parse links from the text content.
	 *
	 * @return array
	 */
	private function get_links() {
		$links = array();

		// Generate an array giving the start and end character of each URL and the URL itself
		preg_match_all( self::URL_REGEX, $this->text, $matches, PREG_OFFSET_CAPTURE );

		if ( empty( $matches[0] ) ) {
			return $links;
		}

		foreach ( $matches[0] as $match ) {
			$url   = $match[0];
			$start = strlen( substr( $this->text, 0, $match[1] ) ); // Get byte position.

			// Basic URL validation
			if ( ! wp_http_validate_url( $url ) ) {
				continue;
			}

			// Remove trailing punctuation that might have been caught
			$url = rtrim( $url, '.,!?' );
			$end = $start + strlen( $url );

			$links[] = array(
				'index'    => array(
					'byteStart' => $start,
					'byteEnd'   => $end,
				),
				'features' => array(
					array(
						'$type' => 'app.bsky.richtext.facet#link',
						'uri'   => esc_url_raw( $url ),
					),
				),
			);
		}

		return $links;
	}

	/**
	 * Parse mentions from the text content.
	 *
	 * @return array
	 */
	private function get_mentions() {
		$mentions = array();

		// Regex to find mentions
		preg_match_all( self::MENTION_REGEX, $this->text, $matches, PREG_OFFSET_CAPTURE );

		if ( empty( $matches[2] ) ) {
			return $mentions;
		}

		// Process each mention
		foreach ( $matches[2] as $match ) {
			$handle = substr( $match[0], 1 ); // Remove the @ symbol
			$start  = strlen( substr( $this->text, 0, $match[1] ) ); // Using position from the actual mention

			// Skip if handle is empty after removing @
			if ( empty( $handle ) ) {
				continue;
			}

			$end = $start + strlen( $match[0] );

			// Add the mention to the array
			$mentions[] = array(
				'index'    => array(
					'byteStart' => $start,
					'byteEnd'   => $end,
				),
				'features' => array(
					array(
						'$type' => 'app.bsky.richtext.facet#mention',
						'did'   => $handle,
					),
				),
			);
		}

		return $mentions;
	}

	/**
	 * Parse hashtags from the text content.
	 *
	 * @return array
	 */
	private function get_hashtags() {

		$hashtags = array();

		// Clean the text for hashtags (removes URLs to avoid them being picked up as hashtags).
		$clean_text = $this->clean_text_for_hashtags();

		// Regex to find hashtags.
		preg_match_all( self::TAG_REGEX, $clean_text, $matches, PREG_OFFSET_CAPTURE );

		if ( empty( $matches[0] ) ) {
			return $hashtags;
		}

		foreach ( $matches[0] as $match ) {
			$original_hashtag = $match[0];
			// Get byte position.
			$start = strlen( substr( $this->text, 0, $match[1] ) );

			// Exclude preceding space or newline (if any) from the start
			if ( preg_match( '/^[\s\r\n]/u', $original_hashtag ) ) {
				++$start;
				$original_hashtag = substr( $original_hashtag, 1 );
			}

			// Clean the hashtag (removing trailing punctuation)
			$cleaned_hashtag = $this->clean_hashtag( $original_hashtag );

			// Calculate the correct byte position for end
			$end = $start + strlen( $cleaned_hashtag );

			$hashtags[] = array(
				'index'    => array(
					'byteStart' => $start,
					'byteEnd'   => $end,
				),
				'features' => array(
					array(
						'$type' => 'app.bsky.richtext.facet#tag',
						'tag'   => substr( $cleaned_hashtag, 1 ), // Remove the '#' prefix
					),
				),
			);
		}

		return $hashtags;
	}

	/**
	 * Clean the text for hashtags.
	 *   *
	 *
	 * @return string
	 */
	private function clean_text_for_hashtags() {
		// Regex to find and remove URLs
		preg_match_all( self::URL_REGEX, $this->text, $url_matches, PREG_OFFSET_CAPTURE );

		// Replace all URLs in the text with placeholders of the same length.
		$clean_text = $this->text;
		foreach ( $url_matches[0] as $url_match ) {
			$url        = $url_match[0];
			$start      = $url_match[1];
			$url_length = strlen( $url );

			// Replace URL with spaces to maintain position alignment
			$clean_text = substr_replace( $clean_text, str_repeat( ' ', $url_length ), $start, $url_length );
		}

		return $clean_text;
	}

	/**
	 * Clean the hashtag.
	 *
	 * @param string $tag - The hashtag
	 *
	 * @return string
	 */
	private function clean_hashtag( $tag ) {
		// Trim whitespace and remove trailing punctuation
		return preg_replace( '/\p{P}+$/u', '', trim( $tag ) );
	}

	/**
	 * Calculate the grapheme length of the post according to Bluesky's rules
	 *
	 * @return int
	 */
	private function calculate_post_length() {
		$text = $this->text;

		// Replace URLs with 20 character placeholders (after removing protocols)
		$text = preg_replace_callback(
			self::URL_REGEX,
			function ( $matches ) {
				$url = $matches[0];
				// Remove protocol if exists
				$url = preg_replace( '#^https?://#', '', $url );
				// Return 20 chars as per Bluesky's counting rules
				return str_repeat( 'x', 20 );
			},
			$text
		);

		return strlen( $text );
	}

	/**
	 * Validate post length
	 *
	 * @throws Exception if post is too short or too long
	 */
	private function validate_post_length() {
		$length = $this->calculate_post_length();

		if ( $length < self::MIN_TEXT_LENGTH ) {
			throw new Exception(
				sprintf(
					// translators: Minimum text length
					esc_html_x( 'Post text cannot be shorter than %d characters', 'Bluesky', 'uncanny-automator' ),
					esc_html( (string) self::MIN_TEXT_LENGTH )
				)
			);
		}

		if ( $length > self::MAX_TEXT_LENGTH ) {
			throw new Exception(
				sprintf(
					// translators: Maximum text length
					esc_html_x( 'Post text cannot be longer than %d characters', 'Bluesky', 'uncanny-automator' ),
					esc_html( (string) self::MAX_TEXT_LENGTH )
				)
			);
		}
	}
}
