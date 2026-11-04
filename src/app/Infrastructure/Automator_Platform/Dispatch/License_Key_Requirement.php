<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform\Dispatch;

use Uncanny_Automator\App\Infrastructure\Api_Log\Value_Objects\Api_Log_Entry;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Url_Matcher;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Request;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Api_Log_Store;

use function Uncanny_Automator\App\Infrastructure\automator_api_log_store;

/**
 * A request to the platform goes out only with a license key to send. One with
 * no key is refused where it would be sent
 * ({@see Platform_Dispatch_Guard::refuse_without_a_license_key()}), and an
 * action's request is still logged: the log row is what Resend replays, so the
 * action can be sent once the site has a license.
 *
 * Only a request to the platform needs the key, the same URLs the license
 * headers are sent to ({@see \Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Headers_Injector}):
 * a request to another service (the usage reports) is sent with or without one.
 *
 * @since 8.0.1
 */
final class License_Key_Requirement {

	/**
	 * @var Platform_Dispatch_Guard
	 */
	private $guard;

	/**
	 * Where an action's refused request is logged; null when no log store is available.
	 *
	 * @var Api_Log_Store|null
	 */
	private $log;

	/**
	 * Which URLs are the platform.
	 *
	 * @var Platform_Url_Matcher
	 */
	private $urls;

	/**
	 * @param Platform_Dispatch_Guard|null $guard Which license decides; the site's when none is given.
	 * @param Api_Log_Store|null           $log   Where an action's refused request is logged; the site's API log when none is given.
	 * @param Platform_Url_Matcher|null    $urls  Which URLs are the platform; the configured platform when none is given.
	 */
	public function __construct( ?Platform_Dispatch_Guard $guard = null, ?Api_Log_Store $log = null, ?Platform_Url_Matcher $urls = null ) {

		$this->guard = $guard ?? new Platform_Dispatch_Guard();
		$this->log   = $log ?? automator_api_log_store();
		$this->urls  = $urls ?? Platform_Url_Matcher::from_constants();
	}

	/**
	 * Refuse a request to the platform when the site has no license key to
	 * send, after logging it when it is an action's.
	 *
	 * @param Request $request The request about to be sent.
	 * @param string  $url     Where it would be sent.
	 *
	 * @return void
	 * @throws Refused_Before_Sending_Exception When the site has no license key to send.
	 */
	public function enforce( Request $request, string $url ): void {

		if ( ! $this->urls->is_platform( $url ) ) {
			return;
		}

		try {
			$this->guard->refuse_without_a_license_key();
		} catch ( Refused_Before_Sending_Exception $refused ) {
			$this->log_refused( $request, $refused );
			throw $refused;
		}
	}

	/**
	 * Log an action's request that was refused before it was sent: the
	 * refusal's status and sentence, and no time on the wire.
	 *
	 * @param Request                          $request The request that was not sent.
	 * @param Refused_Before_Sending_Exception $refused Why not.
	 *
	 * @return void
	 */
	private function log_refused( Request $request, Refused_Before_Sending_Exception $refused ): void {

		$action_data = $request->action_data();
		if ( null === $action_data || null === $this->log ) {
			return;
		}

		$this->log->persist(
			new Api_Log_Entry(
				$action_data['type'] ?? 'action',
				(int) ( $action_data['recipe_log_id'] ?? 0 ),
				(int) ( $action_data['action_log_id'] ?? $action_data['trigger_log_id'] ?? 0 ),
				$request->endpoint(),
				$request->to_legacy_params(),
				array(
					'statusCode' => (int) $refused->getCode(),
					'error'      => array( 'description' => $refused->getMessage() ),
				),
				(int) $refused->getCode(),
				null,
				null,
				0
			)
		);
	}
}
