<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Presentation\Admin;

use Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration\Register_Answer;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Failure_Message;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Site_Registration;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration\Challenge_Progress;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration\Registration_Wire;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration_Status;
use Uncanny_Automator\App\Infrastructure\Connections\Connection_Sync;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Message;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Screen;

/**
 * "This site's registration could not be confirmed" notice (registration and
 * move handoff R1) — with the one repair control, Re-check registration.
 *
 * Reads {@see Platform_Site_Registration::status()}, the one answer to where
 * the registration stands, and shows only when an attempt failed: the
 * platform refused it (with its description — the L286 host refusal among
 * them), could not reach this site's challenge endpoint (the URL is named),
 * or the last attempt failed in passing and is being retried. Never after a
 * first 202 — confirming takes seconds — and never for the site-address
 * question, which the drift notice asks.
 *
 * It also shows — the "header" control of Saad's L210 note — while a
 * connection sync is owed and retrying on its own has not done it within its
 * quick rungs ({@see Connection_Sync::is_stuck()}): the admin can re-check
 * now instead of disconnecting and reconnecting an app whose connection on
 * the platform was never the problem.
 *
 * Shown by the notice center on every wp-admin page; no dismiss, it shows
 * until the registration is fixed. The outcome of a re-check is its own
 * notice, {@see Registration_Recheck_Outcome_Notice}.
 *
 * @since 8.0.0
 */
final class Site_Registration_Status_Notice extends Notice {

	/**
	 * @var Platform_Site_Registration
	 */
	private $registration;

	/**
	 * @var Connection_Sync
	 */
	private $sync;

	/**
	 * @var Registration_Recheck_Control
	 */
	private $recheck;

	/**
	 * @param Platform_Site_Registration   $registration Where the registration stands.
	 * @param Connection_Sync              $sync         Says whether connections are stuck out of sync.
	 * @param Registration_Recheck_Control $recheck      The repair control.
	 */
	public function __construct( Platform_Site_Registration $registration, Connection_Sync $sync, Registration_Recheck_Control $recheck ) {
		$this->registration = $registration;
		$this->sync         = $sync;
		$this->recheck      = $recheck;
	}

	/**
	 * @return string
	 */
	public function id(): string {

		return 'platform-registration';
	}

	/**
	 * @param Notice_Screen $screen The screen.
	 *
	 * @return Notice_Message|null
	 */
	public function message( Notice_Screen $screen ): ?Notice_Message {

		$status = $this->registration->status();

		if ( $status->needs_attention() ) {
			return $this->attention( $status );
		}

		if ( $status->is_registered() && $this->sync->is_stuck() ) {
			return $this->out_of_sync();
		}

		return null;
	}

	/**
	 * Each way a registration can need attention (refused, at this site's
	 * address too; unreachable, with each thing the last check got; being
	 * retried), then connections out of sync.
	 *
	 * @return Notice_Message[]
	 */
	public function previews(): array {

		$url  = ( new Registration_Wire() )->challenge_url();
		$path = '/wp-json/automator/platform/v1/register-challenge';
		$soon = time() + 45 * MINUTE_IN_SECONDS;

		$statuses = array(
			Registration_Status::of(
				Registration_Status::REFUSED,
				array(
					'description' => 'This license has reached its site limit.',
					'until'       => $soon,
				)
			),
			Registration_Status::of(
				Registration_Status::REFUSED,
				array(
					'status' => 422,
					'type'   => 'VALIDATION_ERROR',
				)
			),
			Registration_Status::of(
				Registration_Status::REFUSED,
				array(
					'reason'        => Registration_Status::REASON_HOST,
					'type'          => 'SITE_IS_LOCAL',
					'challenge_url' => 'https://shop.internal' . $path,
				)
			),
			Registration_Status::of(
				Registration_Status::REFUSED,
				array(
					'reason'        => Registration_Status::REASON_HOST,
					'type'          => 'VALIDATION_ERROR',
					'challenge_url' => 'http://shop.example.com' . $path,
				)
			),
			Registration_Status::of(
				Registration_Status::REFUSED,
				array(
					'reason'        => Registration_Status::REASON_HOST,
					'type'          => 'VALIDATION_ERROR',
					'challenge_url' => 'https://203.0.113.7' . $path,
				)
			),
			Registration_Status::of(
				Registration_Status::REFUSED,
				array(
					'reason'        => Registration_Status::REASON_HOST,
					'type'          => 'VALIDATION_ERROR',
					'challenge_url' => 'https://shop.example.com' . $path,
				)
			),
			Registration_Status::of(
				Registration_Status::REFUSED,
				array(
					'reason'          => Registration_Status::REASON_HOST,
					'type'            => 'VALIDATION_ERROR',
					'platform_reason' => Register_Answer::REASON_HOST_NOT_PINNABLE,
					'challenge_url'   => 'https://münchen-shop.de' . $path,
				)
			),
			Registration_Status::of( Registration_Status::UNREACHABLE, array( 'challenge_url' => $url ) ),
			Registration_Status::of(
				Registration_Status::UNREACHABLE,
				array(
					'challenge_url' => $url,
					'reason'        => Challenge_Progress::OUTCOME_HTTP_STATUS,
					'status'        => 403,
				)
			),
		);
		foreach ( array( 'timeout', 'tls', 'connection_failed', 'host_does_not_resolve', 'unknown_attempt', 'invalid_proof' ) as $check ) {
			$statuses[] = Registration_Status::of(
				Registration_Status::UNREACHABLE,
				array(
					'challenge_url' => $url,
					'reason'        => $check,
				)
			);
		}
		$statuses[] = Registration_Status::of(
			Registration_Status::RETRYING,
			array(
				'reason'        => Registration_Status::REASON_HOST_UNRESOLVED,
				'challenge_url' => 'https://shop.example.com' . $path,
				'until'         => $soon,
			)
		);
		$statuses[] = Registration_Status::of(
			Registration_Status::RETRYING,
			array(
				'description' => 'the Automator App Platform is busy',
				'until'       => $soon,
			)
		);
		$statuses[] = Registration_Status::of( Registration_Status::RETRYING );

		$previews   = array_map( array( $this, 'attention' ), $statuses );
		$previews[] = $this->out_of_sync();

		return $previews;
	}

	/**
	 * @param Registration_Status $status A registration that needs attention.
	 *
	 * @return Notice_Message
	 */
	private function attention( Registration_Status $status ): Notice_Message {

		return Notice_Message::error( esc_html_x( "This site's registration needs attention", 'Admin notice', 'uncanny-automator' ) )
			->with_state( $status->state() )
			->with_priority( 40 )
			->with_paragraph( Platform_Failure_Message::for_registration( $status )->text() )
			->with_action( $this->recheck->action() );
	}

	/**
	 * @return Notice_Message
	 */
	private function out_of_sync(): Notice_Message {

		return Notice_Message::warning( esc_html_x( 'Some app connections are out of sync', 'Admin notice', 'uncanny-automator' ) )
			->with_state( 'connections_out_of_sync' )
			->with_paragraph( esc_html__( "Some of this site's app connections disagree with the Automator App Platform, and repairing them on its own hasn't worked yet. Re-check the registration to repair them now. There's no need to reconnect any app.", 'uncanny-automator' ) )
			->with_action( $this->recheck->action() );
	}
}
