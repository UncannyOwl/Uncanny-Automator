<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Client;

use Uncanny_Automator\Api\Services\License\License_Service;
use Uncanny_Automator\Api\Services\Plan\Plan_Service;

/**
 * Data object supplying payload defaults.
 */
class Client_Payload_Context {

	/**
	 * License service dependency.
	 *
	 * @var License_Service|null
	 */
	private ?License_Service $license_service;

	/**
	 * Cached current user.
	 *
	 * @var object|\WP_User|null
	 */
	private ?object $current_user;

	/**
	 * REST base URL override.
	 *
	 * @var string|null
	 */
	private ?string $mcp_rest_url;

	/**
	 * UUID override.
	 *
	 * @var string|null
	 */
	private ?string $uuid;

	/**
	 * Timestamp override.
	 *
	 * @var int|null
	 */
	private ?int $timestamp;

	/**
	 * Request URI override.
	 *
	 * @var string|null
	 */
	private ?string $request_uri;

	/**
	 * License key override.
	 *
	 * @var string|null
	 */
	private ?string $license_key;

	/**
	 * Site name override.
	 *
	 * @var string|null
	 */
	private ?string $site_name;

	/**
	 * Item name override.
	 *
	 * @var string|null
	 */
	private ?string $item_name;

	/**
	 * License type override.
	 *
	 * @var string|null
	 */
	private ?string $license_type;

	/**
	 * Renewal date formatted override.
	 *
	 * @var string|null
	 */
	private ?string $renewal_date_formatted;

	/**
	 * URL to get credits override.
	 *
	 * @var string|null
	 */
	private ?string $url_get_credits;

	/**
	 * Plan service dependency.
	 *
	 * @var Plan_Service|null
	 */
	private Plan_Service $plan_service;

	/**
	 * @param object|\WP_User|null $current_user            Optional current user.
	 * @param string|null          $mcp_rest_url            Optional REST URL.
	 * @param string|null          $uuid                    Optional UUID.
	 * @param int|null             $timestamp               Optional timestamp.
	 * @param string|null          $request_uri             Optional request URI.
	 * @param string|null          $license_key             Optional license key.
	 * @param string|null          $site_name               Optional site name.
	 * @param string|null          $item_name               Optional product name.
	 * @param string|null          $license_type            Optional license type.
	 * @param string|null          $renewal_date_formatted  Optional renewal date.
	 * @param string|null          $url_get_credits         Optional credits URL.
	 * @param License_Service|null $license_service         Optional license service (internal dependency).
	 * @param Plan_Service|null    $plan_service            Optional plan service (internal dependency).
	 */
	public function __construct(
		?object $current_user = null,
		?string $mcp_rest_url = null,
		?string $uuid = null,
		?int $timestamp = null,
		?string $request_uri = null,
		?string $license_key = null,
		?string $site_name = null,
		?string $item_name = null,
		?string $license_type = null,
		?string $renewal_date_formatted = null,
		?string $url_get_credits = null,
		?License_Service $license_service = null,
		?Plan_Service $plan_service = null
	) {
		// Security: Validate string parameters to prevent injection or type errors.
		if ( null !== $mcp_rest_url && ! is_string( $mcp_rest_url ) ) {
			throw new \InvalidArgumentException( 'MCP REST URL must be a string.' );
		}

		if ( null !== $uuid && ! is_string( $uuid ) ) {
			throw new \InvalidArgumentException( 'UUID must be a string.' );
		}

		if ( null !== $license_key && ! is_string( $license_key ) ) {
			throw new \InvalidArgumentException( 'License key must be a string.' );
		}

		if ( null !== $site_name && ! is_string( $site_name ) ) {
			throw new \InvalidArgumentException( 'Site name must be a string.' );
		}

		if ( null === $plan_service ) {
			$plan_service = new Plan_Service();
		}

		$this->current_user           = $current_user;
		$this->mcp_rest_url           = $mcp_rest_url;
		$this->uuid                   = $uuid;
		$this->timestamp              = $timestamp;
		$this->request_uri            = $request_uri;
		$this->license_key            = $license_key;
		$this->site_name              = $site_name;
		$this->item_name              = $item_name;
		$this->license_type           = $license_type;
		$this->renewal_date_formatted = $renewal_date_formatted;
		$this->url_get_credits        = $url_get_credits;
		$this->license_service        = $license_service;
		$this->plan_service           = $plan_service;
	}

