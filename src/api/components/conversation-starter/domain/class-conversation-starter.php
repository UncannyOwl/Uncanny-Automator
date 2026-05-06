<?php
/**
 * Conversation Starter domain model.
 *
 * @package Uncanny_Automator\Api\Components\Conversation_Starter\Domain
 * @since 7.2
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Components\Conversation_Starter\Domain;

use InvalidArgumentException;

/**
 * Represents the exact conversation starter shape expected by the chat SDK.
 */
final class Conversation_Starter {

	/**
	 * Required array keys.
	 *
	 * @var string[]
	 */
	private const REQUIRED_KEYS = array( 'id', 'label', 'prompt' );

	/**
	 * Starter ID.
	 *
	 * @var int
	 */
	private int $id;

	/**
	 * Starter label shown in the UI.
	 *
	 * @var string
	 */
	private string $label;

	/**
	 * Prompt sent when the starter is selected.
	 *
	 * @var string
	 */
	private string $prompt;

	/**
	 * Constructor.
	 *
	 * @param int    $id Starter ID.
	 * @param string $label Starter label.
	 * @param string $prompt Starter prompt.
	 */
	public function __construct( int $id, string $label, string $prompt ) {

		if ( $id <= 0 ) {
			throw new InvalidArgumentException( 'Conversation starter ID must be greater than zero.' );
		}

		$label  = trim( $label );
		$prompt = trim( $prompt );

		if ( '' === $label ) {
			throw new InvalidArgumentException( 'Conversation starter label must not be empty.' );
		}

		if ( '' === $prompt ) {
			throw new InvalidArgumentException( 'Conversation starter prompt must not be empty.' );
		}

		$this->id     = $id;
		$this->label  = $label;
		$this->prompt = $prompt;
	}

	/**
	 * Get the starter ID.
	 *
	 * @return int
	 */
	public function get_id(): int {
		return $this->id;
	}

	/**
	 * Get the starter label.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return $this->label;
	}

	/**
	 * Get the starter prompt.
	 *
	 * @return string
	 */
	public function get_prompt(): string {
		return $this->prompt;
	}

	/**
	 * Create from the SDK response array shape.
	 *
	 * @param array<string,mixed> $data Starter data.
	 *
	 * @return self
	 */
	public static function from_array( array $data ): self {

		self::assert_exact_keys( $data );

		if ( ! is_int( $data['id'] ) ) {
			throw new InvalidArgumentException( 'Conversation starter ID must be an integer.' );
		}

		if ( ! is_string( $data['label'] ) ) {
			throw new InvalidArgumentException( 'Conversation starter label must be a string.' );
		}

		if ( ! is_string( $data['prompt'] ) ) {
			throw new InvalidArgumentException( 'Conversation starter prompt must be a string.' );
		}

		return new self( $data['id'], $data['label'], $data['prompt'] );
	}

	/**
	 * Convert to the exact SDK response array shape.
	 *
	 * @return array{id:int,label:string,prompt:string}
	 */
	public function to_array(): array {
		return array(
			'id'     => $this->id,
			'label'  => $this->label,
			'prompt' => $this->prompt,
		);
	}

	/**
	 * Assert the input has exactly the keys supported by the SDK schema.
	 *
	 * @param array<string,mixed> $data Starter data.
	 *
	 * @return void
	 */
	private static function assert_exact_keys( array $data ): void {

		$keys    = array_keys( $data );
		$missing = array_diff( self::REQUIRED_KEYS, $keys );
		$extra   = array_diff( $keys, self::REQUIRED_KEYS );

		if ( ! empty( $missing ) || ! empty( $extra ) ) {
			throw new InvalidArgumentException( 'Conversation starter must contain exactly id, label, and prompt.' );
		}
	}
}
