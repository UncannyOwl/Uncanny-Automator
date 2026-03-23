<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Database\Stores;

use Uncanny_Automator\Api\Components\Recipe\Value_Objects\Recipe_Id;
use Uncanny_Automator\Api\Components\Recipe\Value_Objects\Recipe_Triggers;
use Uncanny_Automator\Api\Components\Trigger\Trigger;
use Uncanny_Automator\Api\Components\Trigger\Trigger_Config;
use Uncanny_Automator\Api\Components\Trigger\Value_Objects\Trigger_Id;
use Uncanny_Automator\Api\Components\Trigger\Enums\Trigger_Status;
use Uncanny_Automator\Api\Database\Interfaces\Recipe_Trigger_Store;

/**
 * WordPress Recipe Trigger Store.
 *
 * WordPress implementation of trigger persistence using post types and meta.
 * Senior WordPress developers will recognize the patterns: custom post types + meta.
 *
 * @since 7.0.0
 */
class WP_Recipe_Trigger_Store implements Recipe_Trigger_Store {

	/**
	 * Post type for triggers.
	 */
	const POST_TYPE = 'uo-trigger';

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

	// Configuration keys that should NOT be uppercased (modern fields)
	// Define in lowercase - compared against incoming config keys
	const PRESERVE_CASE_KEYS = array(
		'_automator_custom_item_name_',
		'added_by_llm',
	);

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

