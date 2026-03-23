<?php
/**
 * Trigger Search Result Value Object.
 *
 * Represents a single trigger result from component search.
 * This is a read model for the search/catalog bounded context,
 * separate from the Trigger domain entity used in recipes.
 *
 * Uses existing value objects for validation:
 * - Trigger_Code: validates trigger code format
 * - Sentence_String: validates sentence content and length
 * - Integration_Code: validates integration ID
 * - Integration_Name: validates integration name
 * - Integration_Required_Tier: validates tier enum
 * - Component_Availability: availability state
 *
 * @package Uncanny_Automator\Api\Components\Search\Trigger
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Components\Search\Trigger;

use Uncanny_Automator\Api\Components\Search\Shared\Component_Availability;
use Uncanny_Automator\Api\Components\Trigger\Value_Objects\Trigger_Code;
use Uncanny_Automator\Api\Components\Trigger\Value_Objects\Sentence_String;
use Uncanny_Automator\Api\Components\Integration\Value_Objects\Integration_Code;
use Uncanny_Automator\Api\Components\Integration\Value_Objects\Integration_Name;
use Uncanny_Automator\Api\Components\Integration\Value_Objects\Integration_Required_Tier;

/**
 * Value object representing a trigger in search results.
 */
class Trigger_Search_Result {

	/**
	 * Trigger code.
	 *
	 * @var Trigger_Code
	 */
	private Trigger_Code $code;

	/**
	 * Human-readable sentence describing the trigger.
	 *
	 * @var Sentence_String
	 */
	private Sentence_String $sentence;

	/**
	 * Integration ID (e.g., "WPFORMS").
	 *
	 * @var Integration_Code
	 */
	private Integration_Code $integration_id;

	/**
	 * Integration name (e.g., "WPForms").
	 *
	 * @var Integration_Name
	 */
	private Integration_Name $integration_name;

	/**
	 * Required tier for this trigger.
	 *
	 * @var Integration_Required_Tier
	 */
	private Integration_Required_Tier $required_tier;

	/**
	 * Availability information.
	 *
	 * @var Component_Availability
	 */
	private Component_Availability $availability;

	/**
	 * Incompatibility reason if trigger doesn't match recipe type.
	 * Null if compatible.
	 *
	 * @var string|null
	 */
	private ?string $incompatibility_reason;

	/**
	 * Constructor.
	 *
	 * @param Trigger_Code              $code                   Trigger code.
	 * @param Sentence_String           $sentence               Human-readable sentence.
	 * @param Integration_Code          $integration_id         Integration ID.
	 * @param Integration_Name          $integration_name       Integration name.
	 * @param Integration_Required_Tier $required_tier          Required tier.
	 * @param Component_Availability    $availability           Availability info.
	 * @param string|null               $incompatibility_reason Reason if incompatible.
	 */
	public function __construct(
		Trigger_Code $code,
		Sentence_String $sentence,
		Integration_Code $integration_id,
		Integration_Name $integration_name,
		Integration_Required_Tier $required_tier,
		Component_Availability $availability,
		?string $incompatibility_reason = null
	) {
		$this->code                   = $code;
		$this->sentence               = $sentence;
		$this->integration_id         = $integration_id;
		$this->integration_name       = $integration_name;
		$this->required_tier          = $required_tier;
		$this->availability           = $availability;
		$this->incompatibility_reason = $incompatibility_reason;
	}

	/**
	 * Get the trigger code.
	 *
	 * @return Trigger_Code
	 */
	public function get_code(): Trigger_Code {
		return $this->code;
	}

	/**
	 * Get the sentence.
	 *
	 * @return Sentence_String
	 */
	public function get_sentence(): Sentence_String {
		return $this->sentence;
	}

	/**
	 * Get the integration ID.
	 *
	 * @return Integration_Code
	 */
	public function get_integration_id(): Integration_Code {
		return $this->integration_id;
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
	 * Get the required tier.
	 *
	 * @return Integration_Required_Tier
	 */
	public function get_required_tier(): Integration_Required_Tier {
		return $this->required_tier;
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
	 * Check if this trigger is incompatible with the current recipe type.
	 *
	 * @return bool
	 */
	public function is_incompatible(): bool {
		return null !== $this->incompatibility_reason;
	}

	/**
	 * Get the incompatibility reason.
	 *
	 * @return string|null
	 */
	public function get_incompatibility_reason(): ?string {
		return $this->incompatibility_reason;
	}

	/**
	 * Convert to array representation for JSON serialization.
	 *
	 * @return array
	 */
	public function to_array(): array {
		$result = array(
			'type'             => 'trigger',
			'code'             => $this->code->get_value(),
			'sentence'         => $this->sentence->get_value(),
			'integration_id'   => $this->integration_id->get_value(),
			'integration_name' => $this->integration_name->get_value(),
			'required_tier'    => $this->required_tier->get_value(),
			'availability'     => $this->availability->to_array(),
		);

		if ( $this->is_incompatible() ) {
			$result['is_incompatible']        = true;
			$result['incompatibility_reason'] = $this->incompatibility_reason;
		}

		return $result;
	}

	/**
	 * Create from RAG or registry result data.
	 *
	 * Handles field name variations:
	 * - code vs trigger_code
	 * - sentence vs sentence_human_readable
	 * - integration_id vs integration
	 *
	 * @param array                  $data         Result data from RAG or registry.
	 * @param Component_Availability $availability Availability info.
	 * @return self
	 * @throws \InvalidArgumentException If validation fails on any field.
	 */
	public static function from_rag_result( array $data, Component_Availability $availability ): self {
		$incompatibility_reason = null;

		if ( ! empty( $data['is_incompatible'] ) ) {
			$incompatibility_reason = $data['incompatibility_reason'] ?? 'Incompatible with current recipe type';
		}

		// Handle field name variations between RAG and registry.
		$code             = $data['code'] ?? $data['trigger_code'] ?? '';
		$sentence         = $data['sentence'] ?? $data['sentence_human_readable'] ?? '';
		$integration_id   = $data['integration_id'] ?? $data['integration'] ?? '';
		$integration_name = $data['integration_name'] ?? $data['integration'] ?? '';
		$required_tier    = $data['required_tier'] ?? 'lite';

		return new self(
			new Trigger_Code( (string) $code ),
			new Sentence_String( (string) $sentence ),
			new Integration_Code( (string) $integration_id ),
			new Integration_Name( (string) $integration_name ),
			new Integration_Required_Tier( (string) $required_tier ),
			$availability,
			$incompatibility_reason
		);
	}
}
