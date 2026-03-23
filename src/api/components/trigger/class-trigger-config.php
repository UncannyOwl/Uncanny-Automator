<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Trigger;

use Uncanny_Automator\Api\Components\Trigger\Enums\Trigger_Status;

/**
 * Trigger Configuration.
 *
 * Data transfer object for trigger configuration with fluent interface.
 * Contains no validation logic - serves as a bridge between raw data
 * and validated domain objects.
 *
 * @since 7.0.0
 */
class Trigger_Config {

	private $id;
	private $code;
	private $meta_code;
	private $integration;
	private $sentence;
	private $sentence_human_readable;
	private $sentence_human_readable_html;
	private $type;
	private $recipe_id;
	private $status;
	private $hook          = array();
	private $tokens        = array();
	private $configuration = array();
	private $is_deprecated = false;
	private $manifest      = array();

	/**
	 * Set trigger ID.
	 *
	 * @param mixed $id Trigger ID.
	 * @return self
	 */
	public function id( $id ): self {
		$this->id = $id;
		return $this;
	}

	/**
	 * Set trigger code.
	 *
	 * @param string $code Trigger code (e.g., 'WP_USER_LOGIN').
	 * @return self
	 */
	public function code( string $code ): self {
		$this->code = $code;
		return $this;
	}

	/**
	 * Set trigger integration.
	 *
	 * @param string $integration Integration code (e.g., 'WP', 'WC').
	 * @return self
	 */
	public function integration( string $integration ): self {
		$this->integration = $integration;
		return $this;
	}


	/**
	 * Set trigger sentence.
	 *
	 * @param string $sentence Trigger sentence template.
	 * @return self
	 */
	public function sentence( string $sentence ): self {
		$this->sentence = $sentence;
		return $this;
	}

	/**
	 * Set trigger human readable sentence.
	 *
	 * @param string $sentence_human_readable Human readable sentence for UI.
	 * @return self
	 */
	public function sentence_human_readable( string $sentence_human_readable ): self {
		$this->sentence_human_readable = $sentence_human_readable;
		return $this;
	}

	/**
	 * Set trigger human readable sentence HTML.
	 *
	 * @param string $sentence_human_readable_html Human readable sentence HTML for UI.
	 * @return self
	 */
	public function sentence_human_readable_html( string $sentence_human_readable_html ): self {
		$this->sentence_human_readable_html = $sentence_human_readable_html;
		return $this;
	}

	/**
	 * Set trigger user type.
	 *
	 * @param string $type Trigger user type ('user' or 'anonymous').
	 * @return self
	 */
	public function user_type( string $type ): self {
		$this->type = $type;
		return $this;
	}

	/**
	 * Set recipe ID.
	 *
	 * @param mixed $recipe_id Recipe ID.
	 * @return self
	 */
	public function recipe_id( $recipe_id ): self {
		$this->recipe_id = $recipe_id;
		return $this;
	}

	/**
	 * Set trigger status.
	 *
	 * @param string $status Trigger status ('draft' or 'publish').
	 * @return self
	 */
	public function status( string $status ): self {
		$this->status = $status;
		return $this;
	}

	/**
	 * Set trigger hook.
	 *
	 * @param array $hook Hook configuration.
	 * @return self
	 */
	public function hook( array $hook ): self {
		$this->hook = $hook;
		return $this;
	}

	/**
	 * Set trigger tokens.
	 *
	 * @param array $tokens Available tokens.
	 * @return self
	 */
	public function tokens( array $tokens ): self {
		$this->tokens = $tokens;
		return $this;
	}

	/**
	 * Set trigger configuration.
	 *
	 * @param array $configuration Trigger-specific settings.
	 * @return self
	 */
	public function configuration( array $configuration ): self {
		$this->configuration = $configuration;
		return $this;
	}

	/**
	 * Set trigger deprecated status.
	 *
	 * @param bool $is_deprecated Whether trigger is deprecated.
	 * @return self
	 */
	public function is_deprecated( bool $is_deprecated ): self {
		$this->is_deprecated = $is_deprecated;
		return $this;
	}

