<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Services;

/**
 * Projection-time helper for the wire-shape `miscellaneous{}` URL fields.
 *
 * Pure WordPress wrapper around admin URL builders. URL patterns are sourced
 * from the legacy `Services\Recipe\Structure\Miscellaneous::hydrate_properties`
 * (see `src/core/services/recipe/structure/miscellaneous.php`) so the
 * bundled UI's link behaviour is preserved across the projection cutover.
 *
 * Used by the wire serializer at projection time, NOT by the cache or the
 * Recipe domain — Option D keeps URL construction independent of the
 * canonical tree shape.
 */
final class Recipe_Url_Service {

	/**
	 * Shared instance handed out by `instance()`.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Shared singleton accessor.
	 *
	 * Holds no per-request state; reused so callers share one instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		return self::$instance ??= new self();
	}

	/**
	 * Admin URL that triggers the recipe-duplicate flow.
	 *
	 * Pattern lifted verbatim from legacy Miscellaneous so existing
	 * client-side handlers continue to recognise it.
	 */
	public function url_duplicate_recipe( int $recipe_id ): string {
		return sprintf(
			'%s?action=%s&post=%d&return_to_recipe=yes&_wpnonce=%s',
			admin_url( 'edit.php' ),
			'copy_recipe_parts',
			$recipe_id,
			wp_create_nonce( 'Aut0Mat0R' )
		);
	}

	/**
	 * Admin URL that trashes the recipe.
	 *
	 * Returns '' when the current user lacks the delete capability —
	 * `get_delete_post_link` returns null/false in that case; we cast to
	 * string for wire-contract compliance.
	 *
	 * `get_delete_post_link` HTML-escapes ampersands (`&amp;`) for direct
	 * echo into PHP templates. The wire shape is JSON consumed by Lit
	 * `href` bindings, which escape again — decode here so query params
	 * survive a single HTML attribute encode pass.
	 */
	public function url_trash_recipe( int $recipe_id ): string {
		$url = get_delete_post_link( $recipe_id );

		if ( ! is_string( $url ) ) {
			return '';
		}

		return wp_specialchars_decode( $url, ENT_QUOTES );
	}

	/**
	 * Admin URL that opens the recipe in the recipe editor.
	 */
	public function url_edit_recipe( int $recipe_id ): string {
		return admin_url( sprintf( 'post.php?post=%d&action=edit', $recipe_id ) );
	}

	/**
	 * Admin URL for the recipe's log-listing page.
	 */
	public function url_logs( int $recipe_id ): string {
		return sprintf(
			'%s?post_type=uo-recipe&page=uncanny-automator-admin-logs&recipe_id=%d',
			admin_url( 'edit.php' ),
			$recipe_id
		);
	}

	/**
	 * Admin URL that triggers the recipe-export flow.
	 */
	public function url_download_recipe( int $recipe_id ): string {
		return sprintf(
			'%s?action=%s&post=%d&_wpnonce=%s',
			admin_url( 'edit.php' ),
			'export_recipe',
			$recipe_id,
			wp_create_nonce( 'Aut0Mat0R' )
		);
	}

	/**
	 * Admin URL of the recipes-list screen. The same value for every recipe;
	 * the lock dialog's "Return to recipe list" button reads it.
	 */
	public function url_all_recipes(): string {
		return admin_url( 'edit.php?post_type=uo-recipe' );
	}

	/**
	 * Aggregator returning all five URL fields.
	 *
	 * @return array{url_duplicate_recipe: string, url_trash_recipe: string, url_logs: string, url_download_recipe: string, url_all_recipes: string}
	 */
	public function all( int $recipe_id ): array {
		return array(
			'url_duplicate_recipe' => $this->url_duplicate_recipe( $recipe_id ),
			'url_trash_recipe'     => $this->url_trash_recipe( $recipe_id ),
			'url_logs'             => $this->url_logs( $recipe_id ),
			'url_download_recipe'  => $this->url_download_recipe( $recipe_id ),
			'url_all_recipes'      => $this->url_all_recipes(),
		);
	}
}
