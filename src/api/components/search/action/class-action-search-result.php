<?php
/**
 * Action Search Result Value Object.
 *
 * Represents a single action result from component search.
 * This is a read model for the search/catalog bounded context.
 *
 * Uses existing value objects for validation:
 * - Action_Code: validates action code format
 * - Sentence_String: validates sentence content and length
 * - Integration_Code: validates integration ID
 * - Integration_Name: validates integration name
 * - Integration_Required_Tier: validates tier enum
 * - Component_Availability: availability state
 *
 * @package Uncanny_Automator\Api\Components\Search\Action
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Components\Search\Action;

use Uncanny_Automator\Api\Components\Search\Shared\Component_Availability;
use Uncanny_Automator\Api\Components\Action\Value_Objects\Action_Code;
use Uncanny_Automator\Api\Components\Trigger\Value_Objects\Sentence_String;
use Uncanny_Automator\Api\Components\Integration\Value_Objects\Integration_Code;
use Uncanny_Automator\Api\Components\Integration\Value_Objects\Integration_Name;
use Uncanny_Automator\Api\Components\Integration\Value_Objects\Integration_Required_Tier;

/**
 * Value object representing an action in search results.
 */
class Action_Search_Result {

	/**
	 * Action code.
	 *
	 * @var Action_Code
	 */
	private Action_Code $code;

	/**
	 * Human-readable sentence describing the action.
	 *
	 * @var Sentence_String
	 */
	private Sentence_String $sentence;

	/**
	 * Integration ID (e.g., "WC").
	 *
	 * @var Integration_Code
	 */
	private Integration_Code $integration_id;

	/**
	 * Integration name (e.g., "WooCommerce").
	 *
	 * @var Integration_Name
	 */
	private Integration_Name $integration_name;

	/**
	 * Required tier for this action.
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
	 * Constructor.
	 *
	 * @param Action_Code               $code             Action code.
	 * @param Sentence_String           $sentence         Human-readable sentence.
	 * @param Integration_Code          $integration_id   Integration ID.
	 * @param Integration_Name          $integration_name Integration name.
	 * @param Integration_Required_Tier $required_tier    Required tier.
	 * @param Component_Availability    $availability     Availability info.
	 */
	public function __construct(
		Action_Code $code,
		Sentence_String $sentence,
		Integration_Code $integration_id,
		Integration_Name $integration_name,
		Integration_Required_Tier $required_tier,
		Component_Availability $availability
	) {
		$this->code             = $code;
		$this->sentence         = $sentence;
		$this->integration_id   = $integration_id;
		$this->integration_name = $integration_name;
		$this->required_tier    = $required_tier;
		$this->availability     = $availability;
	}

	/**
	 * Get the action code.
	 *
	 * @return Action_Code
	 */
	public function get_code(): Action_Code {
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
	 * Convert to array representation for JSON serialization.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return array(
			'type'             => 'action',
			'code'             => $this->code->get_value(),
			'sentence'         => $this->sentence->get_value(),
			'integration_id'   => $this->integration_id->get_value(),
			'integration_name' => $this->integration_name->get_value(),
			'required_tier'    => $this->required_tier->get_value(),
			'availability'     => $this->availability->to_array(),
		);
	}

	/**
	 * Create from RAG or registry result data.
	 *
	 * Handles field name variations:
	 * - code vs action_code
	 * - sentence vs sentence_human_readable
	 * - integration_id vs integration
	 *
	 * @param array                  $data         Result data from RAG or registry.
	 * @param Component_Availability $availability Availability info.
	 * @return self
	 * @throws \InvalidArgumentException If validation fails on any field.
	 */
	public static function from_rag_result( array $data, Component_Availability $availability ): self {
		// Handle field name variations between RAG and registry.
		$code             = $data['code'] ?? $data['action_code'] ?? '';
		$sentence         = $data['sentence'] ?? $data['sentence_human_readable'] ?? '';
		$integration_id   = $data['integration_id'] ?? $data['integration'] ?? '';
		$integration_name = $data['integration_name'] ?? $data['integration_label'] ?? $integration_id;
		$required_tier    = $data['required_tier'] ?? 'lite';

		return new self(
			new Action_Code( (string) $code ),
			new Sentence_String( (string) $sentence ),
			new Integration_Code( (string) $integration_id ),
			new Integration_Name( (string) $integration_name ),
			new Integration_Required_Tier( (string) $required_tier ),
			$availability
		);
	}
}
