<?php
/**
 * Loopable Token Search Result Value Object.
 *
 * Represents a single loopable token result from component search.
 * This is a read model for the search/catalog bounded context.
 *
 * @package Uncanny_Automator\Api\Components\Search\Loopable_Token
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Components\Search\Loopable_Token;

use Uncanny_Automator\Api\Components\Search\Shared\Component_Availability;
use Uncanny_Automator\Api\Components\Integration\Value_Objects\Integration_Code;
use Uncanny_Automator\Api\Components\Integration\Value_Objects\Integration_Name;

/**
 * Value object representing a loopable token in search results.
 */
class Loopable_Token_Search_Result {

	/**
	 * Token ID (e.g., TOKEN_EXTENDED:DATA_TOKEN_USER_ORDERS:UNIVERSAL:WC:USER_ORDERS).
	 *
	 * @var string
	 */
	private string $id;

	/**
	 * Human-readable name describing the token.
	 *
	 * @var string
	 */
	private string $name;

	/**
	 * Integration code (e.g., "WC").
	 *
	 * @var Integration_Code
	 */
	private Integration_Code $integration_code;

	/**
	 * Integration name (e.g., "WooCommerce").
	 *
	 * @var Integration_Name
	 */
	private Integration_Name $integration_name;

	/**
	 * Whether this token requires a user context.
	 *
	 * @var bool
	 */
	private bool $requires_user;

	/**
	 * Availability information.
	 *
	 * @var Component_Availability
	 */
	private Component_Availability $availability;

	/**
	 * Constructor.
	 *
	 * @param string                 $id               Token ID.
	 * @param string                 $name             Human-readable name.
	 * @param Integration_Code       $integration_code Integration code.
	 * @param Integration_Name       $integration_name Integration name.
	 * @param bool                   $requires_user    Whether user context is required.
	 * @param Component_Availability $availability     Availability info.
	 */
	public function __construct(
		string $id,
		string $name,
		Integration_Code $integration_code,
		Integration_Name $integration_name,
		bool $requires_user,
		Component_Availability $availability
	) {
		$this->validate_id( $id );
		$this->id               = $id;
		$this->name             = $name;
		$this->integration_code = $integration_code;
		$this->integration_name = $integration_name;
		$this->requires_user    = $requires_user;
		$this->availability     = $availability;
	}

	/**
	 * Validate token ID.
	 *
	 * @param string $id Token ID.
	 * @throws \InvalidArgumentException If ID is empty.
	 */
	private function validate_id( string $id ): void {
		if ( empty( trim( $id ) ) ) {
			throw new \InvalidArgumentException( 'Loopable token ID cannot be empty' );
		}
	}

	/**
	 * Get the token ID.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return $this->id;
	}

	/**
	 * Get the name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return $this->name;
	}

	/**
	 * Get the integration code.
	 *
	 * @return Integration_Code
	 */
	public function get_integration_code(): Integration_Code {
		return $this->integration_code;
	}

	/**
	 * Get the integration name.
	 *
	 * @return Integration_Name
	 */
	public function get_integration_name(): Integration_Name {
		return $this->integration_name;
	}

	/**
	 * Check if user context is required.
	 *
	 * @return bool
	 */
	public function requires_user(): bool {
		return $this->requires_user;
	}

	/**
	 * Get availability information.
	 *
	 * @return Component_Availability
	 */
	public function get_availability(): Component_Availability {
		return $this->availability;
	}

	/**
	 * Convert to array representation for JSON serialization.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return array(
			'type'             => 'loopable_token',
			'id'               => $this->id,
			'name'             => $this->name,
			'integration_code' => $this->integration_code->get_value(),
			'integration_name' => $this->integration_name->get_value(),
			'requires_user'    => $this->requires_user,
			'availability'     => $this->availability->to_array(),
		);
	}

	/**
	 * Create from registry token data.
	 *
	 * @param array                  $data         Token data from registry.
	 * @param Component_Availability $availability Availability info.
	 * @return self
	 * @throws \InvalidArgumentException If validation fails on any field.
	 */
	public static function from_registry_data( array $data, Component_Availability $availability ): self {
		$id               = $data['id'] ?? '';
		$name             = $data['name'] ?? '';
		$integration_code = $data['integration'] ?? '';
		$integration_name = $data['integration_name'] ?? $integration_code;
		$requires_user    = $data['requiresUser'] ?? false;

		return new self(
			(string) $id,
			(string) $name,
			new Integration_Code( (string) $integration_code ),
			new Integration_Name( (string) $integration_name ),
			(bool) $requires_user,
			$availability
		);
	}
}
