<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Logs\Wire_Serializer;

use Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Utils\Formatters_Utils;
use Uncanny_Automator\Automator_Status;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Key;
use Uncanny_Automator\App\Recipe_Logs\Lists\Services\Recipe_Run_Delete_Policy;

/**
 * Top-level recipe-log wire serializer. Assembles the strict ModelRecipeLog:
 * identity, title, the recipe's edit URL, whether the current user may delete
 * the run, optional triggering user, root execution, and the flow tree.
 *
 * The payload never carries a nonce: it reaches the Agent's logs tool and the
 * run log's JSON download, so the frontend deletes a run with its own REST
 * request (`DELETE logs/recipe-runs`) instead of a link.
 *
 * Reads tree keys as literals; emits wire keys from Wire_Key.
 */
final class Recipe_Log_Wire_Serializer {

	/**
	 * Status derivation for the recipe root.
	 *
	 * @var Node_Status_Resolver
	 */
	private $resolver;

	/**
	 * Flow (entry + content) serializer.
	 *
	 * @var Log_Flow_Serializer
	 */
	private $flow;

	/**
	 * Who may delete a recipe's runs.
	 *
	 * @var Recipe_Run_Delete_Policy
	 */
	private $delete_policy;

	/**
	 * Wires up the wire serializer's collaborators, defaulting each to its production implementation.
	 *
	 * @since 8.1.0 Takes the run delete policy.
	 *
	 * @param Node_Status_Resolver|null     $resolver      Status derivation.
	 * @param Log_Flow_Serializer|null      $flow          Flow serializer.
	 * @param Recipe_Run_Delete_Policy|null $delete_policy Who may delete a recipe's runs.
	 */
	public function __construct( ?Node_Status_Resolver $resolver = null, ?Log_Flow_Serializer $flow = null, ?Recipe_Run_Delete_Policy $delete_policy = null ) {
		$this->resolver      = $resolver ?? new Node_Status_Resolver();
		$this->flow          = $flow ?? new Log_Flow_Serializer();
		$this->delete_policy = $delete_policy ?? new Recipe_Run_Delete_Policy();
	}

	/**
	 * Assemble the strict ModelRecipeLog wire payload for one run.
	 *
	 * @param int                 $recipe_id  Recipe post id.
	 * @param int                 $run_number 1-indexed run number.
	 * @param int                 $log_id     Recipe-log row id.
	 * @param array<string,mixed> $tree       Schema tree.
	 * @param Run_Data_Bundle     $bundle     Run data.
	 * @return array<string,mixed> The wire ModelRecipeLog payload.
	 */
	public function serialize( int $recipe_id, int $run_number, int $log_id, array $tree, Run_Data_Bundle $bundle ): array {
		$row       = $bundle->recipe_log_row();
		$edit_link = get_edit_post_link( $recipe_id, '&' );

		$payload = array(
			'log_id'            => $log_id,
			Wire_Key::RECIPE_ID => $recipe_id,
			'recipe_title'      => (string) ( $tree['title'] ?? '' ),
			'run_number'        => $run_number,
			'can_delete'        => $this->delete_policy->can_delete( $recipe_id ),
			'recipe_edit_url'   => false !== $edit_link ? (string) $edit_link : '',
			'execution'         => $this->root_execution( $bundle ),
			Wire_Key::FLOW      => $this->flow->serialize( $tree, $bundle ),
		);

		$user = $this->triggered_by_user( (int) ( $row['user_id'] ?? 0 ) );
		if ( null !== $user ) {
			$payload['triggered_by_user'] = $user;
		}

		return $payload;
	}

