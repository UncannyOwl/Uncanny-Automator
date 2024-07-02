<?php

namespace Uncanny_Automator\Services\Dashboard;

/**
 * Class Recent_Articles
 *
 * Handles the retrieval and caching of recent articles for the Automator dashboard.
 *
 * @package Uncanny_Automator\Services\Dashboard
 */
class Recent_Articles {

	/**
	 * Number of articles to fetch per page.
	 *
	 * @var int $per_page
	 */
	protected $per_page = 4;

	/**
	 * API URL from which to fetch the articles.
	 *
	 * @var string $api_url
	 */
	protected $api_url = '';

	/**
	 * Transient key for caching the API response.
	 *
	 * @var string $transient_key
	 */
	protected $transient_key = 'automator_dashboard_recent_articles_posts';

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
	 * Registers the necessary WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'wp_ajax_automator_get_recent_articles', array( $this, 'fetch' ) );
	}

	/**
	 * Constructor for the class.
	 *
	 * Sets the API URL and cache expiration time.
	 *
	 * @return void
	 */
	public function __construct() {

		$this->api_url = $this->api_base_url . "?automator_dashboard=yes&_embed=wp:term&per_page={$this->per_page}";

		///wp-json/wp/v2/posts?automator_dashboard=yes&_embed=wp:term&per_page=4
		$this->expiration = apply_filters( 'automator_dashboard_recent_articles_posts_expiration', DAY_IN_SECONDS );
	}

	/**
	 * Caches the API response using transients.
	 *
	 * @param mixed $mixed The API response data to cache.
	 *
	 * @return self The current instance for method chaining.
	 */
	public function cache_response( $mixed ) {

		set_transient( $this->transient_key, $mixed, $this->expiration );

		return $this;
	}

	/**
	 * Retrieves the cached API response if available.
	 *
	 * @return mixed The cached response data, or false if not found.
	 */
	public function get_cached_response() {
		return get_transient( $this->transient_key );
	}

	/**
	 * Fetches the latest articles from the API or cache.
	 *
	 * @return void
	 */
	public function fetch() {

		// Set Cache-Control header.
		// Set the Cache-Control header to 5 hours
		header( 'Cache-Control: public, max-age=' . 12 * HOUR_IN_SECONDS, true );

		// Set the Expires header to 5 hours from now
		header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', time() + 12 * HOUR_IN_SECONDS ) . ' GMT', true );

		// Verify the nonce.
		if ( ! wp_verify_nonce( automator_filter_input( 'nonce' ), 'automator_get_recent_articles' ) ) {
			wp_die( '-1' );
		}

		$cached_response = $this->get_cached_response();

		if ( false !== $cached_response ) {
			$this->send_cached_response( $cached_response );
		}

		$response = wp_remote_get( $this->api_url );

		if ( is_wp_error( $response ) ) {
			$this->send_error( $response );
		}

		$this->cache_response( $response )->send_success( $response );
	}

	/**
	 * Sends the cached response as JSON.
	 *
	 * @param mixed $cached_response The cached API response data.
	 *
	 * @return void
	 */
	public function send_cached_response( $cached_response ) {
		$body = wp_remote_retrieve_body( $cached_response );
		wp_send_json( array( 'posts' => json_decode( $body, true ) ), 200 );
	}

	/**
	 * Sends the successful API response as JSON.
	 *
	 * @param mixed $response The API response data.
	 *
	 * @return void
	 */
	public function send_success( $response ) {
		$body = wp_remote_retrieve_body( $response );
		wp_send_json( array( 'posts' => json_decode( $body, true ) ), 200 );
	}

	/**
	 * Sends an error response as JSON.
	 *
	 * @param WP_Error $response The WP_Error object representing the error.
	 *
	 * @return void
	 */
	public function send_error( $response ) {
		wp_send_json_error(
			array(
				'message' => $response->get_error_message(),
			),
			$response->get_error_code()
		);
	}

}
