<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Closure;

/**
 * Closure Configuration.
 *
 * Data transfer object for closure configuration with fluent interface.
 * Contains no validation logic - serves as a bridge between raw data
 * and validated domain objects.
 *
 * @since 7.0.0
 */
class Closure_Config {

	private $id;
	private $code;
	private $recipe_id;
	private $integration;
	private $integration_name             = '';
	private $sentence_human_readable      = '';
	private $sentence_human_readable_html = '';
	private array $meta                   = array();

	/**
	 * Set closure ID.
	 *
	 * @param mixed $id Closure ID.
	 * @return self
	 */
	public function id( $id ): self {
		$this->id = $id;
		return $this;
	}

	/**
	 * Set closure code.
	 *
	 * @param string $code Closure code (e.g., 'REDIRECT').
	 * @return self
	 */
	public function code( string $code ): self {
		$this->code = $code;
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
	 * Set integration code.
	 *
	 * @param string $integration Integration code (e.g., 'WP', 'WC').
	 * @return self
	 */
	public function integration( string $integration ): self {
		$this->integration = $integration;
		return $this;
	}

	/**
	 * Set integration name.
	 *
	 * @param string $integration_name Integration display name.
	 * @return self
	 */
	public function integration_name( string $integration_name ): self {
		$this->integration_name = $integration_name;
		return $this;
	}

	/**
	 * Set human-readable sentence.
	 *
	 * @param string $sentence Human-readable sentence.
	 * @return self
	 */
	public function sentence_human_readable( string $sentence ): self {
		$this->sentence_human_readable = $sentence;
		return $this;
	}

	/**
	 * Set HTML sentence.
	 *
	 * @param string $sentence_html HTML sentence.
	 * @return self
	 */
	public function sentence_human_readable_html( string $sentence_html ): self {
		$this->sentence_human_readable_html = $sentence_html;
		return $this;
	}

	/**
	 * Set meta values.
	 *
	 * @param array $meta Meta key-value pairs.
	 * @return self
	 */
	public function meta( array $meta ): self {
		$this->meta = $meta;
		return $this;
	}

	/**
	 * Set single meta value.
	 *
	 * @param string $key Meta key.
	 * @param mixed  $value Meta value.
	 * @return self
	 */
	public function set_meta( string $key, $value ): self {
		$this->meta[ $key ] = $value;
		return $this;
	}

	/**
	 * Get closure ID.
	 *
	 * @return mixed
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Get closure code.
	 *
	 * @return string|null
	 */
	public function get_code(): ?string {
		return $this->code;
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
	 * Get integration code.
	 *
	 * @return string|null
	 */
	public function get_integration(): ?string {
		return $this->integration;
	}

	/**
	 * Get integration name.
	 *
	 * @return string|null
	 */
	public function get_integration_name(): ?string {
		return $this->integration_name;
	}

	/**
	 * Get human-readable sentence.
	 *
	 * @return string
	 */
	public function get_sentence_human_readable(): string {
		return $this->sentence_human_readable;
	}

	/**
	 * Get HTML sentence.
	 *
	 * @return string
	 */
	public function get_sentence_human_readable_html(): string {
		return $this->sentence_human_readable_html;
	}

	/**
	 * Get all meta values.
	 *
	 * @return array
	 */
	public function get_meta(): array {
		return $this->meta;
	}

	/**
	 * Get single meta value.
	 *
	 * @param string $key Meta key.
	 * @param mixed  $default Default value if key not found.
	 * @return mixed
	 */
	public function get_meta_value( string $key, $default_value = null ) {
		return $this->meta[ $key ] ?? $default_value;
	}

	/**
	 * Create from array.
	 *
	 * @param array $data Array data with keys: code, recipe_id, integration, integration_name, etc.
	 * @return self
	 */
	public static function from_array( array $data ): self {
		return ( new self() )
			->id( $data['id'] ?? null )
			->code( $data['code'] ?? '' )
			->recipe_id( $data['recipe_id'] ?? null )
			->integration( $data['integration'] ?? '' )
			->integration_name( $data['integration_name'] ?? '' )
			->sentence_human_readable( $data['sentence_human_readable'] ?? '' )
			->sentence_human_readable_html( $data['sentence_human_readable_html'] ?? '' )
			->meta( $data['meta'] ?? array() );
	}
}