	/**
	 * Build the recipe root's execution sub-object.
	 *
	 * @since 8.0.0 Carries `errors` when the run recorded errors outside its steps.
	 *
	 * @param Run_Data_Bundle $bundle Run data.
	 * @return array<string,mixed> The root execution sub-object (status, date_started, date_ended when finished, errors when any).
	 */
	private function root_execution( Run_Data_Bundle $bundle ): array {
		$row      = $bundle->recipe_log_row();
		$finished = Automator_Status::finished( (int) ( $row['completed'] ?? Automator_Status::NOT_COMPLETED ) );

		$started = $bundle->run_started_at();

		$exec = array(
			'status'       => $this->resolver->recipe_root( $bundle )->to_array(),
			'date_started' => '' === $started ? '' : (string) Formatters_Utils::date_time_format_relative( $started ),
		);

		// Latest child row, or date_time when there are none. Only an unfinished run
		// omits date_ended — that absence is what the FE renders as "in progress".
		if ( $finished ) {
			$latest = $this->latest_child_date( $bundle );
			if ( '' === $latest ) {
				$latest = (string) ( $row['date_time'] ?? '' );
			}
			if ( '' !== $latest ) {
				$exec['date_ended'] = (string) Formatters_Utils::date_time_format_relative( $latest );
			}
		}

		$errors = $this->run_errors( $bundle );
		if ( ! empty( $errors ) ) {
			$exec['errors'] = $errors;
		}

		return $exec;
	}

	/**
	 * The errors the run recorded against itself rather than one of its steps, oldest first,
	 * as `{ code, message }` with the message in plain text.
	 *
	 * @since 8.0.0
	 *
	 * @param Run_Data_Bundle $bundle Run data.
	 * @return array<int,array{code:string,message:string}> The run's own errors; empty when it has none.
	 */
	private function run_errors( Run_Data_Bundle $bundle ): array {
		return array_map(
			static function ( array $error ): array {
				return array(
					'code'    => $error['code'],
					'message' => Log_Message_Text::from_stored( $error['message'] ),
				);
			},
			$bundle->run_errors()
		);
	}

	/**
	 * Latest raw MySQL datetime across the run's trigger and action log rows,
	 * or '' when none carry a date. String max is chronological for the
	 * zero-padded 'Y-m-d H:i:s' format.
	 *
	 * @param Run_Data_Bundle $bundle Run data.
	 * @return string
	 */
	private function latest_child_date( Run_Data_Bundle $bundle ): string {
		$dates = $this->child_dates( $bundle );

		return empty( $dates ) ? '' : (string) max( $dates );
	}

	/**
	 * Every non-empty trigger/action row datetime for the run, unordered.
	 *
	 * @param Run_Data_Bundle $bundle Run data.
	 * @return string[]
	 */
	private function child_dates( Run_Data_Bundle $bundle ): array {
		$dates = array();
		foreach ( $bundle->all_action_ids() as $action_id ) {
			$action_row = $bundle->action_row( $action_id );
			$date       = is_array( $action_row ) ? (string) ( $action_row['date_time'] ?? '' ) : '';
			if ( '' !== $date ) {
				$dates[] = $date;
			}
		}
		foreach ( $bundle->all_trigger_ids() as $trigger_id ) {
			foreach ( $bundle->trigger_rows_for( $trigger_id ) as $trigger_row ) {
				$date = (string) ( $trigger_row['date_time'] ?? '' );
				if ( '' !== $date ) {
					$dates[] = $date;
				}
			}
		}

		return $dates;
	}

	/**
	 * Resolve the wire triggered_by_user object for a user id.
	 *
	 * @param int $user_id Triggering user id.
	 * @return array<string,mixed>|null Null for anonymous / unresolvable.
	 */
	private function triggered_by_user( int $user_id ): ?array {
		if ( $user_id <= 0 ) {
			return null;
		}
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return null;
		}
		$avatar_url = get_avatar_url( $user_id, array( 'size' => 96 ) );
		return array(
			'id'           => $user_id,
			'display_name' => Log_Message_Text::from_stored( (string) $user->display_name ),
			'avatar_url'   => false !== $avatar_url ? (string) $avatar_url : '',
		);
	}
}
