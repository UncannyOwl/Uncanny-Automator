<?php

namespace Uncanny_Automator\Integrations\Wp;

/**
 * Parse-time compatibility layer for legacy WP token IDs.
 *
 * Recipes saved before the Step 1 / Step 2 normalization carry token
 * references such as `{{42:WPUSERSPOSTSTATUS:WPPOSTTYPES_ID}}`. After
 * normalization the trigger only declares `POSTID`. Without compatibility,
 * those recipes resolve to empty strings.
 *
 * Resolution flow:
 *   1. Token parser receives the field text.
 *   2. Parser explodes the inner pattern to `[trigger_id, code, token_id]`.
 *   3. Parser applies the `automator_resolve_token_pieces` filter; this
 *      class hooks the filter via {@see self::rewrite_pieces()}.
 *   4. `rewrite_pieces()` asks {@see self::canonical()} for the canonical
 *      ID and overwrites the third piece in place.
 *   5. Hydration proceeds against the canonical ID.
 *
 * Aliases are NEVER returned by `define_tokens()` — the UI shows only
 * canonical IDs. This class exists purely to keep saved recipes working
 * through and after the postmeta migration.
 *
 * @since 7.5
 *
 * @package Uncanny_Automator\Integrations\Wp
 */
class Wp_Token_Aliases {

	/**
	 * Map of legacy id => canonical id.
	 *
	 * Populated by {@see self::register()} during integration setup. Tests
	 * call {@see self::reset()} in `tearDown()` to keep the static state
	 * clean across runs.
	 *
	 * @var array<string, string>
	 */
	private static $map = array();

	/**
	 * Register an alias map. First-write-wins — re-registering an existing
	 * key is a no-op so test fixtures and integration boot order cannot
	 * silently overwrite each other.
	 *
	 * @param array<string, string> $map Legacy id => canonical id.
	 *
	 * @return void
	 */
	public static function register( array $map ): void {

		// `+` preserves left operand's existing keys — first registration wins.
		self::$map = self::$map + $map;
	}

	/**
	 * Resolve a token ID to its canonical form. Returns the input when no
	 * alias is registered for it (the parser already operates on canonical
	 * IDs as the default path).
	 *
	 * @param string $token_id Token ID from the third position of a
	 *                         `{{trigger_id:CODE:TOKEN_ID}}` triplet.
	 *
	 * @return string Canonical token ID.
	 */
	public static function canonical( string $token_id ): string {

		return self::$map[ $token_id ] ?? $token_id;
	}

	/**
	 * Filter callback for `automator_resolve_token_pieces`. Rewrites the
	 * third piece (`token_id`) to its canonical form when an alias is
	 * registered. No-op when the pieces array doesn't have a third element
	 * or when the ID isn't an alias.
	 *
	 * @param array $pieces `[trigger_id, code, token_id, ...]`.
	 *
	 * @return array
	 */
	public static function rewrite_pieces( array $pieces ): array {

		if ( ! isset( $pieces[2] ) ) {
			return $pieces;
		}

		$pieces[2] = self::canonical( (string) $pieces[2] );

		return $pieces;
	}

	/**
	 * Reset the registered map. Test-only — production code must NEVER call
	 * this. Used in `tearDown()` so each test starts from a clean slate.
	 *
	 * @return void
	 */
	public static function reset(): void {

		self::$map = array();
	}

	/**
	 * Expose the full registered map. Consumed by the migration script
	 * (Step 4) to know which alias IDs to rewrite in stored data.
	 *
	 * @return array<string, string>
	 */
	public static function all(): array {

		return self::$map;
	}