		// Reload and return the saved triggers collection
		return $this->get_recipe_triggers( new Recipe_Id( $recipe_id ) );
	}

	/**
	 * Get all triggers for a recipe.
	 *
	 * @param Recipe_Id $recipe_id Recipe ID.
	 * @return Recipe_Triggers Triggers collection.
	 */
	public function get_recipe_triggers( Recipe_Id $recipe_id ): Recipe_Triggers {

		$recipe_id_value = $recipe_id->get_value();
		$recipe_post     = get_post( $recipe_id_value );

		if ( ! $recipe_post || 'uo-recipe' !== $recipe_post->post_type ) {
			return new Recipe_Triggers( array(), 'user' );
		}

		// Get recipe type for proper collection creation
		$recipe_type = get_post_meta( $recipe_id_value, WP_Recipe_Store::_META_RECIPE_TYPE, true );
		$recipe_type = ! empty( $recipe_type ) ? $recipe_type : 'user';

		// Get trigger posts
		$trigger_posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_parent'    => $recipe_id_value,
				'post_status'    => array( Trigger_Status::PUBLISH, Trigger_Status::DRAFT ),
				'posts_per_page' => -1,
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
			)
		);

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
	 * Get WP_Post object for trigger.
	 *
	 * @param int $trigger_id Trigger ID.
	 * @return \WP_Post|null WP_Post object or null if not found.
	 */
	public function get_wp_post( int $trigger_id ): ?\WP_Post {
		$post = get_post( $trigger_id );

		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}

		return $post;
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

		// Update post
		$result = wp_update_post(
			array(
				'ID'         => $trigger_id_value,
				'post_title' => $trigger_data['title'],
			)
		);

		if ( is_wp_error( $result ) ) {
			// translators: %s is the error message.
			throw new \Exception( sprintf( esc_html_x( 'Failed to update trigger: %s', 'Trigger store update error with message', 'uncanny-automator' ), esc_html( $result->get_error_message() ) ) );
		}

		// Update meta
		foreach ( $trigger_data['meta'] as $meta_key => $meta_value ) {
			update_post_meta( $trigger_id_value, $meta_key, $meta_value );
		}

		// Reload and return the updated trigger
		$trigger_post    = get_post( $trigger_id_value );
		$updated_trigger = $this->hydrate_trigger_from_post( $trigger_post );

		if ( null === $updated_trigger ) {
			// translators: %s is the trigger ID.
			throw new \Exception( sprintf( esc_html_x( 'Failed to reload trigger after update: %s', 'Trigger store reload error', 'uncanny-automator' ), absint( $trigger_id_value ) ) );
		}

		return $updated_trigger;
	}

	/**
	 * Remove trigger from recipe.
	 *
	 * @param Recipe_Id  $recipe_id Recipe ID.
	 * @param Trigger_Id $trigger_id Trigger ID.
	 */
	public function remove_trigger_from_recipe( Recipe_Id $recipe_id, Trigger_Id $trigger_id ): void {
		$trigger_id_value = $trigger_id->get_value();
		$trigger_post     = get_post( $trigger_id_value );

		if ( $trigger_post && self::POST_TYPE === $trigger_post->post_type ) {
			wp_delete_post( $trigger_id_value, true );
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
	 * Delete all triggers for recipe.
	 *
	 * @param Recipe_Id $recipe_id Recipe ID.
	 */
	public function delete_recipe_triggers( Recipe_Id $recipe_id ): void {
		$trigger_posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_parent'    => $recipe_id->get_value(),
				'post_status'    => 'any',
				'posts_per_page' => -1,
			)
		);

		foreach ( $trigger_posts as $trigger_post ) {
			wp_delete_post( $trigger_post->ID, true );
		}

		// Clean up recipe meta
		delete_post_meta( $recipe_id->get_value(), self::META_TRIGGER_LOGIC );
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

		// Define manual trigger codes.
		$manual_trigger_codes = array( 'RECIPE_MANUAL_TRIGGER_ANON', 'RECIPE_MANUAL_TRIGGER' );

		// Build placeholders for IN clause (%s, %s, ...).
		// Safe: count is from our own array, not user input.
		$placeholders = implode( ', ', array_fill( 0, count( $manual_trigger_codes ), '%s' ) );

		// Query for triggers with manual trigger codes.
		// Safe: using $wpdb->prepare() with proper parameter merging.
		$query = $this->db->prepare(
			"SELECT pm.meta_value
			FROM {$this->db->posts} p
			INNER JOIN {$this->db->postmeta} pm ON p.ID = pm.post_id
			WHERE p.post_parent = %d
			AND p.post_type = %s
			AND pm.meta_key = %s
			AND pm.meta_value IN ({$placeholders})",
			array_merge(
				array( $recipe_id->get_value(), self::POST_TYPE, self::META_TRIGGER_CODE ),
				$manual_trigger_codes
			)
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above with $wpdb->prepare()
		$results = $this->db->get_results( $query );

		return ! empty( $results );
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
			'post_parent' => $trigger->get_recipe_id() ? $trigger->get_recipe_id()->get_value() : $recipe_id,
			'post_title'  => $trigger_data['title'],
			'post_status' => $trigger->get_status() ? $trigger->get_status()->get_value() : Trigger_Status::DRAFT,
			'meta_input'  => $trigger_data['meta'],
		);

		$trigger_id = $trigger->get_trigger_id();

		if ( ! $trigger_id->is_null() ) {
			$post_data['ID'] = $trigger_id->get_value();
		}

		$post_id = wp_insert_post( $post_data );

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
			self::META_ADD_ACTION                   => $hook_data['name'] ?? '',
			self::META_SENTENCE                     => $trigger_data['sentence'] ?? '',
			self::META_SENTENCE_HUMAN_READABLE      => $human_readable_sentence,
			self::META_SENTENCE_HUMAN_READABLE_HTML => $human_readable_sentence_html,
		);

		// Add dynamic field values (CAPSLOCK fields).
		$meta = array_merge( $meta, $this->convert_configuration_to_legacy_meta( $config, $trigger_code ) );

		// Generate title: prefer plain text from HTML (filled-in values), fallback to template.
		$title = $this->generate_trigger_title( $trigger_data, $config );

		return array(
			'title' => $title,
			'meta'  => $meta,
		);
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
	 * @param array  $config Configuration array.
	 * @param string $trigger_code Trigger code.
	 * @return array Legacy meta fields.
	 */
	private function convert_configuration_to_legacy_meta( array $config, string $trigger_code ): array {
		$legacy_meta = array();

		// Handle different trigger types with their specific field patterns
		foreach ( $config as $field_key => $field_value ) {
			// Skip _readable keys - they'll be handled when processing their parent field.
			// Also keeps _readable suffix lowercase as expected by sentence builder.
			if ( substr( $field_key, -9 ) === '_readable' ) {
				continue;
			}

			// Store field value as CAPSLOCK meta key (the legacy way)
			// Exception: preserve case for modern fields that should not be uppercased
			$field_key_lower = strtolower( $field_key );
			if ( in_array( $field_key_lower, self::PRESERVE_CASE_KEYS, true ) ) {
				$legacy_key = $field_key; // Keep original case
			} else {
				$legacy_key = strtoupper( $field_key ); // Legacy CAPSLOCK
			}
			$legacy_meta[ $legacy_key ] = $field_value;

			// Store readable label if it exists (keep _readable suffix lowercase)
			$readable_key = $field_key . '_readable';
			if ( isset( $config[ $readable_key ] ) ) {
				// Use uppercase field code + lowercase _readable suffix
				$legacy_readable_key                 = $legacy_key . '_readable';
				$legacy_meta[ $legacy_readable_key ] = $config[ $readable_key ];
			}
		}

		return $legacy_meta;
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
	 * @param int $trigger_post_id Trigger post ID.
	 * @return array|null Clean trigger data or null.
	 */
	private function parse_trigger_meta( int $trigger_post_id ): ?array {
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
		$trigger_code                 = $this->get_meta_value( $all_meta, self::META_TRIGGER_CODE );
		$trigger_type                 = $this->get_meta_value( $all_meta, self::META_TRIGGER_TYPE, 'user' );
		$integration                  = $this->get_meta_value( $all_meta, self::META_INTEGRATION, 'WP' );
		$hook_name                    = $this->get_meta_value( $all_meta, self::META_ADD_ACTION, '' );
		$sentence                     = $this->get_meta_value( $all_meta, self::META_SENTENCE, '' );
		$sentence_human_readable      = $this->get_meta_value( $all_meta, self::META_SENTENCE_HUMAN_READABLE, $sentence );
		$sentence_human_readable_html = $this->get_meta_value( $all_meta, self::META_SENTENCE_HUMAN_READABLE_HTML, '' );

		if ( empty( $trigger_code ) ) {
			return null;
		}

		// Build clean trigger data structure
		$clean_data = array(
			'trigger_id'                   => $trigger_post_id,
			'trigger_code'                 => $trigger_code,
			'trigger_type'                 => $trigger_type,
			'integration'                  => $integration,
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

			// Match CAPSLOCK field names (letters, numbers, underscores only)
			// This also matches preserved-case keys (e.g., _AUTOMATOR_CUSTOM_ITEM_NAME_)
			if ( preg_match( '/^[A-Z][A-Z0-9_]*$/', $meta_key ) && strlen( $meta_key ) > 0 ) {
				$field_name                   = $meta_key; // Keep original case (CAPSLOCK or preserved)
				$field_value                  = is_array( $meta_value ) ? $meta_value[0] : $meta_value;
				$configuration[ $field_name ] = $field_value;

				// Look for readable label
				$readable_key = $meta_key . '_readable';
				if ( isset( $all_meta[ $readable_key ] ) ) {
					$readable_value                           = is_array( $all_meta[ $readable_key ] ) ? $all_meta[ $readable_key ][0] : $all_meta[ $readable_key ];
					$configuration[ $meta_key . '_readable' ] = $readable_value;
				}
			}
		}

		return $configuration;
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
	 * Get post type.
	 *
	 * @return string Post type.
	 */
	public function get_post_type(): string {
		return self::POST_TYPE;
	}
}
