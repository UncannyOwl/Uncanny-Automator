<?php
/**
 * Integration Developer Details Value Object
 *
 * Developer information for integrations with smart fallback logic.
 *
 * @package Uncanny_Automator\Api\Components\Integration\Value_Objects
 * @since 7.0.0
 */
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Integration\Value_Objects;

/**
 * Immutable value object for developer details.
 *
 * @since 7.0.0
 */
class Integration_Developer_Details {

	/**
	 * @var string
	 */
	private string $name;

	/**
	 * @var string|null
	 */
	private ?string $site;

	/**
	 * Constructor.
	 *
	 * @param array $data Developer details data
	 */
	public function __construct( array $data = array() ) {
		$this->name = $data['name'] ?? '';
		$site       = $data['site'] ?? $data['url'] ?? null;
		$this->site = empty( $site ) ? null : $site;
	}

	/**
	 * Get developer name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return $this->name;
	}

	/**
	 * Get developer site URL.
	 *
	 * @return string|null
	 */
	public function get_site(): ?string {
		return $this->site;
	}

	/**
	 * Check if developer details are empty.
	 *
	 * @return bool
	 */
	public function is_empty(): bool {
		return empty( $this->name ) && empty( $this->site );
	}

	/**
	 * Convert to array.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return array(
			'name' => $this->name,
			'site' => $this->site,
		);
	}
}
