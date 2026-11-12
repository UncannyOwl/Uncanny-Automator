<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Recipe_Builder\Trigger\Services;

use Uncanny_Automator\App\Events\Recipe_Change_Events;
use Exception;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Value_Objects\Recipe_Id;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Value_Objects\Recipe_Status;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Value_Objects\Recipe_Triggers;
use Uncanny_Automator\App\Recipe_Builder\Shared\Enums\User_Type;
use Uncanny_Automator\App\Recipe_Builder\Shared\Sentence_Html\Field_Label_Resolver;
use Uncanny_Automator\App\Recipe_Builder\Field\Services\Field_Validation_Service;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Services\Recipe_Violation_Service;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Services\Item_Publish_On_Save_Policy;
use Uncanny_Automator\App\Presentation\Recipe_Builder\Sentence\Item_Sentence_Composer;
use Uncanny_Automator\App\Recipe_Builder\Trigger\Trigger;
use Uncanny_Automator\App\Recipe_Builder\Trigger\Trigger_Config;
use Uncanny_Automator\App\Recipe_Builder\Trigger\Value_Objects\Trigger_Id;
use Uncanny_Automator\App\Infrastructure\Cache\Recipe_Cache;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Services\Recipe_Runnability_Service;
use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Infrastructure\Exceptions\Store_Exception;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Recipe_Store;
use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Recipe_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Recipe_Trigger_Store;
use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Recipe_Trigger_Store;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Recipe;
use Uncanny_Automator\App\Recipe_Builder\Trigger\Enums\Trigger_Status;

use WP_Error;
use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\App\Events\Enums\Recipe_Change_Operation;
use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Integration_Item_Types;
use Uncanny_Automator\App\Events\Dtos\Recipe_Changed;

