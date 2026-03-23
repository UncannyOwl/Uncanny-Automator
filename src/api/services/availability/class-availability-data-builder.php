<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Services\Availability;

/**
 * Builder for Availability_Data with fluent API and sensible defaults.
 *
 * @since 7.0.0
 * @package Uncanny_Automator\Api\Services\Availability
 */
class Availability_Data_Builder {

	/**
	 * The data being built.
	 *
	 * @var Availability_Data
	 */
	private $data;

	/**
	 * Constructor.
	 */
	public function __construct() {

		$this->data = Availability_Data::create_empty();
	}

	/**
	 * Set integration code.
	 *
	 * @param string $integration Integration code.
	 *
	 * @return self
	 */
	public function integration( $integration ) {

		$this->data->integration = $integration;

		return $this;
	}

	/**
	 * Set feature code.
	 *
	 * @param string $code Feature code.
	 *
	 * @return self
	 */
	public function code( $code ) {

		$this->data->code = $code;

		return $this;
	}

	/**
	 * Set feature type.
	 *
	 * @param string $type Feature type (trigger, action, condition, etc.).
	 *
	 * @return self
	 */
	public function type( $type ) {

		$this->data->type = $type;

		return $this;
	}

	/**
	 * Set whether integration is registered.
	 *
	 * @param bool $is_registered Whether integration is registered.
	 *
	 * @return self
	 */
	public function integration_registered( $is_registered ) {

		$this->data->is_integration_registered = $is_registered;

		return $this;
	}

	/**
	 * Set whether feature is registered.
	 *
	 * @param bool $is_registered Whether feature is registered.
	 *
	 * @return self
	 */
	public function feature_registered( $is_registered ) {

		$this->data->is_registered = $is_registered;

		return $this;
	}

	/**
	 * Set whether this is an app integration.
	 *
	 * @param bool $is_app Whether this is an app integration.
	 *
	 * @return self
	 */
	public function app( $is_app ) {

		$this->data->is_app = $is_app;

		return $this;
	}

	/**
	 * Set whether app is connected.
	 *
	 * @param bool $is_connected Whether app is connected.
	 *
	 * @return self
	 */
	public function connected( $is_connected ) {

		$this->data->is_connected = $is_connected;

		return $this;
	}

	/**
	 * Set user tier.
	 *
	 * @param string $tier_id User's current tier.
	 *
	 * @return self
	 */
	public function user_tier( $tier_id ) {

		$this->data->user_tier_id = $tier_id;

		return $this;
	}

	/**
	 * Set required tier.
	 *
	 * @param string $tier_id Required tier for this feature.
	 *
	 * @return self
	 */
	public function requires_tier( $tier_id ) {

		$this->data->requires_tier = $tier_id;

		return $this;
	}

	/**
	 * Set settings URL.
	 *
	 * @param string $url Settings URL for connecting the integration.
	 *
	 * @return self
	 */
	public function settings_url( $url ) {

		$this->data->settings_url = $url;

		return $this;
	}

	/**
	 * Build and return the Availability_Data instance.
	 *
	 * @return Availability_Data
	 */
	public function build() {

		return $this->data;
	}
}
