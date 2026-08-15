<?php
/**
 * Automator license facts used by the feature policy.
 *
 * @package Uncanny_Automator
 * @since 7.5.1.1
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Feature_State\Domain;

/**
 * Carries authoritative license observations without interpreting policy.
 */
final class License_Facts {

	public const LITE_ONLY = 'lite_only';
	public const PRO       = 'pro';

	private string $installation;
	private bool $connected;
	private bool $license_entered;
	private string $license_status;
	private ?int $download_id;
	private ?int $price_id;
	private ?bool $is_lifetime;
	private ?bool $has_active_extended_support_license;

	/**
	 * @param string    $installation                        Plugin installation kind.
	 * @param bool      $connected                           Whether a Lite account is connected.
	 * @param bool      $license_entered                     Whether a Pro key was entered.
	 * @param string    $license_status                      Raw local Pro license status.
	 * @param int|null  $download_id                         Store product/download identifier.
	 * @param int|null  $price_id                            Store price identifier.
	 * @param bool|null $is_lifetime                         Whether this is a lifetime license.
	 * @param bool|null $has_active_extended_support_license Extended-support fact, when known.
	 */
	private function __construct(
		string $installation,
		bool $connected,
		bool $license_entered,
		string $license_status,
		?int $download_id,
		?int $price_id,
		?bool $is_lifetime,
		?bool $has_active_extended_support_license
	) {
		$this->installation                        = $installation;
		$this->connected                           = $connected;
		$this->license_entered                     = $license_entered;
		$this->license_status                      = $license_status;
		$this->download_id                         = $download_id;
		$this->price_id                            = $price_id;
		$this->is_lifetime                         = $is_lifetime;
		$this->has_active_extended_support_license = $has_active_extended_support_license;
	}

	/**
	 * Create facts for a Lite-only installation.
	 *
	 * @param bool $connected Whether a Lite account is connected.
	 *
	 * @return self
	 */
	public static function lite_only( bool $connected ): self {
		return new self( self::LITE_ONLY, $connected, false, '', null, null, null, null );
	}

	/**
	 * Create facts for an installation with Pro active.
	 *
	 * @param bool      $license_entered                     Whether a Pro key was entered.
	 * @param string    $license_status                      Raw local Pro license status.
	 * @param int|null  $download_id                         Store product/download identifier.
	 * @param int|null  $price_id                            Store price identifier.
	 * @param bool|null $is_lifetime                         Whether this is a lifetime license.
	 * @param bool|null $has_active_extended_support_license Extended-support fact, when known.
	 *
	 * @return self
	 */
	public static function pro(
		bool $license_entered,
		string $license_status,
		?int $download_id,
		?int $price_id,
		?bool $is_lifetime,
		?bool $has_active_extended_support_license
	): self {
		return new self(
			self::PRO,
			false,
			$license_entered,
			$license_status,
			$download_id,
			$price_id,
			$is_lifetime,
			$has_active_extended_support_license
		);
	}

	/**
	 * Get the plugin installation kind.
	 *
	 * @return string
	 */
	public function installation(): string {
		return $this->installation;
	}

	/**
	 * Determine whether the Lite account is connected.
	 *
	 * @return bool
	 */
	public function connected(): bool {
		return $this->connected;
	}

	/**
	 * Determine whether a Pro license key was entered.
	 *
	 * @return bool
	 */
	public function license_entered(): bool {
		return $this->license_entered;
	}

	/**
	 * Get the raw local Pro license status.
	 *
	 * @return string
	 */
	public function license_status(): string {
		return $this->license_status;
	}

	/**
	 * Get the store product/download identifier.
	 *
	 * @return int|null
	 */
	public function download_id(): ?int {
		return $this->download_id;
	}

	/**
	 * Get the store price identifier.
	 *
	 * @return int|null
	 */
	public function price_id(): ?int {
		return $this->price_id;
	}

	/**
	 * Get whether this is a lifetime license, when known.
	 *
	 * @return bool|null
	 */
	public function is_lifetime(): ?bool {
		return $this->is_lifetime;
	}

	/**
	 * Get the active extended-support fact, when known.
	 *
	 * @return bool|null
	 */
	public function has_active_extended_support_license(): ?bool {
		return $this->has_active_extended_support_license;
	}
}