	/**
	 * Default legacy → canonical alias map for the WP integration.
	 *
	 * Covers every legacy token ID that historical recipes may have persisted
	 * in postmeta. Sources: `docs/active/integration-token-normalization/WP-canonical-tokens.md`
	 * "Alias map" section + the Step-2 carry-forward decisions.
	 *
	 * @return array<string, string>
	 */
	public static function default_map(): array {

		return array(
			// Bare-title family (Decision 3) — every entity-specific title collapses to POSTTITLE.
			'WPPAGE'                            => 'POSTTITLE',
			'WPPOST'                            => 'POSTTITLE',
			'WPCUSTOMPOST'                      => 'POSTTITLE',
			'WPPOSTCOMMENTS'                    => 'POSTTITLE',

			// WPPOSTTYPES_* family (commit 79fe66c9c6, 2026-05-08).
			'WPPOSTTYPES_ID'                    => 'POSTID',
			'WPPOSTTYPES_URL'                   => 'POSTURL',
			'WPPOSTTYPES_TYPE'                  => 'WPPOSTTYPES',
			'WPPOSTTYPES_THUMB_ID'              => 'POSTIMAGEID',
			'WPPOSTTYPES_THUMB_URL'             => 'POSTIMAGEURL',
			'WPPOSTTYPES_CONTENT'               => 'POSTCONTENT',
			'WPPOSTTYPES_CONTENT_BEAUTIFIED'    => 'POSTCONTENT_BEAUTIFIED',
			'WPPOSTTYPES_EXCERPT'               => 'POSTEXCERPT',
			'WPPOSTTYPES_STATUS'                => 'POSTSTATUS',

			// VIEWPAGE prefix family.
			'WPPAGE_ID'                         => 'POSTID',
			'WPPAGE_URL'                        => 'POSTURL',
			'WPPAGE_POSTNAME'                   => 'POSTNAME',
			'WPPAGE_CONTENT'                    => 'POSTCONTENT',
			'WPPAGE_CONTENT_BEAUTIFIED'         => 'POSTCONTENT_BEAUTIFIED',
			'WPPAGE_EXCERPT'                    => 'POSTEXCERPT',
			'WPPAGE_THUMB_ID'                   => 'POSTIMAGEID',
			'WPPAGE_THUMB_URL'                  => 'POSTIMAGEURL',

			// VIEWPOST / WPVIEWPOSTTYPE prefix family.
			'WPPOST_ID'                         => 'POSTID',
			'WPPOST_URL'                        => 'POSTURL',
			'WPPOST_POSTNAME'                   => 'POSTNAME',
			'WPPOST_CONTENT'                    => 'POSTCONTENT',
			'WPPOST_CONTENT_BEAUTIFIED'         => 'POSTCONTENT_BEAUTIFIED',
			'WPPOST_EXCERPT'                    => 'POSTEXCERPT',
			'WPPOST_TYPE'                       => 'WPPOSTTYPES',
			'WPPOST_THUMB_ID'                   => 'POSTIMAGEID',
			'WPPOST_THUMB_URL'                  => 'POSTIMAGEURL',

			// VIEWCUSTOMPOST prefix family.
			'WPCUSTOMPOST_ID'                   => 'POSTID',
			'WPCUSTOMPOST_URL'                  => 'POSTURL',
			'WPCUSTOMPOST_EXCERPT'              => 'POSTEXCERPT',

			// WPSUBMITCOMMENT prefix family.
			'WPPOSTCOMMENTS_ID'                 => 'POSTID',
			'WPPOSTCOMMENTS_URL'                => 'POSTURL',
			'WPPOSTCOMMENTS_POSTNAME'           => 'POSTNAME',
			'WPPOSTCOMMENTS_EXCERPT'            => 'POSTEXCERPT',
			'WPPOSTCOMMENTS_THUMB_ID'           => 'POSTIMAGEID',
			'WPPOSTCOMMENTS_THUMB_URL'          => 'POSTIMAGEURL',

			// COMMENTAPPROVED_* family (Pro WPCOMMENTAPPROVED trigger).
			'COMMENTAPPROVED_ID'                => 'POSTID',
			'COMMENTAPPROVED_URL'               => 'POSTURL',
			'COMMENTAPPROVED_POSTNAME'          => 'POSTNAME',
			'COMMENTAPPROVED_CONTENT'           => 'POSTCONTENT',
			'COMMENTAPPROVED_CONTENT_BEAUTIFIED' => 'POSTCONTENT_BEAUTIFIED',
			'COMMENTAPPROVED_EXCERPT'           => 'POSTEXCERPT',
			'COMMENTAPPROVED_TYPE'              => 'WPPOSTTYPES',
			'COMMENTAPPROVED_THUMB_ID'          => 'POSTIMAGEID',
			'COMMENTAPPROVED_THUMB_URL'         => 'POSTIMAGEURL',
			'COMMENTAPPROVED_COMMENT'           => 'POSTCOMMENTCONTENT',
			'COMMENTAPPROVED_COMMENTERNAME'     => 'POSTCOMMENTERNAME',
			'COMMENTAPPROVED_COMMENTEREMAIL'    => 'POSTCOMMENTEREMAIL',
			'COMMENTAPPROVED_COMMENTERWEBSITE'  => 'POSTCOMMENTERWEBSITE',

			// Comment short-form aliases.
			'POSTCOMMENT_ID'                    => 'POSTCOMMENTID',
			'COMMENTID'                         => 'POSTCOMMENTID',
			'COMMENTAUTHOR'                     => 'POSTCOMMENTERNAME',
			'COMMENTAUTHOREMAIL'                => 'POSTCOMMENTEREMAIL',
			'COMMENTAUTHORWEB'                  => 'POSTCOMMENTERWEBSITE',
			'COMMENTCONTENT'                    => 'POSTCOMMENTCONTENT',
			'comment'                           => 'POSTCOMMENTCONTENT',

			// Bare type alias (Pro WPPOSTNOTINSTATUS).
			'POSTTYPE'                          => 'WPPOSTTYPES',

			// User-meta legacy IDs (Pro WPUSERUPDATEDMETA).
			'UMETAKEY'                          => 'USER_META_KEY',
			'UMETAVALUE'                        => 'USER_META_VALUE',

			// Role legacy ID (Pro WPUSERCREATEDWITHROLE).
			'USERCREATEDWITHROLE'               => 'WPROLE',

			// Archive URL legacy underscore form.
			'ARCHIVE_URL'                       => 'ARCHIVEURL',
		);
	}
}
