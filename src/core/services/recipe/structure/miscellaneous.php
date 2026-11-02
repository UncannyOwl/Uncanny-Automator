<?php
namespace Uncanny_Automator\Services\Recipe\Structure;

use Uncanny_Automator\Services\Recipe\Builder\Settings\Fields\Field_Collection_Manager;
use Uncanny_Automator\Services\Recipe\Builder\Settings\Repository\Settings_Repository;
use Uncanny_Automator\Services\Recipe\Common;

/**
 * Represents the miscellaneous object inside the recipe object.
 *
 * @package Uncanny_Automator\Services\Recipe\Structure
 *
 * @since 5.0
 */
final class Miscellaneous implements \JsonSerializable {

	/**
	 * @since 5.0
	 */
	use Common\Trait_JSON_Serializer;

	/**
	 * @since 5.1
	 */
	use Common\Trait_Setter_Getter;

	protected $created_on_date                = null;
	protected $last_modified_date_timestamp   = null;
	protected $has_loop                       = false;
	protected $has_loop_running               = false;
	protected $created_with_automator_version = null;
	protected $recipe_total_times             = -1;
	protected $limit_per_user                 = -1;
	protected $url_duplicate_recipe           = null;
	protected $url_trash_recipe               = null;
	protected $url_logs                       = null;
	protected $recipe_throttle                = false;
	protected $url_download_recipe            = null;
	protected $uses_pro_entity                = false;

	/**
	 * Whether the recipe has a "run now" type trigger.
	 *
	 * Also written through the Trait_Setter_Getter from
	 * `Run_Now_Integration` (Pro). Declared here so PHP 8.2 doesn't emit
	 * dynamic-property deprecation notices when Pro sets it.
	 *
	 * @var bool
	 */
	protected $has_run_now = false;

	/**
	 * Whether the recipe is currently running.
	 *
	 * @var bool
	 */
	protected $recipe_is_running = false;

	/**
	 * Whether the recipe can be edited.
	 *
	 * @var bool
	 */
	protected $can_edit = true;

	/**
	 * The latest recipe log status class name (null if never ran).
	 *
	 * @var string|null
	 */
	protected $latest_recipe_log_status = null;

	/**
	 * @var \Uncanny_Automator\Services\Recipe\Structure
	 */
	private static $recipe = null;

	/**
	 * @var mixed The actions object from the recipe structure.
	 */
	private static $actions = null;

	/**
	 * @param \Uncanny_Automator\Services\Recipe\Structure $recipe The recipe structure object.
	 * @param mixed                                        $actions The actions object (already hydrated).
	 */
	public function __construct( \Uncanny_Automator\Services\Recipe\Structure $recipe, $actions = null ) {
		self::$recipe  = $recipe;
		self::$actions = $actions;
		$this->hydrate_properties();
	}

	/**
	 * Hydrates object properties.
	 *
	 * @return self
	 */
	private function hydrate_properties() {

		$meta = self::$recipe->meta();

		$recipe_id = self::$recipe->get_recipe_id();

		$duplicate_url = sprintf(
			'%s?action=%s&post=%d&return_to_recipe=yes&_wpnonce=%s',
			admin_url( 'edit.php' ),
			'copy_recipe_parts',
			$recipe_id,
			wp_create_nonce( 'Aut0Mat0R' )
		);

		$log_url = sprintf( '%s?post_type=uo-recipe&page=uncanny-automator-admin-logs&recipe_id=%d', admin_url( 'edit.php' ), $recipe_id );

		$this->has_loop                       = $this->has_loop();
		$this->has_loop_running               = $this->has_loop_running();
		$this->last_modified_date_timestamp   = get_post_modified_time( 'U', false, $recipe_id );
		$this->created_on_date                = get_the_date( '', $recipe_id );
		$this->created_with_automator_version = $meta['uap_recipe_version'] ?? null;
		$this->limit_per_user                 = $this->limit_object( automator_get_recipe_times_per_user( $recipe_id ) );
		$this->recipe_total_times             = $this->limit_object( automator_get_recipe_total_times( $recipe_id ) );
		$this->url_duplicate_recipe           = $duplicate_url;
		$this->url_trash_recipe               = get_delete_post_link( $recipe_id );
		$this->url_logs                       = $log_url;
		$this->recipe_throttle                = $this->get_throttle_settings();
		$this->url_download_recipe            = sprintf( '%s?action=%s&post=%d&_wpnonce=%s', admin_url( 'edit.php' ), 'export_recipe', $recipe_id, wp_create_nonce( 'Aut0Mat0R' ) );
		$this->uses_pro_entity                = $this->determine_uses_pro_entity();

		return $this;
	}

	/**
	 * Determines if the current recipe has a loop block on it.
	 *
	 * @return bool
	 */
	public function has_loop() {
		return ! empty( Automator()->loop_db()->find_recipe_loops( absint( self::$recipe->get_recipe_id() ) ) );
	}

	/**
	 * Determines if the current recipe has loop that is running.
	 *
	 * @return bool
	 */
	public function has_loop_running() {

		return apply_filters(
			'automator_recipe_has_loop_running',
			$this->has_loop_running,
			absint( self::$recipe->get_recipe_id() )
		);
	}

	/**
	 * Build the {TIMES} display structure from a completion-limit helper result.
	 * A WP_Error coerces to unlimited (-1); the value is masked to '' so the
	 * front-end supplies the "no limit" placeholder.
	 *
	 * @param int|\WP_Error $limit
	 *
	 * @return array{TIMES: array{type: string, value: mixed}}
	 */
	private function limit_object( $limit ) {

		$value = is_wp_error( $limit ) ? -1 : (int) $limit;

		return array(
			'TIMES' => array(
				'type'  => 'int',
				'value' => $this->mask_unlimited_value( $value ),
			),
		);
	}

	/**
	 * Determines if the recipe uses any pro entity.
	 *
	 * Pro entities include:
	 * - Actions with delays (async_mode set)
	 * - Action conditions (filter type items)
	 * - Loops
	 *
	 * @return bool True if the recipe contains any pro entity, false otherwise.
	 */
	private function determine_uses_pro_entity() {

		// Check for loops - already computed in has_loop property.
		if ( $this->has_loop ) {
			return true;
		}

		// If actions object is not available, default to false.
		if ( null === self::$actions ) {
			return false;
		}

		// Get the items from the actions object.
		$items = self::$actions->get( 'items' );

		if ( empty( $items ) || ! is_array( $items ) ) {
			return false;
		}

		foreach ( $items as $item ) {
			// Check for action conditions (filter type).
			if ( is_array( $item ) && isset( $item['type'] ) && 'filter' === $item['type'] ) {
				return true;
			}

			// Check for delays (async_mode in miscellaneous->delay_schedule).
			if ( is_array( $item ) && isset( $item['miscellaneous']['delay_schedule'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param mixed $value
	 *
	 * @return mixed
	 */
	private function mask_unlimited_value( $value ) {
		return -1 === $value ? '' : $value; // Front-end will take care of providing the placeholder.
	}

	/**
	 * @return mixed
	 */
	private function get_throttle_settings() {

		$recipe_id = self::$recipe->get_recipe_id();

		$settings_repository = new Settings_Repository();
		$settings_repository->set_recipe_id( $recipe_id );
		$settings_repository->set_setting_id( 'recipe_throttle' );

		$field_manager = new Field_Collection_Manager( $settings_repository );

		return $field_manager->get_field_collection();
	}
}
