<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Services\Trigger\Services;

use Exception;
use Uncanny_Automator\Api\Components\Recipe\Value_Objects\Recipe_Id;
use Uncanny_Automator\Api\Components\Recipe\Value_Objects\Recipe_Triggers;
use Uncanny_Automator\Api\Components\Shared\Enums\User_Type;
use Uncanny_Automator\Api\Components\Shared\Sentence_Html\Field_Label_Resolver;
use Uncanny_Automator\Api\Services\Sentence_Html\Sentence_Output_Builder;
use Uncanny_Automator\Api\Components\Trigger\Trigger;
use Uncanny_Automator\Api\Components\Trigger\Trigger_Config;
use Uncanny_Automator\Api\Components\Trigger\Value_Objects\Trigger_Id;
use Uncanny_Automator\Api\Database\Stores\WP_Recipe_Store;
use Uncanny_Automator\Api\Database\Stores\WP_Recipe_Trigger_Store;
use Uncanny_Automator\Api\Components\Recipe\Recipe;

use WP_Error;

/**
 * Trigger Service - WordPress Application Layer.
 *
 * WordPress-aware service layer that delegates business logic
 * to pure operations layer and handles WordPress-specific concerns.
 * Acts as adapter between pure business logic and WordPress infrastructure.
 *
 * @since 7.0.0
 */
class Trigger_CRUD_Service {

	/**
	 * Singleton instance.
	 *
	 * @var Trigger_CRUD_Service|null
	 */
	private static $instance = null;

	/**
	 * Recipe store.
	 *
	 * @var WP_Recipe_Store
	 */
	private $recipe_store;

	/**
	 * Trigger store.
	 *
	 * @var WP_Recipe_Trigger_Store
	 */
	private $trigger_store;

	/**
	 * Field label resolver.
	 *
	 * @var Field_Label_Resolver
	 */
	private $label_resolver;

	/**
	 * Sentence output builder.
	 *
	 * @var Sentence_Output_Builder
	 */
	private $sentence_builder;

	/**
	 * Constructor.
	 *
	 * Allows dependency injection for testing. Production code can use instance()
	 * for backward compatibility with singleton pattern.
	 *
	 * @param WP_Recipe_Store|null           $recipe_store     Optional recipe store instance.
	 * @param WP_Recipe_Trigger_Store|null   $trigger_store    Optional trigger store instance.
	 * @param Field_Label_Resolver|null      $label_resolver   Optional label resolver instance.
	 * @param Sentence_Output_Builder|null   $sentence_builder Optional sentence builder instance.
	 */
	public function __construct(
		?WP_Recipe_Store $recipe_store = null,
		?WP_Recipe_Trigger_Store $trigger_store = null,
		?Field_Label_Resolver $label_resolver = null,
		?Sentence_Output_Builder $sentence_builder = null
	) {
		global $wpdb;
		$this->recipe_store     = $recipe_store ?? new WP_Recipe_Store();
		$this->trigger_store    = $trigger_store ?? new WP_Recipe_Trigger_Store( $wpdb );
		$this->label_resolver   = $label_resolver ?? new Field_Label_Resolver();
		$this->sentence_builder = $sentence_builder ?? new Sentence_Output_Builder();
	}

