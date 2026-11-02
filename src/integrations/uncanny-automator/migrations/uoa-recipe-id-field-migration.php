<?php

namespace Uncanny_Automator\Integrations\Uncanny_Automator;

use Uncanny_Automator\Migrations\Migration;

/**
 * Class Uoa_Recipe_Id_Field_Migration.
 *
 * "Add an action to a recipe", "Add a trigger to a recipe" and Pro's
 * "Deactivate or activate a recipe" stored their target recipe under the field
 * code RECIPE_ID. Postmeta keys match case-insensitively, so the field shared
 * one row with the action store's structural `recipe_id` key: the field read
 * back empty and the action was listed under its target recipe. The field is
 * now UOA_RECIPE_ID. This moves each stored value there and leaves one lowercase
 * `recipe_id` row naming the owning recipe.
 *
 * A row's spelling says whose value it holds. `RECIPE_ID` was written by the
 * 7.x builder, so it is the field's. The action store writes the lowercase key
 * before the fields, so a lowercase row whose value is not the owning recipe
 * holds the field's value; one equal to the owning recipe cannot be told apart
 * from a self-targeting action, so the field is left empty and logged.
 *
 * Free migrates Pro's action too: 8.0.0 runs only with Free and Pro updated together.
 *
 * @package Uncanny_Automator
 *
 * @since 8.0.0
 */
class Uoa_Recipe_Id_Field_Migration extends Migration {

	/**
	 * Log file for the actions whose target could not be recovered.
	 *
	 * @var string
	 */
	const LOG_FILE = 'uoa-recipe-id-field-migration';

	/**
	 * Run the migration.
	 *
	 * @return void
	 */
	public function migrate() {
		global $wpdb;

		// Free's two actions and Pro's "Deactivate or activate a recipe".
		$action_codes = array(
			'ADD_ACTION_TO_RECIPE',
			'ADD_TRIGGER_TO_RECIPE',
			'RECIPE_CHANGE_STATUS_CODE',
		);

		$placeholders = implode( ', ', array_fill( 0, count( $action_codes ), '%s' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- dynamic IN() clause built from array_fill('%s').
		$action_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = 'code'
				WHERE p.post_type = %s AND m.meta_value IN ({$placeholders})",
				array_merge( array( AUTOMATOR_POST_TYPE_ACTION ), $action_codes )
			)
		);
		// phpcs:enable

		// One action that fails is logged and skipped, so the run still completes
		// instead of repeating the same failure on every request.
		foreach ( $action_ids as $action_id ) {
			try {
				$this->migrate_action( (int) $action_id );
			} catch ( \Throwable $e ) {
				automator_log( sprintf( 'Action %d was not migrated: %s', (int) $action_id, $e->getMessage() ), 'UOA recipe ID field migration', true, self::LOG_FILE );
			}
		}

		$this->complete();
	}

	/**
	 * Migrate one action. Safe to repeat: a field already moved is kept, and the
	 * old rows are cleared either way, so a run that died part-way is finished.
	 *
	 * @param int $action_id Action post ID.
	 *
	 * @return void
	 */
	private function migrate_action( int $action_id ) {
		global $wpdb;

		$moved = metadata_exists( 'post', $action_id, 'UOA_RECIPE_ID' );
		$owner = $this->owning_recipe( $action_id );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- every spelling of the key, which get_post_meta() cannot tell apart.
		$rows     = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND LOWER( meta_key ) = %s ORDER BY meta_id",
				$action_id,
				'recipe_id'
			)
		);
		$readable = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = BINARY %s LIMIT 1",
				$action_id,
				'RECIPE_ID_readable'
			)
		);
		// phpcs:enable

		$value = $moved ? null : $this->field_value( (array) $rows, $owner );
		if ( null !== $value ) {
			update_post_meta( $action_id, 'UOA_RECIPE_ID', wp_slash( $value ) );
			if ( null !== $readable ) {
				update_post_meta( $action_id, 'UOA_RECIPE_ID_readable', wp_slash( $readable ) );
			}
		} elseif ( ! $moved && ! empty( $rows ) ) {
			automator_log(
				sprintf( 'Action %d: its target recipe cannot be told apart from its own recipe (%d), so its Recipe field is left empty to be picked again.', $action_id, $owner ),
				'UOA recipe ID field migration',
				true,
				self::LOG_FILE
			);
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- BINARY and LOWER() deletes; delete_post_meta() would match every spelling.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = BINARY %s", $action_id, 'RECIPE_ID_readable' ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE post_id = %d AND LOWER( meta_key ) = %s", $action_id, 'recipe_id' ) );
		// phpcs:enable

		if ( 0 < $owner ) {
			add_post_meta( $action_id, 'recipe_id', $owner );
		}

		wp_cache_delete( $action_id, 'post_meta' );

		if ( 0 < $owner ) {
			automator_invalidate_recipe_schema( $owner );
		}
	}

	/**
	 * The field's value among the key's stored rows, or null when none holds it.
	 *
	 * @param object[] $rows  Rows with meta_key and meta_value, every spelling of the key.
	 * @param int      $owner The owning recipe.
	 *
	 * @return string|null
	 */
	private function field_value( array $rows, int $owner ) {
		foreach ( $rows as $row ) {
			if ( 'recipe_id' !== $row->meta_key ) {
				return (string) $row->meta_value;
			}
		}

		foreach ( $rows as $row ) {
			if ( (string) $owner !== (string) $row->meta_value ) {
				return (string) $row->meta_value;
			}
		}

		return null;
	}

	/**
	 * The recipe the action belongs to, through its parent posts (an action can sit inside blocks).
	 *
	 * @param int $action_id Action post ID.
	 *
	 * @return int 0 when no recipe owns it.
	 */
	private function owning_recipe( int $action_id ): int {
		foreach ( get_post_ancestors( $action_id ) as $ancestor_id ) {
			if ( AUTOMATOR_POST_TYPE_RECIPE === get_post_type( $ancestor_id ) ) {
				return (int) $ancestor_id;
			}
		}

		return 0;
	}
}
