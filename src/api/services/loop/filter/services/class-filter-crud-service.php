<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Services\Loop\Filter\Services;

use Uncanny_Automator\Api\Database\Interfaces\Loop\Filter_Store;
use Uncanny_Automator\Api\Database\Interfaces\Loop\Loop_Store;
use Uncanny_Automator\Api\Database\Stores\Loop\WP_Filter_Store;
use Uncanny_Automator\Api\Database\Stores\Loop\WP_Loop_Store;
use Uncanny_Automator\Api\Components\Loop\Filter\Filter;
use Uncanny_Automator\Api\Components\Loop\Filter\Config as Filter_Config;
use Uncanny_Automator\Api\Components\Loop\Filter\Services\Field_Normalizer;
use Uncanny_Automator\Api\Components\Loop\Filter\Value_Objects\User_Type_Value;
use Uncanny_Automator\Api\Presentation\Loop\Filters\Loop_Filter_Sentence_Composer;
use WP_Error;

/**
 * Filter CRUD Service.
 *
 * Handles CRUD operations for loop filters.
 * Hydrates entities with registry data before persistence.
 * Loop filter sentence HTML composition is owned by presentation layer
 * (`Loop_Filter_Sentence_Composer`).
 *
 * @since 7.0.0
 */
class Filter_CRUD_Service {

	private static ?Filter_CRUD_Service $instance = null;
	private Filter_Store $filter_store;
	private Loop_Store $loop_store;
	private Filter_Registry_Service $registry_service;
	private Field_Normalizer $field_normalizer;
	private Loop_Filter_Sentence_Composer $sentence_composer;

	/**
	 * Constructor.
	 *
	 * @param Filter_Store|null                 $filter_store      Optional filter store instance.
	 * @param Loop_Store|null                   $loop_store        Optional loop store instance.
	 * @param Filter_Registry_Service|null      $registry_service  Optional registry service instance.
	 * @param Field_Normalizer|null             $field_normalizer  Optional field normalizer instance.
	 * @param Loop_Filter_Sentence_Composer|null $sentence_composer Optional sentence composer instance.
	 */
	private function __construct(
		?Filter_Store $filter_store = null,
		?Loop_Store $loop_store = null,
		?Filter_Registry_Service $registry_service = null,
		?Field_Normalizer $field_normalizer = null,
		?Loop_Filter_Sentence_Composer $sentence_composer = null
	) {
		global $wpdb;

		$this->filter_store     = $filter_store ?? new WP_Filter_Store( $wpdb );
		$this->loop_store       = $loop_store ?? new WP_Loop_Store( $wpdb, $this->filter_store );
		$this->registry_service = $registry_service ?? Filter_Registry_Service::instance();
		$this->field_normalizer = $field_normalizer ?? new Field_Normalizer();
		$this->sentence_composer = $sentence_composer ?? new Loop_Filter_Sentence_Composer();
	}
	/**
	 * Instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		return self::$instance ??= new self();
	}
	/**
	 * Create with dependencies.
	 *
	 * @param Filter_Store                  $filter_store      The filter store.
	 * @param Loop_Store                    $loop_store        The loop store.
	 * @param Filter_Registry_Service       $registry_service  The registry service.
	 * @param Field_Normalizer              $field_normalizer  The field normalizer.
	 * @param Loop_Filter_Sentence_Composer $sentence_composer The sentence composer.
	 * @return self
	 */
	public static function create_with_dependencies(
		?Filter_Store $filter_store = null,
		?Loop_Store $loop_store = null,
		?Filter_Registry_Service $registry_service = null,
		?Field_Normalizer $field_normalizer = null,
		?Loop_Filter_Sentence_Composer $sentence_composer = null
	): self {
		return new self( $filter_store, $loop_store, $registry_service, $field_normalizer, $sentence_composer );
	}
	/**
	 * Validate loop exists.
	 *
	 * @param int $loop_id The ID.
	 * @return mixed
	 */
	public function validate_loop_exists( int $loop_id ) {
		return $this->loop_store->get( $loop_id );
	}

