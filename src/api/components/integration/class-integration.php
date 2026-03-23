<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Integration;

use Uncanny_Automator\Api\Components\Integration\Value_Objects\Integration_Code;
use Uncanny_Automator\Api\Components\Integration\Value_Objects\Integration_Name;
use Uncanny_Automator\Api\Components\Integration\Value_Objects\Integration_Required_Tier;
use Uncanny_Automator\Api\Components\Integration\Value_Objects\Integration_Type;
use Uncanny_Automator\Api\Components\Integration\Value_Objects\Integration_Details;
use Uncanny_Automator\Api\Components\Integration\Value_Objects\Integration_Items;
use Uncanny_Automator\Api\Components\Integration\Value_Objects\Integration_Tokens;
use Uncanny_Automator\Api\Components\Integration\Value_Objects\Integration_Connected;
use Uncanny_Automator\Api\Services\Dependency\Dependency_Evaluatable;
use Uncanny_Automator\Api\Services\Scope_Tag\Scope_Tag_Evaluatable;
use InvalidArgumentException;

/**
 * Integration Aggregate.
 *
 * Pure domain object representing an Automator integration.
 * Contains zero WordPress dependencies - pure PHP business logic only.
 *
 * This represents the "unscoped" integration object - general, static information
 * about the integration (name, icon, triggers/actions list) that doesn't change
 * based on user context, license, or recipe building state.
 *
 * @since 7.0.0
 */
class Integration implements Dependency_Evaluatable, Scope_Tag_Evaluatable {

	/**
	 * The integration code.
	 *
	 * @var Integration_Code<string>
	 */
	private Integration_Code $code;

	/**
	 * The integration name.
	 *
	 * @var Integration_Name<string>
	 */
	private Integration_Name $name;

	/**
	 * The required tier.
	 *
	 * @var Integration_Required_Tier<string>
	 */
	private Integration_Required_Tier $required_tier;

	/**
	 * The integration type.
	 *
	 * @var Integration_Type<string>
	 */
	private Integration_Type $type;

	/**
	 * The integration details.
	 *
	 * @var Integration_Details<array>
	 */
	private Integration_Details $details;

	/**
	 * The integration items.
	 *
	 * @var Integration_Items<array>
	 */
	private Integration_Items $items;

	/**
	 * The integration tokens.
	 *
	 * @var Integration_Tokens<array>
	 */
	private Integration_Tokens $tokens;

	/**
	 * The connected status.
	 *
	 * @var Integration_Connected<bool>
	 */
	private ?Integration_Connected $connected = null;

	/**
	 * Constructor.
	 *
	 * @param Integration_Config $config Integration configuration object.
	 *  @property Integration_Code<string> $code Integration code.
	 *  @property Integration_Name<string> $name Integration name.
	 *  @property Integration_Required_Tier<string> $required_tier Required tier.
	 *  @property Integration_Type<string> $type Integration type.
	 *  @property Integration_Details<array> $details Integration details.
	 *  @property Integration_Items<array> $items Integration items.
	 *  @property Integration_Tokens<array> $tokens Integration tokens.
	 *  @property ?Integration_Connected<bool> $connected Connected status.
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid configuration.
	 */
	public function __construct( Integration_Config $config ) {

		// Use value objects to ensure data integrity on instance creation instead of runtime.
		// This way, once the instance is created, we can be sure it's valid.
		// Any invalid data will throw an exception here.
		// This also makes the class immutable after creation.
		// Any changes require creating a new instance with new data.
		$this->code          = new Integration_Code( $config->get_code() );
		$this->name          = new Integration_Name( $config->get_name() );
		$this->required_tier = new Integration_Required_Tier( $config->get_required_tier() );
		$this->type          = new Integration_Type( $config->get_type() );
		$this->details       = new Integration_Details( $config->get_details() );
		$this->items         = new Integration_Items( $config->get_items() );
		$this->tokens        = new Integration_Tokens( $config->get_tokens() );

		// Include connected status for integrations that require connection.
		if ( null !== $config->get_connected() ) {
			$this->connected = new Integration_Connected( $config->get_connected() );
		}

		$this->validate_business_rules();
	}

	/**
	 * Get integration code.
	 *
	 * @return Integration_Code Integration code.
	 */
	public function get_code(): Integration_Code {
		return $this->code;
	}

	/**
	 * Get integration name.
	 *
	 * @return Integration_Name Integration name.
	 */
	public function get_name(): Integration_Name {
		return $this->name;
	}

	/**
	 * Get required tier.
	 *
	 * @return Integration_Required_Tier Required tier.
	 */
	public function get_required_tier(): Integration_Required_Tier {
		return $this->required_tier;
	}

	/**
	 * Get integration type.
	 *
	 * @return Integration_Type Integration type.
	 */
	public function get_type(): Integration_Type {
		return $this->type;
	}

	/**
	 * Get integration details.
	 *
	 * @return Integration_Details Integration details.
	 */
	public function get_details(): Integration_Details {
		return $this->details;
	}

	/**
	 * Get integration items.
	 *
	 * @return Integration_Items Integration items.
	 */
	public function get_items(): Integration_Items {
		return $this->items;
	}

	/**
	 * Get integration tokens.
	 *
	 * @return Integration_Tokens Integration tokens.
	 */
	public function get_tokens(): Integration_Tokens {
		return $this->tokens;
	}

	/**
	 * Get connected status.
	 *
	 * @return Integration_Connected|null Connected status (null if not applicable).
	 */
	public function get_connected(): ?Integration_Connected {
		return $this->connected;
	}

