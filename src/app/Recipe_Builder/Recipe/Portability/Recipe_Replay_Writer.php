<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Portability;

use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Recipe_Builder\Field\Services\Custom_Item_Name_Field;
use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Block_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Action_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Block_Modifier_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Block_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Recipe_Trigger_Store;
use Uncanny_Automator\App\Recipe_Builder\Action\Action;
use Uncanny_Automator\App\Recipe_Builder\Action\Action_Config;
use Uncanny_Automator\App\Recipe_Builder\Node_Tests\Services\Node_Test_Run_Store;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Value_Objects\Recipe_Id;
use Uncanny_Automator\App\Recipe_Builder\Trigger\Trigger;
use Uncanny_Automator\App\Recipe_Builder\Trigger\Trigger_Config;
use Uncanny_Automator\App\Recipe_Builder\Trigger\Value_Objects\Trigger_Id;
use WP_Error;

/**
 * The replay write seam: persists tree nodes through the STORES, never the
 * authoring CRUD services.
 *
 * Replay (duplicate/import) is persistence of a known shape, not authoring:
 * the data is the source recipe's own. The authoring CRUDs exist to make
 * untrusted human/LLM input safe and pretty — registry validation, field
 * validation, label/sentence regeneration, starter-filter UX — and every one
 * of those behaviors is wrong for replay. In particular, items whose
 * integration is NOT active/installed/connected MUST persist (the builder's
 * Integration_Catalog renders the missing-dependency / license UX from the
 * stored code+integration; the item simply cannot be enabled until resolved).
 *
 * This class is also the single audited home of the replay trust posture for
 * user-supplied import files: post types are capped to the known recipe-item
 * set by construction (each method targets one store), and render-sensitive
 * HTML metas are passed through `wp_kses_post`. Everything else round-trips
 * verbatim — the fail-closed gate is `set_status(true)`.
 */
final class Recipe_Replay_Writer {

	/**
	 * Legacy bookkeeping postmeta that must not round-trip onto copies:
	 * stale version stamps and structural leftovers the stores re-derive.
	 *
	 * @var string[]
	 */
	const LEGACY_BOOKKEEPING_KEYS = array(
		'uap_trigger_version',
		'uap_action_version',
		'integration_name',
		'type',
		'user_type',
		'automator_duplicated_from',
		WP_Block_Store::META_PATH_CODE,
	);

	/**
	 * Field-map keys the replay writer never round-trips onto imported
	 * posts. One place to extend — infrastructure/state keys only.
	 *
	 * @var string[]
	 */
	private const EXCLUDED_FIELD_KEYS = array(
		Node_Test_Run_Store::META_KEY,
		Node_Test_Run_Store::PENDING_META_KEY,
	);

	/**
	 * Persists triggers (single-trigger add/update surface).
	 *
	 * @var Recipe_Trigger_Store
	 */
	private Recipe_Trigger_Store $trigger_store;

	/**
	 * Persists actions (create + update via aggregate id).
	 *
	 * @var Action_Store
	 */
	private Action_Store $action_store;

	/**
	 * Persists unified `uo-block` rows.
	 *
	 * @var Block_Store
	 */
	private Block_Store $block_store;

	/**
	 * Persists `uo-block-modifier` rows (filter conditions).
	 *
	 * @var Block_Modifier_Store
	 */
	private Block_Modifier_Store $modifier_store;

	/**
	 * Lazy-default constructor injection (Rule 9c): tests inject fakes,
	 * production gets the `Database` factory singletons.
	 *
	 * @param Recipe_Trigger_Store|null $trigger_store
	 * @param Action_Store|null         $action_store
	 * @param Block_Store|null          $block_store
	 * @param Block_Modifier_Store|null $modifier_store
	 */
	public function __construct(
		?Recipe_Trigger_Store $trigger_store = null,
		?Action_Store $action_store = null,
		?Block_Store $block_store = null,
		?Block_Modifier_Store $modifier_store = null
	) {
		$this->trigger_store  = $trigger_store ?? Database::get_recipe_trigger_store();
		$this->action_store   = $action_store ?? Database::get_action_store();
		$this->block_store    = $block_store ?? Database::get_block_store();
		$this->modifier_store = $modifier_store ?? Database::get_block_modifier_store();
	}