	/**
	 * Add filter to loop.
	 *
	 * Hydrates the entity with registry data before persisting.
	 */
	public function add_to_loop(
		int $loop_id,
		string $filter_code,
		string $integration_code,
		array $fields = array(),
		array $backup = array()
	) {
		if ( ! $this->validate_loop_exists( $loop_id ) ) {
			return new WP_Error(
				'loop_not_found',
				sprintf(
					/* translators: %d Loop ID. */
					esc_html__( 'Loop not found with ID: %d', 'uncanny-automator' ),
					$loop_id
				)
			);
		}

		// Validate filter code and integration code are not empty.
		if ( empty( $filter_code ) ) {
			return new WP_Error( 'invalid_filter_code', esc_html__( 'Filter code cannot be empty.', 'uncanny-automator' ) );
		}

		if ( empty( $integration_code ) ) {
			return new WP_Error( 'invalid_integration_code', esc_html__( 'Integration code cannot be empty.', 'uncanny-automator' ) );
		}

		$definition = $this->registry_service->validate_filter_code_and_get_definition( $filter_code );
		if ( is_wp_error( $definition ) ) {
			return $definition;
		}

		try {
			// Hydrate entity with registry data.
			$config = $this->build_hydrated_config( $filter_code, $integration_code, $fields, $backup, $definition );
			$filter = new Filter( $config );

			$existing_filters = $this->filter_store->get_loop_filters( $loop_id );
			$saved_filter     = $this->filter_store->save( $loop_id, $filter, count( $existing_filters ) );

			return array(
				'success'   => true,
				'message'   => esc_html__( 'Filter successfully added to loop.', 'uncanny-automator' ),
				'filter_id' => $saved_filter->get_id()->get_value(),
				'filter'    => $saved_filter->to_array(),
			);
		} catch ( \Exception $e ) {
			return new WP_Error(
				'filter_save_failed',
				sprintf(
					/* translators: %s Error message. */
					esc_html__( 'Failed to save filter: %s', 'uncanny-automator' ),
					$e->getMessage()
				)
			);
		}
	}
	/**
	 * Get filter.
	 *
	 * @param mixed $filter_id The ID.
	 * @return mixed
	 */
	public function get_filter( $filter_id ) {
		$filter = $this->filter_store->get( (int) $filter_id );

		if ( ! $filter ) {
			return new WP_Error(
				'filter_not_found',
				sprintf(
					/* translators: %d Filter ID. */
					esc_html__( 'Filter not found with ID: %d', 'uncanny-automator' ),
					$filter_id
				)
			);
		}

		return array(
			'success'  => true,
			'filter'   => $filter->to_array(),
		);
	}
	/**
	 * Get loop filters.
	 *
	 * @param int $loop_id The ID.
	 * @return mixed
	 */
	public function get_loop_filters( int $loop_id ) {
		if ( ! $this->loop_store->get( $loop_id ) ) {
			return new WP_Error(
				'loop_not_found',
				sprintf(
					/* translators: %d Loop ID. */
					esc_html__( 'Loop not found with ID: %d', 'uncanny-automator' ),
					$loop_id
				)
			);
		}

		try {
			$filters = $this->filter_store->get_loop_filters( $loop_id );

			return array(
				'success'      => true,
				'loop_id'      => $loop_id,
				'filter_count' => count( $filters ),
				'filters'      => array_map( fn( $f ) => $f->to_array(), $filters ),
			);
		} catch ( \Exception $e ) {
			return new WP_Error(
				'loop_filters_error',
				sprintf(
					/* translators: %s Error message. */
					esc_html__( 'Failed to retrieve filters: %s', 'uncanny-automator' ),
					$e->getMessage()
				)
			);
		}
	}
	/**
	 * Update filter.
	 *
	 * @param mixed $filter_id The ID.
	 * @param array $fields The fields.
	 * @param array $backup The backup.
	 * @return mixed
	 */
	public function update_filter( $filter_id, array $fields = array(), array $backup = array() ) {
		$existing = $this->filter_store->get( (int) $filter_id );

		if ( ! $existing ) {
			return new WP_Error(
				'filter_not_found',
				sprintf(
					/* translators: %d Filter ID. */
					esc_html__( 'Filter not found with ID: %d', 'uncanny-automator' ),
					$filter_id
				)
			);
		}

		try {
			$existing_data = $existing->to_array();
			$definition    = $this->registry_service->validate_filter_code_and_get_definition( $existing_data['code'] );

			$config = $this->build_hydrated_config(
				$existing_data['code'],
				$existing_data['integration_code'],
				! empty( $fields ) ? $fields : $existing_data['fields'],
				! empty( $backup ) ? $backup : $existing_data['backup'],
				is_wp_error( $definition ) ? array() : $definition
			);
			$config->id( $existing_data['id'] );

			$post = get_post( (int) $filter_id );
			if ( ! $post ) {
				return new WP_Error( 'filter_post_not_found', esc_html__( 'Filter post not found.', 'uncanny-automator' ) );
			}

			$saved = $this->filter_store->save( $post->post_parent, new Filter( $config ), $post->menu_order );

			return array(
				'success'  => true,
				'filter'   => $saved->to_array(),
			);
		} catch ( \Exception $e ) {
			return new WP_Error(
				'filter_update_failed',
				sprintf(
					/* translators: %s Error message. */
					esc_html__( 'Failed to update filter: %s', 'uncanny-automator' ),
					$e->getMessage()
				)
			);
		}
	}
	/**
	 * Delete filter.
	 *
	 * @param mixed $filter_id The ID.
	 * @param bool $confirmed The confirmed.
	 * @return mixed
	 */
	public function delete_filter( $filter_id, bool $confirmed = false ) {
		if ( ! $confirmed ) {
			return new WP_Error( 'confirmation_required', esc_html__( 'Deletion must be confirmed.', 'uncanny-automator' ) );
		}

		$filter = $this->filter_store->get( (int) $filter_id );
		if ( ! $filter ) {
			return new WP_Error(
				'filter_not_found',
				sprintf(
					/* translators: %d Filter ID. */
					esc_html__( 'Filter not found with ID: %d', 'uncanny-automator' ),
					$filter_id
				)
			);
		}

		try {
			$data = $filter->to_array();
			$this->filter_store->delete( $filter );

			return array(
				'success'           => true,
				'deleted_filter_id' => $data['id'],
				'filter_code'       => $data['code'],
			);
		} catch ( \Exception $e ) {
			return new WP_Error(
				'delete_filter_failed',
				sprintf(
					/* translators: %s Error message. */
					esc_html__( 'Failed to delete filter: %s', 'uncanny-automator' ),
					$e->getMessage()
				)
			);
		}
	}
	/**
	 * Delete loop filters.
	 *
	 * @param int $loop_id The ID.
	 * @param bool $confirmed The confirmed.
	 * @return mixed
	 */
	public function delete_loop_filters( int $loop_id, bool $confirmed = false ) {
		if ( ! $confirmed ) {
			return new WP_Error( 'confirmation_required', esc_html__( 'Deletion must be confirmed.', 'uncanny-automator' ) );
		}

		if ( ! $this->validate_loop_exists( $loop_id ) ) {
			return new WP_Error(
				'loop_not_found',
				sprintf(
					/* translators: %d Loop ID. */
					esc_html__( 'Loop not found with ID: %d', 'uncanny-automator' ),
					$loop_id
				)
			);
		}

		try {
			$count = count( $this->filter_store->get_loop_filters( $loop_id ) );
			$this->filter_store->delete_loop_filters( $loop_id );

			return array(
				'success'        => true,
				'loop_id'        => $loop_id,
				'deleted_count'  => $count,
			);
		} catch ( \Exception $e ) {
			return new WP_Error(
				'delete_filters_failed',
				sprintf(
					/* translators: %s Error message. */
					esc_html__( 'Failed to delete filters: %s', 'uncanny-automator' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Build a fully hydrated config from registry data.
	 */
	private function build_hydrated_config(
		string $filter_code,
		string $integration_code,
		array $fields,
		array $backup,
		array $definition
	): Filter_Config {
		$meta_structure    = $definition['meta_structure'] ?? array();
		$normalized_fields = $this->normalize_fields_to_nested( $fields, $meta_structure );
		$hydrated_backup   = $this->build_backup( $backup, $definition, $integration_code, $normalized_fields );

		return ( new Filter_Config() )
			->code( $filter_code )
			->integration_code( $integration_code )
			->integration_name( $this->get_integration_name( $integration_code ) )
			->type( ! empty( $definition['is_pro'] ) ? 'pro' : 'lite' )
			->user_type( $this->determine_user_type( $definition['iteration_types'] ?? array() ) )
			->fields( $normalized_fields )
			->backup( $hydrated_backup )
			->version( defined( 'AUTOMATOR_PLUGIN_VERSION' ) ? AUTOMATOR_PLUGIN_VERSION : 'unknown' );
	}

	/**
	 * Normalize fields to nested format expected by UI.
	 *
	 * Delegates to Field_Normalizer domain service.
	 *
	 * @param array $fields         Flat fields from API.
	 * @param array $meta_structure Field definitions from registry.
	 * @return array Normalized nested fields.
	 */
	private function normalize_fields_to_nested( array $fields, array $meta_structure ): array {
		return $this->field_normalizer->normalize_to_nested( $fields, $meta_structure );
	}

	/**
	 * Build backup with sentence from registry.
	 */
	private function build_backup( array $existing, array $definition, string $integration_code, array $fields ): array {
		$existing_without_sentence = $this->strip_sentence_artifacts( $existing );
		$sentence = $definition['sentence'] ?? '';
		if ( empty( $sentence ) ) {
			return $existing_without_sentence;
		}

		$sentence_html = $this->sentence_composer->compose( $sentence, $fields );

		return array_merge(
			$existing_without_sentence,
			array(
				'integration_name' => $this->get_integration_name( $integration_code ),
				'sentence'         => $sentence,
				'sentence_html'    => htmlspecialchars( $sentence_html, ENT_QUOTES, 'UTF-8' ),
			)
		);
	}

	/**
	 * Remove sentence artifacts from incoming backup payload.
	 *
	 * @param array $backup Existing backup data.
	 *
	 * @return array
	 */
	private function strip_sentence_artifacts( array $backup ): array {
		unset( $backup['sentence'], $backup['sentence_html'] );

		return $backup;
	}
	/**
	 * Get integration name.
	 *
	 * @param string $code The code.
	 * @return string
	 */
	private function get_integration_name( string $code ): string {
		if ( function_exists( 'Automator' ) ) {
			$name = Automator()->get_integration_name_by_code( $code );
			if ( ! empty( $name ) ) {
				return $name;
			}
		}
		return $code;
	}
	/**
	 * Determine user type from iteration types.
	 *
	 * Delegates to User_Type_Value domain logic.
	 *
	 * @param array $iteration_types The iteration types (e.g., ['users'], ['posts'], ['token']).
	 * @return string User type constant ('user' or 'anonymous').
	 */
	private function determine_user_type( array $iteration_types ): string {
		return User_Type_Value::from_iteration_types( $iteration_types )->get_value();
	}
}
