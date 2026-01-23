<?php
/**
 * Action Instance Builder.
 *
 * Handles creation and updating of Action domain objects.
 * Extracts action instance management logic from Action_Instance_Service for better separation of concerns.
 *
 * @since 7.0.0
 * @package Uncanny_Automator\Api\Services\Action\Helpers
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Services\Action\Utilities;

use Uncanny_Automator\Api\Components\Action\Enums\Action_Status;
use Uncanny_Automator\Api\Components\Action\Action_Config;
use Uncanny_Automator\Api\Components\Action\Action;
use Uncanny_Automator\Api\Components\Recipe\Value_Objects\Recipe_Id;
use Uncanny_Automator\Api\Components\Shared\Sentence_Html\Field_Label_Resolver;
use Uncanny_Automator\Api\Services\Sentence_Html\Sentence_Output_Builder;
use WP_Error;

/**
 * Action Instance Builder Class.
 *
 * Responsible for creating new Action instances and updating existing ones
 * with new configuration data.
 */
class Action_Builder {

	/**
	 * Async config converter dependency.
	 *
	 * @var Async_Config_Converter
	 */
	private $async_converter;

	/**
	 * Field label resolver dependency.
	 *
	 * @var Field_Label_Resolver
	 */
	private $label_resolver;

	/**
	 * Sentence output builder dependency.
	 *
	 * @var Sentence_Output_Builder
	 */
	private $sentence_builder;

	/**
	 * Constructor.
	 *
	 * @param Async_Config_Converter      $async_converter   Async config converter.
	 * @param Field_Label_Resolver|null   $label_resolver    Optional label resolver (for testing).
	 * @param Sentence_Output_Builder|null $sentence_builder Optional sentence builder (for testing).
	 */
	public function __construct(
		Async_Config_Converter $async_converter,
		?Field_Label_Resolver $label_resolver = null,
		?Sentence_Output_Builder $sentence_builder = null
	) {
		$this->async_converter  = $async_converter;
		$this->label_resolver   = $label_resolver ?? new Field_Label_Resolver();
		$this->sentence_builder = $sentence_builder ?? new Sentence_Output_Builder();
	}

