<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Services\Condition\Utilities;

use Uncanny_Automator\Api\Components\Condition\Value_Objects\Condition_Group;
use Uncanny_Automator\Api\Components\Condition\Value_Objects\Condition_Group_Mode;
use Uncanny_Automator\Api\Components\Condition\Value_Objects\Condition_Id;
use Uncanny_Automator\Api\Components\Condition\Value_Objects\Condition_Fields;
use Uncanny_Automator\Api\Components\Condition\Value_Objects\Individual_Condition;
use Uncanny_Automator\Api\Components\Recipe\Value_Objects\Recipe_Id;
use WP_Error;

class Condition_Factory {

	private Condition_Validator $validation;

	/**
	 * Constructor.
	 *
	 * @param Condition_Validator $validation Condition validator.
	 */
	public function __construct( Condition_Validator $validation ) {
		$this->validation = $validation;
	}
	/**
	 * Create group.
	 *
	 * @param Recipe_Id $recipe_id The ID.
	 * @param array $action_ids The ID.
	 * @param string $mode The mode.
	 * @param array $conditions The condition.
	 * @return Condition_Group
	 */
	public function create_group( Recipe_Id $recipe_id, array $action_ids, string $mode, array $conditions ) {
		try {
			$mode_vo = new Condition_Group_Mode( $mode );

			$individual_conditions = array();
			foreach ( $conditions as $condition_config ) {
				$condition = $this->create_condition( $condition_config );
				if ( is_wp_error( $condition ) ) {
					return $condition;
				}
				$individual_conditions[] = $condition;
			}

			return Condition_Group::create(
				$action_ids,
				$mode_vo,
				$recipe_id,
				$individual_conditions
			);

		} catch ( \Throwable $exception ) {
			return new WP_Error(
				'group_creation_failed',
				sprintf(
					/* translators: %s Error message. */
					esc_html_x( 'Failed to create condition group: %s', 'Condition factory error', 'uncanny-automator' ),
					$exception->getMessage()
				)
			);
		}
	}
	/**
	 * Create condition.
	 *
	 * @param array $config The configuration.
	 * @return Individual_Condition
	 */
	public function create_condition( array $config ) {
		try {
			$integration_code = $config['integration_code'] ?? '';
			$condition_code   = $config['condition_code'] ?? '';
			$fields           = $this->strip_presentation_artifacts( $config['fields'] ?? array() );

			$validation_result = $this->validation->ensure_condition_exists( $integration_code, $condition_code );
			if ( is_wp_error( $validation_result ) ) {
				return $validation_result;
			}

			$condition_fields = new Condition_Fields( $fields );
			$backup_info      = $this->validation->create_backup_info( $integration_code, $condition_code, $fields );
			if ( is_wp_error( $backup_info ) ) {
				return $backup_info;
			}

			return new Individual_Condition(
				Condition_Id::generate(),
				$integration_code,
				$condition_code,
				$condition_fields,
				$backup_info
			);

		} catch ( \Throwable $exception ) {
			return new WP_Error(
				'condition_creation_failed',
				sprintf(
					/* translators: %s Error message. */
					esc_html_x( 'Failed to create condition: %s', 'Condition factory error', 'uncanny-automator' ),
					$exception->getMessage()
				)
			);
		}
	}
	/**
	 * Refresh condition with id.
	 *
	 * @param Individual_Condition $condition The condition.
	 * @param array $fields The fields.
	 * @return Individual_Condition
	 */
	public function refresh_condition_with_id( Individual_Condition $condition, array $fields ) {
		try {
			$integration_code = $condition->get_integration();
			$condition_code   = $condition->get_condition_code();
			$fields           = $this->strip_presentation_artifacts( $fields );

			$condition_fields = new Condition_Fields( $fields );
			$backup_info      = $this->validation->create_backup_info( $integration_code, $condition_code, $fields );
			if ( is_wp_error( $backup_info ) ) {
				return $backup_info;
			}

			return new Individual_Condition(
				$condition->get_condition_id(),
				$integration_code,
				$condition_code,
				$condition_fields,
				$backup_info
			);

		} catch ( \Throwable $exception ) {
			return new WP_Error(
				'condition_refresh_failed',
				sprintf(
					/* translators: %s Error message. */
					esc_html_x( 'Failed to refresh condition: %s', 'Condition factory error', 'uncanny-automator' ),
					$exception->getMessage()
				)
			);
		}
	}

	/**
	 * Remove presentation artifacts from incoming condition fields.
	 *
	 * Condition backup metadata is server-owned and always regenerated.
	 *
	 * @param array $fields Incoming fields.
	 *
	 * @return array
	 */
	private function strip_presentation_artifacts( array $fields ): array {
		unset(
			$fields['nameDynamic'],
			$fields['titleHTML'],
			$fields['integrationName'],
			$fields['sentence'],
			$fields['sentence_html'],
			$fields['backup'],
			$fields['backup_info']
		);

		return $fields;
	}
}