	/**
	 * Persist a `trigger` node. Registry-free: code/integration/sentence come
	 * from the node itself, so triggers for inactive/uninstalled integrations
	 * persist and render the Catalog's missing-dependency UX.
	 *
	 * @param int                 $recipe_id   Target recipe.
	 * @param string              $recipe_type Recipe user type ('user'/'anonymous') — the trigger inherits it.
	 * @param array<string,mixed> $node        Tree node.
	 * @return int|WP_Error New trigger id, or the failure.
	 */
	public function create_trigger( int $recipe_id, string $recipe_type, array $node ) {
		try {
			$trigger = new Trigger( $this->trigger_config( null, $recipe_id, $recipe_type, $node, $this->item_fields( $node ) ) );
			$saved   = $this->trigger_store->add_trigger_to_recipe( new Recipe_Id( $recipe_id ), $trigger );

			return (int) $saved->get_trigger_id()->get_value();
		} catch ( \Throwable $e ) {
			return new WP_Error( 'replay_trigger_failed', esc_html( $e->getMessage() ) );
		}
	}

	/**
	 * Pass-2 update: re-persist a trigger with rewritten configuration.
	 *
	 * @param int                 $trigger_id  Trigger to update.
	 * @param int                 $recipe_id   Owning recipe.
	 * @param string              $recipe_type Recipe user type.
	 * @param array<string,mixed> $node        Original tree node (sentence etc.).
	 * @param array<string,mixed> $fields      Rewritten field bag.
	 * @return WP_Error|null Error to abort, or null on success.
	 */
	public function update_trigger_fields( int $trigger_id, int $recipe_id, string $recipe_type, array $node, array $fields ) {
		try {
			$trigger = new Trigger( $this->trigger_config( $trigger_id, $recipe_id, $recipe_type, $node, $fields ) );
			$this->trigger_store->update_recipe_trigger(
				new Recipe_Id( $recipe_id ),
				new Trigger_Id( $trigger_id ),
				$trigger
			);

			return null;
		} catch ( \Throwable $e ) {
			return new WP_Error( 'replay_trigger_update_failed', esc_html( $e->getMessage() ) );
		}
	}

	/**
	 * Persist an `action` node under a recipe/loop/block parent.
	 *
	 * @param int                 $recipe_id Target recipe.
	 * @param int                 $parent_id Direct parent post (recipe, loop, or block).
	 * @param array<string,mixed> $node      Tree node.
	 * @return int|WP_Error New action id, or the failure.
	 */
	public function create_action( int $recipe_id, int $parent_id, array $node, ?string $path_code = null ) {
		try {
			$action = new Action( $this->action_config( null, $recipe_id, $parent_id, $node, $this->item_fields( $node ) ) );
			$saved  = $this->action_store->save( $action );
			$new_id = (int) $saved->get_action_id()->get_value();

			// Block children carry their path bucket as structural meta.
			if ( null !== $path_code && '' !== $path_code ) {
				$this->block_store->set_path_code( $new_id, $path_code );
			}

			return $new_id;
		} catch ( \Throwable $e ) {
			return new WP_Error( 'replay_action_failed', esc_html( $e->getMessage() ) );
		}
	}

	/**
	 * Pass-2 update: re-persist an action with rewritten fields.
	 *
	 * @param int                 $action_id Action to update.
	 * @param int                 $recipe_id Owning recipe.
	 * @param array<string,mixed> $node      Original tree node.
	 * @param array<string,mixed> $fields    Rewritten field bag.
	 * @return WP_Error|null Error to abort, or null on success.
	 */
	public function update_action_fields( int $action_id, int $recipe_id, array $node, array $fields ) {
		try {
			$post   = get_post( $action_id );
			$parent = $post ? (int) $post->post_parent : $recipe_id;
			$action = new Action( $this->action_config( $action_id, $recipe_id, $parent, $node, $fields ) );
			$this->action_store->save( $action );

			return null;
		} catch ( \Throwable $e ) {
			return new WP_Error( 'replay_action_update_failed', esc_html( $e->getMessage() ) );
		}
	}

	/**
	 * Persist a unified `block` node (filter/delay/…).
	 *
	 * @param int                 $recipe_id Target recipe.
	 * @param int                 $parent_id Direct parent (recipe or enclosing block).
	 * @param array<string,mixed> $node      Tree node.
	 * @return int|WP_Error New block id, or the failure.
	 */
	public function create_block( int $recipe_id, int $parent_id, array $node ) {
		try {
			return $this->block_store->create(
				$parent_id,
				(string) ( $node['block_type'] ?? '' ),
				$this->sanitize_fields( is_array( $node['fields'] ?? null ) ? $node['fields'] : array() ),
				(int) ( $node['menu_order'] ?? 0 ),
				$recipe_id
			);
		} catch ( \Throwable $e ) {
			return new WP_Error( 'replay_block_failed', esc_html( $e->getMessage() ) );
		}
	}

	/**
	 * Pass-2 update: write rewritten fields onto a block.
	 *
	 * @param int                 $block_id Block to update.
	 * @param array<string,mixed> $fields   Rewritten field bag.
	 * @return WP_Error|null Error to abort, or null on success.
	 */
	public function update_block_fields( int $block_id, array $fields ) {
		try {
			$this->block_store->update( $block_id, array( 'fields' => $this->sanitize_fields( $fields ) ) );

			return null;
		} catch ( \Throwable $e ) {
			return new WP_Error( 'replay_block_update_failed', esc_html( $e->getMessage() ) );
		}
	}

