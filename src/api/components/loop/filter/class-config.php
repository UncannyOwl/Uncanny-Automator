<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Loop\Filter;

/**
 * Config DTO.
 *
 * Data transfer object for filter configuration with fluent interface.
 * Models the database schema for uo-loop-filter post type.
 *
 * @since 7.0.0
 */
class Config {

	private ?int $id                  = null;
	private ?string $code             = null;
	private ?string $integration_code = null;
	private ?string $integration_name = null;
	private string $type              = 'lite';
	private string $user_type         = 'user';
	private array $fields             = array();
	private array $backup             = array();
	private ?string $version          = null;
	/**
	 * Id.
	 *
	 * @param int $id The ID.
	 * @return self
	 */
	public function id( ?int $id ): self {
		$this->id = $id;
		return $this;
	}
	/**
	 * Code.
	 *
	 * @param string $code The code.
	 * @return self
	 */
	public function code( string $code ): self {
		$this->code = $code;
		return $this;
	}
	/**
	 * Integration code.
	 *
	 * @param string $integration_code The integration code.
	 * @return self
	 */
	public function integration_code( string $integration_code ): self {
		$this->integration_code = $integration_code;
		return $this;
	}
	/**
	 * Integration name.
	 *
	 * @param string $integration_name The name.
	 * @return self
	 */
	public function integration_name( string $integration_name ): self {
		$this->integration_name = $integration_name;
		return $this;
	}
	/**
	 * Type.
	 *
	 * @param string $type The type.
	 * @return self
	 */
	public function type( string $type ): self {
		$this->type = $type;
		return $this;
	}
	/**
	 * User type.
	 *
	 * @param string $user_type The type.
	 * @return self
	 */
	public function user_type( string $user_type ): self {
		$this->user_type = $user_type;
		return $this;
	}
	/**
	 * Fields.
	 *
	 * @param array $fields The fields.
	 * @return self
	 */
	public function fields( array $fields ): self {
		$this->fields = $fields;
		return $this;
	}
	/**
	 * Backup.
	 *
	 * @param array $backup The backup.
	 * @return self
	 */
	public function backup( array $backup ): self {
		$this->backup = $backup;
		return $this;
	}
	/**
	 * Version.
	 *
	 * @param string $version The version.
	 * @return self
	 */
	public function version( string $version ): self {
		$this->version = $version;
		return $this;
	}
	/**
	 * Get id.
	 *
	 * @return ?
	 */
	public function get_id(): ?int {
		return $this->id;
	}
	/**
	 * Get code.
	 *
	 * @return ?
	 */
	public function get_code(): ?string {
		return $this->code;
	}
	/**
	 * Get integration code.
	 *
	 * @return ?
	 */
	public function get_integration_code(): ?string {
		return $this->integration_code;
	}
	/**
	 * Get integration name.
	 *
	 * @return ?
	 */
	public function get_integration_name(): ?string {
		return $this->integration_name ?? $this->integration_code;
	}
	/**
	 * Get type.
	 *
	 * @return string
	 */
	public function get_type(): string {
		return $this->type;
	}
	/**
	 * Get user type.
	 *
	 * @return string
	 */
	public function get_user_type(): string {
		return $this->user_type;
	}
	/**
	 * Get fields.
	 *
	 * @return array
	 */
	public function get_fields(): array {
		return $this->fields;
	}
	/**
	 * Get backup.
	 *
	 * @return array
	 */
	public function get_backup(): array {
		return $this->backup;
	}
	/**
	 * Get version.
	 *
	 * @return ?
	 */
	public function get_version(): ?string {
		return $this->version;
	}
	/**
	 * From array.
	 *
	 * @param array $data The data.
	 * @return self
	 */
	public static function from_array( array $data ): self {
		return ( new self() )
			->id( $data['id'] ?? null )
			->code( $data['code'] ?? '' )
			->integration_code( $data['integration_code'] ?? '' )
			->integration_name( $data['integration_name'] ?? $data['integration_code'] ?? '' )
			->type( $data['type'] ?? 'lite' )
			->user_type( $data['user_type'] ?? 'user' )
			->fields( $data['fields'] ?? array() )
			->backup( $data['backup'] ?? array() )
			->version( $data['version'] ?? '' );
	}
}
