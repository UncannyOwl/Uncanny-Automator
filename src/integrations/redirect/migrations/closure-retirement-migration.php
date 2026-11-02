<?php
declare(strict_types=1);

namespace Uncanny_Automator\Integrations\Redirect\Migrations;

use Uncanny_Automator\Migrations\Migration;

/**
 * The atomic closure-retirement upgrade: one site-wide pass converts every `uo-closure` REDIRECT row into a
 * `uo-action` REDIRECT_TO_URL row in place, post id preserved.
 *
 * Lives with the Redirect integration, the closure's successor, on the core `Migration` base: named, recorded once
 * per site in `automator_migrations`, logged on start and completion. Registered from `Automator_Load::load_migrations()`
 * rather than from the integration's `load()`, because integrations load on demand and no published recipe uses
 * `REDIRECT_TO_URL` until this pass ran. It runs early on `init` (or `wp_loaded`, ahead of the trigger queue, when
 * `init` already passed), so the first request after the update converts before any recipe fires; the base's
 * `shutdown` hook is the fallback.
 *
 * Set-based SQL rather than the stores: a store write costs about 25 ms per row through the schema-lifecycle and
 * cache listeners, while the whole pass here costs a few tens of milliseconds for hundreds of rows. The
 * `Closure_Retirement_Parity_Test` pins the meta written here to what the action store writes, so the key set
 * below never drifts silently.
 *
 * Re-entrant: the `post_type` flip is the last write per row, so an interrupted pass leaves the row
 * discoverable and every earlier statement converges (renames only where the old key exists, constants are
 * overwritten).
 *
 * @since 8.0.0
 */
final class Closure_Retirement_Migration extends Migration {

	/**
	 * The migration's name in `automator_migrations`.
	 *
	 * @var string
	 */
	public const NAME = 'closure_retirement_8_0_0';

	/**
	 * The action's sentence template, as `REDIRECT_TO_URL::setup_action()` declares it.
	 *
	 * @var string
	 */
	private const SENTENCE = 'Redirect to {{a URL:REDIRECT_URL}}';

	/**
	 * The closure's own code, and the action's: a pass interrupted after the meta rewrite leaves a closure row
	 * already carrying the action's code, which must still convert.
	 *
	 * @var string[]
	 */
	private const REDIRECT_CODES = array(
		'REDIRECT',
		'REDIRECT_TO_URL',
	);

	/**
	 * Closure meta the action store never writes for an action; dropped so a converted row carries exactly the
	 * store's key set.
	 *
	 * @var string[]
	 */
	private const CLOSURE_ONLY_KEYS = array(
		'integration_name',
		'type',
		'uap_closure_version',
	);

	/**
	 * The base hooks the `shutdown` fallback; this adds the early run. The pass reaches `src/app` only through
	 * the `automator_*` helpers, as any code outside `src/app` must.
	 */
	public function __construct() {
		parent::__construct( self::NAME );
		add_action( did_action( 'init' ) ? 'wp_loaded' : 'init', array( $this, 'maybe_run_migration' ), 0 );
	}

	/**
	 * The base's entry point: convert, then record completion so the pass never runs again.
	 *
	 * @return void
	 */
	public function migrate() {
		$this->run();
		$this->complete();
	}

	/**
	 * Convert every redirect closure. Completion is the base's business ({@see self::migrate()}).
	 *
	 * @return array<int, int[]> recipe_id => converted post ids.
	 */
	public function run(): array {
		$converted = array();
		$skipped   = 0;

		foreach ( $this->find_closures() as $row ) {
			if ( ! in_array( (string) $row->code, self::REDIRECT_CODES, true ) || AUTOMATOR_POST_TYPE_RECIPE !== (string) $row->parent_type ) {
				++$skipped;
				continue;
			}
			$this->convert( (int) $row->ID, (int) $row->post_parent, (string) $row->url );
			$converted[ (int) $row->post_parent ][] = (int) $row->ID;
		}

		foreach ( array_keys( $converted ) as $recipe_id ) {
			automator_invalidate_recipe_schema( $recipe_id );
		}
		if ( array() !== $converted ) {
			automator_refresh_entity_popularity_cache();
		}
		automator_delete_option( 'has_redirect_closures' );
		if ( $skipped > 0 ) {
			automator_log( sprintf( 'Closure retirement: %d closure row(s) left untouched (unknown code or no recipe parent).', $skipped ), 'Closure_Retirement_Migration' );
		}

		return $converted;
	}