	/**
	 * Create action instance from parameters and definition.
	 *
	 * @param int      $recipe_id Recipe ID.
	 * @param string   $action_code Action code.
	 * @param array    $config User configuration.
	 * @param array    $action_definition Action definition from registry.
	 * @param array    $async_config Async configuration.
	 * @param int|null $parent_id Parent ID (defaults to recipe_id if not provided).
	 * @return Action|\WP_Error Action instance or error.
	 */
	public function create( int $recipe_id, string $action_code, array $config, array $action_definition, array $async_config = array(), ?int $parent_id = null ) {

		try {

			$integration_code    = $action_definition['integration'] ?? '';
			$meta_code           = $action_definition['meta_code'] ?? '';
			$action_type         = $action_definition['user_type'] ?? 'user';
			$sentence_template   = $action_definition['sentence'] ?? '';
			$sentence_hr_default = $action_definition['sentence_human_readable'] ?? $sentence_template;

			// Default parent_id to recipe_id if not provided.
			$parent_id = $parent_id ?? $recipe_id;

			// Build meta with registry data and user configuration.
			// Store the original sentence template for regenerating on updates.
			$meta = array(
				'recipe_id' => $recipe_id,
				'sentence'  => $sentence_template, // Original template with field codes
			);

			// Get configuration fields and extract labels using shared resolver.
			$configuration_fields = $this->label_resolver->get_configuration_fields( $action_code, 'actions' );
			$field_labels         = $this->label_resolver->extract_field_labels( $configuration_fields );

			// Ensure HTML format for TinyMCE fields (converts plain text newlines to <p> tags).
			$config = $this->label_resolver->ensure_html_format( $config, $configuration_fields );

			// JSON-encode array values (repeater fields) - Automator expects JSON strings, not PHP arrays.
			$config = $this->encode_array_fields( $config );

			// Enrich config with readable labels and merge into meta.
			$enriched_config = $this->label_resolver->enrich_with_readable_labels( $config, $configuration_fields, $action_code, 'actions' );
			$meta            = array_merge( $meta, $enriched_config );

			// Generate sentence outputs with filled-in field values.
			// - brackets: "Enroll the user in {{Course: Introduction to Python}}"
			// - html: HTML version with styling spans
			if ( ! empty( $sentence_template ) ) {
				$sentence_result = $this->build_sentence_outputs( $sentence_template, $meta, $field_labels );

				$meta['sentence_human_readable']      = $sentence_result['brackets'];
				$meta['sentence_human_readable_html'] = $sentence_result['html'];
			} else {
				$meta['sentence_human_readable'] = $sentence_hr_default;
			}

			// Add async configuration to meta if provided
			if ( ! empty( $async_config ) && is_array( $async_config ) ) {
				// Convert async configuration to flat structure for meta storage
				$async_meta = $this->async_converter->convert_to_meta( $async_config );
				$meta       = array_merge( $meta, $async_meta );
			}

			// Create action instance
			$action_config = ( new Action_Config() )
				->id( null ) // New action, no ID yet
				->integration_code( $integration_code )
				->recipe_id( $recipe_id )
				->parent_id( new Recipe_Id( $parent_id ) )
				->code( $action_code )
				->meta_code( $meta_code )
				->user_type( $action_type )
				->status( Action_Status::DRAFT )
				->meta( $meta );

			return new Action( $action_config );

		} catch ( \Exception $e ) {
			return new WP_Error(
				'action_creation_error',
				sprintf(
					/* translators: %s Error message. */
					esc_html_x( 'Failed to create action instance: %s', 'Action builder error', 'uncanny-automator' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Update action instance with new configuration.
	 *
	 * @param Action      $existing_action Current action instance.
	 * @param array       $new_config New configuration to merge.
	 * @param array       $async_config Async configuration.
	 * @param string|null $status Optional status to set ('draft' or 'publish').
	 * @return Action Updated action instance.
	 */
	public function update( Action $existing_action, array $new_config, array $async_config = array(), ?string $status = null ): Action {

		// Get current meta and merge with new config
		$current_meta = $existing_action->get_action_meta()->to_array();

		// Merge new config with existing meta (preserving registry fields)
		$updated_meta = array_merge( $current_meta, $new_config );

		// Handle async configuration update
		if ( ! empty( $async_config ) ) {
			// Convert async configuration to meta format and merge
			$async_meta   = $this->async_converter->convert_to_meta( $async_config );
			$updated_meta = array_merge( $updated_meta, $async_meta );
		}

		if ( isset( $async_config ) && empty( $async_config ) ) {
			// Empty async config means remove async settings
			$async_keys_to_remove = array(
				'async_mode',
				'async_delay_number',
				'async_delay_unit',
				'async_schedule_date',
				'async_schedule_time',
				'async_custom',
				'async_sentence',
			);
			// Remove async keys from updated meta.
			foreach ( $async_keys_to_remove as $key ) {
				unset( $updated_meta[ $key ] );
			}
		}

		// Get configuration fields and extract labels using shared resolver.
		$action_code          = $existing_action->get_action_code()->get_value();
		$configuration_fields = $this->label_resolver->get_configuration_fields( $action_code, 'actions' );
		$field_labels         = $this->label_resolver->extract_field_labels( $configuration_fields );

		// Ensure HTML format for TinyMCE fields (converts plain text newlines to <p> tags).
		$new_config = $this->label_resolver->ensure_html_format( $new_config, $configuration_fields );

		// JSON-encode array values (repeater fields) - Automator expects JSON strings, not PHP arrays.
		$new_config = $this->encode_array_fields( $new_config );

		// Enrich new config values with readable labels.
		$enriched_config = $this->label_resolver->enrich_with_readable_labels( $new_config, $configuration_fields, $action_code, 'actions' );
		$updated_meta    = array_merge( $updated_meta, $enriched_config );

		// Use the original sentence template (with field codes like {{a course:LDCOURSE}})
		// to regenerate the filled sentence_human_readable and HTML.
		$sentence_template = $updated_meta['sentence'] ?? '';
		if ( ! empty( $sentence_template ) ) {
			$sentence_result                              = $this->build_sentence_outputs( $sentence_template, $updated_meta, $field_labels );
			$updated_meta['sentence_human_readable']      = $sentence_result['brackets'];
			$updated_meta['sentence_human_readable_html'] = $sentence_result['html'];
		}

		// Create updated action config
		$config = ( new Action_Config() )
			->id( $existing_action->get_action_id()->get_value() )
			->recipe_id( $existing_action->get_action_recipe_id()->get_value() )
			->integration_code( $existing_action->get_action_integration_code()->get_value() )
			->code( $existing_action->get_action_code()->get_value() )
			->meta_code( $existing_action->get_action_meta_code()->get_value() )
			->user_type( $existing_action->get_action_type()->get_value() )
			->meta( $updated_meta );

		// Preserve parent_id if it exists
		if ( null !== $existing_action->get_parent_id() ) {
			$config->parent_id( $existing_action->get_parent_id()->get_parent() );
		}

		$status_obj      = $existing_action->get_status();
		$resolved_status = $status ?? ( $status_obj ? $status_obj->get_value() : null );

		if ( null !== $resolved_status ) {
			$config->status( $resolved_status );
		}

		return new Action( $config );
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
