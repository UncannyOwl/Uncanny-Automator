<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Infrastructure\Database\Stores;

use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\App\Events\Recipe_Change_Record;
use Uncanny_Automator\App\Infrastructure\Exceptions\Store_Exception;
use Uncanny_Automator\App\Recipe_Builder\Field\Services\Item_Field_Filter;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Value_Objects\Recipe_Id;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Value_Objects\Recipe_Triggers;
use Uncanny_Automator\Json_String_Repair;
use Uncanny_Automator\App\Recipe_Builder\Trigger\Trigger;
use Uncanny_Automator\App\Recipe_Builder\Trigger\Trigger_Config;
use Uncanny_Automator\App\Recipe_Builder\Trigger\Value_Objects\Trigger_Id;
use Uncanny_Automator\App\Recipe_Builder\Trigger\Enums\Trigger_Status;
use Uncanny_Automator\App\Recipe_Builder\Trigger\Services\Utilities\Trigger_Hook_Resolver;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Recipe_Trigger_Store;
use Uncanny_Automator\App\Infrastructure\Cache\Recipe_Cache;
use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Integration_Item_Types;
use Uncanny_Automator\App\Recipe_Builder\Field\Services\Declared_Field_Codes_Resolver;
use Uncanny_Automator\App\Recipe_Builder\Field\Services\Custom_Item_Name_Field;
use Uncanny_Automator\App\Infrastructure\Database\Stores\Traits\Item_Usage_Counts;

/**
 * WordPress Recipe Trigger Store.
 *
 * WordPress implementation of trigger persistence using post types and meta.
 * Senior WordPress developers will recognize the patterns: custom post types + meta.
 *
 * @since 7.0.0
 */
class WP_Recipe_Trigger_Store extends WP_Post_Store implements Recipe_Trigger_Store {

	use Item_Usage_Counts;

	/**
	 * Post type for triggers.
	 */
	const POST_TYPE = AUTOMATOR_POST_TYPE_TRIGGER;

	/**
	 * WordPress database object.
	 *
	 * @var \wpdb
	 */
	private \wpdb $db;

	// WordPress post meta keys - following existing naming conventions
	const META_TRIGGER_LOGIC                = 'automator_trigger_logic';
	const META_TRIGGER_CODE                 = 'code';
	const META_TRIGGER_TYPE                 = 'user_type';
	const META_INTEGRATION                  = 'integration';
	const META_INTEGRATION_NAME             = 'integration_name';
	const META_SENTENCE                     = 'sentence';
	const META_SENTENCE_HUMAN_READABLE      = 'sentence_human_readable';
	const META_SENTENCE_HUMAN_READABLE_HTML = 'sentence_human_readable_html';
	const META_ADD_ACTION                   = 'add_action';
	const META_TYPE                         = 'type';

	// Default hook configuration
	const DEFAULT_HOOK_PRIORITY   = 10;
	const DEFAULT_HOOK_ARGS_COUNT = 1;

	/**
	 * The Pro run-now dispatch hook. Loopable triggers (CSV/JSON/XML/RSS
	 * "Import a file") declare run-now capability by registering this hook as
	 * their add_action — that postmeta is the registration-derived truth this
	 * store reads. Writers of the contract: Pro's Loopable_Trigger base (the
	 * single Pro-side declaration site) and Pro's Recipe_State_Flags, which
	 * reports has_run_now for the same recipes on the wire.
	 */
	const RUN_NOW_TRIGGER_HOOK = 'automator_pro_run_now_recipe';

	// Configuration keys that should NOT be uppercased (modern fields)
	// Define in lowercase - compared against incoming config keys
	const PRESERVE_CASE_KEYS = array(
		Custom_Item_Name_Field::FIELD_CODE,
		'added_by_llm',
		'product_rating', // Woo's review-rating trigger (WCPRODREVIEWRATING) declares it lowercase.
	);

	/**
	 * Suffixes of the companion keys stored beside a field as `{CODE}{suffix}`.
	 * The suffix stays lowercase after the uppercased code; every reader looks it up that way.
	 *
	 * @since 8.0.0
	 *
	 * @var string[]
	 */
	const COMPANION_SUFFIXES = array( '_readable', '_custom' );

	// Standard system meta keys (not user configuration)
	const SYSTEM_META_KEYS = array(
		self::META_TRIGGER_CODE,
		self::META_TRIGGER_TYPE,
		self::META_INTEGRATION,
		self::META_INTEGRATION_NAME,
		self::META_ADD_ACTION,
		self::META_SENTENCE,
		self::META_SENTENCE_HUMAN_READABLE,
		self::META_TYPE,
	);

	/**
	 * Constructor.
	 *
	 * @param \wpdb|null $db WordPress database abstraction.
	 */
	public function __construct( ?\wpdb $db = null ) {
		global $wpdb;
		$this->db = is_null( $db ) ? $wpdb : $db;
	}

