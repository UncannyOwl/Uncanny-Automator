<?php
namespace Uncanny_Automator\Services\Dashboard;

use DateTimeImmutable;
use DateTimeZone;
use WP_Error;

/**
 * Class Recent_Articles
 *
 * The newest posts of the Automator blog for the Dashboard's Latest news card.
 * The site reads them from automatorplugin.com and caches them, so the browser
 * never calls the blog.
 *
 * @package Uncanny_Automator\Services\Dashboard
 */
class Recent_Articles {

	/**
	 * Seconds to wait for the blog before giving up.
	 *
	 * @var int
	 */
	const TIMEOUT = 3;

	/**
	 * Seconds a failed read is remembered, so a slow or unreachable blog does
	 * not hold up every Dashboard visit.
	 *
	 * @var int
	 */
	const FAILURE_EXPIRATION = 15 * MINUTE_IN_SECONDS;

	/**
	 * Number of posts to fetch.
	 *
	 * @var int $per_page
	 */
	protected $per_page = 3;

	/**
	 * API URL from which to fetch the posts.
	 *
	 * @var string $api_url
	 */
	protected $api_url = '';

	/**
	 * Transient key for the cached posts.
	 *
	 * @var string $transient_key
	 */
	protected $transient_key = 'automator_dashboard_recent_articles_posts';

	/**
	 * Transient key that marks a recent failed read.
	 *
	 * @var string $failure_transient_key
	 */
	protected $failure_transient_key = 'automator_dashboard_recent_articles_failed';

	/**
	 * The API base URL.
	 *
	 * @var string
	 */
	protected $api_base_url = 'https://automatorplugin.com/wp-json/wp/v2/posts';

	/**
	 * Cache expiration time in seconds.
	 *
	 * @var int $expiration
	 */
	protected $expiration = 0;

	/**
	 * Constructor for the class.
	 *
	 * Sets the API URL and cache expiration time.
	 *
	 * @return void
	 */
	public function __construct() {

		$this->api_url = add_query_arg(
			array(
				'per_page' => $this->per_page,
				'_fields'  => 'title,link,date_gmt',
			),
			$this->api_base_url
		);

		$this->expiration = apply_filters( 'automator_dashboard_recent_articles_posts_expiration', DAY_IN_SECONDS );
	}

	/**
	 * The newest posts, newest first, from the cache or the blog.
	 *
	 * @return array<int,array{title:string,url:string,published_at:string}>|WP_Error An empty list when the blog has no post; a 503 error when the blog cannot be read.
	 */
	public function get_posts() {

		$cached = get_transient( $this->transient_key );

		if ( $this->is_post_list( $cached ) ) {
			return $cached;
		}

		if ( false !== get_transient( $this->failure_transient_key ) ) {
			return $this->unavailable();
		}

		$posts = $this->read_blog();

		if ( is_wp_error( $posts ) ) {
			set_transient( $this->failure_transient_key, 1, self::FAILURE_EXPIRATION );
			return $posts;
		}

		set_transient( $this->transient_key, $posts, $this->expiration );

		return $posts;
	}

	/**
	 * Reads the posts from the blog's REST API.
	 *
	 * @return array<int,array{title:string,url:string,published_at:string}>|WP_Error
	 */
	private function read_blog() {

		$response = wp_remote_get( $this->api_url, array( 'timeout' => self::TIMEOUT ) );

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return $this->unavailable();
		}

		$items = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $items ) ) {
			return $this->unavailable();
		}

		$posts = array();

		foreach ( $items as $item ) {
			$post = is_array( $item ) ? $this->to_post( $item ) : null;
			if ( null !== $post ) {
				$posts[] = $post;
			}
		}

		return array_slice( $posts, 0, $this->per_page );
	}

	/**
	 * A blog post as the Dashboard shows it, or null when it lacks a title, a
	 * web link or a publication date.
	 *
	 * @param array<string,mixed> $item A post from the blog's REST API.
	 *
	 * @return array{title:string,url:string,published_at:string}|null
	 */
	private function to_post( array $item ) {

		$rendered = isset( $item['title']['rendered'] ) && is_string( $item['title']['rendered'] ) ? $item['title']['rendered'] : '';
		$title    = trim( html_entity_decode( wp_strip_all_tags( $rendered ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		$url      = isset( $item['link'] ) && is_string( $item['link'] ) ? esc_url_raw( $item['link'], array( 'http', 'https' ) ) : '';
		$date     = isset( $item['date_gmt'] ) && is_string( $item['date_gmt'] )
			? DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i:s', $item['date_gmt'], new DateTimeZone( 'UTC' ) )
			: false;

		if ( '' === $title || '' === $url || false === $date ) {
			return null;
		}

		return array(
			'title'        => $title,
			'url'          => $url,
			'published_at' => $date->format( DATE_ATOM ),
		);
	}

	/**
	 * Whether a cached value is a list of posts. Older versions cached the raw
	 * HTTP response under the same key.
	 *
	 * @param mixed $value The cached value.
	 *
	 * @return bool
	 */
	private function is_post_list( $value ) {

		if ( ! is_array( $value ) || array_values( $value ) !== $value ) {
			return false;
		}

		foreach ( $value as $post ) {
			if ( ! is_array( $post ) || ! isset( $post['title'], $post['url'], $post['published_at'] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * The error the Dashboard gets while the blog cannot be read.
	 *
	 * @return WP_Error
	 */
	private function unavailable() {
		return new WP_Error(
			'automator_recent_articles_unavailable',
			esc_html_x( 'The latest Automator news could not be loaded.', 'Dashboard latest news error', 'uncanny-automator' ),
			array( 'status' => 503 )
		);
	}
}
