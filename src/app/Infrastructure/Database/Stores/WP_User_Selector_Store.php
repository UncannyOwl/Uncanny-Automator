<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Infrastructure\Database\Stores;

use Uncanny_Automator\App\Infrastructure\Database\Interfaces\User_Selector_Store;
use Uncanny_Automator\App\Recipe_Builder\User_Selector\User_Selector;
use Uncanny_Automator\App\Recipe_Builder\User_Selector\User_Selector_Config;
use Uncanny_Automator\App\Recipe_Builder\User_Selector\Value_Objects\User_Selector_Source;
use Uncanny_Automator\App\Infrastructure\Cache\Recipe_Cache;

/**
 * WordPress User Selector Store.
 *
 * Implements user selector persistence using WordPress post meta.
 * Handles conversion between domain objects and legacy meta format
 * for backward compatibility with existing Automator recipes.
 *
 * Meta keys used:
 * - 'source': User selector source type (stored as legacy camelCase 'existingUser'/'newUser'; reads normalise to kebab via User_Selector_Source)
 * - 'fields': User selector field data (array). The existing-user create-new-user
 *   fields are written `create_`-prefixed; reads also accept the unprefixed 7.x shape
 * - 'recipe_requires_user': Whether recipe requires user data
 *
 * @since 7.0.0
 */
class WP_User_Selector_Store implements User_Selector_Store {

	/**
	 * Meta key for source type.
	 */
	const META_SOURCE = 'source';

	/**
	 * Meta key for fields data.
	 */
	const META_FIELDS = 'fields';

	/**
	 * Meta key for recipe requires user flag.
	 */
	const META_REQUIRES_USER = 'recipe_requires_user';

	/**
	 * Canonical kebab → legacy camelCase, for write-side persistence.
	 *
	 * Storage stays camelCase so legacy runtime readers (core, Pro) keep
	 * resolving without a migration. Reads normalise via User_Selector_Source.
	 *
	 * @var array
	 */
	private static $source_storage_map = array(
		'existing-user' => 'existingUser',
		'new-user'      => 'newUser',
	);

	/**
	 * WordPress database.
	 *
	 * @var \wpdb
	 */
	private \wpdb $db;

	/**
	 * @param \wpdb|null $db Database override (tests); defaults to the global.
	 */
	public function __construct( ?\wpdb $db = null ) {
		global $wpdb;
		$this->db = $db ?? $wpdb;
	}

	/**
	 * Persist a User_Selector.
	 *
	 * @param User_Selector $user_selector User selector to persist.
	 * @return User_Selector Persisted user selector.
	 */
	public function save( User_Selector $user_selector ): User_Selector {
		$recipe_id = $user_selector->get_recipe_id();

		if ( null === $recipe_id ) {
			throw new \InvalidArgumentException( 'Cannot save user selector without recipe ID' );
		}

		// Persist the legacy camelCase form (storage frozen; reads normalise).
		$canonical = $user_selector->get_source()->get_value();
		$source    = self::$source_storage_map[ $canonical ] ?? $canonical;
		$fields    = $this->build_fields_from_user_selector( $user_selector );

		// Persist meta.
		update_post_meta( $recipe_id, self::META_SOURCE, $source );
		update_post_meta( $recipe_id, self::META_FIELDS, $fields );
		update_post_meta( $recipe_id, self::META_REQUIRES_USER, true );

		Recipe_Cache::invalidate( $recipe_id );

		// Return refreshed user selector.
		return $this->get_by_recipe_id( $recipe_id );
	}

	/**
	 * Load a User_Selector by recipe ID.
	 *
	 * @param int $recipe_id Recipe ID.
	 * @return User_Selector|null User selector or null if not found.
	 */
	public function get_by_recipe_id( int $recipe_id ): ?User_Selector {
		$source = get_post_meta( $recipe_id, self::META_SOURCE, true );

		if ( empty( $source ) ) {
			return null;
		}

		$fields = get_post_meta( $recipe_id, self::META_FIELDS, true );

		if ( ! is_array( $fields ) ) {
			$fields = array();
		}

		try {
			// Hydrate domain object from meta (may throw on invalid stored source).
			$config = $this->build_config_from_meta( $recipe_id, $source, $fields );
			return new User_Selector( $config );
		} catch ( \InvalidArgumentException $e ) {
			// Invalid data in storage, return null.
			return null;
		}
	}