	/**
	 * Check if integration is a plugin integration.
	 *
	 * @return bool
	 */
	public function is_plugin(): bool {
		return $this->type->is_plugin();
	}

	/**
	 * Check if integration is an app integration.
	 *
	 * @return bool
	 */
	public function is_app(): bool {
		return $this->type->is_app();
	}

	/**
	 * Check if integration is a built-in integration.
	 *
	 * @return bool
	 */
	public function is_built_in(): bool {
		return $this->type->is_built_in();
	}

	/**
	 * Check if integration requires Pro license.
	 *
	 * @return bool
	 */
	public function requires_pro(): bool {
		return $this->required_tier->is_pro();
	}

	/**
	 * Check if integration is connected (for app integrations).
	 *
	 * @return bool
	 */
	public function is_connected(): bool {
		return $this->connected && $this->connected->is_connected();
	}

	/**
	 * Check if integration is a third-party integration.
	 *
	 * @return bool
	 */
	public function is_third_party(): bool {
		$plugin = $this->details->get_plugin();
		return $plugin && $plugin->is_third_party();
	}

	/**
	 * Check if third-party integration requires connection.
	 *
	 * Requires both settings_url (via account details) and connected status.
	 *
	 * @return bool
	 */
	public function does_third_party_require_connection(): bool {
		if ( ! $this->is_third_party() ) {
			return false;
		}

		$account          = $this->details->get_account();
		$has_settings_url = $account && $account->has_settings_url();
		$has_connected    = null !== $this->connected;

		return $has_settings_url && $has_connected;
	}

	/**
	 * Check if integration has settings URL.
	 *
	 * @return bool
	 */
	public function has_settings_url(): bool {
		$account = $this->details->get_account();
		return $account && $account->has_settings_url();
	}

	/**
	 * Check if integration requires connection status.
	 *
	 * Returns true for app integrations or third-party integrations that require connection.
	 *
	 * @return bool
	 */
	public function requires_connection(): bool {
		return null !== $this->connected && ( $this->is_app() || $this->does_third_party_require_connection() );
	}

	/**
	 * Convert to array (includes plugin_details in details for internal use).
	 *
	 * @return array Integration data as array.
	 */
	public function to_array(): array {
		return $this->build_array( false );
	}

	/**
	 * Convert to REST format (excludes plugin_details from details).
	 *
	 * @return array Integration data for REST API response.
	 */
	public function to_rest(): array {
		return $this->build_array( true );
	}

	/**
	 * Build integration array data.
	 *
	 * @param bool $rest_format Whether to use REST format (excludes plugin_details).
	 *
	 * @return array Integration data as array.
	 */
	private function build_array( bool $rest_format ): array {
		$data = array(
			'code'          => $this->code->get_value(),
			'name'          => $this->name->get_value(),
			'required_tier' => $this->required_tier->get_value(),
			'type'          => $this->type->get_value(),
			'details'       => $rest_format ? $this->details->to_rest() : $this->details->to_array(),
			'items'         => $rest_format ? $this->items->to_rest() : $this->items->to_array(),
			'tokens'        => $rest_format ? $this->tokens->to_rest() : $this->tokens->to_array(),
		);

		// Include connected status for integrations that require connection.
		if ( ! $rest_format && $this->requires_connection() ) {
			$data['connected'] = $this->connected->get_value();
		}

		return $data;
	}

	/**
	 * Get entity code (interface implementation).
	 *
	 * Implements Dependency_Evaluatable and Scope_Tag_Evaluatable.
	 *
	 * @return string Integration code.
	 */
	public function get_entity_code(): string {
		return $this->code->get_value();
	}

	/**
	 * Get entity name (interface implementation).
	 *
	 * Implements Dependency_Evaluatable and Scope_Tag_Evaluatable.
	 *
	 * @return string Integration name.
	 */
	public function get_entity_name(): string {
		return $this->name->get_value();
	}

	/**
	 * Get entity required tier (interface implementation).
	 *
	 * Implements Dependency_Evaluatable and Scope_Tag_Evaluatable.
	 *
	 * @return string Required tier.
	 */
	public function get_entity_required_tier(): string {
		return $this->required_tier->get_value();
	}

	/**
	 * Get entity type (interface implementation).
	 *
	 * Implements Dependency_Evaluatable and Scope_Tag_Evaluatable.
	 *
	 * @return string Entity type ('integration').
	 */
	public function get_entity_type(): string {
		return 'integration';
	}

	/**
	 * Validate business rules.
	 *
	 * @return void
	 * @throws InvalidArgumentException If business rules are violated.
	 */
	private function validate_business_rules(): void {
		// Business rule: App integrations must have a connected status.
		if ( $this->type->is_app() && null === $this->connected ) {
			throw new InvalidArgumentException(
				'App integrations must have a connected status'
			);
		}

		// Business rule: Third-party integrations can have connected status only if they have settings_url.
		if ( $this->is_third_party() && null !== $this->connected ) {
			// REVIEW : Is this too limiting?
			// We need a settings URL to be able to connect but who knows how all Third Party integrations handle this.
			$account = $this->details->get_account();
			if ( ! $account || ! $account->has_settings_url() ) {
				throw new InvalidArgumentException(
					'Third-party integrations can only have connected status if they have settings_url'
				);
			}
		}

		// Business rule: Regular plugin and built-in integrations should not have a connected status.
		if ( ! $this->type->is_app() && ! $this->is_third_party() && null !== $this->connected ) {
			throw new InvalidArgumentException(
				'Only app integrations and third-party integrations with settings_url can have a connected status'
			);
		}
	}
}
