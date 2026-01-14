<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Closure;

use Uncanny_Automator\Api\Components\Closure\Closure_Config;
use Uncanny_Automator\Api\Components\Closure\Value_Objects\Closure_Code;
use Uncanny_Automator\Api\Components\Closure\Value_Objects\Closure_Integration;
use Uncanny_Automator\Api\Components\Closure\Value_Objects\Closure_Sentence;
use Uncanny_Automator\Api\Components\Recipe\Value_Objects\Recipe_Id;
use Uncanny_Automator\Api\Components\Security\Security;

/**
 * Closure Aggregate.
 *
 * Domain object representing a post-recipe closure (redirect, etc).
 * Validation happens in value object construction.
 *
 * @since 7.0.0
 */
class Closure {

	private Closure_Code $code;
	private Closure_Integration $integration;
	private Closure_Sentence $sentence;
	private Recipe_Id $recipe_id;
	private string $integration_name = '';
	private int $id                  = 0;
	private array $meta              = array();

	/**
	 * Constructor.
	 *
	 * Creates VOs from config data - validation happens here.
	 * Once constructed, the Closure instance is guaranteed to be valid.
	 *
	 * @param Closure_Config $config Configuration data.
	 * @throws \InvalidArgumentException If validation fails in VOs.
	 */
	public function __construct( Closure_Config $config ) {
		// VO instantiation validates the data
		$this->code        = new Closure_Code( (string) $config->get_code() );
		$this->integration = new Closure_Integration( (string) $config->get_integration() );
		$this->sentence    = new Closure_Sentence(
			$config->get_sentence_human_readable(),
			$config->get_sentence_human_readable_html()
		);

		// Recipe ID might already be a VO from fluent config, or raw value
		$recipe_id = $config->get_recipe_id();
		if ( $recipe_id instanceof Recipe_Id ) {
			$this->recipe_id = $recipe_id;
		} else {
			$this->recipe_id = new Recipe_Id( $recipe_id );
		}

		// Store other data
		$this->id               = (int) $config->get_id();
		$this->integration_name = (string) $config->get_integration_name();
		$this->meta             = $config->get_meta();
	}

	/**
	 * Get recipe ID.
	 *
	 * @return Recipe_Id Recipe ID.
	 */
	public function get_recipe_id(): Recipe_Id {
		return $this->recipe_id;
	}

	/**
	 * Get code value.
	 *
	 * @return string
	 */
	public function get_code(): string {
		return $this->code->get_value();
	}

	/**
	 * Get version.
	 *
	 * @return string
	 */
	public function get_version(): string {
		return AUTOMATOR_PLUGIN_VERSION;
	}

	/**
	 * Get integration value.
	 *
	 * @return string
	 */
	public function get_integration(): string {
		return $this->integration->get_value();
	}

	/**
	 * Get integration name.
	 *
	 * @return string
	 */
	public function get_integration_name(): string {
		return $this->integration_name;
	}

	/**
	 * Get user type.
	 *
	 * @return string
	 */
	public function get_user_type(): string {
		return 'anonymous';
	}

	/**
	 * Get closure type.
	 *
	 * @return string
	 */
	public function get_type(): string {
		return 'free';
	}

	/**
	 * Get human-readable sentence.
	 *
	 * @return string
	 */
	public function get_sentence_human_readable(): string {
		$sentence = $this->sentence->get_human_readable();

		// If not set, generate default sentence
		if ( empty( $sentence ) ) {
			$sentence = $this->generate_default_sentence();
		}

		return $sentence;
	}

	/**
	 * Get HTML sentence.
	 *
	 * @return string
	 */
	public function get_sentence_human_readable_html(): string {
		$sentence_html = $this->sentence->get_human_readable_html();

		// If not set, generate default HTML sentence
		if ( empty( $sentence_html ) ) {
			$sentence_html = $this->generate_default_sentence_html();
		}

		return $sentence_html;
	}

	/**
	 * Generate default human-readable sentence based on closure code.
	 *
	 * @return string Default sentence.
	 */
	private function generate_default_sentence(): string {
		return 'Redirect to {{a link:REDIRECTURL}} when recipe is completed';
	}

	/**
	 * Generate default HTML sentence based on closure code.
	 *
	 * @return string Default HTML sentence.
	 */
	private function generate_default_sentence_html(): string {
		$redirect_url = $this->meta['REDIRECTURL'] ?? '';

		return '<div><span class="item-title__normal">Redirect to </span><span class="item-title__token" data-token-id="REDIRECTURL" data-options-id="REDIRECTURL"><span class="item-title__token-label">Redirect URL:</span> ' . Security::sanitize( $redirect_url, Security::URL_OUTPUT ) . '</span><span class="item-title__normal"> when recipe is completed</span></div>';
	}

	/**
	 * Get ID.
	 *
	 * @return int Closure ID (0 if not persisted).
	 */
	public function get_id(): int {
		return $this->id;
	}

	/**
	 * Get closure metadata.
	 *
	 * @return array Metadata array (e.g., REDIRECTURL for REDIRECT closures).
	 */
	public function get_meta(): array {
		return $this->meta;
	}

	/**
	 * Reconstruct config from domain object (for persistence layer).
	 *
	 * Used by the store layer to serialize closure back to config format.
	 * This allows the store to persist all closure data.
	 *
	 * @return Closure_Config The reconstructed configuration.
	 */
	public function to_config(): Closure_Config {
		return ( new Closure_Config() )
			->id( $this->id )
			->code( $this->code->get_value() )
			->recipe_id( $this->recipe_id )
			->integration( $this->integration->get_value() )
			->integration_name( $this->integration_name )
			->sentence_human_readable( $this->sentence->get_human_readable() )
			->sentence_human_readable_html( $this->sentence->get_human_readable_html() )
			->meta( $this->meta );
	}
}