	/**
	 * Persist a `filter_condition` modifier node under a block.
	 *
	 * @param int                 $recipe_id       Target recipe.
	 * @param int                 $parent_block_id Owning block.
	 * @param array<string,mixed> $node            Modifier node.
	 * @return int|WP_Error New modifier id, or the failure.
	 */
	public function create_block_modifier( int $recipe_id, int $parent_block_id, array $node ) {
		try {
			return $this->modifier_store->create(
				$recipe_id,
				$parent_block_id,
				(string) ( $node['block_modifier_type'] ?? '' ),
				(string) ( $node['integration_code'] ?? '' ),
				(string) ( $node['code'] ?? '' ),
				$this->sanitize_fields( is_array( $node['fields'] ?? null ) ? $node['fields'] : array() ),
				is_array( $node['backup'] ?? null ) ? $node['backup'] : array(),
				(int) ( $node['menu_order'] ?? 0 )
			);
		} catch ( \Throwable $e ) {
			return new WP_Error( 'replay_modifier_failed', esc_html( $e->getMessage() ) );
		}
	}

	/**
	 * Pass-2 update: write rewritten fields onto a block modifier.
	 *
	 * @param int                 $modifier_id Modifier to update.
	 * @param array<string,mixed> $fields      Rewritten field bag.
	 * @return WP_Error|null Error to abort, or null on success.
	 */
	public function update_block_modifier_fields( int $modifier_id, array $fields ) {
		try {
			$this->modifier_store->update( $modifier_id, array( 'fields' => $this->sanitize_fields( $fields ) ) );

			return null;
		} catch ( \Throwable $e ) {
			return new WP_Error( 'replay_modifier_update_failed', esc_html( $e->getMessage() ) );
		}
	}

	/**
	 * Build a trigger aggregate config from a tree node, registry-free.
	 * Fallbacks for unregistered codes: meta_code falls back to the code for
	 * the aggregate's invariant, the sentence falls back to the code so the
	 * copy has a title, and the hook falls back to the node's carried
	 * `add_action` or a code-derived placeholder. A trigger that lands on the placeholder is forced to
	 * `draft` — the placeholder is only inert while it stays out of
	 * Trigger_Query's live map, and that is now enforced rather than assumed.
	 *
	 * @param int|null            $id          Existing id (update) or null (create).
	 * @param int                 $recipe_id   Owning recipe.
	 * @param string              $recipe_type Recipe user type the trigger inherits.
	 * @param array<string,mixed> $node        Tree node.
	 * @param array<string,mixed> $fields      Field bag (original or rewritten).
	 */
	private function trigger_config( ?int $id, int $recipe_id, string $recipe_type, array $node, array $fields ): Trigger_Config {
		$code         = (string) ( $node['code'] ?? '' );
		$presentation = is_array( $node['presentation'] ?? null ) ? $node['presentation'] : array();
		$sentence     = (string) ( $presentation['sentence'] ?? $fields['sentence'] ?? '' );
		$readable     = (string) ( $presentation['sentence_human_readable'] ?? $fields['sentence_human_readable'] ?? '' );
		$hook         = $this->resolve_hook_names( $presentation['hook'] ?? $fields['add_action'] ?? '' );
		// The tree carries the real hook (Trigger_Tree_Entry reads it off the
		// aggregate), so a duplicated trigger keeps the hook it actually listens
		// on. The strtolower($code) placeholder below is a last resort for a
		// legacy dump that carries no add_action at all — without it the
		// Trigger_Hook VO rejects the empty name and the whole import fails.
		//
		// A placeholder row must never go LIVE. Trigger_Query::get_active_triggers()
		// keys its map by trigger CODE with scalar assignment, so one published
		// row carrying a fabricated hook replaces the entry for that code and
		// every recipe using it stops firing site-wide — the WPFSUBFORM outage.
		// Forcing draft keeps the import complete and visible: the author sees a
		// trigger that needs attention, and find_triggers() (published triggers on
		// published recipes) never sees the placeholder at all. Fixing the map is
		// still the durable fix; this stops us feeding it.
		$hook_resolved = array() !== $hook;

		$configuration = $fields;
		unset( $configuration['sentence'], $configuration['sentence_human_readable'], $configuration['add_action'] );

		return ( new Trigger_Config() )
			->id( $id )
			->code( $code )
			->meta_code( $code )
			->integration( (string) ( $node['integration_code'] ?? '' ) )
			->user_type( in_array( $recipe_type, array( 'user', 'anonymous' ), true ) ? $recipe_type : 'user' )
			->recipe_id( $recipe_id )
			->status( ! empty( $node['is_item_on'] ) && $hook_resolved ? 'publish' : 'draft' )
			->sentence( '' !== $sentence ? $sentence : $code )
			->sentence_human_readable( '' !== $readable ? $readable : ( '' !== $sentence ? $sentence : $code ) )
			->hook( array( 'name' => $hook_resolved ? $hook : strtolower( $code ) ) )
			->tokens( array() )
			->configuration( $configuration );
	}

