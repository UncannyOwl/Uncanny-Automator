<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\User_Selector\Value_Objects;

/**
 * User Selector User Data Value Object.
 *
 * Immutable value object that encapsulates user data fields for creating
 * new users or as fallback data when user matching requires creation.
 *
 * @since 7.0.0
 */
class User_Selector_User_Data {

	/**
	 * User's first name.
	 *
	 * @var string
	 */
	private $first_name;

	/**
	 * User's last name.
	 *
	 * @var string
	 */
	private $last_name;

	/**
	 * User's email address.
	 *
	 * @var string
	 */
	private $email;

	/**
	 * User's login username.
	 *
	 * @var string
	 */
	private $username;

	/**
	 * User's display name.
	 *
	 * @var string
	 */
	private $display_name;

	/**
	 * User's password.
	 *
	 * @var string
	 */
	private $password;

	/**
	 * User's role.
	 *
	 * @var string
	 */
	private $role;

	/**
	 * Whether to log user in after creation.
	 *
	 * @var bool
	 */
	private $log_user_in;

	/**
	 * Constructor.
	 *
	 * @param array $data User data array.
	 */
	public function __construct( array $data = array() ) {
		$this->first_name   = isset( $data['firstName'] ) ? (string) $data['firstName'] : '';
		$this->last_name    = isset( $data['lastName'] ) ? (string) $data['lastName'] : '';
		$this->email        = isset( $data['email'] ) ? (string) $data['email'] : '';
		$this->username     = isset( $data['username'] ) ? (string) $data['username'] : '';
		$this->display_name = isset( $data['displayName'] ) ? (string) $data['displayName'] : '';
		$this->password     = isset( $data['password'] ) ? (string) $data['password'] : '';
		$this->role         = isset( $data['role'] ) ? (string) $data['role'] : 'subscriber';
		$this->log_user_in  = isset( $data['logUserIn'] ) ? (bool) $data['logUserIn'] : false;
	}

	/**
	 * Create from array.
	 *
	 * @param array $data User data array.
	 * @return self
	 */
	public static function from_array( array $data ): self {
		return new self( $data );
	}

	/**
	 * Get first name.
	 *
	 * @return string
	 */
	public function get_first_name(): string {
		return $this->first_name;
	}

	/**
	 * Get last name.
	 *
	 * @return string
	 */
	public function get_last_name(): string {
		return $this->last_name;
	}

	/**
	 * Get email.
	 *
	 * @return string
	 */
	public function get_email(): string {
		return $this->email;
	}

	/**
	 * Get username.
	 *
	 * @return string
	 */
	public function get_username(): string {
		return $this->username;
	}

	/**
	 * Get display name.
	 *
	 * @return string
	 */
	public function get_display_name(): string {
		return $this->display_name;
	}

	/**
	 * Get password.
	 *
	 * @return string
	 */
	public function get_password(): string {
		return $this->password;
	}

	/**
	 * Get role.
	 *
	 * @return string
	 */
	public function get_role(): string {
		return $this->role;
	}

	/**
	 * Check if user should be logged in after creation.
	 *
	 * @return bool
	 */
	public function should_log_user_in(): bool {
		return $this->log_user_in;
	}

	/**
	 * Convert to array.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return array(
			'firstName'   => $this->first_name,
			'lastName'    => $this->last_name,
			'email'       => $this->email,
			'username'    => $this->username,
			'displayName' => $this->display_name,
			'password'    => $this->password,
			'role'        => $this->role,
			'logUserIn'   => $this->log_user_in,
		);
	}

	/**
	 * Check if user data is empty.
	 *
	 * @return bool
	 */
	public function is_empty(): bool {
		return empty( $this->email ) && empty( $this->username );
	}
}
