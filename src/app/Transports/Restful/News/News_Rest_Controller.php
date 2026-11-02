<?php

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\News;

use Uncanny_Automator\App\Transports\Restful\Utilities\Traits\Rest_Responses;
use Uncanny_Automator\App\Transports\Restful\Utilities\Traits\Restful_Permissions;
use Uncanny_Automator\Services\Dashboard\Recent_Articles;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * News REST registration hub.
 *
 * Static-only. Provides the read-only `GET /uap/v2/news/get-latest` endpoint
 * the Dashboard's Latest news card reads. Single endpoint — no `Routes/` split.
 */
final class News_Rest_Controller {

	use Restful_Permissions;
	use Rest_Responses;

	private const NAMESPACE_ID = AUTOMATOR_REST_API_END_POINT;

	private const REST_BASE = 'news';

	/**
	 * Register the news routes. Called from `Restful_Bootstrap::register_rest_routes()`.
	 */
	public static function register_routes(): void {

		// GET /uap/v2/news/get-latest
		register_rest_route(
			self::NAMESPACE_ID,
			'/' . self::REST_BASE . '/get-latest',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'get_latest' ),
					'permission_callback' => array( self::class, 'permissions_check' ),
				),
			)
		);
	}

	/**
	 * GET handler. Up to three blog posts under `data.posts`, or a 503 while
	 * the blog cannot be read; the Dashboard leaves the card out either way.
	 */
	public static function get_latest( WP_REST_Request $request ): WP_REST_Response {
		$posts = ( new Recent_Articles() )->get_posts();

		return $posts instanceof WP_Error ? self::error_reply( $posts ) : self::success( array( 'posts' => $posts ) );
	}
}
