<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Settings\Wire;

use Uncanny_Automator\App\Recipe_Builder\Recipe\Settings\Enums\Recipe_Setting_Id;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Field_Output_Serializer;
use Uncanny_Automator\App\Recipe_Builder\Field\Services\Utilities\Field_Value_Normalizer;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Value_Objects\Recipe_Throttle;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Value_Objects\Recipe_Total_Times;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Value_Objects\Recipe_Times_Per_User;
// The recipe's user type; Action\Enums\User_Type is the action-level one.
use Uncanny_Automator\App\Recipe_Builder\Shared\Enums\User_Type;

/**
 * Project the schema-v3 tree's recipe-level fields into the wire-shape
 * `recipe.settings{}` map.
 *
 * Six entries keyed by `Recipe_Setting_Id` constants:
 *   - 3 editable (recipe_throttle / recipe_total_times / recipe_times_per_user) —
 *     empty `{}` (stdClass via Field_Output_Serializer) when unset / unlimited (-1).
 *   - 3 derived (recipe_user_type / recipe_created_on / recipe_completed_runs) —
 *     always populated from values the wire serializer already projected.
 *
 * Composed by Recipe_Wire_Serializer (extracted here so the enum and the
 * projection sit next to each other, making FE/BE drift impossible by
 * construction).
 *
 * Lazy-default DI per Rule 9c.
 */
final class Settings_Wire_Projection {

	/**
	 * Serializes FieldsOutput envelopes (empty arrays → stdClass) for the wire shape.
	 */
	private $fields;

	/**
	 * Lazy-default DI per Rule 9c — production gets a default Field_Output_Serializer;
	 * tests may inject a stub.
	 */
	public function __construct( ?Field_Output_Serializer $fields = null ) {
		$this->fields = $fields ?? new Field_Output_Serializer();
	}

	/**
	 * Build the recipe.settings{} map.
	 *
	 * @param array  $tree            Schema v3 canonical tree.
	 * @param string $created_on_date Already-formatted display date from
	 *                                miscellaneous.created_on_date.
	 * @param int    $total_runs      Already-computed total runs from
	 *                                stats.total_runs.
	 */
	public function project( array $tree, string $created_on_date, int $total_runs ): array {
		$total_times    = (int) ( $tree['total_times'] ?? Recipe_Total_Times::UNLIMITED );
		$times_per_user = (int) ( $tree['times_per_user'] ?? Recipe_Times_Per_User::UNLIMITED );
		$throttle       = $tree['throttle'] ?? array();
		// Unset recipe type stays unset — do not collapse null/missing to 'user'
		// (mirrors the tree builder's nullable recipe_type). FieldOutput `value`
		// is always a string on the wire, so the unset state is the empty string.
		$recipe_type = (string) ( $tree['recipe_type'] ?? '' );

		return array(
			Recipe_Setting_Id::RECIPE_THROTTLE       => $this->throttle_envelope(
				is_array( $throttle ) ? $throttle : array(),
				$recipe_type
			),
			Recipe_Setting_Id::RECIPE_TOTAL_TIMES    => $this->limit_envelope( $total_times, Recipe_Total_Times::class ),
			Recipe_Setting_Id::RECIPE_TIMES_PER_USER => $this->limit_envelope( $times_per_user, Recipe_Times_Per_User::class ),
			Recipe_Setting_Id::RECIPE_USER_TYPE      => array(
				'USER_TYPE' => array(
					'type'          => 'text',
					'value'         => $recipe_type,
					'miscellaneous' => array( 'label' => esc_html_x( 'Recipe type', 'Recipe user-type setting label', 'uncanny-automator' ) ),
				),
			),
			Recipe_Setting_Id::RECIPE_CREATED_ON     => array(
				'CREATED_ON' => array(
					'type'          => 'text',
					'value'         => $created_on_date,
					'miscellaneous' => array( 'label' => esc_html_x( 'Created on', 'Recipe created-on setting label', 'uncanny-automator' ) ),
				),
			),
			Recipe_Setting_Id::RECIPE_COMPLETED_RUNS => array(
				'COMPLETED_RUNS' => array(
					'type'          => 'int',
					'value'         => (string) $total_runs,
					'miscellaneous' => array( 'label' => esc_html_x( 'Completed runs', 'Recipe completed-runs setting label', 'uncanny-automator' ) ),
				),
			),
		);
	}

