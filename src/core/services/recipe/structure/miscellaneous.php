<?php
namespace Uncanny_Automator\Services\Recipe\Structure;

use Uncanny_Automator\Services\Recipe\Builder\Settings\Fields\Field;
use Uncanny_Automator\Services\Recipe\Builder\Settings\Fields\Field_Collection_Manager;
use Uncanny_Automator\Services\Recipe\Builder\Settings\Fields\Field_Manager;
use Uncanny_Automator\Services\Recipe\Builder\Settings\Repository\Settings_Repository;
use Uncanny_Automator\Services\Recipe\Common;
use Uncanny_Automator\Services\Recipe\Process\Universal_Run_Number_Threshold;
use Uncanny_Automator\Services\Recipe\Process\User_Run_Number_Threshold;

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

	/**
	 * @var \Uncanny_Automator\Services\Recipe\Structure
	 */
	private static $recipe = null;

	public function __construct( \Uncanny_Automator\Services\Recipe\Structure $recipe ) {
		self::$recipe = $recipe;
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

		$settings_repository = new Settings_Repository();
		$field_manager       = new Field_Manager( $settings_repository );

		$this->has_loop                       = $this->has_loop();
		$this->has_loop_running               = $this->has_loop_running();
		$this->created_on_date                = get_the_date( '', $recipe_id );
		$this->created_with_automator_version = $meta['uap_recipe_version'] ?? null;
		$this->limit_per_user                 = $this->get_limit( new User_Run_Number_Threshold( $field_manager ) );
		$this->recipe_total_times             = $this->get_limit( new Universal_Run_Number_Threshold( $field_manager ) );
		$this->url_duplicate_recipe           = $duplicate_url;
		$this->url_trash_recipe               = get_delete_post_link( $recipe_id );
		$this->url_logs                       = $log_url;
		$this->recipe_throttle                = $this->get_throttle_settings();
		$this->url_download_recipe            = sprintf( '%s?action=%s&post=%d&_wpnonce=%s', admin_url( 'edit.php' ), 'export_recipe', $recipe_id, wp_create_nonce( 'Aut0Mat0R' ) );

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
	 * Gets the run limit configuration for a threshold
	 *
	 * @param User_Run_Number_Threshold|Universal_Run_Number_Threshold $threshold_adapter
	 * @return array{TIMES: array{type: string, value: mixed}}
	 */
	private function get_limit( $threshold_adapter ) {

		$recipe_id = self::$recipe->get_recipe_id();
		$field_id  = $threshold_adapter->get_field_id();

		// Set recipe ID on threshold adapter
		$threshold_adapter->set_recipe_id( $recipe_id );

		// Get field instance.
		$field = $this->get_field( absint( $recipe_id ), $field_id );

		// Default values
		$type  = 'int';
		$value = $threshold_adapter->get_field_value_legacy();

		// Override with field values if available.
		if ( $field instanceof Field ) {
			$value = $threshold_adapter->backwards_compat_get_limit_value( $field );
			$type  = $field->get_type();
		}

		return array(
			'TIMES' => array(
				'type'  => $type,
				'value' => $this->mask_unlimited_value( $value ),
			),
		);
	}

	private function mask_unlimited_value( $value ) {
		return -1 === $value ? '' : $value; // Front-end will take care of providing the placeholder.
	}

	private function get_field( int $recipe_id, string $setting_id ) {

		$repository = new Settings_Repository();
		$repository->set_recipe_id( $recipe_id );

		$field_manager = new Field_Manager( $repository );

		return $field_manager->get_field( absint( $recipe_id ), $setting_id );
	}

	private function get_throttle_settings() {

		$recipe_id = self::$recipe->get_recipe_id();

		$settings_repository = new Settings_Repository();
		$settings_repository->set_recipe_id( $recipe_id );
		$settings_repository->set_setting_id( 'recipe_throttle' );

		$field_manager = new Field_Collection_Manager( $settings_repository );

		return $field_manager->get_field_collection();
	}
}