	/**
	 * Save all triggers for a recipe.
	 *
	 * Note: This method is currently unused but kept for interface compliance.
	 * Individual trigger operations (add/update/remove) are preferred.
	 *
	 * @param Recipe_Id       $recipe_id Recipe ID.
	 * @param Recipe_Triggers $triggers Triggers collection.
	 * @return Recipe_Triggers The saved triggers collection with IDs and all persisted values.
	 */
	public function save_recipe_triggers( Recipe_Id $recipe_id, Recipe_Triggers $triggers ): Recipe_Triggers {

		$recipe_id = $recipe_id->get_value();

		// Save new triggers.
		foreach ( $triggers->get_triggers() as $trigger ) {
			$this->save_single_trigger( $recipe_id, $trigger );
		}

		// Save trigger logic
		if ( $triggers->get_logic() ) {
			update_post_meta(
				$recipe_id,
				self::META_TRIGGER_LOGIC,
				$triggers->get_logic()->get_value()
			);
		} else {
			delete_post_meta( $recipe_id, self::META_TRIGGER_LOGIC );
		}

		Recipe_Cache::invalidate( $recipe_id );

		// Reload and return the saved triggers collection
		return $this->get_recipe_triggers( new Recipe_Id( $recipe_id ) );
	}

	/**
	 * @inheritDoc
	 */
	public function get_recipe_trigger_posts( int $recipe_id ): array {
		return get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_parent'    => $recipe_id,
				'post_status'    => array( Trigger_Status::PUBLISH, Trigger_Status::DRAFT ),
				'posts_per_page' => -1, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Top-level triggers per recipe are bounded by the builder UI; no realistic cap needed.
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
			)
		);
	}
	/**
	 * Every trigger belonging to a recipe. Returns an empty collection when the id is not a recipe.
	 *
	 * @param Recipe_Id $recipe_id The ID.
	 * @return Recipe_Triggers
	 */
	public function get_recipe_triggers( Recipe_Id $recipe_id ): Recipe_Triggers {

		$recipe_id_value = $recipe_id->get_value();
		$recipe_post     = get_post( $recipe_id_value );

		if ( ! $recipe_post || AUTOMATOR_POST_TYPE_RECIPE !== $recipe_post->post_type ) {
			return new Recipe_Triggers( array(), 'user' );
		}

		// Get recipe type for proper collection creation
		$recipe_type = get_post_meta( $recipe_id_value, WP_Recipe_Store::_META_RECIPE_TYPE, true );
		$recipe_type = ! empty( $recipe_type ) ? $recipe_type : 'user';

		// Get trigger posts
		$trigger_posts = $this->get_recipe_trigger_posts( $recipe_id_value );

		$triggers = array();
		foreach ( $trigger_posts as $trigger_post ) {
			$trigger = $this->hydrate_trigger_from_post( $trigger_post );
			if ( $trigger ) {
				$triggers[] = $trigger;
			}
		}

		// Get trigger logic
		$logic = get_post_meta( $recipe_id_value, self::META_TRIGGER_LOGIC, true );
		$logic = ! empty( $logic ) ? $logic : null;

		return new Recipe_Triggers( $triggers, $recipe_type, $logic );
	}

	/**
	 * Add single trigger to recipe.
	 *
	 * @param Recipe_Id $recipe_id Recipe ID.
	 * @param Trigger   $trigger Trigger to add.
	 * @return Trigger The saved Trigger with generated ID and all persisted values.
	 * @throws \RuntimeException If trigger cannot be reloaded after save.
	 */
	public function add_trigger_to_recipe( Recipe_Id $recipe_id, Trigger $trigger ): Trigger {

		$trigger_id = $this->save_single_trigger( $recipe_id->get_value(), $trigger );

		// Reload and return the persisted trigger
		$trigger_post  = get_post( $trigger_id );
		$saved_trigger = $this->hydrate_trigger_from_post( $trigger_post );

		if ( null === $saved_trigger ) {
			// translators: %s is the trigger ID.
			throw new \RuntimeException( sprintf( esc_html_x( 'Failed to reload trigger after creation: %s', 'Trigger store reload error', 'uncanny-automator' ), absint( $trigger_id ) ) );
		}

		return $saved_trigger;
	}

	/**
	 * Update single trigger in recipe.
	 *
	 * @param Recipe_Id  $recipe_id Recipe ID.
	 * @param Trigger_Id $trigger_id Trigger ID.
	 * @param Trigger    $trigger Updated trigger.
	 * @return Trigger The updated Trigger with all persisted values.
	 * @throws \Exception If update fails or trigger cannot be reloaded.
	 */
	public function update_recipe_trigger( Recipe_Id $recipe_id, Trigger_Id $trigger_id, Trigger $trigger ): Trigger {
		$trigger_id_value = $trigger_id->get_value();
		$trigger_data     = $this->prepare_trigger_for_storage( $trigger );

		$post_update = array( 'ID' => $trigger_id_value );

		// With no sentence anywhere there is nothing to title the trigger from, so it keeps the title it has.
		if ( '' !== $trigger_data['title'] ) {
			$post_update['post_title'] = $trigger_data['title'];
		}

		// Update post. Request WordPress errors: a refused update otherwise answers 0 and passes as saved.
		$result = wp_update_post( $post_update, true );

		if ( is_wp_error( $result ) ) {
			// translators: %s is the error message.
			throw new Store_Exception( sprintf( esc_html_x( 'Failed to update trigger: %s', 'Trigger store update error with message', 'uncanny-automator' ), esc_html( $result->get_error_message() ) ) );
		}

		// Update meta
		$candidate_edits = array();
		$written         = array();
		$stored          = get_post_meta( $trigger_id_value );
		foreach ( $this->without_raw_structural_artifacts( $trigger_data['meta'] ) as $meta_key => $meta_value ) {
			// A value the save left as it was read goes unwritten: it was read still serialized
			// (an array, an object), and writing it back would serialize it again.
			if ( isset( $stored[ $meta_key ] ) && array( $meta_value ) === $stored[ $meta_key ] ) {
				continue;
			}

			// A key's first materialization is configuration, not an edit —
			// only a key that already had a stored value can be a candidate
			// (spec Stage 3, AMENDED 2026-08-12).
			$existed = metadata_exists( 'post', $trigger_id_value, $meta_key );
			$changed = update_post_meta( $trigger_id_value, $meta_key, $meta_value );
			if ( false !== $changed ) {
				$written[] = $meta_key;
			}
			if ( $existed && false !== $changed ) {
				$candidate_edits[] = $meta_key;
			}
		}

		Recipe_Change_Record::note_item_fields(
			Integration_Item_Types::TRIGGER,
			(int) $trigger_id_value,
			(int) $recipe_id->get_value(),
			$trigger->get_integration()->get_value(),
			$trigger->get_trigger_code()->get_value(),
			$written
		);

		// Declared codes resolve lazily — only a save that actually edited
		// stored values pays the definition lookup.
		if ( ! empty( $candidate_edits )
			&& array_intersect( $candidate_edits, $this->declared_field_codes( $trigger, $trigger_data['meta'] ) ) ) {
			Dispatcher::action( 'automator_node_fields_changed', 'item', $trigger_id_value );
		}

		// The field meta above lands AFTER wp_update_post, so the hook's
		// invalidation ran against the pre-edit values. See
		// Store_Write_Ordering_Test.
		Recipe_Cache::invalidate( $recipe_id->get_value() );

		// Reload and return the updated trigger
		$trigger_post = get_post( $trigger_id_value );

		$updated_trigger = $this->hydrate_trigger_from_post( $trigger_post );

		if ( null === $updated_trigger ) {
			// translators: %s is the trigger ID.
			throw new Store_Exception( sprintf( esc_html_x( 'Failed to reload trigger after update: %s', 'Trigger store reload error', 'uncanny-automator' ), absint( $trigger_id_value ) ) );
		}

		return $updated_trigger;
	}

	/**
	 * The trigger's declared field codes — the allow-list write-time
	 * invalidation compares candidate edits against (spec Stage 3, AMENDED
	 * 2026-08-12). The aggregate carries the identity, so callers never
	 * supply this; resolution is memoized per request by the registry and
	 * only ever invoked when a save produced candidate edits.
	 *
	 * @param Trigger              $trigger       Trigger being saved.
	 * @param array<string, mixed> $incoming_meta Meta map being written — degraded fallback source.
	 * @return array<string>
	 */
	private function declared_field_codes( Trigger $trigger, array $incoming_meta ): array {
		return ( new Declared_Field_Codes_Resolver() )->resolve(
			$trigger->get_integration()->get_value(),
			Integration_Item_Types::TRIGGER,
			$trigger->get_trigger_code()->get_value(),
			$incoming_meta
		);
	}

	/**
	 * Remove trigger from recipe.
	 *
	 * Deletes the trigger only when it is one of the recipe's triggers.
	 *
	 * @since 7.0.0
	 * @since 8.0.0 Throws when the trigger is not one of the recipe's, instead of deleting another recipe's trigger or doing nothing.
	 *
	 * @param Recipe_Id  $recipe_id Recipe ID.
	 * @param Trigger_Id $trigger_id Trigger ID.
	 *
	 * @throws Store_Exception When the trigger is not one of the recipe's triggers, or WordPress does not delete it.
	 */
	public function remove_trigger_from_recipe( Recipe_Id $recipe_id, Trigger_Id $trigger_id ): void {
		$trigger_id_value = (int) $trigger_id->get_value();
		$recipe_id_value  = (int) $recipe_id->get_value();
		$trigger_post     = $this->get_wp_post( $trigger_id_value );

		if ( null === $trigger_post || $recipe_id_value !== (int) $trigger_post->post_parent ) {
			throw new Store_Exception(
				sprintf(
					/* translators: %1$d is the trigger ID, %2$d is the recipe ID. */
					esc_html_x( 'Trigger %1$d is not a trigger of recipe %2$d.', 'Trigger store delete error', 'uncanny-automator' ),
					absint( $trigger_id_value ),
					absint( $recipe_id_value )
				)
			);
		}

		if ( ! wp_delete_post( $trigger_id_value, true ) ) {
			throw new Store_Exception(
				sprintf(
					/* translators: %d is the trigger ID. */
					esc_html_x( 'Failed to delete trigger: %d', 'Trigger store delete error', 'uncanny-automator' ),
					absint( $trigger_id_value )
				)
			);
		}
	}

	/**
	 * Set trigger logic for recipe.
	 *
	 * @param Recipe_Id $recipe_id Recipe ID.
	 * @param string    $logic Trigger logic.
	 */
	public function set_recipe_trigger_logic( Recipe_Id $recipe_id, string $logic ): void {
		update_post_meta( $recipe_id->get_value(), self::META_TRIGGER_LOGIC, $logic );

		Recipe_Cache::invalidate( $recipe_id->get_value() );
	}

	/**
	 * Get trigger logic for recipe.
	 *
	 * @param Recipe_Id $recipe_id Recipe ID.
	 * @return string|null Trigger logic or null.
	 */
	public function get_recipe_trigger_logic( Recipe_Id $recipe_id ): ?string {
		$logic = get_post_meta( $recipe_id->get_value(), self::META_TRIGGER_LOGIC, true );
		return ! empty( $logic ) ? $logic : null;
	}

	/**
	 * Check if recipe has triggers.
	 *
	 * @param Recipe_Id $recipe_id Recipe ID.
	 * @return bool True if recipe has triggers.
	 */
	public function recipe_has_triggers( Recipe_Id $recipe_id ): bool {
		return $this->count_recipe_triggers( $recipe_id ) > 0;
	}

	/**
	 * Whether the recipe has at least one live (published) trigger.
	 *
	 * @param Recipe_Id $recipe_id Recipe ID.
	 * @return bool True when a published trigger exists.
	 */
	public function recipe_has_live_triggers( Recipe_Id $recipe_id ): bool {
		$count = (int) $this->db->get_var(
			$this->db->prepare(
				"SELECT COUNT(*)
				FROM {$this->db->posts}
				WHERE post_type = %s
				AND post_parent = %d
				AND post_status = %s",
				self::POST_TYPE,
				$recipe_id->get_value(),
				'publish'
			)
		);

		return $count > 0;
	}

	/**
	 * Count triggers for recipe.
	 *
	 * @param Recipe_Id $recipe_id Recipe ID.
	 * @return int Number of triggers.
	 */
	public function count_recipe_triggers( Recipe_Id $recipe_id ): int {
		return (int) $this->db->get_var(
			$this->db->prepare(
				"SELECT COUNT(*)
				FROM {$this->db->posts}
				WHERE post_type = %s
				AND post_parent = %d
				AND post_status IN (%s, %s)",
				self::POST_TYPE,
				$recipe_id->get_value(),
				Trigger_Status::PUBLISH,
				Trigger_Status::DRAFT
			)
		);
	}

	/**
	 * Check if recipe has a manual trigger.
	 *
	 * @since 7.0.0
	 * @param Recipe_Id $recipe_id Recipe ID.
	 * @return bool True if recipe has a manual trigger.
	 */
	public function recipe_has_manual_trigger( Recipe_Id $recipe_id ): bool {
		// Presence check delegates to the id lookup — same query, single source.
		return null !== $this->get_manual_trigger_id( $recipe_id );
	}

	/**
	 * Whether the recipe has a published manual trigger supported by run receipts.
	 *
	 * @param Recipe_Id $recipe_id Recipe ID.
	 * @return bool
	 */
	public function recipe_has_live_manual_trigger( Recipe_Id $recipe_id ): bool {
		return null !== $this->db->get_var(
			$this->db->prepare(
				"SELECT p.ID
				FROM {$this->db->posts} p
				INNER JOIN {$this->db->postmeta} pm ON p.ID = pm.post_id
				WHERE p.post_parent = %d
				AND p.post_type = %s
				AND p.post_status = %s
				AND pm.meta_key = %s
				AND pm.meta_value = %s
				LIMIT 1",
				$recipe_id->get_value(),
				self::POST_TYPE,
				Trigger_Status::PUBLISH,
				self::META_TRIGGER_CODE,
				'RECIPE_MANUAL_TRIGGER_ANON'
			)
		);
	}

	/**
	 * The post ID of the recipe's manual (run-now) trigger, or null when none.
	 *
	 * @param Recipe_Id $recipe_id Recipe ID.
	 * @return int|null
	 */
	public function get_manual_trigger_id( Recipe_Id $recipe_id ): ?int {
		$manual_id = $this->get_trigger_id_by_code( $recipe_id, 'RECIPE_MANUAL_TRIGGER_ANON' );
		if ( null !== $manual_id ) {
			return $manual_id;
		}

		// Run-now-style triggers are manual triggers for the route guard and
		// the publish-first promotion — see RUN_NOW_TRIGGER_HOOK.
		$query = $this->db->prepare(
			"SELECT p.ID
			FROM {$this->db->posts} p
			INNER JOIN {$this->db->postmeta} pm ON p.ID = pm.post_id
			WHERE p.post_parent = %d
			AND p.post_type = %s
			AND pm.meta_key = 'add_action'
			AND pm.meta_value = %s
			LIMIT 1",
			$recipe_id->get_value(),
			self::POST_TYPE,
			self::RUN_NOW_TRIGGER_HOOK
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above with $wpdb->prepare()
		$id = $this->db->get_var( $query );

		return null !== $id ? (int) $id : null;
	}

	/**
	 * The post ID of the first trigger in a recipe matching a trigger code, or
	 * null when the recipe has no such trigger.
	 *
	 * The generic "find a trigger by code in a recipe" primitive — domain
	 * lookups like {@see self::get_manual_trigger_id()} are thin wrappers, and
	 * the public helpers `automator_get_recipe_trigger_id()` /
	 * `automator_recipe_has_trigger()` expose it to external callers (Pro).
	 *
	 * @param Recipe_Id $recipe_id    Recipe ID.
	 * @param string    $trigger_code Trigger code to match.
	 * @return int|null
	 */
	public function get_trigger_id_by_code( Recipe_Id $recipe_id, string $trigger_code ): ?int {

		$query = $this->db->prepare(
			"SELECT p.ID
			FROM {$this->db->posts} p
			INNER JOIN {$this->db->postmeta} pm ON p.ID = pm.post_id
			WHERE p.post_parent = %d
			AND p.post_type = %s
			AND pm.meta_key = %s
			AND pm.meta_value = %s
			LIMIT 1",
			$recipe_id->get_value(),
			self::POST_TYPE,
			self::META_TRIGGER_CODE,
			$trigger_code
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above with $wpdb->prepare()
		$id = $this->db->get_var( $query );

		return null !== $id ? (int) $id : null;
	}

	/**
	 * Save single trigger as WordPress post.
	 *
	 * @param int     $recipe_id Recipe ID.
	 * @param Trigger $trigger Trigger to save.
	 * @return int Trigger post ID.
	 * @throws \RuntimeException On save failure.
	 */
	private function save_single_trigger( int $recipe_id, Trigger $trigger ): int {

		$trigger_data = $this->prepare_trigger_for_storage( $trigger );

		// Determine the trigger status.
		$trigger_status = $trigger->get_status()
			? $trigger->get_status()->get_value()
			: Trigger_Status::DRAFT; // Default to draft if no status is provided.

		// Arrange the post data.
		$post_data = array(
			'post_type'   => self::POST_TYPE,
			'post_parent' => $recipe_id,
			'post_title'  => $trigger_data['title'] ?? '',
			'post_status' => $trigger_status,
			'meta_input'  => $this->without_raw_structural_artifacts( $trigger_data['meta'] ?? array() ),
		);

		$trigger_id = $trigger->get_trigger_id();

		if ( ! $trigger_id->is_null() ) {
			$post_data['ID'] = $trigger_id->get_value();
		}

		// Request WordPress errors: a refused write otherwise answers 0, and get_post( 0 ) reads the global post.
		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			// translators: %s is the error message.
			throw new \RuntimeException( sprintf( esc_html_x( 'Unexpected error saving trigger: %s', 'Trigger store save error with message', 'uncanny-automator' ), esc_html( $post_id->get_error_message() ) ) );
		}

		return $post_id;
	}

	/**
	 * Prepare trigger data for WordPress storage.
	 *
	 * @param Trigger $trigger Trigger to prepare.
	 * @return array Prepared data with title and meta.
	 */
	private function prepare_trigger_for_storage( Trigger $trigger ): array {

		$trigger_data = $trigger->to_array();

		$trigger_code = $trigger_data['trigger_code'];
		$hook_data    = $trigger_data['trigger_hook'];
		$config       = $trigger_data['configuration'];

		$human_readable_sentence      = $trigger_data['sentence_human_readable'] ?? '';
		$human_readable_sentence_html = $trigger_data['sentence_human_readable_html'] ?? '';

		// Build legacy meta structure.
		$meta = array(
			// Basic trigger info
			self::META_TRIGGER_CODE                 => $trigger_code,
			self::META_TRIGGER_TYPE                 => $trigger_data['trigger_type'],
			self::META_INTEGRATION                  => $trigger_data['integration'],
			self::META_ADD_ACTION                   => $this->stored_hooks( (string) $trigger_code, $hook_data['name'] ?? '' ),
			self::META_SENTENCE                     => $trigger_data['sentence'] ?? '',
			self::META_SENTENCE_HUMAN_READABLE      => $human_readable_sentence,
			self::META_SENTENCE_HUMAN_READABLE_HTML => $human_readable_sentence_html,
		);

		// Add dynamic field values (CAPSLOCK fields).
		foreach ( $this->convert_configuration_to_legacy_meta( $config, $trigger_code ) as $meta_key => $meta_value ) {
			$meta[ $meta_key ] = Json_String_Repair::slash_for_storage( $meta_value );
		}

		// Generate title: prefer plain text from HTML (filled-in values), fallback to template.
		$title = $this->generate_trigger_title( $trigger_data, $config );

		return array(
			'title' => $title,
			'meta'  => $meta,
		);
	}

	/**
	 * The value a trigger's `add_action` meta holds: the hooks the trigger's code listens on.
	 *
	 * The engine and the manual-trigger lookups read this meta. The hooks the trigger carries are
	 * stored only for a code this request does not know.
	 *
	 * @since 8.0.4
	 *
	 * @param string $trigger_code Trigger code.
	 * @param mixed  $carried      The hook name or names the trigger carries.
	 *
	 * @return string|string[] One hook name, a list when there are several, '' when there is none.
	 */
	private function stored_hooks( string $trigger_code, $carried ) {

		$hooks = ( new Trigger_Hook_Resolver() )->resolve( $trigger_code, $carried );

		if ( empty( $hooks ) ) {
			return '';
		}

		if ( 1 === count( $hooks ) ) {
			return $hooks[0];
		}

		return $hooks;
	}

	/**
	 * Generate a human-readable title for the trigger.
	 *
	 * Extracts plain text from HTML sentence if available (with filled-in values),
	 * otherwise falls back to the template with tokens stripped.
	 *
	 * @param array $trigger_data Trigger data array.
	 * @param array $config Configuration values.
	 * @return string Human-readable title.
	 */
	private function generate_trigger_title( array $trigger_data, array $config ): string {

		// If we have HTML, extract plain text from it (strips tags, keeps filled-in values).
		$html = $trigger_data['sentence_human_readable_html'] ?? '';

		if ( ! empty( $html ) ) {
			// Strip HTML tags to get plain text with filled-in values.
			$plain = wp_strip_all_tags( $html );
			$plain = html_entity_decode( $plain, ENT_QUOTES, 'UTF-8' );
			$plain = preg_replace( '/\s+/', ' ', trim( $plain ) );

			if ( ! empty( $plain ) ) {
				return $plain;
			}
		}

		// Fallback: use template with tokens simplified.
		$template = $trigger_data['sentence_human_readable'] ?? '';
		$result   = preg_replace( '/{{([^:}]+):[^}]+}}/', '{{$1}}', $template );

		return str_replace( array( '{', '}' ), '', $result );
	}

	/**
	 * Convert clean configuration to legacy meta format.
	 *
	 * Companion keys are matched to their field without case, as meta keys are,
	 * because exports and MCP can send `_CUSTOM` or another spelling of the field
	 * code. They are written under the field's stored key with the suffix lowercased.
	 *
	 * @param array  $config Configuration array.
	 * @param string $trigger_code Trigger code.
	 * @return array Legacy meta fields.
	 */
	private function convert_configuration_to_legacy_meta( array $config, string $trigger_code ): array {
		$legacy_meta = array();
		$fields      = array();
		$companions  = array();

		foreach ( $config as $key => $value ) {
			$suffix = $this->companion_suffix( $key );

			if ( null === $suffix ) {
				$fields[ $key ] = $value;
				continue;
			}

			$companion_key = strtolower( substr( $key, 0, - strlen( $suffix ) ) ) . $suffix;

			// A key already spelled with the lowercase suffix wins over an uppercase copy of it.
			if ( substr( $key, - strlen( $suffix ) ) === $suffix || ! array_key_exists( $companion_key, $companions ) ) {
				$companions[ $companion_key ] = $value;
			}
		}

		foreach ( $fields as $field_key => $field_value ) {
			// Store field value as CAPSLOCK meta key (the legacy way)
			// Exception: preserve case for modern fields that should not be uppercased
			$field_key_lower = strtolower( $field_key );
			if ( in_array( $field_key_lower, self::PRESERVE_CASE_KEYS, true ) ) {
				$legacy_key = $field_key; // Keep original case
			} else {
				$legacy_key = strtoupper( $field_key ); // Legacy CAPSLOCK
			}
			$legacy_meta[ $legacy_key ] = $field_value;

			foreach ( self::COMPANION_SUFFIXES as $suffix ) {
				if ( isset( $companions[ $field_key_lower . $suffix ] ) ) {
					$legacy_meta[ $legacy_key . $suffix ] = $companions[ $field_key_lower . $suffix ];
				}
			}
		}

		return $legacy_meta;
	}

	/**
	 * The companion suffix a configuration key ends with, compared without case.
	 *
	 * @since 8.0.0
	 *
	 * @param string $key Configuration key.
	 * @return string|null The suffix as listed in COMPANION_SUFFIXES, or null for a field key.
	 */
	private function companion_suffix( string $key ): ?string {
		foreach ( self::COMPANION_SUFFIXES as $suffix ) {
			if ( 0 === strcasecmp( substr( $key, - strlen( $suffix ) ), $suffix ) ) {
				return $suffix;
			}
		}

		return null;
	}

	/**
	 * Hydrate trigger from WordPress post.
	 *
	 * Public method to allow Service layer to hydrate individual triggers.
	 * This is part of the Store's public API for trigger retrieval.
	 *
	 * @param \WP_Post $trigger_post Trigger post.
	 * @return Trigger|null Trigger instance or null.
	 */
	public function hydrate_trigger_from_post( \WP_Post $trigger_post ): ?Trigger {

		// Fallback: Parse legacy meta format (the beautiful disaster)
		$legacy_data = $this->parse_trigger_meta( $trigger_post->ID );

		if ( empty( $legacy_data ) ) {
			return null;
		}

		try {
			$config = Trigger_Config::from_array( $legacy_data );
			return new Trigger( $config );
		} catch ( \Exception $e ) {
			// Skip invalid triggers.
			automator_log( sprintf( 'Failed to hydrate trigger from post: %s', $e->getMessage() ), 'WP Recipe Trigger Store Error' );
			return null;
		}
	}

	/**
	 * Parse trigger meta into clean format.
	 *
	 * @since 8.0.0 Public, so a reader can use what a trigger the aggregate refuses stores.
	 *
	 * @param int $trigger_post_id Trigger post ID.
	 * @return array|null Clean trigger data, or null when the post stores no trigger code.
	 */
	public function parse_trigger_meta( int $trigger_post_id ): ?array {
		// Get all meta for this post
		$all_meta = get_post_meta( $trigger_post_id );

		if ( empty( $all_meta ) ) {
			return null;
		}

		// Get the trigger post to access recipe_id (post_parent)
		$trigger_post = get_post( $trigger_post_id );
		if ( ! $trigger_post ) {
			return null;
		}

		// Extract basic trigger info
		// Trimmed as the aggregate trims them, so a reader of this row and of a hydrated trigger see one value.
		$trigger_code = trim( (string) $this->get_meta_value( $all_meta, self::META_TRIGGER_CODE ) );
		$trigger_type = $this->get_meta_value( $all_meta, self::META_TRIGGER_TYPE, 'user' );
		$integration  = trim( (string) $this->get_meta_value( $all_meta, self::META_INTEGRATION ) );
		// `add_action` holds a single hook name OR — for triggers registered via
		// `add_action( array( 'a', 'b' ), ... )` — a serialized list of names.
		// `get_post_meta( $id )` returns the raw (still-serialized) value, so
		// unserialize here to round-trip multi-hook triggers.
		$hook_name                    = automator_safe_unserialize( $this->get_meta_value( $all_meta, self::META_ADD_ACTION, '' ) );
		$sentence                     = trim( (string) $this->get_meta_value( $all_meta, self::META_SENTENCE, '' ) );
		$sentence_human_readable      = trim( (string) $this->get_meta_value( $all_meta, self::META_SENTENCE_HUMAN_READABLE, $sentence ) );
		$sentence_human_readable_html = $this->get_meta_value( $all_meta, self::META_SENTENCE_HUMAN_READABLE_HTML, '' );

		if ( empty( $trigger_code ) ) {
			return null;
		}

		// Build clean trigger data structure
		$clean_data = array(
			'trigger_id'                   => $trigger_post_id,
			'trigger_code'                 => $trigger_code,
			'trigger_type'                 => $trigger_type,
			'integration'                  => '' !== $integration ? $integration : 'WP',
			'sentence'                     => $sentence,
			'sentence_human_readable'      => $sentence_human_readable,
			'sentence_human_readable_html' => $sentence_human_readable_html,
			'recipe_id'                    => $trigger_post->post_parent,
			'trigger_hook'                 => array(
				'name'       => $hook_name,
				'priority'   => self::DEFAULT_HOOK_PRIORITY,
				'args_count' => self::DEFAULT_HOOK_ARGS_COUNT,
			),
			'trigger_tokens'               => array(), // Tokens resolve at runtime, cannot be populated statically
			'status'                       => $trigger_post->post_status,
			'configuration'                => $this->extract_configuration_from_legacy_meta( $all_meta, $trigger_code ),
		);

		return $clean_data;
	}

	/**
	 * Extract configuration from legacy meta.
	 *
	 * @param array  $all_meta All post meta.
	 * @param string $trigger_code Trigger code.
	 * @return array Configuration array.
	 */
	private function extract_configuration_from_legacy_meta( array $all_meta, string $trigger_code ): array {

		$configuration = array();

		// Find CAPSLOCK field values and their readable labels
		foreach ( $all_meta as $meta_key => $meta_value ) {
			// Skip our standard meta keys (system metadata only - not user configuration)
			if ( in_array( $meta_key, self::SYSTEM_META_KEYS, true ) ) {
				continue;
			}

			// Handle CAPSLOCK field values (the legacy mess)
			// Skip _readable keys - they're handled when processing their parent field
			if ( substr( $meta_key, -9 ) === '_readable' ) {
				continue;
			}

			// Match CAPSLOCK field names (letters, numbers, underscores only).
			// Additionally allow keys listed in PRESERVE_CASE_KEYS (e.g.
			// `_automator_custom_item_name_`) — these are case-preserved
			// modern fields the regex doesn't naturally match.
			$is_capslock_field    = (bool) preg_match( '/^[A-Z][A-Z0-9_]*$/', $meta_key );
			$is_preserve_case_key = in_array( $meta_key, self::PRESERVE_CASE_KEYS, true );
			if ( ( $is_capslock_field || $is_preserve_case_key ) && strlen( $meta_key ) > 0 ) {
				$field_name  = $meta_key; // Keep original case (CAPSLOCK or preserved)
				$field_value = is_array( $meta_value ) ? $meta_value[0] : $meta_value;

				if ( Json_String_Repair::looks_like_structured_json( $field_value ) ) {
					$field_value = Json_String_Repair::repair( $field_value );
				}

				$configuration[ $field_name ] = $field_value;

				foreach ( self::COMPANION_SUFFIXES as $suffix ) {
					$companion_key = $meta_key . $suffix;

					if ( ! isset( $all_meta[ $companion_key ] ) ) {
						continue;
					}

					$companion_value = is_array( $all_meta[ $companion_key ] ) ? $all_meta[ $companion_key ][0] : $all_meta[ $companion_key ];

					if ( Json_String_Repair::looks_like_structured_json( $companion_value ) ) {
						$companion_value = Json_String_Repair::repair( $companion_value );
					}

					$configuration[ $companion_key ] = $companion_value;
				}
			}
		}

		return $configuration;
	}

	/**
	 * Drop structural keys whose value is a still-serialized string — raw
	 * hydration artifacts of integration side-effect caches (WEBHOOK_SAMPLE,
	 * captured-token bags): bulk get_post_meta() never unserializes, so
	 * writing the string back serializes it a SECOND time and corrupts the
	 * cache. Clean scalar structural values (a custom-name rename) are real
	 * writes and pass through. Same hazard WP_Action_Store's $core_fields
	 * comment documents.
	 *
	 * @param array $meta Prepared meta map about to be written.
	 * @return array The map minus raw structural artifacts.
	 */
	private function without_raw_structural_artifacts( array $meta ): array {
		$structural_keys = array_keys( array_diff_key( $meta, Item_Field_Filter::omit_structural_keys( $meta ) ) );

		foreach ( $structural_keys as $key ) {
			if ( is_string( $meta[ $key ] ) && is_serialized( $meta[ $key ] ) ) {
				unset( $meta[ $key ] );
			}
		}

		return $meta;
	}

	/**
	 * Get meta value from all meta array.
	 *
	 * @param array  $all_meta All meta array.
	 * @param string $key Meta key.
	 * @param mixed  $default_value Default value.
	 * @return mixed Meta value or default.
	 */
	private function get_meta_value( array $all_meta, string $key, $default_value = '' ) {
		if ( ! isset( $all_meta[ $key ] ) ) {
			return $default_value;
		}

		$value = $all_meta[ $key ];
		return is_array( $value ) ? $value[0] : $value;
	}


	/**
	 * Deprecated one-line accessor retained for 8.0.0.
	 *
	 * Its callers (the recipe-item REST classes) move off it in a later PR;
	 * deleting it here would break them in the interim. Removed in the
	 * post-8.0.0 cleanup pass together with those callers.
	 *
	 * @deprecated 8.0.0
	 */
	public function get_post_type(): string {
		return self::POST_TYPE;
	}
}