	/**
	 * Get the license service instance.
	 *
	 * @return License_Service
	 */
	private function get_license_service(): License_Service {
		if ( null === $this->license_service ) {
			$this->license_service = new License_Service();
		}

		return $this->license_service;
	}

	/**
	 * Resolve the current WordPress user object.
	 *
	 * @return object|\WP_User
	 */
	public function get_current_user(): object {
		if ( null !== $this->current_user ) {
			return $this->current_user;
		}

		return \wp_get_current_user();
	}

	/**
	 * Determine the MCP REST base URL.
	 *
	 * @return string
	 */
	public function get_mcp_rest_url(): string {
		if ( null !== $this->mcp_rest_url ) {
			return $this->mcp_rest_url;
		}

		return (string) \rest_url( 'automator/v1/mcp' );
	}

	/**
	 * Generate or return a UUID.
	 *
	 * @return string
	 */
	public function generate_uuid(): string {
		if ( null !== $this->uuid ) {
			return $this->uuid;
		}

		return (string) \wp_generate_uuid4();
	}

	/**
	 * Resolve the current timestamp.
	 *
	 * @return int
	 */
	public function get_timestamp(): int {
		if ( null !== $this->timestamp ) {
			return (int) $this->timestamp;
		}

		return time();
	}

	/**
	 * Resolve the active request URI.
	 *
	 * @return string
	 */
	public function get_request_uri(): string {
		if ( null !== $this->request_uri ) {
			return \wp_unslash( $this->request_uri );
		}

		return isset( $_SERVER['REQUEST_URI'] ) ? \wp_unslash( (string) $_SERVER['REQUEST_URI'] ) : '';  // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Server var for context.
	}

	/**
	 * Retrieve the Automator license key.
	 *
	 * @return string
	 */
	public function get_license_key(): string {
		if ( null !== $this->license_key ) {
			return $this->license_key;
		}

		return $this->get_license_service()->get_license_key();
	}

	/**
	 * Retrieve the licensed site name.
	 *
	 * @return string
	 */
	public function get_site_name(): string {
		if ( null !== $this->site_name ) {
			return $this->site_name;
		}

		return $this->get_license_service()->get_site_name();
	}

	/**
	 * Retrieve the product name associated with the license.
	 *
	 * @return string
	 */
	public function get_item_name(): string {
		if ( null !== $this->item_name ) {
			return $this->item_name;
		}

		return $this->get_license_service()->get_item_name();
	}

	/**
	 * Retrieve the license type descriptor.
	 *
	 * @return string
	 */
	public function get_license_type(): string {
		if ( null !== $this->license_type ) {
			return $this->license_type;
		}

		$license_type = $this->get_license_service()->get_license_type();

		return false === $license_type ? '' : $license_type;
	}

	/**
	 * Retrieve the plan ID associated with the license.
	 *
	 * @return string
	 */
	public function get_plan_id(): string {
		if ( null !== $this->license_type ) {
			return $this->license_type;
		}

		return $this->plan_service->get_current_plan_id();
	}

	/**
	 * Retrieve the formatted renewal date.
	 *
	 * @return string
	 */
	public function get_renewal_date_formatted(): string {
		if ( null !== $this->renewal_date_formatted ) {
			return $this->renewal_date_formatted;
		}

		return $this->get_license_service()->get_renewal_date_formatted();
	}

	/**
	 * Retrieve the URL to purchase credits.
	 *
	 * @return string
	 */
	public function get_url_get_credits(): string {
		if ( null !== $this->url_get_credits ) {
			return $this->url_get_credits;
		}

		return $this->get_license_service()->get_url_get_credits();
	}
}
