<?php
/**
 * URL Agent Context — derives ModelContext from a URL instead of WP globals.
 *
 * Used by the detached chat window: when the user navigates in the main
 * WordPress window, the chat calls the refresh endpoint with the new page_url.
 * This class resolves context from that URL so the agent has up-to-date
 * situational awareness without relying on get_current_screen() / $_GET.
 *
 * @since 7.1.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Application\Mcp\Agent;

use WP_Post;
use WP_Screen;

/**
 * Builds ModelContext from a URL rather than WordPress globals.
 *
 * Overrides the four protected seams in Agent_Context so the inherited
 * build_*() methods work identically in REST context.
 *
 * @since 7.1.0
 */
class Url_Agent_Context extends Agent_Context {

	/**
	 * The source URL to derive context from.
	 *
	 * @var string
	 */
	private string $url;

	/**
	 * Resolved screen data from Url_Screen_Resolver.
	 *
	 * @var array{screen_id: string, post_id: int, post_type: string, taxonomy: string, tag_id: int, page_slug: string}
	 */
	private array $resolved;

	/**
	 * Constructor.
	 *
	 * @param string $url Admin URL to derive context from.
	 */
	public function __construct( string $url ) {
		$this->url      = $url;
		$this->resolved = ( new Url_Screen_Resolver() )->resolve( $url );
	}

	/**
	 * Get WP_Screen from the resolved screen ID.
	 *
	 * @return WP_Screen|null
	 */
	protected function get_current_screen(): ?WP_Screen {

		$screen_id = $this->resolved['screen_id'];

		if ( '' === $screen_id ) {
			return null;
		}

		// WP_Screen and get_current_screen() are admin-only; ensure both are available in REST context.
		if ( ! function_exists( 'get_current_screen' ) ) {
			require_once ABSPATH . 'wp-admin/includes/screen.php';
		}

		if ( ! class_exists( 'WP_Screen' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
		}

		$screen = WP_Screen::get( $screen_id );

		if ( '' !== $this->resolved['taxonomy'] ) {
			$screen->taxonomy = $this->resolved['taxonomy'];
		}

		return $screen;
	}

	/**
	 * Get the post from the resolved post ID.
	 *
	 * @return WP_Post|null
	 */
	protected function get_current_post(): ?WP_Post {

		$post_id = $this->resolved['post_id'];

		if ( 0 === $post_id ) {
			return null;
		}

		$post = get_post( $post_id );

		return $post instanceof WP_Post ? $post : null;
	}

	/**
	 * Derive admin page title from the resolved URL data.
	 *
	 * @return string Best-effort title, or empty string.
	 */
	protected function get_admin_page_title(): string {

		$post_type = $this->resolved['post_type'];
		$taxonomy  = $this->resolved['taxonomy'];

		// Editing a specific post — use the post title.
		if ( $this->resolved['post_id'] > 0 ) {
			$post = $this->get_current_post();
			if ( null !== $post ) {
				return $post->post_title;
			}
		}

		// Post type list or new post screen.
		if ( '' !== $post_type ) {
			$pto = get_post_type_object( $post_type );
			if ( null !== $pto ) {
				return $pto->labels->name;
			}
		}

		// Taxonomy screen.
		if ( '' !== $taxonomy ) {
			$tax = get_taxonomy( $taxonomy );
			if ( false !== $tax ) {
				return $tax->labels->name;
			}
		}

		// Dashboard.
		if ( 'dashboard' === $this->resolved['screen_id'] ) {
			return 'Dashboard';
		}

		return '';
	}

	/**
	 * Return the original URL passed to the constructor.
	 *
	 * @return string
	 */
	protected function get_current_admin_url(): string {
		return $this->url;
	}
}
