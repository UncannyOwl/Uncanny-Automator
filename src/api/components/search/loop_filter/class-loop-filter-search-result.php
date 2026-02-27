<?php
/**
 * Loop Filter Search Result Value Object.
 *
 * Represents a single loop filter result from component search.
 * This is a read model for the search/catalog bounded context.
 *
 * @package Uncanny_Automator\Api\Components\Search\Loop_Filter
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Components\Search\Loop_Filter;

use Uncanny_Automator\Api\Components\Search\Shared\Component_Availability;
use Uncanny_Automator\Api\Components\Trigger\Value_Objects\Sentence_String;
use Uncanny_Automator\Api\Components\Integration\Value_Objects\Integration_Code;
use Uncanny_Automator\Api\Components\Integration\Value_Objects\Integration_Name;

/**
 * Value object representing a loop filter in search results.
 */
class Loop_Filter_Search_Result {

	/**
	 * Filter code.
	 *
	 * @var string
	 */
	private string $code;

	/**
	 * Human-readable sentence describing the filter.
	 *
	 * @var Sentence_String
	 */
	private Sentence_String $sentence;

	/**
	 * Integration code (e.g., "WP").
	 *
	 * @var Integration_Code
	 */
	private Integration_Code $integration_code;

	/**
	 * Integration name (e.g., "WordPress").
	 *
	 * @var Integration_Name
	 */
	private Integration_Name $integration_name;

	/**
	 * Iteration types this filter supports.
	 *
	 * @var array
	 */
	private array $iteration_types;

	/**
	 * Whether this filter requires Pro.
	 *
	 * @var bool
	 */
	private bool $is_pro;

	/**
	 * Availability information.
	 *
	 * @var Component_Availability
	 */
	private Component_Availability $availability;

	/**
	 * Constructor.
	 *
	 * @param string                 $code             Filter code.
	 * @param Sentence_String        $sentence         Human-readable sentence.
	 * @param Integration_Code       $integration_code Integration code.
	 * @param Integration_Name       $integration_name Integration name.
	 * @param array                  $iteration_types  Supported iteration types.
	 * @param bool                   $is_pro           Whether Pro is required.
	 * @param Component_Availability $availability     Availability info.
	 */
	public function __construct(
		string $code,
		Sentence_String $sentence,
		Integration_Code $integration_code,
		Integration_Name $integration_name,
		array $iteration_types,
		bool $is_pro,
		Component_Availability $availability
	) {
		$this->validate_code( $code );
		$this->code             = $code;
		$this->sentence         = $sentence;
		$this->integration_code = $integration_code;
		$this->integration_name = $integration_name;
		$this->iteration_types  = $iteration_types;
		$this->is_pro           = $is_pro;
		$this->availability     = $availability;
	}

	/**
	 * Validate filter code.
	 *
	 * @param string $code Filter code.
	 * @throws \InvalidArgumentException If code is empty.
	 */
	private function validate_code( string $code ): void {
		if ( empty( trim( $code ) ) ) {
			throw new \InvalidArgumentException( 'Loop filter code cannot be empty' );
		}
	}

	/**
	 * Get the filter code.
	 *
	 * @return string
	 */
	public function get_code(): string {
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
	 * Get the iteration types.
	 *
	 * @return array
	 */
	public function get_iteration_types(): array {
		return $this->iteration_types;
	}

	/**
	 * Check if Pro is required.
	 *
	 * @return bool
	 */
	public function is_pro(): bool {
		return $this->is_pro;
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
			'type'             => 'loop_filter',
			'code'             => $this->code,
			'sentence'         => $this->sentence->get_value(),
			'integration_code' => $this->integration_code->get_value(),
			'integration_name' => $this->integration_name->get_value(),
			'iteration_types'  => $this->iteration_types,
			'required_tier'    => $this->is_pro ? 'pro' : 'lite',
			'availability'     => $this->availability->to_array(),
		);
	}

	/**
	 * Create from RAG search result data.
	 *
	 * Handles field name variations between RAG API and internal formats.
	 *
	 * @param array                  $data         Filter data from RAG API.
	 * @param Component_Availability $availability Availability info.
	 * @return self
	 * @throws \InvalidArgumentException If validation fails on any field.
	 */
	public static function from_rag_result( array $data, Component_Availability $availability ): self {
		// Handle field name variations between RAG and registry formats.
		$code             = $data['code'] ?? '';
		$sentence         = $data['sentence'] ?? $data['sentence_readable'] ?? '';
		$integration_code = $data['integration_id'] ?? $data['integration_code'] ?? '';
		$integration_name = $data['integration_name'] ?? $data['integration'] ?? $integration_code;
		$iteration_types  = $data['iteration_types'] ?? array();
		$required_tier    = $data['required_tier'] ?? 'lite';
		$is_pro           = 'pro' === $required_tier || ! empty( $data['is_pro'] );

		return new self(
			(string) $code,
			new Sentence_String( (string) $sentence ),
			new Integration_Code( (string) $integration_code ),
			new Integration_Name( (string) $integration_name ),
			$iteration_types,
			$is_pro,
			$availability
		);
	}
}