	/**
	 * Get singleton instance.
	 *
	 * @return Trigger_CRUD_Service
	 */
	public static function instance(): Trigger_CRUD_Service {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Validate recipe exists.
	 *
	 * @param int $recipe_id Recipe ID.
	 * @return Recipe|null Returns recipe or null if not found.
	 */
	public function validate_recipe_exists( int $recipe_id ) {
		return $this->recipe_store->get( $recipe_id );
	}

	/**
	 * Validate trigger code in registry.
	 *
	 * @param string $trigger_code Trigger code to validate.
	 * @return bool|\WP_Error True if valid, WP_Error if invalid.
	 */
	public function validate_trigger_code( string $trigger_code ) {
		$trigger_registry_service = Trigger_Registry_Service::get_instance();
		$trigger_validation       = $trigger_registry_service->get_trigger_definition( $trigger_code, false );

		if ( is_wp_error( $trigger_validation ) ) {
			return new WP_Error(
				'invalid_trigger_code',
				sprintf(
					/* translators: %s Trigger code. */
					esc_html_x( 'Trigger code does not exist: %s', 'Trigger CRUD error', 'uncanny-automator' ),
					$trigger_code
				)
			);
		}

		return true;
	}

	/**
	 * Build trigger configuration from array.
	 *
	 * @param int    $recipe_id Recipe ID.
	 * @param string $trigger_code Trigger code.
	 * @param array  $config Configuration array.
	 * @return Trigger_Config Built trigger configuration.
	 */
	public function build_trigger_config( int $recipe_id, string $trigger_code, array $config ): Trigger_Config {
		// Get configuration fields and extract labels using shared resolver.
		$configuration_fields = $this->label_resolver->get_configuration_fields( $trigger_code, 'triggers' );
		$field_labels         = $this->label_resolver->extract_field_labels( $configuration_fields );

		// JSON-encode array values (repeater fields) - Automator expects JSON strings, not PHP arrays.
		$configuration = $config['configuration'] ?? array();
		$configuration = $this->encode_array_fields( $configuration );

		// Enrich configuration with readable labels.
		$configuration = $this->label_resolver->enrich_with_readable_labels( $configuration, $configuration_fields, $trigger_code, 'triggers' );

		// Generate sentence outputs with filled-in field values.
		// - brackets: "A user submits {{Form: Simple Contact Form}} {{1}} time(s)"
		// - html: HTML version with styling spans
		// NOTE: Must use $config['sentence'] (with field codes like {{decorator:CODE}}) not sentence_human_readable.
		// The parser needs field codes to look up values in configuration.
		$sentence_template              = $config['sentence'] ?? '';
		$sentence_human_readable_filled = $sentence_template;
		$sentence_human_readable_html   = '';
		if ( ! empty( $sentence_template ) ) {
			$sentence_result                = $this->build_sentence_outputs( $sentence_template, $configuration, $field_labels );
			$sentence_human_readable_filled = $sentence_result['brackets'];
			$sentence_human_readable_html   = $sentence_result['html'];
		}

		return ( new Trigger_Config() )
			->id( null ) // Will be generated when creating the trigger
			->recipe_id( $recipe_id )
			->code( $trigger_code )
			->meta_code( $config['meta_code'] ?? '' )
			->integration( $config['integration'] )
			->sentence( $config['sentence'] )
			->sentence_human_readable( $sentence_human_readable_filled )
			->sentence_human_readable_html( $sentence_human_readable_html )
			->user_type( $config['type'] )
			->hook( is_array( $config['hook'] ) ? $config['hook'] : array( 'name' => $config['hook'] ) )
			->tokens( $config['tokens'] ?? array() )
			->configuration( $configuration );
	}

	/**
	 * Build add trigger success response.
	 *
	 * @param int   $recipe_id Recipe ID.
	 * @param array $trigger_data Trigger data array.
	 * @return array Success response.
	 */
	public function build_add_trigger_response( int $recipe_id, array $trigger_data ): array {
		return array(
			'success'    => true,
			'message'    => 'Trigger successfully added to recipe',
			'recipe_id'  => $recipe_id,
			'trigger_id' => $trigger_data['trigger_id'] ?? null,
			'trigger'    => array(
				'code'                    => $trigger_data['trigger_code'],
				'integration'             => $trigger_data['integration'],
				'type'                    => $trigger_data['trigger_type'],
				'sentence'                => $trigger_data['sentence'],
				'sentence_human_readable' => $trigger_data['sentence_human_readable'],
				'hook'                    => $trigger_data['trigger_hook'],
				'tokens'                  => $trigger_data['trigger_tokens'],
				'configuration'           => $trigger_data['configuration'],
			),
		);
	}

	/**
	 * Add trigger to recipe.
	 *
	 * @param int    $recipe_id Recipe ID.
	 * @param string $trigger_code Trigger code.
	 * @param array  $config Trigger configuration.
	 * @throws Exception IF rules are violated.
	 *
	 * @return array|\WP_Error Success data or error.
	 */
	public function add_to_recipe( int $recipe_id, string $trigger_code, array $config = array() ) {

		try {
			// Validate recipe exists
			$recipe = $this->validate_recipe_exists( $recipe_id );
			if ( ! $recipe ) {
				return new WP_Error(
					'recipe_not_found',
					sprintf(
						/* translators: %d Recipe ID. */
						esc_html_x( 'Add trigger failed: Recipe not found with ID: %d', 'Trigger CRUD error', 'uncanny-automator' ),
						$recipe_id
					)
				);
			}

			// Validate trigger code exists in registry
			$validation = $this->validate_trigger_code( $trigger_code );
			if ( is_wp_error( $validation ) ) {
				return $validation;
			}

			// Build trigger configuration
			$trigger_config = $this->build_trigger_config( $recipe_id, $trigger_code, $config );

			// Create trigger instance - domain validation happens here
			$trigger = new Trigger( $trigger_config );

			// Get the targetted recipe triggers.
			$trigger_logic = User_Type::USER === $recipe->get_recipe_type()->get_value()
			? $recipe->get_recipe_trigger_logic()->get_value()
			: null;

			// Create Recipe Triggers to enforce invariance.
			$collection = $this->trigger_store->get_recipe_triggers( new Recipe_Id( $recipe_id ) );

			( new Recipe_Triggers(
				$collection->get_triggers(),
				$recipe->get_recipe_type()->get_value(),
				$trigger_logic
			) );

			// Save trigger to recipe
			$recipe_id_vo  = new Recipe_Id( $recipe_id );
			$saved_trigger = $this->trigger_store->add_trigger_to_recipe( $recipe_id_vo, $trigger );

			// Return success response with saved trigger data
			$trigger_data = $saved_trigger->to_array();
			return $this->build_add_trigger_response( $recipe_id, $trigger_data );

		} catch ( \Throwable $e ) {
			return new WP_Error(
				'trigger_creation_failed',
				sprintf(
					/* translators: %s Error message. */
					esc_html_x( 'Failed to create trigger: %s', 'Trigger CRUD error', 'uncanny-automator' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Validate trigger type matches recipe type.
	 *
	 * @param string $recipe_type Recipe type ('user' or 'anonymous').
	 * @param string $trigger_type Trigger type.
	 * @return true|\WP_Error True if valid, WP_Error if mismatch.
	 */
	public function validate_trigger_type_match( string $recipe_type, string $trigger_type ) {
		if ( $recipe_type !== $trigger_type ) {
			return new WP_Error(
				'trigger_type_mismatch',
				sprintf(
					/* translators: 1: trigger type, 2: recipe type. */
					esc_html_x( "Trigger type '%1\$s' does not match recipe type '%2\$s'", 'Trigger CRUD error', 'uncanny-automator' ),
					$trigger_type,
					$recipe_type
				)
			);
		}
		return true;
	}

	/**
	 * Validate anonymous recipe trigger limit.
	 *
	 * @param string $recipe_type Recipe type.
	 * @param int    $current_count Current trigger count.
	 * @return true|\WP_Error True if valid, WP_Error if limit exceeded.
	 */
	public function validate_anonymous_trigger_limit( string $recipe_type, int $current_count ) {
		if ( 'anonymous' === $recipe_type && $current_count >= 1 ) {
			return new WP_Error(
				'anonymous_trigger_limit',
				esc_html_x( 'Anonymous recipes can only have 1 trigger', 'Trigger CRUD error', 'uncanny-automator' )
			);
		}
		return true;
	}

	/**
	 * Build trigger entity success response.
	 *
	 * @param int   $recipe_id Recipe ID.
	 * @param int   $saved_trigger_id Saved trigger ID.
	 * @param array $trigger_data Trigger data array.
	 * @return array Success response.
	 */
	public function build_trigger_entity_response( int $recipe_id, int $saved_trigger_id, array $trigger_data ): array {
		$trigger_data['trigger_id'] = $saved_trigger_id;

		return array(
			'success'    => true,
			'message'    => esc_html_x( 'Trigger successfully added to recipe', 'Trigger CRUD success message', 'uncanny-automator' ),
			'recipe_id'  => $recipe_id,
			'trigger_id' => $saved_trigger_id,
			'trigger'    => $this->format_trigger_response( $trigger_data ),
		);
	}

	/**
	 * Add trigger entity to recipe.
	 *
	 * Accepts a validated Trigger domain entity and saves it to the recipe.
	 * This is the preferred method as all validation happens at the domain level.
	 *
	 * @since 7.0.0
	 * @param Trigger $trigger Validated trigger domain entity.
	 * @return array|\WP_Error Success data or error.
	 */
	public function add_trigger_entity( Trigger $trigger ) {

		try {

			// Get recipe ID from trigger entity
			$recipe_id = $trigger->get_recipe_id()->get_value();

			// Get recipe entity (WordPress infrastructure)
			$recipe = $this->recipe_store->get( $recipe_id );
			if ( ! $recipe ) {
				return new WP_Error(
					'recipe_not_found',
					sprintf(
						/* translators: %d Recipe ID. */
						esc_html_x( 'Add trigger entity failed: Recipe not found with ID: %d', 'Trigger CRUD error', 'uncanny-automator' ),
						$recipe_id
					)
				);
			}

			// Validate trigger can be added to this recipe
			$recipe_type   = $recipe->get_recipe_type()->get_value();
			$trigger_type  = $trigger->get_trigger_type()->get_value();
			$current_count = $recipe->get_recipe_triggers()->count();

			$type_validation = $this->validate_trigger_type_match( $recipe_type, $trigger_type );
			if ( is_wp_error( $type_validation ) ) {
				return $type_validation;
			}

			$limit_validation = $this->validate_anonymous_trigger_limit( $recipe_type, $current_count );
			if ( is_wp_error( $limit_validation ) ) {
				return $limit_validation;
			}

			// Save to WordPress infrastructure
			$recipe_id_vo  = new Recipe_Id( $recipe_id );
			$saved_trigger = $this->trigger_store->add_trigger_to_recipe( $recipe_id_vo, $trigger );

			// Return success response with saved trigger data
			$trigger_data     = $saved_trigger->to_array();
			$saved_trigger_id = $saved_trigger->get_trigger_id()->get_value();

			// Validate that the trigger was saved successfully with an ID
			if ( null === $saved_trigger_id ) {
				throw new \RuntimeException( esc_html_x( 'Trigger was saved but no ID was returned', 'Trigger CRUD error', 'uncanny-automator' ) );
			}

			return $this->build_trigger_entity_response( $recipe_id, $saved_trigger_id, $trigger_data );

		} catch ( \Throwable $e ) {
			return new WP_Error(
				'trigger_save_failed',
				sprintf(
					/* translators: %s Error message. */
					esc_html_x( 'Failed to save trigger: %s', 'Trigger CRUD error', 'uncanny-automator' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Get recipe triggers.
	 *
	 * @param int $recipe_id Recipe ID.
	 * @return array|\WP_Error Recipe triggers or error.
	 */
	public function get_recipe_triggers( int $recipe_id ) {
		try {
			// Validate recipe exists
			$recipe = $this->recipe_store->get( $recipe_id );
			if ( ! $recipe ) {
				return new WP_Error(
					'recipe_not_found',
					sprintf(
						/* translators: %d Recipe ID. */
						esc_html_x( 'Get recipe triggers failed: Recipe not found with ID: %d', 'Trigger CRUD error', 'uncanny-automator' ),
						$recipe_id
					)
				);
			}

			// Get triggers
			$recipe_id_vo        = new Recipe_Id( $recipe_id );
			$triggers_collection = $this->trigger_store->get_recipe_triggers( $recipe_id_vo );

			// Format response
			$triggers = array();
			foreach ( $triggers_collection->get_triggers() as $trigger ) {
				if ( $trigger instanceof Trigger ) {
					$trigger_data = $trigger->to_array();
					$triggers[]   = $this->format_trigger_response( $trigger_data );
				}
			}

			return array(
				'success'       => true,
				'recipe_id'     => $recipe_id,
				'triggers'      => $triggers,
				'trigger_logic' => $triggers_collection->get_logic() ? $triggers_collection->get_logic()->get_value() : null,
				'trigger_count' => count( $triggers ),
			);

		} catch ( \Throwable $e ) {
			return new WP_Error(
				'trigger_retrieval_failed',
				sprintf(
					/* translators: %s Error message. */
					esc_html_x( 'Failed to get recipe triggers: %s', 'Trigger CRUD error', 'uncanny-automator' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Get a specific trigger instance by ID.
	 *
	 * @since 7.0.0
	 * @param int $trigger_id Trigger instance ID.
	 * @return array|\WP_Error Trigger data or error.
	 */
	public function get_trigger( int $trigger_id ) {
		$trigger = $this->get_individual_trigger( $trigger_id );
		if ( ! $trigger ) {
			return new WP_Error(
				'trigger_not_found',
				sprintf(
					/* translators: %d Trigger ID. */
					esc_html_x( 'Trigger instance not found with ID: %d', 'Trigger CRUD error', 'uncanny-automator' ),
					$trigger_id
				)
			);
		}

		// Convert to array and remove trigger_tokens from output
		$trigger_array = $trigger->to_array();
		unset( $trigger_array['trigger_tokens'] );

		return array(
			'success' => true,
			'trigger' => $trigger_array,
		);
	}

	/**
	 * Separate WordPress post fields from trigger configuration.
	 *
	 * @param array $config Mixed configuration array.
	 * @return array Array with 'post_updates' and 'trigger_config' keys.
	 */
	public function separate_post_fields( array $config ): array {
		$post_fields    = array( 'post_title', 'post_excerpt', 'post_content', 'post_status', 'menu_order' );
		$post_updates   = array();
		$trigger_config = array();

		foreach ( $config as $key => $value ) {
			if ( in_array( $key, $post_fields, true ) ) {
				$post_updates[ $key ] = $value;
			} else {
				$trigger_config[ $key ] = $value;
			}
		}

		return array(
			'post_updates'   => $post_updates,
			'trigger_config' => $trigger_config,
		);
	}

	/**
	 * Update WordPress post fields.
	 *
	 * @param int   $trigger_id Trigger post ID.
	 * @param array $post_updates Post fields to update.
	 * @return true|\WP_Error True if successful, WP_Error on failure.
	 */
	public function update_post_fields( int $trigger_id, array $post_updates ) {
		if ( empty( $post_updates ) ) {
			return true;
		}

		$post_updates['ID'] = $trigger_id;
		$update_result      = wp_update_post( $post_updates, true );

		if ( is_wp_error( $update_result ) ) {
			return $update_result;
		}

		return true;
	}

	/**
	 * Update trigger configuration.
	 *
	 * @param int   $trigger_id Trigger ID.
	 * @param array $config Updated trigger configuration.
	 * @return array|\WP_Error Success data or error.
	 */
	public function update_trigger( int $trigger_id, array $config = array() ) {
		try {
			// Get existing trigger instance
			$existing_trigger = $this->get_individual_trigger( $trigger_id );
			if ( ! $existing_trigger ) {
				return new WP_Error(
					'trigger_not_found',
					sprintf(
						/* translators: %d Trigger ID. */
						esc_html_x( 'Trigger instance not found with ID: %d', 'Trigger CRUD error', 'uncanny-automator' ),
						$trigger_id
					)
				);
			}

			// Get recipe ID from trigger post
			$trigger_post = get_post( $trigger_id );
			if ( ! $trigger_post || 'uo-trigger' !== $trigger_post->post_type ) {
				return new WP_Error(
					'invalid_trigger',
					esc_html_x( 'Invalid trigger post', 'Trigger CRUD error', 'uncanny-automator' )
				);
			}

			$recipe_id      = new Recipe_Id( $trigger_post->post_parent );
			$trigger_obj_id = new Trigger_Id( $trigger_id );

			// Separate post fields from trigger configuration
			$separated      = $this->separate_post_fields( $config );
			$post_updates   = $separated['post_updates'];
			$trigger_config = $separated['trigger_config'];

			// Update WordPress post fields
			$post_update_result = $this->update_post_fields( $trigger_id, $post_updates );
			if ( is_wp_error( $post_update_result ) ) {
				return $post_update_result;
			}

			// Update trigger instance with new configuration
			$updated_trigger = $this->update_trigger_instance( $existing_trigger, $trigger_config );

			// Save updated trigger using recipe store operations and get persisted version
			$saved_trigger = $this->trigger_store->update_recipe_trigger( $recipe_id, $trigger_obj_id, $updated_trigger );

			return array(
				'success' => true,
				'message' => 'Trigger successfully updated',
				'trigger' => $saved_trigger->to_array(),
			);

		} catch ( \Throwable $e ) {
			return new WP_Error(
				'trigger_update_failed',
				sprintf(
					/* translators: %s Error message. */
					esc_html_x( 'Failed to update trigger: %s', 'Trigger CRUD error', 'uncanny-automator' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Update trigger instance with new configuration.
	 *
	 * @since 7.0.0
	 * @param Trigger $existing_trigger Current trigger instance.
	 * @param array   $new_config New configuration to merge.
	 * @return Trigger Updated trigger instance.
	 */
	private function update_trigger_instance( Trigger $existing_trigger, array $new_config ): Trigger {
		// Get current configuration and merge with new config.
		$current_config = $existing_trigger->get_trigger_configuration()->get_value();

		// JSON-encode array values (repeater fields) - Automator expects JSON strings, not PHP arrays.
		$new_config = $this->encode_array_fields( $new_config );

		$updated_config = array_merge( $current_config, $new_config );

		// Get configuration fields and extract labels using shared resolver.
		$trigger_code         = $existing_trigger->get_trigger_code()->get_value();
		$configuration_fields = $this->label_resolver->get_configuration_fields( $trigger_code, 'triggers' );
		$field_labels         = $this->label_resolver->extract_field_labels( $configuration_fields );

		// Enrich new config values with readable labels.
		$enriched_config = $this->label_resolver->enrich_with_readable_labels( $new_config, $configuration_fields, $trigger_code, 'triggers' );
		$updated_config  = array_merge( $updated_config, $enriched_config );

		// Use the original sentence template (with field codes like {{a form:WPFFORMS}})
		// to regenerate the filled sentence_human_readable and HTML.
		$sentence_template              = $existing_trigger->get_sentence()->get_value();
		$sentence_human_readable_filled = $existing_trigger->get_sentence_human_readable()->get_value();
		$sentence_human_readable_html   = '';

		if ( ! empty( $sentence_template ) ) {
			$sentence_result                = $this->build_sentence_outputs( $sentence_template, $updated_config, $field_labels );
			$sentence_human_readable_filled = $sentence_result['brackets'];
			$sentence_human_readable_html   = $sentence_result['html'];
		}

		// Create updated trigger config
		$config = ( new Trigger_Config() )
			->id( $existing_trigger->get_trigger_id()->get_value() )
			->recipe_id( $existing_trigger->get_recipe_id()->get_value() )
			->code( $existing_trigger->get_trigger_code()->get_value() )
			->meta_code( $existing_trigger->get_trigger_meta_code()->get_value() )
			->integration( $existing_trigger->get_integration()->get_value() )
			->sentence( $sentence_template )
			->sentence_human_readable( $sentence_human_readable_filled )
			->sentence_human_readable_html( $sentence_human_readable_html )
			->user_type( $existing_trigger->get_trigger_type()->get_value() )
			->hook( $existing_trigger->get_trigger_hook()->to_array() )
			->tokens( $existing_trigger->get_trigger_tokens()->to_array() )
			->configuration( $updated_config );

		// Preserve status if it exists
		$status = $existing_trigger->get_status();
		if ( null !== $status ) {
			$config->status( $status->get_value() );
		}

		return new Trigger( $config );
	}

	/**
	 * Remove trigger from recipe.
	 *
	 * @param int $recipe_id Recipe ID.
	 * @param int $trigger_id Trigger ID.
	 * @return array|\WP_Error Success data or error.
	 */
	public function remove_from_recipe( int $recipe_id, int $trigger_id ) {
		try {
			// Validate recipe exists
			$recipe = $this->recipe_store->get( $recipe_id );
			if ( ! $recipe ) {
				return new WP_Error(
					'recipe_not_found',
					sprintf(
						/* translators: %d Recipe ID. */
						esc_html_x( 'Remove trigger failed: Recipe not found with ID: %d', 'Trigger CRUD error', 'uncanny-automator' ),
						$recipe_id
					)
				);
			}

			// Remove trigger
			$recipe_id_vo  = new Recipe_Id( $recipe_id );
			$trigger_id_vo = new Trigger_Id( $trigger_id );
			$this->trigger_store->remove_trigger_from_recipe( $recipe_id_vo, $trigger_id_vo );

			return array(
				'success'    => true,
				'message'    => 'Trigger successfully removed from recipe',
				'recipe_id'  => $recipe_id,
				'trigger_id' => $trigger_id,
			);

		} catch ( \Throwable $e ) {
			return new WP_Error(
				'trigger_removal_failed',
				sprintf(
					/* translators: %s Error message. */
					esc_html_x( 'Failed to remove trigger: %s', 'Trigger CRUD error', 'uncanny-automator' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Normalize and validate trigger logic value.
	 *
	 * @param string $logic Trigger logic to validate.
	 * @return string|\WP_Error Normalized logic or WP_Error if invalid.
	 */
	public function normalize_and_validate_logic( string $logic ) {
		$normalized_logic = strtolower( $logic );
		$allowed_logic    = array( 'all', 'any' );

		if ( ! in_array( $normalized_logic, $allowed_logic, true ) ) {
			return new WP_Error(
				'invalid_logic',
				sprintf(
					/* translators: %s Invalid logic. */
					esc_html_x( 'Trigger logic must be either "all" or "any", got: %s', 'Trigger CRUD error', 'uncanny-automator' ),
					$logic
				)
			);
		}

		return $normalized_logic;
	}

	/**
	 * Set trigger logic for recipe.
	 *
	 * @param int    $recipe_id Recipe ID.
	 * @param string $logic Trigger logic ('all' or 'any').
	 * @return array|\WP_Error Success data or error.
	 */
	public function set_trigger_logic( int $recipe_id, string $logic ) {
		try {
			// Validate recipe exists
			$recipe = $this->recipe_store->get( $recipe_id );
			if ( ! $recipe ) {
				return new WP_Error(
					'recipe_not_found',
					sprintf(
						/* translators: %d Recipe ID. */
						esc_html_x( 'Set trigger logic failed: Recipe not found with ID: %d', 'Trigger CRUD error', 'uncanny-automator' ),
						$recipe_id
					)
				);
			}

			// Normalize and validate logic value
			$normalized_logic = $this->normalize_and_validate_logic( $logic );
			if ( is_wp_error( $normalized_logic ) ) {
				return $normalized_logic;
			}

			// Set trigger logic
			$recipe_id_vo = new Recipe_Id( $recipe_id );
			$this->trigger_store->set_recipe_trigger_logic( $recipe_id_vo, $normalized_logic );

			return array(
				'success'       => true,
				'message'       => 'Trigger logic successfully updated',
				'recipe_id'     => $recipe_id,
				'trigger_logic' => $normalized_logic,
			);

		} catch ( \Throwable $e ) {
			return new WP_Error(
				'trigger_logic_update_failed',
				sprintf(
					/* translators: %s Error message. */
					esc_html_x( 'Failed to update trigger logic: %s', 'Trigger CRUD error', 'uncanny-automator' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Get trigger logic for recipe.
	 *
	 * @param int $recipe_id Recipe ID.
	 * @return array|\WP_Error Trigger logic or error.
	 */
	public function get_trigger_logic( int $recipe_id ) {
		try {
			// Validate recipe exists
			$recipe = $this->recipe_store->get( $recipe_id );
			if ( ! $recipe ) {
				return new WP_Error(
					'recipe_not_found',
					sprintf(
						/* translators: %d Recipe ID. */
						esc_html_x( 'Get trigger logic failed: Recipe not found with ID: %d', 'Trigger CRUD error', 'uncanny-automator' ),
						$recipe_id
					)
				);
			}

			// Get trigger logic
			$recipe_id_vo = new Recipe_Id( $recipe_id );
			$logic        = $this->trigger_store->get_recipe_trigger_logic( $recipe_id_vo );

			return array(
				'success'       => true,
				'recipe_id'     => $recipe_id,
				'trigger_logic' => $logic,
			);

		} catch ( \Throwable $e ) {
			return new WP_Error(
				'trigger_logic_retrieval_failed',
				sprintf(
					/* translators: %s Error message. */
					esc_html_x( 'Failed to get trigger logic: %s', 'Trigger CRUD error', 'uncanny-automator' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Check if recipe has triggers.
	 *
	 * @param int $recipe_id Recipe ID.
	 * @return array|\WP_Error Check result or error.
	 */
	public function recipe_has_triggers( int $recipe_id ) {
		try {
			// Validate recipe exists
			$recipe = $this->recipe_store->get( $recipe_id );
			if ( ! $recipe ) {
				return new WP_Error(
					'recipe_not_found',
					sprintf(
						/* translators: %d Recipe ID. */
						esc_html_x( 'Recipe has triggers failed: Recipe not found with ID: %d', 'Trigger CRUD error', 'uncanny-automator' ),
						$recipe_id
					)
				);
			}

			// Check if recipe has triggers
			$recipe_id_vo  = new Recipe_Id( $recipe_id );
			$has_triggers  = $this->trigger_store->recipe_has_triggers( $recipe_id_vo );
			$trigger_count = $this->trigger_store->count_recipe_triggers( $recipe_id_vo );

			return array(
				'success'       => true,
				'recipe_id'     => $recipe_id,
				'has_triggers'  => $has_triggers,
				'trigger_count' => $trigger_count,
			);

		} catch ( \Throwable $e ) {
			return new WP_Error(
				'trigger_check_failed',
				sprintf(
					/* translators: %s Error message. */
					esc_html_x( 'Failed to check recipe triggers: %s', 'Trigger CRUD error', 'uncanny-automator' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Delete all triggers from recipe.
	 *
	 * @param int $recipe_id Recipe ID.
	 * @return array|\WP_Error Success data or error.
	 */
	public function delete_recipe_triggers( int $recipe_id ) {
		try {
			// Validate recipe exists
			$recipe = $this->recipe_store->get( $recipe_id );
			if ( ! $recipe ) {
				return new WP_Error(
					'recipe_not_found',
					sprintf(
						/* translators: %d Recipe ID. */
						esc_html_x( 'Delete recipe triggers failed: Recipe not found with ID: %d', 'Trigger CRUD error', 'uncanny-automator' ),
						$recipe_id
					)
				);
			}

			// Delete all triggers
			$recipe_id_vo = new Recipe_Id( $recipe_id );
			$this->trigger_store->delete_recipe_triggers( $recipe_id_vo );

			return array(
				'success'   => true,
				'message'   => 'All triggers successfully deleted from recipe',
				'recipe_id' => $recipe_id,
			);

		} catch ( \Throwable $e ) {
			return new WP_Error(
				'trigger_deletion_failed',
				sprintf(
					/* translators: %s Error message. */
					esc_html_x( 'Failed to delete recipe triggers: %s', 'Trigger CRUD error', 'uncanny-automator' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Format trigger response.
	 *
	 * Transforms raw trigger data into a structured API response format.
	 * Maps internal domain keys (with 'trigger_' prefix) to clean external API keys.
	 *
	 * @param array $trigger_data Raw trigger data from domain entity.
	 * @return array Formatted trigger data for API response.
	 */
	private function format_trigger_response( array $trigger_data ): array {
		// Handle edge case: empty or invalid data
		if ( empty( $trigger_data ) ) {
			return array(
				'id'                      => null,
				'recipe_id'               => null,
				'code'                    => '',
				'integration'             => '',
				'type'                    => '',
				'sentence'                => '',
				'sentence_human_readable' => '',
				'hook'                    => array(),
				'tokens'                  => array(),
				'configuration'           => array(),
			);
		}

		// Transform domain keys to API response keys
		// Domain uses 'trigger_' prefix, API uses cleaner keys
		return array(
			'id'                      => $trigger_data['trigger_id'] ?? null,
			'trigger_id'              => $trigger_data['trigger_id'] ?? null, // Backward compatibility
			'recipe_id'               => $trigger_data['recipe_id'] ?? null,
			'code'                    => $trigger_data['trigger_code'] ?? '',
			'integration'             => $trigger_data['integration'] ?? '',
			'type'                    => $trigger_data['trigger_type'] ?? '',
			'sentence'                => $trigger_data['sentence'] ?? '',
			'sentence_human_readable' => $trigger_data['sentence_human_readable'] ?? '',
			'hook'                    => $trigger_data['trigger_hook'] ?? array(),
			'tokens'                  => $trigger_data['trigger_tokens'] ?? array(),
			'configuration'           => $trigger_data['configuration'] ?? array(),
		);
	}

	/**
	 * Get individual trigger instance by ID.
	 *
	 * Retrieves a single trigger using WordPress post system and store hydration.
	 *
	 * @param int $trigger_id Trigger post ID.
	 * @return Trigger|null Trigger instance or null if not found.
	 */
	private function get_individual_trigger( int $trigger_id ): ?Trigger {
		// Get trigger post directly from WordPress
		$trigger_post = get_post( $trigger_id );

		// Validate it's a trigger post
		if ( ! $trigger_post || 'uo-trigger' !== $trigger_post->post_type ) {
			return null;
		}

		// Use store's public hydration method
		return $this->trigger_store->hydrate_trigger_from_post( $trigger_post );
	}

	/**
	 * JSON-encode array values in config.
	 *
	 * Automator stores repeater field values as JSON strings, not PHP arrays.
	 * This method converts any array values to JSON strings for compatibility.
	 *
	 * @param array $config Configuration array from AI agent.
	 * @return array Config with arrays JSON-encoded.
	 */
	private function encode_array_fields( array $config ): array {
		foreach ( $config as $key => $value ) {
			if ( is_array( $value ) ) {
				$config[ $key ] = wp_json_encode( $value );
			}
		}
		return $config;
	}

	/**
	 * Build sentence outputs using the Sentence_Output_Builder.
	 *
	 * Delegates to the shared Sentence_Output_Builder to convert raw configuration
	 * arrays into domain objects and generate both bracket-wrapped and HTML sentence formats.
	 *
	 * @param string $sentence_template The sentence template with {{decorator:CODE}} tokens.
	 * @param array  $configuration     Field values including _readable suffixes.
	 * @param array  $field_labels      Map of field codes to labels.
	 *
	 * @return array{brackets: string, html: string} Sentence outputs.
	 */
	private function build_sentence_outputs( string $sentence_template, array $configuration, array $field_labels ): array {
		return $this->sentence_builder->build( $sentence_template, $configuration, $field_labels );
	}
}