	/**
	 * Set trigger meta code.
	 *
	 * @param string $meta_code Trigger meta code.
	 * @return self
	 */
	public function meta_code( string $meta_code ): self {
		$this->meta_code = $meta_code;
		return $this;
	}

	/**
	 * Get trigger ID.
	 *
	 * @return mixed
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Get trigger code.
	 *
	 * @return string|null
	 */
	public function get_code(): ?string {
		return $this->code;
	}

	/**
	 * Get trigger integration.
	 *
	 * @return string|null
	 */
	public function get_integration(): ?string {
		return $this->integration;
	}


	/**
	 * Get trigger sentence.
	 *
	 * @return string|null
	 */
	public function get_sentence(): ?string {
		return $this->sentence;
	}

	/**
	 * Get trigger human readable sentence.
	 *
	 * @return string|null
	 */
	public function get_sentence_human_readable(): ?string {
		return $this->sentence_human_readable;
	}

	/**
	 * Get trigger human readable sentence HTML.
	 *
	 * @return string|null
	 */
	public function get_sentence_human_readable_html(): ?string {
		return $this->sentence_human_readable_html;
	}

	/**
	 * Get trigger user type.
	 *
	 * @return string|null
	 */
	public function get_user_type(): ?string {
		return $this->type;
	}

	/**
	 * Get recipe ID.
	 *
	 * @return mixed
	 */
	public function get_recipe_id() {
		return $this->recipe_id;
	}

	/**
	 * Get trigger status.
	 *
	 * @return string|null
	 */
	public function get_status(): ?string {
		return $this->status;
	}

	/**
	 * Get trigger hook.
	 *
	 * @return array
	 */
	public function get_hook(): array {
		return $this->hook;
	}

	/**
	 * Get trigger tokens.
	 *
	 * @return array
	 */
	public function get_tokens(): array {
		return $this->tokens;
	}

	/**
	 * Get trigger configuration.
	 *
	 * @return array
	 */
	public function get_configuration(): array {
		return $this->configuration;
	}

	/**
	 * Get trigger deprecated status.
	 *
	 * @return bool
	 */
	public function get_is_deprecated(): bool {
		return $this->is_deprecated;
	}

	/**
	 * Get trigger meta code.
	 *
	 * @return string
	 */
	public function get_meta_code(): string {
		return $this->meta_code;
	}

	/**
	 * Set trigger manifest.
	 *
	 * @param array $manifest Trigger manifest data.
	 * @return self
	 */
	public function manifest( array $manifest ): self {
		$this->manifest = $manifest;
		return $this;
	}

	/**
	 * Get trigger manifest.
	 *
	 * @return array
	 */
	public function get_manifest(): array {
		return $this->manifest;
	}

	/**
	 * Create from array.
	 *
	 * @param array $data Array data.
	 * @return self
	 */
	public static function from_array( array $data ): self {
		$config = ( new self() )
			->id( $data['trigger_id'] ?? null )
			->code( $data['trigger_code'] ?? '' )
			->meta_code( $data['trigger_meta_code'] ?? '' )
			->user_type( $data['trigger_type'] ?? 'user' )
			->recipe_id( $data['recipe_id'] ?? null )
			->status( $data['status'] ?? Trigger_Status::DRAFT )
			->hook( $data['trigger_hook'] ?? array() )
			->tokens( $data['trigger_tokens'] ?? array() )
			->configuration( $data['configuration'] ?? array() )
			->is_deprecated( $data['is_deprecated'] ?? false )
			->manifest( $data['manifest'] ?? array() );

		// Add new fields if present
		if ( isset( $data['integration'] ) ) {
			$config->integration( $data['integration'] );
		}
		if ( isset( $data['sentence'] ) ) {
			$config->sentence( $data['sentence'] );
		}
		if ( isset( $data['sentence_human_readable'] ) ) {
			$config->sentence_human_readable( $data['sentence_human_readable'] );
		}
		if ( isset( $data['sentence_human_readable_html'] ) ) {
			$config->sentence_human_readable_html( $data['sentence_human_readable_html'] );
		}

		return $config;
	}
}
