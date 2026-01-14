<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Services\Availability;

/**
 * Data Transfer Object for feature availability data.
 *
 * Use the builder pattern to construct instances with sensible defaults.
 *
 * @since 7.0.0
 * @package Uncanny_Automator\Api\Services\Availability
 */
class Availability_Data {

	/**
	 * Integration code.
	 *
	 * @var string
	 */
	public $integration = '';

	/**
	 * Feature code (trigger_code, action_code, etc.).
	 *
	 * @var string
	 */
	public $code = '';

	/**
	 * Feature type (trigger, action, condition, etc.).
	 *
	 * @var string
	 */
	public $type = '';

	/**
	 * Whether the integration is registered.
	 *
	 * @var bool
	 */
	public $is_integration_registered = false;

	/**
	 * Whether the feature is registered.
	 *
	 * @var bool
	 */
	public $is_registered = false;

	/**
	 * Whether this is an app-based integration.
	 *
	 * @var bool
	 */
	public $is_app = false;

	/**
	 * Whether the app is connected.
	 *
	 * @var bool
	 */
	public $is_connected = false;

	/**
	 * User's current tier.
	 *
	 * @var string
	 */
	public $user_tier_id = '';

	/**
	 * Required tier for this feature.
	 *
	 * @var string
	 */
	public $requires_tier = '';

	/**
	 * Settings URL for connecting the integration (if app-based).
	 *
	 * @var string
	 */
	public $settings_url = '';

	/**
	 * Private constructor - use builder() instead.
	 */
	private function __construct() {}

	/**
	 * Create a new builder instance.
	 *
	 * @return Availability_Data_Builder
	 */
	public static function builder() {

		return new Availability_Data_Builder();
	}

	/**
	 * Internal factory method for builder.
	 *
	 * @internal Used by Availability_Data_Builder only.
	 *
	 * @return self
	 */
	public static function create_empty() {

		return new self();
	}

	/**
	 * Convert to array.
	 *
	 * @return array
	 */
	public function to_array() {

		return array(
			'integration'               => $this->integration,
			'code'                      => $this->code,
			'type'                      => $this->type,
			'is_integration_registered' => $this->is_integration_registered,
			'is_registered'             => $this->is_registered,
			'is_app'                    => $this->is_app,
			'is_connected'              => $this->is_connected,
			'user_tier_id'              => $this->user_tier_id,
			'requires_tier'             => $this->requires_tier,
			'settings_url'              => $this->settings_url,
		);
	}
}
