<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer;

use Uncanny_Automator\App\Recipe_Builder\Recipe\Notices\Recipe_Notice_Center;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Schema\Recipe_Schema_Reader;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Key;

/**
 * Cache-first read of a recipe's canonical tree, projected to the ModelRecipe
 * wire shape the recipe-builder UI parses, with the recipe's notices.
 *
 * This is the single wire-retrieval entry point for a stored recipe: it
 * composes {@see Recipe_Schema_Reader} (cache-or-rebuild), {@see Recipe_Wire_Serializer}
 * (projection) and {@see Recipe_Notice_Center} (the optional top-level
 * `notices` list). Notices are stored-recipe state, so they are added here and
 * not in the serializer, which also projects library templates.
 *
 * Deliberately NOT a Bridge. Bridges front the legacy `src/core/` seam; this
 * reads new-format `uo-block` storage through app-owned stores and services and
 * needs nothing from the legacy recipe-object surface. It previously lived on
 * `Automator_Recipe_Object_Bridge`, which put a new concept behind a legacy
 * boundary and made the bridge depend on the entire schema + wire stack.
 */
final class Recipe_Wire_Service {

	/**
	 * Singleton instance — see `instance()`.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * The cache-or-rebuild tree read.
	 *
	 * @var Recipe_Schema_Reader
	 */
	private Recipe_Schema_Reader $reader;

	/**
	 * The tree-to-wire projection.
	 *
	 * @var Recipe_Wire_Serializer
	 */
	private Recipe_Wire_Serializer $serializer;

	/**
	 * The recipe notices sent as the optional top-level `notices` list.
	 *
	 * @since 8.0.0
	 *
	 * @var Recipe_Notice_Center
	 */
	private Recipe_Notice_Center $notices;

	/**
	 * Lazy-default DI: production reads the shared reader, serializer and
	 * notice center; tests feed a fixture tree through a reader over a builder
	 * double, may inject a serializer whose token decorator carries a resolver
	 * spy, and may inject a center holding their own notices.
	 *
	 * @param Recipe_Schema_Reader|null   $reader     Tree read seam.
	 * @param Recipe_Wire_Serializer|null $serializer Wire projection.
	 * @param Recipe_Notice_Center|null   $notices    Recipe notice center.
	 */
	public function __construct( ?Recipe_Schema_Reader $reader = null, ?Recipe_Wire_Serializer $serializer = null, ?Recipe_Notice_Center $notices = null ) {
		$this->reader     = $reader ?? Recipe_Schema_Reader::instance();
		$this->serializer = $serializer ?? Recipe_Wire_Serializer::instance();
		$this->notices    = $notices ?? automator_recipe_notice_center();
	}

	/**
	 * Shared singleton accessor.
	 *
	 * Holds no per-request state; reused so the schema read runs once per
	 * process per recipe.
	 *
	 * @return self
	 */
	public static function instance(): self {
		return self::$instance ??= new self();
	}

	/**
	 * Fetch the recipe as a `ModelRecipe`-shaped associative array suitable
	 * for the recipe-builder UI's Zod parse.
	 *
	 * Reads the canonical tree through {@see Recipe_Schema_Reader}, which migrates
	 * legacy storage first, then projects it. `notices` is set when at least one
	 * recipe notice applies and left out otherwise (never `null`, never `[]`).
	 *
	 * @param int $recipe_id Recipe post ID.
	 *
	 * @return array<string, mixed>|null Wire array, or null on missing recipe.
	 * @throws \Uncanny_Automator\App\Recipe_Builder\Block\Migration\Legacy_Storage_Migration_Exception When the legacy migration fails; the REST route surfaces it as a 500.
	 */
	public function get_wire_array( int $recipe_id ): ?array {
		$tree = $this->reader->get_or_build( $recipe_id );
		if ( null === $tree ) {
			return null;
		}

		$wire    = $this->serializer->serialize( $tree );
		$notices = $this->notices->for_recipe( $recipe_id );
		if ( null !== $notices ) {
			$wire[ Wire_Key::NOTICES ] = $notices;
		}

		return $wire;
	}
}
