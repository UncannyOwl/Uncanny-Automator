<?php
/**
 * Component Availability Value Object.
 *
 * Represents the availability state of a component in search results.
 * Used across trigger, action, and condition search results.
 *
 * @package Uncanny_Automator\Api\Components\Search\Shared
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Components\Search\Shared;

/**
 * Value object representing component availability.
 */
class Component_Availability {

	/**
	 * Whether the component is available for use.
	 *
	 * @var bool
	 */
	private bool $available;

	/**
	 * Message explaining availability status.
	 *
	 * @var string
	 */
	private string $message;

	/**
	 * List of blockers preventing availability.
	 *
	 * @var string[]
	 */
	private array $blockers;

	/**
	 * Constructor.
	 *
	 * @param bool     $available Whether the component is available.
	 * @param string   $message   Message explaining availability.
	 * @param string[] $blockers  List of blockers.
	 */
	public function __construct( bool $available, string $message = '', array $blockers = array() ) {
		$this->available = $available;
		$this->message   = $message;
		$this->blockers  = $blockers;
	}

	/**
	 * Check if the component is available.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return $this->available;
	}

	/**
	 * Get the availability message.
	 *
	 * @return string
	 */
	public function get_message(): string {
		return $this->message;
	}

	/**
	 * Get the list of blockers.
	 *
	 * @return string[]
	 */
	public function get_blockers(): array {
		return $this->blockers;
	}

	/**
	 * Convert to array representation.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return array(
			'available' => $this->available,
			'message'   => $this->message,
			'blockers'  => $this->blockers,
		);
	}

	/**
	 * Create an available component.
	 *
	 * @return self
	 */
	public static function available(): self {
		return new self( true );
	}

	/**
	 * Create an unavailable component.
	 *
	 * @param string   $message  Message explaining why unavailable.
	 * @param string[] $blockers List of blockers.
	 * @return self
	 */
	public static function unavailable( string $message, array $blockers = array() ): self {
		return new self( false, $message, $blockers );
	}

	/**
	 * Create from array data.
	 *
	 * @param array $data Array with 'available', 'message', 'blockers' keys.
	 * @return self
	 */
	public static function from_array( array $data ): self {
		return new self(
			(bool) ( $data['available'] ?? false ),
			(string) ( $data['message'] ?? '' ),
			(array) ( $data['blockers'] ?? array() )
		);
	}
}