	/**
	 * Normalise a carried hook to a list of hook names.
	 *
	 * A multi-hook trigger carries `add_action` as an array of names; casting it
	 * to a string stored the literal "Array". A single hook comes back as a one
	 * item list, and an empty or missing hook as an empty list.
	 *
	 * @since 8.0.0
	 *
	 * @param mixed $raw The carried hook: a name, a list of names, or empty.
	 *
	 * @return string[] Non-empty hook names.
	 */
	private function resolve_hook_names( $raw ): array {
		$names = is_array( $raw ) ? $raw : array( $raw );
		$names = array_filter( array_map( 'strval', array_filter( $names, 'is_scalar' ) ), 'strlen' );

		return array_values( $names );
	}

	/**
	 * Build an action aggregate config from a tree node, registry-free.
	 *
	 * @param int|null            $id        Existing id (update) or null (create).
	 * @param int                 $recipe_id Owning recipe.
	 * @param int                 $parent_id Direct parent post.
	 * @param array<string,mixed> $node      Tree node.
	 * @param array<string,mixed> $fields    Field bag (original or rewritten).
	 */
	private function action_config( ?int $id, int $recipe_id, int $parent_id, array $node, array $fields ): Action_Config {
		$code = (string) ( $node['code'] ?? '' );
		$meta = $fields;

		$custom_name = (string) ( $node['custom_name'] ?? '' );
		if ( '' !== $custom_name ) {
			$meta[ Custom_Item_Name_Field::FIELD_CODE ] = $custom_name;
		}

		// Registry-owned presentation the field bag excludes. Without it the copy
		// stores its own code as the sentence and the admin list renders
		// "Action: WRITE_DATA_TO_LOG" instead of the readable sentence.
		foreach ( (array) ( $node['presentation'] ?? array() ) as $key => $value ) {
			if ( '' !== (string) $value && ! isset( $meta[ $key ] ) ) {
				$meta[ $key ] = $value;
			}
		}

		return ( new Action_Config() )
			->id( $id )
			->recipe_id( $recipe_id )
			->parent_id( new Recipe_Id( $parent_id ) )
			->integration_code( (string) ( $node['integration_code'] ?? '' ) )
			->code( $code )
			->meta_code( $code )
			->user_type( 'user' )
			->status( ! empty( $node['is_item_on'] ) ? 'publish' : 'draft' )
			->meta( $meta );
	}

	/**
	 * Item field bag: node fields with legacy bookkeeping stripped and
	 * render-sensitive HTML metas sanitized. Everything else verbatim —
	 * skeleton/partial/unknown keys are the source's own data. Public so the
	 * materializer records the exact persisted bag for its Pass-2 rewrite.
	 *
	 * @param array<string,mixed> $node Tree node.
	 * @return array<string,mixed>
	 */
	public function item_fields( array $node ): array {
		$fields = is_array( $node['fields'] ?? null ) ? $node['fields'] : array();

		foreach ( self::LEGACY_BOOKKEEPING_KEYS as $key ) {
			unset( $fields[ $key ] );
		}

		return $this->sanitize_fields( $fields );
	}

	/**
	 * Pass render-sensitive HTML values through `wp_kses_post`, and strip
	 * every {@see self::EXCLUDED_FIELD_KEYS} entry. Import files are
	 * user-supplied; *_html metas are echoed in admin UIs, and a crafted
	 * document could otherwise smuggle Node_Test_Run_Store's run-state keys
	 * straight into an item/block/modifier's field bag — a replayed node is
	 * untested by definition, so those keys must never survive the trip
	 * regardless of what the document claims. This is the single choke point
	 * every entity kind's field bag passes through before persist (item,
	 * block, and block-modifier writers all route their field bag here).
	 *
	 * @param array<string,mixed> $fields Field bag.
	 * @return array<string,mixed>
	 */
	private function sanitize_fields( array $fields ): array {
		foreach ( self::EXCLUDED_FIELD_KEYS as $key ) {
			unset( $fields[ $key ] );
		}

		foreach ( $fields as $key => $value ) {
			if ( is_string( $value ) && is_string( $key ) && substr( $key, -5 ) === '_html' ) {
				$fields[ $key ] = wp_kses_post( $value );
			}
		}

		return $fields;
	}
}