	/**
	 * Every closure row with its code, parent type and URL.
	 *
	 * @return object[]
	 */
	private function find_closures(): array {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.ID, c.post_parent, p.post_type AS parent_type,
				        COALESCE( code.meta_value, '' ) AS code,
				        COALESCE( url.meta_value, '' ) AS url
				 FROM {$wpdb->posts} c
				 LEFT JOIN {$wpdb->posts} p ON p.ID = c.post_parent
				 LEFT JOIN {$wpdb->postmeta} code ON code.post_id = c.ID AND code.meta_key = 'code'
				 LEFT JOIN {$wpdb->postmeta} url ON url.post_id = c.ID AND url.meta_key IN ( 'REDIRECTURL', 'REDIRECT_URL' )
				 WHERE c.post_type = %s
				 ORDER BY c.ID",
				AUTOMATOR_POST_TYPE_CLOSURE
			)
		);
		// phpcs:enable
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Convert one row: meta first, post row last.
	 *
	 * @param int    $post_id   The closure row.
	 * @param int    $recipe_id Its recipe.
	 * @param string $url       The redirect URL, possibly empty.
	 *
	 * @return void
	 */
	private function convert( int $post_id, int $recipe_id, string $url ): void {
		global $wpdb;
		$sentences = automator_compose_item_sentence( self::SENTENCE, array( 'REDIRECT_URL' => $url ), array( 'REDIRECT_URL' => 'Redirect URL' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$wpdb->postmeta,
			array( 'meta_key' => 'REDIRECT_URL' ),
			array(
				'post_id'  => $post_id,
				'meta_key' => 'REDIRECTURL',
			)
		);
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key IN ( %s, %s, %s )",
				$post_id,
				self::CLOSURE_ONLY_KEYS[0],
				self::CLOSURE_ONLY_KEYS[1],
				self::CLOSURE_ONLY_KEYS[2]
			)
		);
		foreach ( array(
			'code'                         => 'REDIRECT_TO_URL',
			'integration'                  => 'REDIRECT',
			'sentence'                     => self::SENTENCE,
			'sentence_human_readable'      => $sentences['brackets'],
			'sentence_human_readable_html' => $sentences['html'],
			'user_type'                    => 'user',
			'recipe_id'                    => (string) $recipe_id,
			'REDIRECT_URL'                 => $url,
		) as $key => $value ) {
			$exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s", $post_id, $key ) );
			if ( $exists > 0 ) {
				$wpdb->update(
					$wpdb->postmeta,
					array( 'meta_value' => $value ),
					array(
						'post_id'  => $post_id,
						'meta_key' => $key,
					)
				);
			} else {
				$wpdb->insert(
					$wpdb->postmeta,
					array(
						'post_id'    => $post_id,
						'meta_key'   => $key,
						'meta_value' => $value,
					)
				);
			}
		}
		$wpdb->update(
			$wpdb->posts,
			array(
				'post_title' => $this->title( $sentences['brackets'] ),
				'menu_order' => $this->next_menu_order( $recipe_id ),
				'post_type'  => AUTOMATOR_POST_TYPE_ACTION,
			),
			array( 'ID' => $post_id )
		);
		// phpcs:enable
		clean_post_cache( $post_id );
	}

	/**
	 * The title the action store generates: the bracket sentence with token labels and braces stripped.
	 *
	 * @param string $sentence_human_readable Bracket sentence.
	 *
	 * @return string
	 */
	private function title( string $sentence_human_readable ): string {
		$nice = preg_replace( '/{{([^:}]+):[^}]+}}/', '{{$1}}', $sentence_human_readable );
		return str_replace( array( '{', '}' ), '', (string) $nice );
	}

	/**
	 * Ten past the recipe's highest top-level `menu_order` among its live and draft actions and blocks (the set
	 * `WP_Action_Store::next_menu_order()` reads), so the Redirect lands last.
	 *
	 * @param int $recipe_id The recipe.
	 *
	 * @return int
	 */
	private function next_menu_order( int $recipe_id ): int {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$max = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(menu_order) FROM {$wpdb->posts}
				 WHERE post_parent = %d
				 AND post_type IN ( %s, %s )
				 AND post_status IN ( %s, %s )",
				$recipe_id,
				AUTOMATOR_POST_TYPE_ACTION,
				AUTOMATOR_POST_TYPE_BLOCK,
				'publish',
				'draft'
			)
		);
		return $max + 10;
	}
}
