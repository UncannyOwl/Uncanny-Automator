<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Database\Stores;

use Uncanny_Automator\Api\Database\Interfaces\User_Selector_Store;
use Uncanny_Automator\Api\Components\User_Selector\User_Selector;
use Uncanny_Automator\Api\Components\User_Selector\User_Selector_Config;

/**
 * WordPress User Selector Store.
 *
 * Implements user selector persistence using WordPress post meta.
 * Handles conversion between domain objects and legacy meta format
 * for backward compatibility with existing Automator recipes.
 *
 * Meta keys used:
 * - 'source': User selector source type ('existingUser' or 'newUser')
 * - 'fields': User selector field data (JSON array)
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

		// Convert domain object to legacy meta format.
		$source = $user_selector->get_source()->get_value();
		$fields = $this->build_fields_from_user_selector( $user_selector );

		// Persist meta.
		update_post_meta( $recipe_id, self::META_SOURCE, $source );
		update_post_meta( $recipe_id, self::META_FIELDS, $fields );
		update_post_meta( $recipe_id, self::META_REQUIRES_USER, true );

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

		// Hydrate domain object from meta.
		$config = $this->build_config_from_meta( $recipe_id, $source, $fields );

		try {
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
	 * @param \Uncanny_Automator\Api\Components\User_Selector\Value_Objects\User_Selector_User_Data $user_data User data.
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

		if ( 'existingUser' === $source ) {
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

			// Extract user data if present for create fallback.
			$user_data = $this->extract_user_data_from_fields( $fields, 'create_' );
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
