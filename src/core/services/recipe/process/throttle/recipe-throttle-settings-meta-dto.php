<?php

namespace Uncanny_Automator\Services\Recipe\Process\Throttle;

/**
 * Class Recipe_Throttle_Settings_Meta_DTO
 *
 * Data Transfer Object for recipe throttle settings
 */
class Recipe_Throttle_Settings_Meta_DTO {

	/**
	 * @var bool
	 */
	private $enabled;

	/**
	 * @var string 'user'|'recipe'
	 */
	private $scope;

	/**
	 * @var int
	 */
	private $duration;

	/**
	 * @var string
	 */
	private $unit;

	/**
	 * @param bool $enabled
	 * @param string $scope
	 * @param int $duration
	 * @param string $unit
	 */
	public function __construct( bool $enabled, string $scope, int $duration, string $unit ) {
		$this->enabled  = $enabled;
		$this->scope    = $scope;
		$this->duration = $duration;
		$this->unit     = $unit;
	}

	/**
	 * @return bool
	 */
	public function is_enabled() {
		return $this->enabled;
	}

	/**
	 * @return string
	 */
	public function get_scope() {
		return $this->scope;
	}

	/**
	 * @return int
	 */
	public function get_duration() {
		return $this->duration;
	}

	/**
	 * @return string
	 */
	public function get_unit() {
		return $this->unit;
	}

	/**
	 * Creates DTO from WordPress meta array
	 *
	 * @param array $meta Meta data array
	 * @return Recipe_Throttle_Settings_Meta_DTO|null
	 */
	public static function from_meta( array $meta ) {
		// Check if required fields are present
		if ( ! isset(
			$meta['ENABLED']['value'],
			$meta['NUMBER']['value'],
			$meta['UNIT']['value']
		) ) {
			return null;
		}

		// Default to 'recipe' if PER_RECIPE_OR_USER is not set
		$scope = isset( $meta['PER_RECIPE_OR_USER']['value'] )
			? (string) $meta['PER_RECIPE_OR_USER']['value']
			: 'recipe';

		// Get values with proper type casting
		$enabled  = (bool) $meta['ENABLED']['value'];
		$duration = (int) $meta['NUMBER']['value'];
		$unit     = (string) $meta['UNIT']['value'];

		// Ensure duration is at least 1
		$duration = max( 1, $duration );

		return new self( $enabled, $scope, $duration, $unit );
	}
}
