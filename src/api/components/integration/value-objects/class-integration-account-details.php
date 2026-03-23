<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Integration\Value_Objects;

/**
 * Integration Account Details Value Object.
 *
 * Contains account-related information for app integrations.
 *
 * @since 7.0.0
 */
class Integration_Account_Details {

	/**
	 * Settings URL for connecting the account.
	 *
	 * @var string
	 */
	private string $settings_url;

	/**
	 * Account-specific icon URL.
	 *
	 * For integrations where the account differs from the integration
	 * (e.g., Google Sheets uses Google account icon, not Google Sheets icon).
	 *
	 * @var string|null
	 */
	private ?string $icon;

	/**
	 * Account name.
	 *
	 * @var string|null
	 */
	private ?string $name;

	/**
	 * Constructor.
	 *
	 * @param array $account_details Account details array with keys: settings_url, icon
	 *
	 * @return void
	 */
	public function __construct( array $account_details ) {
		$this->settings_url = $account_details['settings_url'] ?? '';
		$this->icon         = $account_details['icon'] ?? null;
		$this->name         = $account_details['name'] ?? null;
	}

	/**
	 * Get settings URL.
	 *
	 * @return string Settings URL for connecting the account
	 */
	public function get_settings_url(): string {
		return $this->settings_url;
	}

	/**
	 * Check if settings URL is available.
	 *
	 * @return bool True if settings URL is available
	 */
	public function has_settings_url(): bool {
		return ! empty( $this->settings_url );
	}

	/**
	 * Get account-specific icon URL.
	 *
	 * @return string|null Icon URL or null if not set
	 */
	public function get_icon(): ?string {
		return $this->icon;
	}

	/**
	 * Check if account-specific icon URL is available.
	 *
	 * @return bool True if account-specific icon URL is available
	 */
	public function has_icon(): bool {
		return ! empty( $this->icon );
	}

	/**
	 * Get account name.
	 *
	 * @return string|null Account name or null if not set
	 */
	public function get_name(): ?string {
		return $this->name;
	}

	/**
	 * Check if account name is available.
	 *
	 * @return bool True if account name is available
	 */
	public function has_name(): bool {
		return ! empty( $this->name );
	}

	/**
	 * Convert to array.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return array(
			'settings_url' => $this->settings_url,
			'icon'         => $this->icon,
			'name'         => $this->name,
		);
	}
}