/**
 * Trigger Service - WordPress Application Layer.
 *
 * WordPress-aware service layer that delegates business logic
 * to pure operations layer and handles WordPress-specific concerns.
 * Acts as adapter between pure business logic and WordPress infrastructure.
 * Sentence and sentence HTML composition is owned by presentation layer
 * (`Item_Sentence_Composer`).
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
	 * @var Recipe_Store
	 */
	private $recipe_store;

	/**
	 * Trigger store.
	 *
	 * @var Recipe_Trigger_Store
	 */
	private $trigger_store;

	/**
	 * Field label resolver.
	 *
	 * @var Field_Label_Resolver
	 */
	private $label_resolver;

	/**
	 * Sentence output composer.
	 *
	 * @var Item_Sentence_Composer
	 */
	private $sentence_composer;

	/**
	 * Field validation service (single validation entry point).
	 *
	 * @var Field_Validation_Service
	 */
	private Field_Validation_Service $validation_service;

	/**
	 * Set-status validation rule engine (the item→on gate).
	 *
	 * @var Recipe_Violation_Service
	 */
	private Recipe_Violation_Service $violation_service;

	/**
	 * Whether to switch a trigger on after an add or a save.
	 *
	 * @var Item_Publish_On_Save_Policy
	 */
	private Item_Publish_On_Save_Policy $publish_policy;

	/**
	 * Constructor.
	 *
	 * Allows dependency injection for testing. Production code can use instance()
	 * for backward compatibility with singleton pattern.
	 *
	 * @param Recipe_Store|null           $recipe_store      Optional recipe store instance.
	 * @param Recipe_Trigger_Store|null   $trigger_store     Optional trigger store instance.
	 * @param Field_Label_Resolver|null      $label_resolver    Optional label resolver instance.
	 * @param Item_Sentence_Composer|null    $sentence_composer Optional sentence composer instance.
	 * @param Field_Validation_Service|null  $validation_service Optional field validation service.
	 * @param Recipe_Violation_Service|null  $violation_service  Optional set-status validation engine.
	 * @param Item_Publish_On_Save_Policy|null $publish_policy   Optional publish-on-save policy.
	 */
	public function __construct(
		?Recipe_Store $recipe_store = null,
		?Recipe_Trigger_Store $trigger_store = null,
		?Field_Label_Resolver $label_resolver = null,
		?Item_Sentence_Composer $sentence_composer = null,
		?Field_Validation_Service $validation_service = null,
		?Recipe_Violation_Service $violation_service = null,
		?Item_Publish_On_Save_Policy $publish_policy = null
	) {
		global $wpdb;
		$this->recipe_store       = $recipe_store ?? new WP_Recipe_Store();
		$this->trigger_store      = $trigger_store ?? new WP_Recipe_Trigger_Store( $wpdb );
		$this->label_resolver     = $label_resolver ?? new Field_Label_Resolver();
		$this->sentence_composer  = $sentence_composer ?? new Item_Sentence_Composer();
		$this->validation_service = $validation_service ?? Field_Validation_Service::instance();
		$this->violation_service  = $violation_service ?? Recipe_Violation_Service::instance();
		$this->publish_policy     = $publish_policy ?? Item_Publish_On_Save_Policy::instance();
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
		$trigger_registry_service = Trigger_Registry_Service::instance();
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
	 * Refuse a trigger config that lacks a key the trigger is built from, or holds a value of the wrong type.
	 *
	 * A trigger is built from its integration, sentence and type; its hooks come
	 * from the registered trigger, and meta_code, tokens and configuration are
	 * optional. Without this check the build stops on a PHP TypeError, whose
	 * message names the server's files.
	 *
	 * @since 8.0.0
	 * @since 8.0.4 No longer requires hook.
	 *
	 * @param array $config Trigger configuration.
	 * @return WP_Error|null The refusal, or null when the trigger can be built from the config.
	 */
	private function validate_trigger_config_keys( array $config ): ?WP_Error {
		$missing = array();
		foreach ( array( 'integration', 'sentence', 'type' ) as $key ) {
			if ( ! isset( $config[ $key ] ) ) {
				$missing[] = $key;
			}
		}

		if ( ! empty( $missing ) ) {
			return new WP_Error(
				'invalid_trigger_config',
				sprintf(
					/* translators: %s Comma-separated configuration keys. */
					esc_html_x( 'Add trigger failed: the trigger configuration has no %s.', 'Trigger CRUD error', 'uncanny-automator' ),
					implode( ', ', $missing )
				)
			);
		}

		foreach ( array( 'integration', 'sentence', 'type', 'meta_code' ) as $key ) {
			if ( isset( $config[ $key ] ) && ! is_string( $config[ $key ] ) ) {
				return new WP_Error(
					'invalid_trigger_config',
					sprintf(
						/* translators: %s Configuration key. */
						esc_html_x( 'Add trigger failed: %s in the trigger configuration must be a string.', 'Trigger CRUD error', 'uncanny-automator' ),
						$key
					)
				);
			}
		}

		foreach ( array( 'tokens', 'configuration' ) as $key ) {
			if ( isset( $config[ $key ] ) && ! is_array( $config[ $key ] ) ) {
				return new WP_Error(
					'invalid_trigger_config',
					sprintf(
						/* translators: %s Configuration key. */
						esc_html_x( 'Add trigger failed: %s in the trigger configuration must be an array.', 'Trigger CRUD error', 'uncanny-automator' ),
						$key
					)
				);
			}
		}

		return null;
	}

	/**
	 * Build trigger configuration from array.
	 *
	 * The trigger carries the hook of its registry definition, never one from the
	 * caller's config; the trigger store resolves what is stored.
	 *
	 * @since 8.0.4 The hook comes from the registry definition, never the caller.
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
		$configuration = $this->strip_sentence_artifacts( $configuration );
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

		$definition = Trigger_Registry_Service::instance()->get_trigger_definition( $trigger_code, false );
		$carried    = is_array( $definition ) ? ( $definition['trigger_hook']['name'] ?? '' ) : '';

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
			->hook( array( 'name' => $carried ) )
			->tokens( $config['tokens'] ?? array() )
			->configuration( $configuration );
	}

	/**
	 * Build add trigger success response.
	 *
	 * @since 7.0.0
	 * @since 7.2.4 Uses the canonical trigger response formatter for contract parity.
	 *
	 * @param int   $recipe_id Recipe ID.
	 * @param array $trigger_data Trigger data array.
	 * @return array Success response.
	 */
	public function build_add_trigger_response( int $recipe_id, array $trigger_data ): array {
		$trigger_payload              = $this->format_trigger_response( $trigger_data );
		$trigger_payload['recipe_id'] = $recipe_id;

		return array(
			'success'    => true,
			'message'    => 'Trigger successfully added to recipe',
			'recipe_id'  => $recipe_id,
			'trigger_id' => $trigger_payload['trigger_id'] ?? null,
			'trigger'    => $trigger_payload,
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
		return Recipe_Change_Events::operation(
			function () use ( $recipe_id, $trigger_code, $config ) {

				$trashed_error = $this->validate_recipe_writable( $recipe_id );
				if ( is_wp_error( $trashed_error ) ) {
					return $trashed_error;
				}

				$saved_trigger_id = 0;
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

					$config_error = $this->validate_trigger_config_keys( $config );
					if ( is_wp_error( $config_error ) ) {
						return $config_error;
					}

					// Build trigger configuration
					$trigger_config = $this->build_trigger_config( $recipe_id, $trigger_code, $config );

					// Create trigger instance - domain validation happens here
					$trigger = new Trigger( $trigger_config );

					// The trigger refuses a value it can't use; the add saves nothing.
					if ( ! empty( $config['configuration'] ) ) {
						$save_check = $this->validation_service->check_item_save( 'trigger', $trigger_code, $trigger->get_trigger_configuration()->get_value() );
						if ( is_wp_error( $save_check ) ) {
							return $save_check;
						}
					}

					// Validate trigger type matches recipe type (user vs anonymous).
					$recipe_type  = $recipe->get_recipe_type()->get_value();
					$trigger_type = $trigger->get_trigger_type()->get_value();

					$type_validation = $this->validate_trigger_type_match( $recipe_type, $trigger_type );
					if ( is_wp_error( $type_validation ) ) {
						return $type_validation;
					}

					// Get the targetted recipe triggers.
					$trigger_logic = User_Type::USER === $recipe_type
					? $recipe->get_recipe_trigger_logic()->get_value()
					: null;

					// Validate anonymous recipe trigger limit.
					$collection    = $this->trigger_store->get_recipe_triggers( new Recipe_Id( $recipe_id ) );
					$current_count = count( $collection->get_triggers() );

					$limit_validation = $this->validate_anonymous_trigger_limit( $recipe_type, $current_count );
					if ( is_wp_error( $limit_validation ) ) {
						return $limit_validation;
					}

					// Create Recipe Triggers to enforce invariance.
					( new Recipe_Triggers(
						$collection->get_triggers(),
						$recipe_type,
						$trigger_logic
					) );

					// Save trigger to recipe
					$recipe_id_vo     = new Recipe_Id( $recipe_id );
					$saved_trigger    = $this->trigger_store->add_trigger_to_recipe( $recipe_id_vo, $trigger );
					$saved_trigger_id = (int) $saved_trigger->get_trigger_id()->get_value();

					// Return success response with saved trigger data
					$trigger_data = $saved_trigger->to_array();
					$response     = $this->build_add_trigger_response( $recipe_id, $trigger_data );

					// automator_trigger_response lets Pro (or any other consumer) enrich
					// the response — the CRUD service stays oblivious to what they add.
					if ( $saved_trigger_id > 0 ) {
						// A refusal from the item-on check leaves it draft; the add still succeeds.
						if ( $this->publish_policy->publishes_on_add( $recipe_id, 'trigger', $trigger->get_integration()->get_value(), $trigger_code, ! empty( $config['configuration'] ) ) ) {
							$this->set_status( $saved_trigger_id, $recipe_id, true );
						}

						Recipe_Change_Events::changed( new Recipe_Changed( Recipe_Change_Operation::CREATED, Integration_Item_Types::TRIGGER, $saved_trigger_id, $recipe_id ) );
						$response = Dispatcher::filter( 'automator_trigger_response', $response, $recipe_id, $saved_trigger_id, $trigger_code );
					}

					return $response;

				} catch ( \Throwable $e ) {
					// The trigger landed before the failure: report the add, so the created listeners run.
					if ( $saved_trigger_id > 0 ) {
						Recipe_Change_Events::changed( new Recipe_Changed( Recipe_Change_Operation::CREATED, Integration_Item_Types::TRIGGER, $saved_trigger_id, $recipe_id ) );
					}
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
		);
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
		return Recipe_Change_Events::operation(
			function () use ( $trigger_id, $post_updates ) {
				if ( empty( $post_updates ) ) {
					return true;
				}

				$post_updates['ID'] = $trigger_id;
				$update_result      = wp_update_post( $post_updates, true );

				if ( is_wp_error( $update_result ) ) {
					return $update_result;
				}

				$trigger_post = $this->trigger_store->get_wp_post( $trigger_id );
				if ( $trigger_post instanceof \WP_Post && 0 < (int) $trigger_post->post_parent ) {
					Recipe_Change_Events::changed( new Recipe_Changed( Recipe_Change_Operation::UPDATED, Integration_Item_Types::TRIGGER, $trigger_id, (int) $trigger_post->post_parent ) );
				}

				return true;
			}
		);
	}

	/**
	 * Update trigger configuration.
	 *
	 * @since 7.0.0
	 * @since 7.2.4 Returns canonical trigger response format on success.
	 *
	 * @param int         $trigger_id             Trigger ID.
	 * @param array       $config                 Updated trigger configuration.
	 * @param int|null    $expected_recipe_id     Expected owning recipe.
	 * @param string|null $requested_code         Expected trigger code.
	 * @return array|\WP_Error Success data or error.
	 */
	public function update_trigger( int $trigger_id, array $config = array(), ?int $expected_recipe_id = null, ?string $requested_code = null ) {
		return Recipe_Change_Events::operation(
			function () use ( $trigger_id, $config, $expected_recipe_id, $requested_code ) {
				$stored_before = null;
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
					if ( ! $trigger_post || AUTOMATOR_POST_TYPE_TRIGGER !== $trigger_post->post_type ) {
						return new WP_Error(
							'invalid_trigger',
							esc_html_x( 'Invalid trigger post', 'Trigger CRUD error', 'uncanny-automator' )
						);
					}

					$trashed_error = $this->validate_recipe_writable( (int) $trigger_post->post_parent );
					if ( is_wp_error( $trashed_error ) ) {
						return $trashed_error;
					}

					$ownership_error = $this->validate_ownership( $trigger_id, $trigger_post, $expected_recipe_id );
					if ( is_wp_error( $ownership_error ) ) {
						return $ownership_error;
					}

					$code_error = $this->validate_code_unchanged( $trigger_id, $requested_code );
					if ( is_wp_error( $code_error ) ) {
						return $code_error;
					}

					$recipe_id      = new Recipe_Id( $trigger_post->post_parent );
					$trigger_obj_id = new Trigger_Id( $trigger_id );

					// Separate post fields from trigger configuration
					$separated      = $this->separate_post_fields( $config );
					$post_updates   = $separated['post_updates'];
					$trigger_config = $separated['trigger_config'];

					// The on / off switch is set_status()'s, never a raw post write.
					$requested_status = null;
					if ( array_key_exists( 'post_status', $post_updates ) ) {
						$requested_status = is_scalar( $post_updates['post_status'] ) ? (string) $post_updates['post_status'] : '';
					}
					unset( $post_updates['post_status'] );
					if ( null !== $requested_status && ! Trigger_Status::is_valid( $requested_status ) ) {
						return new WP_Error(
							'invalid_trigger_status',
							sprintf(
								/* translators: %s Requested status. */
								esc_html_x( 'A trigger’s status is "publish" or "draft", not "%s". Nothing in this request was saved.', 'Trigger CRUD error', 'uncanny-automator' ),
								esc_html( $requested_status )
							)
						);
					}

					// The store writes a trigger's title from its sentence, so a requested title would be lost.
					if ( array_key_exists( 'post_title', $post_updates ) ) {
						return new WP_Error(
							'trigger_title_not_writable',
							esc_html_x( 'A trigger’s title comes from its sentence and cannot be set. Nothing in this request was saved.', 'Trigger CRUD error', 'uncanny-automator' )
						);
					}

					$updated_trigger = $this->update_trigger_instance( $existing_trigger, $trigger_config );

					// The trigger refuses a value it can't use; nothing of the request is saved.
					$save_check = $this->validation_service->check_item_save( 'trigger', $updated_trigger->get_trigger_code()->get_value(), $updated_trigger->get_trigger_configuration()->get_value() );
					if ( is_wp_error( $save_check ) ) {
						return $save_check;
					}

					// A switch-on the item-on rules refuse saves nothing of the request.
					if ( null !== $requested_status && Trigger_Status::is_item_on( $requested_status ) ) {
						$rejection = $this->item_on_rejection( $trigger_id, $updated_trigger );
						if ( $rejection instanceof WP_Error ) {
							return new WP_Error(
								$rejection->get_error_code(),
								$rejection->get_error_message() . ' ' . esc_html_x( 'Nothing in this request was saved.', 'Trigger CRUD error', 'uncanny-automator' ),
								$rejection->get_error_data()
							);
						}
					}

					// Nothing is written above this line. The trigger as stored now tells a failed save that wrote from one that did not.
					$stored_before = $this->stored_trigger( $trigger_id );

					// Update WordPress post fields
					$post_update_result = $this->update_post_fields( $trigger_id, $post_updates );
					if ( is_wp_error( $post_update_result ) ) {
						return $post_update_result;
					}

					// Save updated trigger using recipe store operations and get persisted version
					$saved_trigger = $this->trigger_store->update_recipe_trigger( $recipe_id, $trigger_obj_id, $updated_trigger );

					$response = array(
						'success' => true,
						'message' => 'Trigger successfully updated',
						'trigger' => $this->format_trigger_response( $saved_trigger->to_array() ),
					);

					$int_recipe_id = (int) $recipe_id->get_value();
					$trigger_code  = $existing_trigger->get_trigger_code()->get_value();

					// An explicit status from the caller wins over the save rule. A refusal
					// on the save rule leaves the trigger as it was; the save still succeeds.
					$status_result = null;
					if ( null !== $requested_status ) {
						$recipe_was_live = $this->recipe_is_live( $int_recipe_id );
						$status_result   = $this->set_status( $trigger_id, $int_recipe_id, Trigger_Status::is_item_on( $requested_status ) );
						if ( $recipe_was_live && ! is_wp_error( $status_result ) && ! $this->recipe_is_live( $int_recipe_id ) ) {
							$response['message'] = esc_html_x( 'Trigger successfully updated. The recipe was switched off because it no longer has both a live trigger and a live action.', 'Trigger CRUD message', 'uncanny-automator' );
						}
					} elseif ( $this->publish_policy->publishes_on_update( $int_recipe_id, 'trigger', $trigger_code ) ) {
						$this->set_status( $trigger_id, $int_recipe_id, true );
					}

					// The field changes landed even when the status change was refused, so the event fires either way.
					Recipe_Change_Events::changed( new Recipe_Changed( Recipe_Change_Operation::UPDATED, Integration_Item_Types::TRIGGER, $trigger_id, $int_recipe_id ) );

					if ( is_wp_error( $status_result ) ) {
						return new WP_Error(
							$status_result->get_error_code(),
							$status_result->get_error_message() . ' ' . esc_html_x( 'The field changes in this request were saved.', 'Trigger CRUD error', 'uncanny-automator' ),
							$status_result->get_error_data()
						);
					}

					$response = Dispatcher::filter( 'automator_trigger_response', $response, $int_recipe_id, $trigger_id, $trigger_code );

					return $response;

				} catch ( \Throwable $e ) {
					// An event means a write landed: the post fields or the meta changed before the failure.
					if ( null !== $stored_before && $stored_before !== $this->stored_trigger( $trigger_id ) ) {
						Recipe_Change_Events::changed( new Recipe_Changed( Recipe_Change_Operation::UPDATED, Integration_Item_Types::TRIGGER, $trigger_id, (int) $recipe_id->get_value() ) );
					}
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
		);
	}

	/**
	 * The trigger as stored: its post's fields and its meta.
	 *
	 * Read before an update writes and again when the update fails, it tells a
	 * save that wrote something from one that wrote nothing.
	 *
	 * @since 8.0.0
	 *
	 * @param int $trigger_id Trigger post ID.
	 * @return array
	 */
	private function stored_trigger( int $trigger_id ): array {
		$post   = get_post( $trigger_id );
		$fields = $post instanceof \WP_Post ? get_object_vars( $post ) : array();

		// WordPress stamps the modified time on every update, one that changes nothing included.
		unset( $fields['post_modified'], $fields['post_modified_gmt'] );

		return array(
			'post' => $fields,
			'meta' => get_post_meta( $trigger_id ),
		);
	}

	/**
	 * Toggle the trigger's post_status between publish and draft.
	 *
	 * @param int  $trigger_id         Trigger post ID.
	 * @param int  $expected_recipe_id Recipe ID from the REST URL.
	 * @param bool $is_on              true → publish, false → draft.
	 *
	 * @return array|WP_Error array{ success: true } on success; WP_Error on validation failure, or (status 500) when the status write is refused.
	 */
	public function set_status( int $trigger_id, int $expected_recipe_id, bool $is_on ) {
		return Recipe_Change_Events::operation(
			function () use ( $trigger_id, $expected_recipe_id, $is_on ) {
				$trigger_post = $this->find_recipe_trigger_post( $trigger_id, $expected_recipe_id );
				if ( is_wp_error( $trigger_post ) ) {
					return $trigger_post;
				}

				$writable = $this->validate_recipe_writable( $expected_recipe_id );
				if ( is_wp_error( $writable ) ) {
					return new WP_Error( $writable->get_error_code(), $writable->get_error_message(), array( 'status' => 409 ) );
				}

				// Going live: the trigger's persisted config must pass full validation
				// (fail-closed). Drafting is never gated.
				if ( $is_on ) {
					$trigger = $this->trigger_store->hydrate_trigger_from_post( $trigger_post );
					if ( null === $trigger ) {
						return new WP_Error(
							'trigger_not_found',
							sprintf(
								/* translators: %1$d Trigger ID, %2$d Recipe ID. */
								esc_html_x( 'Trigger %1$d not found in recipe %2$d.', 'Trigger CRUD error', 'uncanny-automator' ),
								$trigger_id,
								$expected_recipe_id
							),
							array( 'status' => 404 )
						);
					}

					$rejection = $this->item_on_rejection( $trigger_id, $trigger );
					if ( $rejection instanceof WP_Error ) {
						return $rejection;
					}
				}

				$status = $is_on ? Trigger_Status::PUBLISH : Trigger_Status::DRAFT;
				try {
					Database::get_recipe_item_status_store()->update_post_status( $trigger_id, $status );
				} catch ( Store_Exception $e ) {
					// The write did not land: nothing changed, so no event.
					return new WP_Error(
						'trigger_status_update_failed',
						sprintf(
							/* translators: %s Error message. */
							esc_html_x( 'Failed to update trigger status: %s', 'Trigger CRUD error', 'uncanny-automator' ),
							$e->getMessage()
						),
						array( 'status' => 500 )
					);
				}
				Recipe_Cache::invalidate( $expected_recipe_id );

				Recipe_Runnability_Service::instance()->demote_if_empty( $expected_recipe_id );

				Recipe_Change_Events::changed( new Recipe_Changed( Recipe_Change_Operation::UPDATED, Integration_Item_Types::TRIGGER, $trigger_id, $expected_recipe_id ) );

				return array( 'success' => true );
			}
		);
	}

	/**
	 * Whether the recipe is live right now.
	 *
	 * @param int $recipe_id Recipe post ID.
	 *
	 * @return bool
	 */
	private function recipe_is_live( int $recipe_id ): bool {
		$recipe_post = $this->recipe_store->get_wp_post( $recipe_id );

		return $recipe_post instanceof \WP_Post && Recipe_Status::is_recipe_on( $recipe_post->post_status );
	}

	/**
	 * The item-on rules' refusal for a trigger going live, or null when it may.
	 *
	 * @param int     $trigger_id Trigger post ID.
	 * @param Trigger $trigger    The trigger as it would go live.
	 *
	 * @return WP_Error|null
	 */
	private function item_on_rejection( int $trigger_id, Trigger $trigger ): ?WP_Error {
		return Recipe_Violation_Service::rejection_error(
			$this->violation_service->evaluate_item_on(
				$trigger_id,
				'trigger',
				$trigger->get_trigger_code()->get_value(),
				$trigger->get_integration()->get_value(),
				$trigger->get_custom_name(),
				$trigger->get_trigger_configuration()->get_value()
			),
			esc_html_x( 'This trigger can’t be switched on yet.', 'Trigger CRUD error', 'uncanny-automator' )
		);
	}

	/**
	 * Update trigger instance with new configuration.
	 *
	 * @since 7.0.0
	 * @since 8.0.0 A sent key replaces the stored key it spells in another case.
	 * @since 8.0.0 A trigger with no stored sentence takes its template from its definition.
	 * @param Trigger $existing_trigger Current trigger instance.
	 * @param array   $new_config New configuration to merge.
	 * @return Trigger Updated trigger instance.
	 */
	private function update_trigger_instance( Trigger $existing_trigger, array $new_config ): Trigger {
		// Get current configuration and merge with new config.
		$current_config = $existing_trigger->get_trigger_configuration()->get_value();
		$new_config     = $this->strip_sentence_artifacts( $new_config );

		// JSON-encode array values (repeater fields) - Automator expects JSON strings, not PHP arrays.
		$new_config = $this->encode_array_fields( $new_config );

		// Meta keys compare without case, so a sent `PAGE_CUSTOM` names the stored `PAGE_custom`; both reaching the store would keep the stored one.
		$updated_config = array_merge( array_diff_ukey( $current_config, $new_config, 'strcasecmp' ), $new_config );

		// Get configuration fields and extract labels using shared resolver.
		$trigger_code         = $existing_trigger->get_trigger_code()->get_value();
		$configuration_fields = $this->label_resolver->get_configuration_fields( $trigger_code, 'triggers' );
		$field_labels         = $this->label_resolver->extract_field_labels( $configuration_fields );

		// Enrich new config values with readable labels.
		$enriched_config = $this->label_resolver->enrich_with_readable_labels( $new_config, $configuration_fields, $trigger_code, 'triggers' );
		$updated_config  = array_merge( $updated_config, $enriched_config );
		$updated_config  = $this->label_resolver->prune_stale_readables( $updated_config, $new_config, $enriched_config );

		// Use the original sentence template (with field codes like {{a form:WPFFORMS}})
		// to regenerate the filled sentence_human_readable and HTML.
		$sentence_template = $existing_trigger->get_sentence();
		if ( '' === $sentence_template ) {
			$definition        = Trigger_Registry_Service::instance()->get_trigger_definition( $trigger_code, false );
			$sentence_template = is_array( $definition ) ? (string) ( $definition['sentence'] ?? '' ) : '';
		}

		$sentence_human_readable_filled = $existing_trigger->get_sentence_human_readable();
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
	 * @since 7.0.0
	 * @since 8.0.0 Refuses a trigger that does not exist or belongs to another recipe.
	 *
	 * @param int $recipe_id Recipe ID.
	 * @param int $trigger_id Trigger ID.
	 * @return array|\WP_Error Success data or error.
	 */
	public function remove_from_recipe( int $recipe_id, int $trigger_id ) {
		return Recipe_Change_Events::operation(
			function () use ( $recipe_id, $trigger_id ) {
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

					$trigger_post = $this->find_recipe_trigger_post( $trigger_id, $recipe_id );
					if ( is_wp_error( $trigger_post ) ) {
						return $trigger_post;
					}

					$this->trigger_store->remove_trigger_from_recipe( new Recipe_Id( $recipe_id ), new Trigger_Id( $trigger_id ) );

					// Noted before the demote: the delete has landed even if the demote then fails.
					Recipe_Change_Events::changed( new Recipe_Changed( Recipe_Change_Operation::DELETED, Integration_Item_Types::TRIGGER, $trigger_id, $recipe_id ) );

					Recipe_Runnability_Service::instance()->demote_if_empty( $recipe_id );

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
		);
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
		return Recipe_Change_Events::operation(
			function () use ( $recipe_id, $logic ) {
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

					Recipe_Change_Events::changed( new Recipe_Changed( Recipe_Change_Operation::UPDATED, Recipe_Changed::RECIPE, $recipe_id, $recipe_id ) );

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
		);
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
	 * Format trigger response.
	 *
	 * @since 7.0.0
	 * @since 7.2.4 Includes MCP-aligned keys (`trigger_code`, nullable `sentence_human_readable_html`).
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
				'id'                           => null,
				'trigger_id'                   => null,
				'recipe_id'                    => null,
				'trigger_code'                 => '',
				'code'                         => '',
				'integration'                  => '',
				'type'                         => '',
				'sentence'                     => '',
				'sentence_human_readable'      => '',
				'sentence_human_readable_html' => null,
				'hook'                         => array(),
				'tokens'                       => array(),
				'configuration'                => array(),
			);
		}

		// Transform domain keys to API response keys
		// Domain uses 'trigger_' prefix, API uses cleaner keys
		return array(
			'id'                           => $trigger_data['trigger_id'] ?? null,
			'trigger_id'                   => $trigger_data['trigger_id'] ?? null, // Backward compatibility
			'recipe_id'                    => $trigger_data['recipe_id'] ?? null,
			'trigger_code'                 => $trigger_data['trigger_code'] ?? '',
			'code'                         => $trigger_data['trigger_code'] ?? '',
			'integration'                  => $trigger_data['integration'] ?? '',
			'type'                         => $trigger_data['trigger_type'] ?? '',
			'sentence'                     => $trigger_data['sentence'] ?? '',
			'sentence_human_readable'      => $trigger_data['sentence_human_readable'] ?? '',
			'sentence_human_readable_html' => $trigger_data['sentence_human_readable_html'] ?? null,
			'hook'                         => $trigger_data['trigger_hook'] ?? array(),
			'tokens'                       => $trigger_data['trigger_tokens'] ?? array(),
			'configuration'                => $trigger_data['configuration'] ?? array(),
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
		if ( ! $trigger_post || AUTOMATOR_POST_TYPE_TRIGGER !== $trigger_post->post_type ) {
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
	 * Build sentence outputs using the presentation composer.
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
		return $this->sentence_composer->compose( $sentence_template, $configuration, $field_labels );
	}

	/**
	 * Remove client-provided sentence artifacts from trigger configuration.
	 *
	 * @param array $config Incoming trigger configuration.
	 *
	 * @return array
	 */
	private function strip_sentence_artifacts( array $config ): array {
		unset(
			$config['sentence'],
			$config['sentence_human_readable'],
			$config['sentence_human_readable_html']
		);

		return $config;
	}

	/**
	 * The trigger's post, when the trigger exists and belongs to the recipe the caller named.
	 *
	 * @since 8.0.0
	 *
	 * @param int $trigger_id         Trigger post ID.
	 * @param int $expected_recipe_id Recipe the caller named.
	 *
	 * @return \WP_Post|WP_Error The trigger post; `trigger_not_found` or `trigger_ownership_mismatch` (status 404) otherwise.
	 */
	private function find_recipe_trigger_post( int $trigger_id, int $expected_recipe_id ) {
		$trigger_post = $this->trigger_store->get_wp_post( $trigger_id );
		if ( ! $trigger_post instanceof \WP_Post || AUTOMATOR_POST_TYPE_TRIGGER !== $trigger_post->post_type ) {
			return new WP_Error(
				'trigger_not_found',
				sprintf(
					/* translators: %1$d Trigger ID, %2$d Recipe ID. */
					esc_html_x( 'Trigger %1$d not found in recipe %2$d.', 'Trigger CRUD error', 'uncanny-automator' ),
					$trigger_id,
					$expected_recipe_id
				),
				array( 'status' => 404 )
			);
		}

		$ownership = $this->validate_ownership( $trigger_id, $trigger_post, $expected_recipe_id );
		if ( is_wp_error( $ownership ) ) {
			return new WP_Error( $ownership->get_error_code(), $ownership->get_error_message(), array( 'status' => 404 ) );
		}

		return $trigger_post;
	}

	/**
	 * Validate that the trigger belongs to the expected recipe.
	 *
	 * @param int      $trigger_id         Trigger post ID.
	 * @param \WP_Post $trigger_post       Trigger post object.
	 * @param int|null $expected_recipe_id Expected parent recipe ID, or null to skip.
	 *
	 * @return \WP_Error|null WP_Error on mismatch, null when OK.
	 */
	private function validate_ownership( int $trigger_id, \WP_Post $trigger_post, ?int $expected_recipe_id ) {

		if ( null === $expected_recipe_id ) {
			return null;
		}

		if ( (int) $trigger_post->post_parent === $expected_recipe_id ) {
			return null;
		}

		return new WP_Error(
			'trigger_ownership_mismatch',
			sprintf(
				/* translators: %1$d Trigger ID, %2$d Recipe ID. */
				esc_html_x( 'Trigger %1$d does not belong to recipe %2$d.', 'Trigger CRUD error', 'uncanny-automator' ),
				$trigger_id,
				$expected_recipe_id
			)
		);
	}

	/**
	 * Validate that the component code has not changed.
	 *
	 * @param int         $trigger_id     Trigger post ID.
	 * @param string|null $requested_code Code the caller wants to set, or null to skip.
	 *
	 * @return \WP_Error|null WP_Error when the code differs, null when OK.
	 */
	private function validate_code_unchanged( int $trigger_id, ?string $requested_code ) {

		if ( null === $requested_code || '' === $requested_code ) {
			return null;
		}

		$stored_code = get_post_meta( $trigger_id, 'code', true );

		if ( ! $stored_code || $requested_code === $stored_code ) {
			return null;
		}

		return new WP_Error(
			'trigger_code_change_rejected',
			sprintf(
				/* translators: %1$s Current code, %2$s Requested code. */
				esc_html_x( 'Cannot change trigger_code from "%1$s" to "%2$s" on an existing trigger. Delete and recreate instead.', 'Trigger CRUD error', 'uncanny-automator' ),
				$stored_code,
				$requested_code
			)
		);
	}

	/**
	 * Validate that a recipe is writable (not trashed).
	 *
	 * @param int $recipe_id Recipe post ID.
	 *
	 * @return \WP_Error|null WP_Error if trashed, null when writable.
	 */
	private function validate_recipe_writable( int $recipe_id ) {
		$recipe = $this->recipe_store->get( $recipe_id );
		if ( $recipe && ! $recipe->get_recipe_status()->is_writable() ) {
			return new WP_Error(
				'recipe_trashed',
				sprintf(
					/* translators: %d Recipe ID. */
					esc_html_x( 'Recipe %d is trashed. Restore it to draft status before making changes.', 'Trigger CRUD error', 'uncanny-automator' ),
					$recipe_id
				)
			);
		}
		return null;
	}
}
