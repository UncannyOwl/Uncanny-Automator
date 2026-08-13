<?php
/**
 * Uncanny Agent settings access.
 *
 * @package Uncanny_Automator
 * @since 7.6
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Uncanny_Agent\Domain;

/**
 * Immutable result of the Uncanny Agent settings access rule.
 */
final class Uncanny_Agent_Settings_Access {

	public const FREE       = 'free';
	public const PRO        = 'pro';
	public const NO_LICENSE = 'no_license';

	/**
	 * Settings access result.
	 *
	 * @var string
	 */
	private string $result;

	/**
	 * Create a settings access result.
	 *
	 * @param string $result Settings access result.
	 */
	private function __construct( string $result ) {
		$this->result = $result;
	}

	/**
	 * Evaluate local license facts.
	 *
	 * @param License_Facts $facts Local license facts.
	 *
	 * @return self
	 */
	public static function evaluate( License_Facts $facts ): self {
		if ( ! $facts->is_connected() ) {
			return new self( self::NO_LICENSE );
		}

		switch ( $facts->type() ) {
			case License_Facts::FREE:
				return new self( self::FREE );

			case License_Facts::PRO:
				return new self( self::PRO );

			default:
				return new self( self::NO_LICENSE );
		}
	}

	/**
	 * Get the settings access result.
	 *
	 * @return string
	 */
	public function result(): string {
		return $this->result;
	}

	/**
	 * Determine if the settings page is available.
	 *
	 * @return bool
	 */
	public function is_allowed(): bool {
		return in_array( $this->result, array( self::FREE, self::PRO ), true );
	}
}
