<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Block\Value_Objects;

/**
 * Recipe-instance backup envelope.
 *
 * Three-keyed integration display payload carried on item and modifier rows
 * so the frontend can show a deterministic chip even when the underlying
 * integration is unavailable. Frozen after construction.
 *
 * `from_array` tolerates the legacy `{integrationName, nameDynamic, titleHTML}`
 * keys alongside the canonical `{integration_name, sentence, sentence_html}`
 * shape so existing migration paths round-trip without separate adapters.
 * Canonical keys win when both shapes are present.
 */
final class Recipe_Backup {

	/**
	 * Integration display name (e.g. "WordPress").
	 *
	 * @var string
	 */
	private $integration_name;

	/**
	 * Plain-text sentence displayed on the item / modifier chip.
	 *
	 * @var string
	 */
	private $sentence;

	/**
	 * HTML sentence displayed when rich rendering is supported.
	 *
	 * @var string
	 */
	private $sentence_html;

	/**
	 * Construct from canonical scalar values.
	 *
	 * @param string $integration_name Integration display name.
	 * @param string $sentence         Plain-text chip sentence.
	 * @param string $sentence_html    HTML chip sentence.
	 */
	public function __construct( string $integration_name, string $sentence, string $sentence_html ) {
		$this->integration_name = $integration_name;
		$this->sentence         = $sentence;
		$this->sentence_html    = $sentence_html;
	}

	/**
	 * Build from a raw associative array tolerating both canonical and legacy
	 * key conventions. Missing keys default to empty strings.
	 *
	 * @param array $data Raw payload — accepts canonical and/or legacy keys.
	 * @return self
	 */
	public static function from_array( array $data ): self {
		return new self(
			(string) ( $data['integration_name'] ?? $data['integrationName'] ?? '' ),
			(string) ( $data['sentence'] ?? $data['nameDynamic'] ?? '' ),
			(string) ( $data['sentence_html'] ?? $data['titleHTML'] ?? '' )
		);
	}

	/**
	 * Display name of the integration, as it read when the row was last saved.
	 *
	 * @return string
	 */
	public function get_integration_name(): string {
		return $this->integration_name;
	}

	/**
	 * Plain-text sentence describing the row, for display when the integration cannot regenerate it.
	 *
	 * @return string
	 */
	public function get_sentence(): string {
		return $this->sentence;
	}

	/**
	 * The same sentence with its markup, for display where rich text is allowed.
	 *
	 * @return string
	 */
	public function get_sentence_html(): string {
		return $this->sentence_html;
	}

	/**
	 * Serialize to the canonical wire shape.
	 *
	 * @return array{integration_name: string, sentence: string, sentence_html: string}
	 */
	public function to_array(): array {
		return array(
			'integration_name' => $this->integration_name,
			'sentence'         => $this->sentence,
			'sentence_html'    => $this->sentence_html,
		);
	}
}
