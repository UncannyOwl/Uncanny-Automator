<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer;

use Uncanny_Automator\App\Recipe_Builder\Recipe\Services\Recipe_Misc_Service;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Services\Recipe_Url_Service;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Settings\Wire\Settings_Wire_Projection;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Value_Objects\Recipe_Status;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Key;
use Uncanny_Automator\App\Recipe_Builder\Shared\Enums\Post_Status;

/**
 * Top-level entry point: schema v3 tree → ModelRecipe wire shape.
 *
 * Strict 11-key shape per the wire contract:
 *   { recipe_id, recipe_status, can_edit, title, notes, categories, tags,
 *     recipe_type, miscellaneous, settings, flow }
 *
 * `recipe_status` maps the post status to publish/draft; `can_edit` and the
 * Pro-writable `miscellaneous` flags come from the `automator_recipe_state_flags`
 * filter (free defaults); `recipe_type` is nullable (the tree carries the unset
 * state through).
 *
 * Composes:
 *   - Flow_Wire_Serializer for the `flow` projection (entry / run_as / content)
 *   - Recipe_Misc_Service (state flags + dates) + Recipe_Url_Service for `miscellaneous{}`,
 *     plus Run_As_Availability_Resolver for `miscellaneous.run_as_availability`
 *   - Settings_Wire_Projection for the `settings{}` 6-key map
 *     (extracted to Recipe/Settings/Wire/ so the enum and projection
 *     co-locate — see that sub-module's README)
 *
 * Input is a tree array (typically from Recipe_Schema_Store). NO Recipe
 * domain object reconstruction — Option D keeps the wire path independent
 * of the in-memory aggregate.
 *
 * Reads tree keys as literals; emits wire keys from Wire_Key.
 *
 * Lazy-default DI per Rule 9c.
 */
final class Recipe_Wire_Serializer {

	/**
	 * Shared instance handed out by `instance()`.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Projects the recipe's triggers, content and run-as into the `flow` key.
	 *
	 * @var Flow_Wire_Serializer
	 */
	private $flow;

	/**
	 * Supplies the `miscellaneous` key — the assorted recipe-level values the builder reads.
	 *
	 * @var Recipe_Misc_Service
	 */
	private $misc;

	/**
	 * Supplies the edit and view URLs the builder links to.
	 *
	 * @var Recipe_Url_Service
	 */
	private $urls;

	/**
	 * Projects the schema-v3 tree's recipe-level fields into the `settings{}` 6-key map.
	 */
	private $settings;

	/**
	 * Projection-time decorator that adds per-node `availability` + `tags` to the
	 * serialized `flow`. Site-state-dependent (license/plugins), so never cached.
	 *
	 * @var Recipe_Entity_Availability_Decorator
	 */
	private $availability;

	/**
	 * Projection-time decorator that derives per-item `tokens[]` on the
	 * serialized `flow`. Config-derived in edit-page/REST context, so never
	 * cached on the schema tree.
	 *
	 * @var Recipe_Entity_Token_Decorator
	 */
	private $tokens;

	/**
	 * Projection-time decorator that stamps `deferred_order` on the serialized
	 * `flow`'s deferred blocks. Ranked from the tree's fields, so never cached.
	 *
	 * @since 8.0.0
	 * @var Deferred_Order_Decorator
	 */
	private $deferred;

	/**
	 * Resolves `miscellaneous.run_as_availability` from site state (a
	 * registered user resolver), so never cached.
	 *
	 * @since 8.0.0
	 * @var Run_As_Availability_Resolver
	 */
	private $run_as_availability;

	/**
	 * Lazy-default DI per Rule 9c. The `$settings` param was added in P1 to extract
	 * the settings projection into its own sub-module; production falls back to a
	 * default instance, tests may inject a stub.
	 */
	public function __construct(
		?Flow_Wire_Serializer $flow = null,
		?Recipe_Misc_Service $misc = null,
		?Recipe_Url_Service $urls = null,
		?Settings_Wire_Projection $settings = null,
		?Recipe_Entity_Availability_Decorator $availability = null,
		?Recipe_Entity_Token_Decorator $tokens = null,
		?Deferred_Order_Decorator $deferred = null,
		?Run_As_Availability_Resolver $run_as_availability = null
	) {
		$this->flow         = $flow ?? new Flow_Wire_Serializer();
		$this->misc         = $misc ?? Recipe_Misc_Service::instance();
		$this->urls         = $urls ?? Recipe_Url_Service::instance();
		$this->settings     = $settings ?? new Settings_Wire_Projection();
		$this->availability = $availability ?? new Recipe_Entity_Availability_Decorator();
		$this->tokens       = $tokens ?? new Recipe_Entity_Token_Decorator();
		$this->deferred     = $deferred ?? new Deferred_Order_Decorator();

		$this->run_as_availability = $run_as_availability ?? new Run_As_Availability_Resolver();
	}

	/**
	 * Shared singleton accessor.
	 *
	 * @return self
	 */
	public static function instance(): self {
		return self::$instance ??= new self();
	}

	/**
	 * Project the cached tree to the ModelRecipe wire shape.
	 *
	 * @param array $tree Schema v3 canonical tree (typically from Recipe_Schema_Store).
	 * @return array Wire-shape ModelRecipe array suitable for wp_json_encode.
	 */
	public function serialize( array $tree ): array {
		$recipe_id  = (int) ( $tree['recipe_id'] ?? 0 );
		$status     = (string) ( $tree['status'] ?? Post_Status::DRAFT );
		$total_runs = $this->misc->total_runs( $recipe_id );
		$flags      = $this->misc->state_flags( $recipe_id );

		$miscellaneous = array_merge(
			array(
				Wire_Key::LAST_MODIFIED_DATE_TIMESTAMP => $this->misc->last_modified_date_timestamp( $recipe_id ),
				Wire_Key::HAS_RUN_NOW                  => $flags['has_run_now'],
				Wire_Key::RUN_AS_AVAILABILITY          => $this->run_as_availability->resolve(),
				Wire_Key::LATEST_RECIPE_LOG_ID         => $flags['latest_recipe_log_id'],
				Wire_Key::LATEST_RUN_NUMBER            => $flags['latest_run_number'],
			),
			$this->urls->all( $recipe_id )
		);

		return array(
			Wire_Key::RECIPE_ID     => $recipe_id,
			Wire_Key::RECIPE_STATUS => Recipe_Status::to_wire_status( $status ),
			Wire_Key::CAN_EDIT      => $flags['can_edit'],
			Wire_Key::TITLE         => (string) ( $tree['title'] ?? '' ),
			Wire_Key::NOTES         => (string) ( $tree['notes'] ?? '' ),
			Wire_Key::CATEGORIES    => is_array( $tree['categories'] ?? null ) ? $tree['categories'] : array(),
			Wire_Key::TAGS          => is_array( $tree['tags'] ?? null ) ? $tree['tags'] : array(),
			Wire_Key::RECIPE_TYPE   => $tree['recipe_type'] ?? null,
			Wire_Key::MISCELLANEOUS => $miscellaneous,
			Wire_Key::SETTINGS      => $this->settings->project(
				$tree,
				$this->misc->created_on_date( $recipe_id ),
				$total_runs
			),
			Wire_Key::FLOW          => $this->deferred->decorate(
				$this->availability->decorate(
					$this->tokens->decorate( $this->flow->serialize( $tree ), $recipe_id )
				),
				$tree
			),
		);
	}
}
