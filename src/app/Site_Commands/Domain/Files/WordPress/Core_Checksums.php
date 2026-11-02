<?php
/**
 * The checksum list wordpress.org publishes for one WordPress release.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Domain\Files\WordPress;

/**
 * Relative path => MD5 for every core file, or why the list could not be had.
 */
final class Core_Checksums {

	/**
	 * Installed version.
	 *
	 * @var string
	 */
	private string $version;

	/**
	 * The locale the list is for.
	 *
	 * @var string
	 */
	private string $locale;

	/**
	 * Relative path => MD5; null when the list could not be fetched.
	 *
	 * @var array<string, string>|null
	 */
	private ?array $checksums;

	/**
	 * Why the list could not be fetched.
	 *
	 * @var string|null
	 */
	private ?string $error;

	/**
	 * Constructor.
	 *
	 * @param string                     $version   Installed version.
	 * @param string                     $locale    The locale the list is for.
	 * @param array<string, string>|null $checksums Relative path => MD5, null when the list could not be fetched.
	 * @param string|null                $error     Why the list could not be fetched.
	 */
	public function __construct( string $version, string $locale, ?array $checksums, ?string $error ) {
		$this->version   = $version;
		$this->locale    = $locale;
		$this->checksums = $checksums;
		$this->error     = $error;
	}

	/**
	 * Installed version.
	 *
	 * @return string
	 */
	public function version(): string {
		return $this->version;
	}

	/**
	 * The locale the list is for.
	 *
	 * @return string
	 */
	public function locale(): string {
		return $this->locale;
	}

	/**
	 * Relative path => MD5; null when the list could not be fetched.
	 *
	 * @return array<string, string>|null
	 */
	public function checksums(): ?array {
		return $this->checksums;
	}

	/**
	 * Why the list could not be fetched.
	 *
	 * @return string|null
	 */
	public function error(): ?string {
		return $this->error;
	}
}
