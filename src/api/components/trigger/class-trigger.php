<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Trigger;

use Uncanny_Automator\Api\Components\Trigger\Value_Objects\Trigger_Id;
use Uncanny_Automator\Api\Components\Trigger\Value_Objects\Trigger_Code;
use Uncanny_Automator\Api\Components\Trigger\Value_Objects\Trigger_User_Type;
use Uncanny_Automator\Api\Components\Trigger\Value_Objects\Trigger_Hook;
use Uncanny_Automator\Api\Components\Trigger\Value_Objects\Trigger_Tokens;
use Uncanny_Automator\Api\Components\Trigger\Value_Objects\Trigger_Configuration;
use Uncanny_Automator\Api\Components\Trigger\Value_Objects\Trigger_Integration;
use Uncanny_Automator\Api\Components\Trigger\Value_Objects\Sentence_String;
use Uncanny_Automator\Api\Components\Trigger\Value_Objects\Trigger_Meta_Code;
use Uncanny_Automator\Api\Components\Trigger\Value_Objects\Trigger_Deprecated;
use Uncanny_Automator\Api\Components\Recipe\Value_Objects\Recipe_Id;
use Uncanny_Automator\Api\Components\Trigger\Enums\Trigger_Status;
use Uncanny_Automator\Api\Components\Trigger\Value_Objects\Trigger_Status_Value;

/**
 * Trigger Aggregate.
 *
 * Pure domain object representing a trigger instance within a recipe.
 * Contains zero WordPress dependencies - pure PHP business logic only.
 *
 * @since 7.0.0
 */
class Trigger {

	/**
	 * Trigger ID.
	 *
	 * @var Trigger_Id
	 */
	private Trigger_Id $trigger_id;

	/**
	 * Trigger code.
	 *
	 * @var Trigger_Code
	 */
	private Trigger_Code $trigger_code;

	/**
	 * Trigger meta code.
	 *
	 * @var Trigger_Meta_Code
	 */
	private Trigger_Meta_Code $trigger_meta_code;

	/**
	 * The user type of the trigger.
	 *
	 * @var Trigger_User_Type
	 */
	private Trigger_User_Type $trigger_type;

	/**
	 * The hook of the trigger.
	 *
	 * @var Trigger_Hook
	 */
	private Trigger_Hook $trigger_hook;

	/**
	 * The tokens of the trigger.
	 *
	 * @var Trigger_Tokens
	 */
	private Trigger_Tokens $trigger_tokens;

	/**
	 * The configuration of the trigger.
	 *
	 * @var Trigger_Configuration
	 */
	private Trigger_Configuration $trigger_configuration;

	/**
	 * The recipe ID of the trigger.
	 *
	 * @var Recipe_Id
	 */
	private Recipe_Id $recipe_id;

	/**
	 * The integration of the trigger.
	 *
	 * @var Trigger_Integration
	 */
	private Trigger_Integration $integration;

	/**
	 * The sentence of the trigger.
	 *
	 * @var Sentence_String
	 */
	private Sentence_String $sentence;

	/**
	 * The human readable sentence of the trigger.
	 *
	 * @var Sentence_String
	 */
	private Sentence_String $sentence_human_readable;

	/**
	 * The human readable sentence HTML of the trigger.
	 *
	 * @var Sentence_String|null
	 */
	private ?Sentence_String $sentence_human_readable_html = null;

	/**
	 * The deprecated status of the trigger.
	 *
	 * @var Trigger_Deprecated
	 */
	private Trigger_Deprecated $deprecated;

	/**
	 * The status of the trigger.
	 *
	 * @var Trigger_Status_Value|null
	 */
	private ?Trigger_Status_Value $status = null;

	/**
	 * The manifest of the trigger.
	 *
	 * @var array
	 */
	private array $manifest = array();

	/**
	 * Constructor.
	 *
	 * @param Trigger_Config $config Trigger configuration object.
	 */
	public function __construct( Trigger_Config $config ) {

		// Use value objects to ensure data integrity on instance creation instead of runtime.
		// This way, once the instance is created, we can be sure it's valid.
		// Any invalid data will throw an exception here.
		// This also makes the class immutable after creation.
		// Any changes require creating a new instance with new data.
		// This way LLMs can reason and drift all they want but at the end of the day,
		// truth lives in our business logic, not in the LLM's head. ~ Joseph Gabito
		$this->trigger_id            = new Trigger_Id( $config->get_id() );
		$this->trigger_code          = new Trigger_Code( $config->get_code() );
		$this->trigger_meta_code     = new Trigger_Meta_Code( $config->get_meta_code() );
		$this->trigger_hook          = new Trigger_Hook( $config->get_hook() );
		$this->trigger_tokens        = new Trigger_Tokens( $config->get_tokens() );
		$this->trigger_configuration = new Trigger_Configuration( $config->get_configuration() );
		$this->trigger_type          = new Trigger_User_Type( $config->get_user_type() );

		// Store additional metadata as Value Objects (AI drift protection).
		$this->recipe_id   = new Recipe_Id( $config->get_recipe_id() );
		$this->integration = new Trigger_Integration( $config->get_integration() );
		$this->sentence    = new Sentence_String( $config->get_sentence() );
		$this->deprecated  = new Trigger_Deprecated( $config->get_is_deprecated() );

		$this->sentence_human_readable = Sentence_String::for_human_readable( $config->get_sentence_human_readable() );
		$this->manifest                = $config->get_manifest();

		// Optional: sentence_human_readable_html (generated dynamically)
		$html = $config->get_sentence_human_readable_html();

		if ( null !== $html && '' !== $html ) {
			$this->sentence_human_readable_html = Sentence_String::for_html( $html );
		}

		// Set status - defaults to draft
		if ( null !== $config->get_status() ) {
			$this->status = new Trigger_Status_Value( $config->get_status() );
		}

		// Validate business rules. Thinking beyond individual value objects. However, for now,
		// all validation is handled by value objects.
		$this->validate_business_rules();
	}

