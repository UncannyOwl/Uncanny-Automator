<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Action\Dtos;

use Uncanny_Automator\Api\Components\Action\Value_Objects\Async_Custom;

/**
 * Async Custom DTO.
 *
 * Data Transfer Object for custom mode async configuration.
 *
 * @since 7.0.0
 */
class Async_Custom_Dto {

	/**
	 * Custom value.
	 *
	 * @var Async_Custom|null
	 */
	private ?Async_Custom $custom;

	/**
	 * Constructor.
	 *
	 * @param string|null $custom Custom async value.
	 */
	public function __construct( ?string $custom = null ) {
		$this->custom = null !== $custom ? new Async_Custom( $custom ) : null;
	}

	/**
	 * Get custom value.
	 *
	 * @return Async_Custom|null
	 */
	public function get_custom(): ?Async_Custom {
		return $this->custom;
	}

	/**
	 * Check if DTO has data.
	 *
	 * @return bool
	 */
	public function is_complete(): bool {
		return null !== $this->custom;
	}

	/**
	 * Generate human-readable sentence.
	 *
	 * @return string
	 */
	public function to_sentence(): string {
		if ( ! $this->is_complete() ) {
			return '';
		}

		// For custom, we don't generate a sentence - just return the value
		return $this->custom->get_value();
	}

	/**
	 * Convert to array for storage.
	 *
	 * @return array
	 */
	public function to_array(): array {
		$data = array();

		if ( null !== $this->custom ) {
			$data['custom'] = $this->custom->get_value();
		}

		return $data;
	}
}