	/**
	 * Project a recipe completion-limit value to its FieldsOutput envelope.
	 *
	 * The "no limit set" rule is owned by the limit VO (single authoritative
	 * source — `$limit_vo::renders_as_no_limit()`), not re-implemented here, so
	 * the projection can't drift from the domain. No-limit → emit empty `{}`
	 * (stdClass via Field_Output_Serializer); a positive value produces a
	 * `TIMES` field entry typed `int`.
	 *
	 * @param int    $value    Projected limit value from the schema tree.
	 * @param string $limit_vo The limit VO class to consult (Recipe_Total_Times
	 *                         or Recipe_Times_Per_User).
	 * @return array|\stdClass
	 */
	private function limit_envelope( int $value, string $limit_vo ) {
		if ( $limit_vo::renders_as_no_limit( $value ) ) {
			return $this->fields->serialize( array() );
		}
		return array(
			'TIMES' => array(
				'type'          => 'int',
				'value'         => (string) $value,
				'miscellaneous' => array( 'label' => esc_html_x( 'Times', 'Recipe completion-limit setting label', 'uncanny-automator' ) ),
			),
		);
	}

	/**
	 * Project the domain throttle shape ({enabled,duration,unit,scope}) into the
	 * FieldsOutput envelope the FE renders and the settings/update endpoint
	 * consumes: ENABLED / NUMBER / UNIT (+ PER_RECIPE_OR_USER for user recipes).
	 *
	 * Disabled/unset → `{}` (stdClass), matching the limit settings — the FE
	 * reads an absent ENABLED as unchecked. ENABLED carries the wire string
	 * 'true'/'false' (FieldsOutput value is always a string); selects carry a
	 * `readable` (required by FieldOutputSchema's superRefine).
	 *
	 * @param array  $throttle    Domain shape from Recipe_Settings_Service::get_throttle().
	 * @param string $recipe_type 'user' | 'anonymous' — scope only applies to user recipes.
	 * @return array|\stdClass
	 */
	private function throttle_envelope( array $throttle, string $recipe_type ) {
		if ( ! ( $throttle['enabled'] ?? false ) ) {
			return $this->fields->serialize( array() );
		}

		$unit = (string) ( $throttle['unit'] ?? Recipe_Throttle::DEFAULT_UNIT );

		$envelope = array(
			'ENABLED' => array(
				'type'          => 'checkbox',
				'value'         => Field_Value_Normalizer::to_wire_string( $throttle['enabled'] ),
				'miscellaneous' => array( 'label' => esc_html_x( 'Enable throttling', 'Recipe throttle setting label', 'uncanny-automator' ) ),
			),
			'NUMBER'  => array(
				'type'          => 'int',
				'value'         => (string) ( $throttle['duration'] ?? 1 ),
				'miscellaneous' => array( 'label' => esc_html_x( 'Throttle duration', 'Recipe throttle setting label', 'uncanny-automator' ) ),
			),
			'UNIT'    => array(
				'type'          => 'select',
				'value'         => $unit,
				'readable'      => ucfirst( $unit ),
				'miscellaneous' => array( 'label' => esc_html_x( 'Duration unit', 'Recipe throttle setting label', 'uncanny-automator' ) ),
			),
		);

		if ( User_Type::USER === $recipe_type && ! empty( $throttle['scope'] ) ) {
			$scope                          = (string) $throttle['scope'];
			$envelope['PER_RECIPE_OR_USER'] = array(
				'type'          => 'select',
				'value'         => $scope,
				'readable'      => 'user' === $scope
					? esc_html_x( 'Per user', 'Recipe throttle scope option', 'uncanny-automator' )
					: esc_html_x( 'Per recipe', 'Recipe throttle scope option', 'uncanny-automator' ),
				'miscellaneous' => array( 'label' => esc_html_x( 'Throttle scope', 'Recipe throttle setting label', 'uncanny-automator' ) ),
			);
		}

		return $envelope;
	}
}
