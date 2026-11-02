<?php

namespace Uncanny_Automator\Integrations\Redirect;

use Uncanny_Automator\App\Recipe_Runner\Exceptions\Action_Error_Exception;
use Uncanny_Automator\App\Recipe_Runner\Value_Objects\Background_Job_Result;
use Uncanny_Automator\Item_Manifest;
use Uncanny_Automator\Recipe\Action;
use Uncanny_Automator\Recipe\Log_Properties;

/**
 * Class Redirect_To_Url
 *
 * @since 8.0.0
 *
 * @package Uncanny_Automator
 * @property Redirect_Helpers $item_helpers
 */
class Redirect_To_Url extends Action {

	use Item_Manifest;
	use Log_Properties;

	/**
	 * Identity and sentences.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'REDIRECT' );
		$this->set_action_code( 'REDIRECT_TO_URL' );
		$this->set_action_meta( 'REDIRECT_URL' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		// translators: %1$s is the action meta code.
		$this->set_sentence( sprintf( esc_html_x( 'Redirect to {{a URL:%1$s}}', 'Redirect', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'Redirect to a URL', 'Redirect', 'uncanny-automator' ) );
		// Explicit catalog metadata, so the item is described even if complete.json never lists this built-in integration.
		$this->set_readable_description( esc_html_x( 'Sends the visitor to a URL when the recipe reaches this action. The rest of the recipe keeps running in the background.', 'Redirect', 'uncanny-automator' ) );
		$this->set_mcp_description( esc_html_x( 'Redirect action: sends the visitor\'s browser to the configured URL (an absolute HTTP or HTTPS URL, or a path on this site) as soon as the recipe reaches it; any actions after it finish in the background. It works only in a browser request, so it completes with a notice when the recipe runs on cron, inside a loop or from the command line. The URL field supports tokens.', 'Redirect', 'uncanny-automator' ) );
	}

	/**
	 * The one field.
	 *
	 * @return array
	 */
	public function options() {
		return array( $this->item_helpers->redirect_url_field() );
	}

	/**
	 * Validate, record the redirect for delivery when the leg ends and, when something still runs after this
	 * action and the run can finish in the background, record a one-second site job for this row and ask the
	 * walker to detach. Last at every level (every migrated recipe), or without a snapshot, the rest runs now
	 * and the redirect follows the walk: no job, no cron.
	 *
	 * @param int   $user_id     The user.
	 * @param array $action_data The action row and meta.
	 * @param int   $recipe_id   The recipe.
	 * @param array $args        Trigger args.
	 * @param array $parsed      Parsed field values.
	 *
	 * @return bool|null
	 * @throws Action_Error_Exception When the URL is empty or not http(s).
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		$delivery = Redirect_Delivery::instance();
		$raw      = isset( $parsed['REDIRECT_URL'] ) ? (string) $parsed['REDIRECT_URL'] : '';
		$url      = $delivery->validate_url( $raw );

		if ( '' === $url ) {
			throw Action_Error_Exception::validation_failed(
				sprintf(
					// translators: %s is the redirect URL after tokens were parsed.
					esc_html_x( 'The redirect URL "%s" is not a valid HTTP or HTTPS URL.', 'Redirect', 'uncanny-automator' ),
					esc_html( $raw )
				)
			);
		}

		if ( ! $delivery->can_deliver_in_this_request( (array) $action_data ) ) {
			$this->add_log_error( esc_html_x( 'The recipe ran in the background, so there was no browser to redirect.', 'Redirect', 'uncanny-automator' ) );
			$this->set_complete_with_notice( true );
			return null;
		}

		$recipe_log_id = (int) ( $action_data['recipe_log_id'] ?? 0 );
		if ( ! $delivery->set_pending( $recipe_log_id, $url ) ) {
			$this->add_log_error( esc_html_x( 'Another Redirect action in this run already redirected the visitor.', 'Redirect', 'uncanny-automator' ) );
			$this->set_complete_with_notice( true );
			return null;
		}

		$this->set_log_properties(
			array(
				'type'  => 'url',
				'label' => esc_html_x( 'Redirecting to', 'Redirect', 'uncanny-automator' ),
				'value' => $url,
			)
		);

		// Last at every level (every migrated recipe): finish on the spot and keep the run free of any cron
		// dependency. Without a snapshot the background completion could not rebuild the run.
		if ( ! automator_run_has_work_after( (int) $recipe_id, (int) ( $action_data['ID'] ?? 0 ) ) || ! automator_run_has_snapshot( $recipe_log_id ) ) {
			return true;
		}

		if ( automator_defer_background_job_to_site( $this->job_data( $user_id, (array) $action_data, (int) $recipe_id, (array) $parsed ), 1, 1 ) ) {
			$this->action_data['completed_awaiting'] = true;
			automator_request_run_detach( $recipe_log_id );
		}

		return true;
	}

	/**
	 * The job record for this row: what App_Action::defer_to_site() sends, minus the id, key and origin the seam mints.
	 *
	 * @param int   $user_id     The user.
	 * @param array $action_data The action row.
	 * @param int   $recipe_id   The recipe.
	 * @param array $parsed      Parsed field values, stored with the job.
	 *
	 * @return array<string, mixed>
	 */
	private function job_data( $user_id, array $action_data, int $recipe_id, array $parsed ): array {
		return array(
			'recipe_log_id'    => (int) ( $action_data['recipe_log_id'] ?? 0 ),
			'recipe_id'        => $recipe_id,
			'action_id'        => (int) ( $action_data['ID'] ?? 0 ),
			'action_log_id'    => (int) ( $action_data['action_log_id'] ?? 0 ),
			'user_id'          => (int) $user_id,
			'integration_code' => 'REDIRECT',
			'parsed'           => $parsed,
			'remembered'       => array(),
		);
	}

	/**
	 * The one retry, the moment the job is due. The redirect was delivered when the leg ended, so the job is
	 * done; the completion that follows settles this row and releases the steps recorded behind it.
	 *
	 * @param array $action_data The action row the retry runs on.
	 * @param array $parsed      Parsed field values recorded with the job.
	 * @param array $remembered  Values remembered by earlier attempts (none).
	 *
	 * @return array{result: Background_Job_Result, remembered: array}
	 */
	public function retry_site_job( array $action_data, array $parsed, array $remembered ): array {
		return array(
			'result'     => Background_Job_Result::from_site( Background_Job_Result::STATUS_COMPLETED, array(), array() ),
			'remembered' => array(),
		);
	}

	/**
	 * Settle this row COMPLETED from the job's outcome. The runner then releases the nodes waiting on it.
	 *
	 * @param int   $user_id     The user the run executes as.
	 * @param array $action_data The action row, with recipe_log_id, action_log_id and background_job_id.
	 * @param int   $recipe_id   The recipe.
	 * @param array $args        The run's trigger args from the snapshot.
	 * @param array $parsed      Parsed field values recorded with the job.
	 * @param array $remembered  Values remembered at dispatch (none).
	 * @param array $result      The job's result envelope (empty).
	 *
	 * @return void
	 */
	public function complete_job( $user_id, array $action_data, $recipe_id, array $args, array $parsed, array $remembered, array $result ) {
		$this->user_id      = $user_id;
		$this->action_data  = $action_data;
		$this->recipe_id    = $recipe_id;
		$this->args         = $args;
		$this->maybe_parsed = $parsed;
		$this->complete_from_result( true );
	}
}
