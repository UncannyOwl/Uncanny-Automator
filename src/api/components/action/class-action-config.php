<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Action;

use Uncanny_Automator\Api\Components\Recipe\Value_Objects\Recipe_Id;
use Uncanny_Automator\Api\Components\Action\Enums\Action_Status;

/**
 * Action Configuration.
 *
 * Data transfer object for action configuration with fluent interface.
 * Contains no validation logic - serves as a bridge between raw data
 * and validated domain objects.
 *
 * @since 7.0.0
 */
class Action_Config {

	private array $data = array();
	private $id;
	private $recipe_id;
	private $parent_id;
	private $integration_code;
	private $code;
	private $meta_code;
	private $user_type;
	private $status;
	private $meta          = array();
	private $is_deprecated = false;

	/**
	 * Set a configuration value (generic approach).
	 *
	 * @param string $key Configuration key.
	 * @param mixed  $value Configuration value.
	 * @return self
	 */
	public function set( string $key, $value ): self {
		$this->data[ $key ] = $value;
		return $this;
	}

	/**
	 * Get a configuration value (generic approach).
	 *
	 * @param string $key Configuration key.
	 * @param mixed  $default Default value if key not found.
	 * @return mixed
	 */
	public function get( string $key, $default_value = null ) {
		return $this->data[ $key ] ?? $default_value;
	}

	/**
	 * Set action ID.
	 *
	 * @param mixed $id Action ID.
	 * @return self
	 */
	public function id( $id ): self {
		$this->id = $id;
		return $this;
	}

	/**
	 * Set action recipe ID.
	 *
	 * @param mixed $recipe_id Action recipe ID.
	 * @return self
	 */
	public function recipe_id( $recipe_id ): self {
		$this->recipe_id = $recipe_id;
		return $this;
	}

	/**
	 * Set action parent ID.
	 *
	 * @param \Uncanny_Automator\Api\Components\Interfaces\Parent_Id $parent_id Parent identifier (Recipe_ID or Loop_ID).
	 * @return self
	 */
	public function parent_id( $parent_id ): self {
		$this->parent_id = $parent_id;
		return $this;
	}

	/**
	 * Set action integration code.
	 *
	 * @param string $integration_code Integration identifier (e.g., 'WP', 'MAILCHIMP').
	 * @return self
	 */
	public function integration_code( string $integration_code ): self {
		$this->integration_code = $integration_code;
		return $this;
	}

	/**
	 * Set action code.
	 *
	 * @param string $code Action code (e.g., 'SEND_EMAIL', 'CREATE_POST').
	 * @return self
	 */
	public function code( string $code ): self {
		$this->code = $code;
		return $this;
	}

	/**
	 * Set action meta code.
	 *
	 * @param string $meta_code Action meta code identifier.
	 * @return self
	 */
	public function meta_code( string $meta_code ): self {
		$this->meta_code = $meta_code;
		return $this;
	}

	/**
	 * Set action user type.
	 *
	 * @param string $user_type Action user type ('user' or 'anonymous').
	 * @return self
	 */
	public function user_type( string $user_type ): self {
		$this->user_type = $user_type;
		return $this;
	}

	/**
	 * Set action status.
	 *
	 * @param string $status Action status ('draft' or 'publish').
	 * @return self
	 */
	public function status( string $status ): self {
		$this->status = $status;
		return $this;
	}

	/**
	 * Set action meta.
	 *
	 * @param array $meta Action-specific settings and configuration.
	 * @return self
	 */
	public function meta( array $meta ): self {
		$this->meta = $meta;
		return $this;
	}

	/**
	 * Set action deprecated status.
	 *
	 * @param bool $is_deprecated Whether action is deprecated.
	 * @return self
	 */
	public function is_deprecated( bool $is_deprecated ): self {
		$this->is_deprecated = $is_deprecated;
		return $this;
	}

	/**
	 * Get action ID.
	 *
	 * @return mixed
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Get action recipe ID.
	 *
	 * @return mixed
	 */
	public function get_recipe_id() {
		return $this->recipe_id;
	}

	/**
	 * Get action parent ID.
	 *
	 * @return \Uncanny_Automator\Api\Components\Interfaces\Parent_Id|null Parent identifier or null.
	 */
	public function get_parent_id() {
		return $this->parent_id;
	}

	/**
	 * Get action integration code.
	 *
	 * @return string|null
	 */
	public function get_integration_code(): ?string {
		return $this->integration_code;
	}

	/**
	 * Get action code.
	 *
	 * @return string|null
	 */
	public function get_code(): ?string {
		return $this->code;
	}

	/**
	 * Get action meta code.
	 *
	 * @return string|null
	 */
	public function get_meta_code(): ?string {
		return $this->meta_code;
	}

	/**
	 * Get action user type.
	 *
	 * @return string|null
	 */
	public function get_user_type(): ?string {
		return $this->user_type;
	}

	/**
	 * Get action status.
	 *
	 * @return string|null
	 */
	public function get_status(): ?string {
		return $this->status;
	}

	/**
	 * Get action meta.
	 *
	 * @return array
	 */
	public function get_meta(): array {
		return $this->meta;
	}

	/**
	 * Get action deprecated status.
	 *
	 * @return bool
	 */
	public function get_is_deprecated(): bool {
		return $this->is_deprecated;
	}

	/**
	 * Create from array.
	 *
	 * @param array $data Array data.
	 * @return self
	 */
	public static function from_array( array $data ): self {
		$config = ( new self() )
			->id( $data['action_id'] ?? null )
			->recipe_id( $data['action_recipe_id'] ?? null )
			->integration_code( $data['action_integration_code'] ?? '' )
			->code( $data['action_code'] ?? '' )
			->meta_code( $data['action_meta_code'] ?? '' )
			->user_type( $data['action_user_type'] ?? 'user' )
			->status( $data['action_status'] ?? Action_Status::DRAFT )
			->meta( $data['action_meta'] ?? array() )
			->is_deprecated( $data['is_deprecated'] ?? false );

		// Set parent_id - defaults to recipe_id as Recipe_Id for backward compatibility
		if ( isset( $data['action_parent_id'] ) && $data['action_parent_id'] instanceof \Uncanny_Automator\Api\Components\Interfaces\Parent_Id ) {
			$config->parent_id( $data['action_parent_id'] );
		} elseif ( null !== $data['action_recipe_id'] ?? null ) {
			$config->parent_id( new Recipe_Id( $data['action_recipe_id'] ) );
		}

		return $config;
	}
}