	/**
	 * Delete a User_Selector by recipe ID.
	 *
	 * @param int $recipe_id Recipe ID.
	 * @return void
	 */
	public function delete_by_recipe_id( int $recipe_id ): void {
		delete_post_meta( $recipe_id, self::META_SOURCE );
		delete_post_meta( $recipe_id, self::META_FIELDS );
		delete_post_meta( $recipe_id, self::META_REQUIRES_USER );

		Recipe_Cache::invalidate( $recipe_id );
	}

	/**
	 * Check if a recipe has a user selector configured.
	 *
	 * @param int $recipe_id Recipe ID.
	 * @return bool True if user selector exists.
	 */
	public function exists_for_recipe( int $recipe_id ): bool {
		$source = get_post_meta( $recipe_id, self::META_SOURCE, true );
		return ! empty( $source );
	}

	/**
	 * Whether any published Everyone recipe's run-as can log the user in.
	 *
	 * The query narrows to recipes whose serialized `fields` meta has the
	 * log-in flag on in either stored shape: `logUserIn` (new-user fields, and
	 * the 7.x create-new-user fields) or `create_logUserIn` (create-new-user
	 * fields written by this store). Each candidate then counts only when its
	 * selector can create the user ({@see User_Selector::can_log_user_in()}).
	 *
	 * @since 8.0.0
	 *
	 * @return bool
	 */
	public function any_published_recipe_logs_user_in(): bool {
		$candidate_ids = $this->db->get_col(
			$this->db->prepare(
				"SELECT p.ID
				FROM {$this->db->posts} p
				INNER JOIN {$this->db->postmeta} pm_type ON p.ID = pm_type.post_id
				INNER JOIN {$this->db->postmeta} pm_fields ON p.ID = pm_fields.post_id
				WHERE p.post_type = %s
				AND p.post_status = %s
				AND pm_type.meta_key = %s
				AND pm_type.meta_value = %s
				AND pm_fields.meta_key = %s
				AND ( pm_fields.meta_value LIKE %s OR pm_fields.meta_value LIKE %s )",
				AUTOMATOR_POST_TYPE_RECIPE,
				'publish',
				'uap_recipe_type',
				'anonymous',
				self::META_FIELDS,
				'%' . $this->db->esc_like( '"logUserIn";s:3:"yes"' ) . '%',
				'%' . $this->db->esc_like( '"create_logUserIn";s:3:"yes"' ) . '%'
			)
		);

		foreach ( $candidate_ids as $recipe_id ) {
			$selector = $this->get_by_recipe_id( (int) $recipe_id );

			if ( null !== $selector && $selector->can_log_user_in() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Build fields array from User_Selector domain object.
	 *
	 * Converts domain object to legacy meta format used by Automator.
	 *
	 * @param User_Selector $user_selector User selector.
	 * @return array Fields array for storage.
	 */
	private function build_fields_from_user_selector( User_Selector $user_selector ): array {
		$fields = array();
		$source = $user_selector->get_source();

		if ( $source->is_existing_user() ) {
			$fields = $this->build_existing_user_fields( $user_selector );
		} else {
			$fields = $this->build_new_user_fields( $user_selector );
		}

		return $fields;
	}

	/**
	 * Build fields for existing user source.
	 *
	 * @param User_Selector $user_selector User selector.
	 * @return array Fields array.
	 */
	private function build_existing_user_fields( User_Selector $user_selector ): array {
		$fields = array(
			'uniqueField'      => $user_selector->get_unique_field() ? $user_selector->get_unique_field()->get_value() : null,
			'uniqueFieldValue' => $user_selector->get_unique_field_value(),
		);

		$fallback = $user_selector->get_fallback();
		if ( $fallback ) {
			$fields['fallback'] = $fallback->get_value();

			// Include user data if fallback creates new user.
			if ( $fallback->creates_new_user() ) {
				$user_data = $user_selector->get_user_data();
				if ( $user_data ) {
					$fields = array_merge( $fields, $this->build_user_data_fields( $user_data, 'create_' ) );
				}
			}
		}

		return $fields;
	}

	/**
	 * Build fields for new user source.
	 *
	 * @param User_Selector $user_selector User selector.
	 * @return array Fields array.
	 */
	private function build_new_user_fields( User_Selector $user_selector ): array {
		$user_data = $user_selector->get_user_data();
		$fields    = $user_data ? $this->build_user_data_fields( $user_data, '' ) : array();

		$fallback = $user_selector->get_fallback();
		if ( $fallback ) {
			$fields['fallback'] = $fallback->get_value();

			// Include prioritized field if fallback selects existing user.
			if ( $fallback->selects_existing_user() ) {
				$prioritized_field = $user_selector->get_prioritized_field();
				if ( $prioritized_field ) {
					$fields['prioritizedField'] = $prioritized_field->get_value();
				}
			}
		}

		return $fields;
	}

	/**
	 * Build user data fields from User_Selector_User_Data value object.
	 *
	 * @param \Uncanny_Automator\App\Recipe_Builder\User_Selector\Value_Objects\User_Selector_User_Data $user_data User data.
	 * @param string $prefix Field name prefix.
	 * @return array Fields array.
	 */
	private function build_user_data_fields( $user_data, string $prefix = '' ): array {
		return array(
			$prefix . 'firstName'   => $user_data->get_first_name(),
			$prefix . 'lastName'    => $user_data->get_last_name(),
			$prefix . 'email'       => $user_data->get_email(),
			$prefix . 'username'    => $user_data->get_username(),
			$prefix . 'displayName' => $user_data->get_display_name(),
			$prefix . 'password'    => $user_data->get_password(),
			$prefix . 'role'        => $user_data->get_role(),
			$prefix . 'logUserIn'   => $user_data->should_log_user_in() ? 'yes' : 'no',
		);
	}

	/**
	 * Build User_Selector_Config from meta data.
	 *
	 * Hydrates domain config from legacy meta format.
	 *
	 * @param int    $recipe_id Recipe ID.
	 * @param string $source    Source type.
	 * @param array  $fields    Fields data.
	 * @return User_Selector_Config Configuration object.
	 */
	private function build_config_from_meta( int $recipe_id, string $source, array $fields ): User_Selector_Config {
		$config = new User_Selector_Config();
		$config->recipe_id( $recipe_id );
		$config->source( $source );

		$is_existing = ( new User_Selector_Source( $source ) )->is_existing_user();
		if ( $is_existing ) {
			$this->hydrate_existing_user_config( $config, $fields );
		} else {
			$this->hydrate_new_user_config( $config, $fields );
		}

		return $config;
	}

	/**
	 * Hydrate config for existing user source.
	 *
	 * @param User_Selector_Config $config Configuration object.
	 * @param array                $fields Fields data.
	 */
	private function hydrate_existing_user_config( User_Selector_Config $config, array $fields ): void {
		if ( isset( $fields['uniqueField'] ) ) {
			$config->unique_field( $fields['uniqueField'] );
		}

		if ( isset( $fields['uniqueFieldValue'] ) ) {
			$config->unique_field_value( $fields['uniqueFieldValue'] );
		}

		if ( isset( $fields['fallback'] ) ) {
			$config->fallback( $fields['fallback'] );

			// Extract user data if present for create fallback. Writes prefix
			// these keys `create_`; the 7.x builder stored them unprefixed.
			$user_data = $this->extract_user_data_from_fields( $fields, 'create_' );
			if ( empty( $user_data ) ) {
				$user_data = $this->extract_user_data_from_fields( $fields, '' );
			}
			if ( ! empty( $user_data ) ) {
				$config->user_data( $user_data );
			}
		}
	}

	/**
	 * Hydrate config for new user source.
	 *
	 * @param User_Selector_Config $config Configuration object.
	 * @param array                $fields Fields data.
	 */
	private function hydrate_new_user_config( User_Selector_Config $config, array $fields ): void {
		// Extract user data.
		$user_data = $this->extract_user_data_from_fields( $fields, '' );
		if ( ! empty( $user_data ) ) {
			$config->user_data( $user_data );
		}

		if ( isset( $fields['fallback'] ) ) {
			$config->fallback( $fields['fallback'] );
		}

		if ( isset( $fields['prioritizedField'] ) ) {
			$config->prioritized_field( $fields['prioritizedField'] );
		}
	}

	/**
	 * Extract user data array from fields.
	 *
	 * @param array  $fields Fields data.
	 * @param string $prefix Field name prefix.
	 * @return array User data array.
	 */
	private function extract_user_data_from_fields( array $fields, string $prefix = '' ): array {
		$user_data = array();
		$keys      = array( 'firstName', 'lastName', 'email', 'username', 'displayName', 'password', 'role', 'logUserIn' );

		foreach ( $keys as $key ) {
			$field_key = $prefix . $key;
			if ( isset( $fields[ $field_key ] ) ) {
				$value = $fields[ $field_key ];

				// Convert 'yes'/'no' to boolean for logUserIn.
				if ( 'logUserIn' === $key ) {
					$value = 'yes' === $value;
				}

				$user_data[ $key ] = $value;
			}
		}

		return $user_data;
	}
}