	/**
	 * Get trigger ID.
	 *
	 * @return Trigger_Id
	 */
	public function get_trigger_id(): Trigger_Id {
		return $this->trigger_id;
	}

	/**
	 * Get trigger code.
	 *
	 * @return Trigger_Code
	 */
	public function get_trigger_code(): Trigger_Code {
		return $this->trigger_code;
	}

	/**
	 * Get trigger meta code.
	 *
	 * @return Trigger_Meta_Code
	 */
	public function get_trigger_meta_code(): Trigger_Meta_Code {
		return $this->trigger_meta_code;
	}

	/**
	 * Get trigger type.
	 *
	 * @return Trigger_User_Type
	 */
	public function get_trigger_type(): Trigger_User_Type {
		return $this->trigger_type;
	}

	/**
	 * Get trigger hook.
	 *
	 * @return Trigger_Hook
	 */
	public function get_trigger_hook(): Trigger_Hook {
		return $this->trigger_hook;
	}

	/**
	 * Get trigger tokens.
	 *
	 * @return Trigger_Tokens
	 */
	public function get_trigger_tokens(): Trigger_Tokens {
		return $this->trigger_tokens;
	}

	/**
	 * Get trigger configuration.
	 *
	 * @return Trigger_Configuration
	 */
	public function get_trigger_configuration(): Trigger_Configuration {
		return $this->trigger_configuration;
	}

	/**
	 * Get recipe ID.
	 *
	 * @return Recipe_Id
	 */
	public function get_recipe_id(): Recipe_Id {
		return $this->recipe_id;
	}

	/**
	 * Get trigger integration.
	 *
	 * @return Trigger_Integration
	 */
	public function get_integration(): Trigger_Integration {
		return $this->integration;
	}

	/**
	 * Get trigger sentence.
	 *
	 * @return Sentence_String
	 */
	public function get_sentence(): Sentence_String {
		return $this->sentence;
	}

	/**
	 * Get trigger human readable sentence.
	 *
	 * @return Sentence_String
	 */
	public function get_sentence_human_readable(): Sentence_String {
		return $this->sentence_human_readable;
	}

	/**
	 * Get trigger human readable sentence HTML.
	 *
	 * @return Sentence_String|null
	 */
	public function get_sentence_human_readable_html(): ?Sentence_String {
		return $this->sentence_human_readable_html;
	}

	/**
	 * Get trigger deprecated status.
	 *
	 * @return Trigger_Deprecated
	 */
	public function get_deprecated(): Trigger_Deprecated {
		return $this->deprecated;
	}

	/**
	 * Get trigger status.
	 *
	 * @return Trigger_Status_Value|null Trigger status or null.
	 */
	public function get_status(): ?Trigger_Status_Value {
		return $this->status;
	}

	/**
	 * Check if trigger is for user recipes.
	 *
	 * @return bool
	 */
	public function is_user_trigger(): bool {
		return $this->trigger_type->is_user();
	}

	/**
	 * Check if trigger is for anonymous recipes.
	 *
	 * @return bool
	 */
	public function is_anonymous_trigger(): bool {
		return $this->trigger_type->is_anonymous();
	}

	/**
	 * To array.
	 *
	 * @return array
	 */
	public function to_array(): array {
		// All fields are now REQUIRED - guaranteed to exist
		return array(
			'trigger_id'                   => $this->trigger_id->get_value(),
			'trigger_code'                 => $this->trigger_code->get_value(),
			'trigger_meta_code'            => $this->trigger_meta_code->get_value(),
			'trigger_type'                 => $this->trigger_type->get_value(),
			'trigger_hook'                 => $this->trigger_hook->to_array(),
			'trigger_tokens'               => $this->trigger_tokens->to_array(),
			'configuration'                => $this->trigger_configuration->get_value(),
			'recipe_id'                    => $this->recipe_id->get_value(),
			'integration'                  => $this->integration->get_value(),
			'sentence'                     => $this->sentence->get_value(),
			'sentence_human_readable'      => $this->sentence_human_readable->get_value(),
			'sentence_human_readable_html' => null !== $this->sentence_human_readable_html ? $this->sentence_human_readable_html->get_value() : null,
			'is_deprecated'                => $this->deprecated->get_value(),
			'status'                       => null !== $this->status ? $this->status->get_value() : null,
			'manifest'                     => $this->manifest,
		);
	}

	/**
	 * To JSON.
	 *
	 * @return string
	 * @throws \JsonException On encoding failure.
	 */
	public function to_json(): string {
		$json = wp_json_encode( $this->to_array(), JSON_UNESCAPED_SLASHES );
		if ( false === $json ) {
			throw new \RuntimeException( 'Failed to encode trigger to JSON' );
		}
		return $json;
	}

	/**
	 * Validate business rules.
	 *
	 * @throws \InvalidArgumentException If rules violated.
	 */
	private function validate_business_rules(): void {
		// Additional domain-level validation can be added here
		// For now, value objects handle their own validation
	}
}
