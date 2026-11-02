<?php
/**
 * Loop Filter Search Result Value Object.
 *
 * Represents a single loop filter result from component search.
 * This is a read model for the search/catalog bounded context.
 *
 * @package Uncanny_Automator\App\Infrastructure\Search\Loop_Filter
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Infrastructure\Search\Loop_Filter;

use Uncanny_Automator\App\Infrastructure\Search\Shared\Component_Availability;
use Uncanny_Automator\App\Plan\Domain\Plan_Levels;
use Uncanny_Automator\App\Recipe_Builder\Trigger\Value_Objects\Sentence_String;
use Uncanny_Automator\App\Integration_Catalog\Integration\Value_Objects\Integration_Code;
use Uncanny_Automator\App\Integration_Catalog\Integration\Value_Objects\Integration_Name;
use Uncanny_Automator\App\Integration_Catalog\Integration\Value_Objects\Integration_Required_Tier;

/**
 * Value object representing a loop filter in search results.
 */
class Loop_Filter_Search_Result {

	/**
	 * The catalog's tier for a loop filter any Pro plan can use.
	 *
	 * @since 8.0.0
	 *
	 * @var string
	 */
	private const CATALOG_PRO_TIER = 'pro';

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
	 * The plan this filter requires.
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
	 * @param string                    $code             Filter code.
	 * @param Sentence_String           $sentence         Human-readable sentence.
	 * @param Integration_Code          $integration_code Integration code.
	 * @param Integration_Name          $integration_name Integration name.
	 * @param array                     $iteration_types  Supported iteration types.
	 * @param Integration_Required_Tier $required_tier    The plan the filter requires.
	 * @param Component_Availability    $availability     Availability info.
	 */
	public function __construct(
		string $code,
		Sentence_String $sentence,
		Integration_Code $integration_code,
		Integration_Name $integration_name,
		array $iteration_types,
		Integration_Required_Tier $required_tier,
		Component_Availability $availability
	) {
		$this->validate_code( $code );
		$this->code             = $code;
		$this->sentence         = $sentence;
		$this->integration_code = $integration_code;
		$this->integration_name = $integration_name;
		$this->iteration_types  = $iteration_types;
		$this->required_tier    = $required_tier;
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
	 * Get the plan this filter requires.
	 *
	 * @since 8.0.0
	 *
	 * @return Integration_Required_Tier
	 */
	public function get_required_tier(): Integration_Required_Tier {
		return $this->required_tier;
	}

	/**
	 * Check if Pro is required.
	 *
	 * @return bool
	 */
	public function is_pro(): bool {
		return $this->required_tier->is_pro();
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
			'required_tier'    => $this->required_tier->get_value(),
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

		return new self(
			(string) $code,
			new Sentence_String( (string) $sentence ),
			new Integration_Code( (string) $integration_code ),
			new Integration_Name( (string) $integration_name ),
			$iteration_types,
			self::required_tier_from( $data ),
			$availability
		);
	}

	/**
	 * The plan a catalog loop filter row requires, in the plan vocabulary actions and
	 * conditions use (lite, pro-basic, pro-plus, pro-elite).
	 *
	 * The catalog tags a loop filter any Pro plan can use as `pro`, or flags it with
	 * `is_pro`; both read as Pro Basic, the first Pro plan.
	 *
	 * @since 8.0.0
	 *
	 * @param array $data Filter data from the catalog.
	 * @return Integration_Required_Tier
	 * @throws \InvalidArgumentException If the tier is not a plan.
	 */
	public static function required_tier_from( array $data ): Integration_Required_Tier {
		$required_tier = (string) ( $data['required_tier'] ?? Plan_Levels::LITE );

		if ( self::CATALOG_PRO_TIER === $required_tier || ( Plan_Levels::LITE === $required_tier && ! empty( $data['is_pro'] ) ) ) {
			$required_tier = Plan_Levels::PRO_BASIC;
		}

		return new Integration_Required_Tier( $required_tier );
	}
}
