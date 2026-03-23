<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Client;

/**
 * Builder for Client_Payload_Service instances.
 */
class Client_Payload_Service_Builder {

	/**
	 * Token service to inject.
	 *
	 * @var Client_Token_Service|null
	 */
	private ?Client_Token_Service $token_service = null;

	/**
	 * Public key manager to inject.
	 *
	 * @var Client_Public_Key_Manager|null
	 */
	private ?Client_Public_Key_Manager $public_key_manager = null;

	/**
	 * Context override.
	 *
	 * @var Client_Payload_Context|null
	 */
	private ?Client_Payload_Context $context = null;

	/**
	 * Predefined current user.
	 *
	 * @var object|\WP_User|null
	 */
	private ?object $current_user = null;

	/**
	 * MCP REST endpoint override.
	 *
	 * @var string|null
	 */
	private ?string $mcp_rest_url = null;

	/**
	 * UUID override.
	 *
	 * @var string|null
	 */
	private ?string $uuid = null;

	/**
	 * Timestamp override.
	 *
	 * @var int|null
	 */
	private ?int $timestamp = null;

	/**
	 * Request URI override.
	 *
	 * @var string|null
	 */
	private ?string $request_uri = null;

	/**
	 * License key override.
	 *
	 * @var string|null
	 */
	private ?string $license_key = null;

	/**
	 * Site name override.
	 *
	 * @var string|null
	 */
	private ?string $site_name = null;

	/**
	 * Item name override.
	 *
	 * @var string|null
	 */
	private ?string $item_name = null;

	/**
	 * License type override.
	 *
	 * @var string|null
	 */
	private ?string $license_type = null;

	/**
	 * Provide a token service instance.
	 *
	 * @param Client_Token_Service $service Token service.
	 * @return self
	 */
	public function with_token_service( Client_Token_Service $service ): self {
		$this->token_service = $service;
		return $this;
	}

	/**
	 * Provide a public key manager instance.
	 *
	 * @param Client_Public_Key_Manager $manager Public key manager.
	 * @return self
	 */
	public function with_public_key_manager( Client_Public_Key_Manager $manager ): self {
		$this->public_key_manager = $manager;
		return $this;
	}

	/**
	 * Provide a payload context instance.
	 *
	 * @param Client_Payload_Context $context Payload context.
	 * @return self
	 */
	public function with_context( Client_Payload_Context $context ): self {
		$this->context = $context;
		return $this;
	}

	/**
	 * Override the current user object.
	 *
	 * @param object|\WP_User $user Current user.
	 * @return self
	 */
	public function with_current_user( object $user ): self {
		$this->current_user = $user;
		return $this;
	}

	/**
	 * Override the MCP REST URL.
	 *
	 * @param string $url REST URL.
	 * @return self
	 */
	public function with_mcp_rest_url( string $url ): self {
		$this->mcp_rest_url = $url;
		return $this;
	}

	/**
	 * Override the UUID.
	 *
	 * @param string $uuid UUID string.
	 * @return self
	 */
	public function with_uuid( string $uuid ): self {
		$this->uuid = $uuid;
		return $this;
	}

	/**
	 * Override the timestamp.
	 *
	 * @param int $timestamp Timestamp.
	 * @return self
	 */
	public function with_timestamp( int $timestamp ): self {
		$this->timestamp = $timestamp;
		return $this;
	}

	/**
	 * Override the request URI.
	 *
	 * @param string $uri Request URI.
	 * @return self
	 */
	public function with_request_uri( string $uri ): self {
		$this->request_uri = $uri;
		return $this;
	}

	/**
	 * Override the license key.
	 *
	 * @param string $key License key.
	 * @return self
	 */
	public function with_license_key( string $key ): self {
		$this->license_key = $key;
		return $this;
	}

	/**
	 * Override the site name.
	 *
	 * @param string $name Site name.
	 * @return self
	 */
	public function with_site_name( string $name ): self {
		$this->site_name = $name;
		return $this;
	}

	/**
	 * Override the item name.
	 *
	 * @param string $name Item name.
	 * @return self
	 */
	public function with_item_name( string $name ): self {
		$this->item_name = $name;
		return $this;
	}

	/**
	 * Override the license type.
	 *
	 * @param string $type License type.
	 * @return self
	 */
	public function with_license_type( string $type ): self {
		$this->license_type = $type;
		return $this;
	}

	/**
	 * Build the payload service instance using provided overrides.
	 *
	 * @return Client_Payload_Service
	 */
	public function build(): Client_Payload_Service {
		$context = $this->context;

		if ( null === $context ) {
			$context = new Client_Payload_Context(
				$this->current_user,
				$this->mcp_rest_url,
				$this->uuid,
				$this->timestamp,
				$this->request_uri,
				$this->license_key,
				$this->site_name,
				$this->item_name,
				$this->license_type
			);
		}

		return new Client_Payload_Service(
			$this->token_service ? $this->token_service : new Client_Token_Service(),
			$this->public_key_manager ? $this->public_key_manager : new Client_Public_Key_Manager(),
			$context
		);
	}
}
