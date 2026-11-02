<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Presentation\Admin;

use Uncanny_Automator\App\Infrastructure\Automator_Platform\Local_Site_Status;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration\Site_Address_Drift;
use Uncanny_Automator\App\Infrastructure\Connections\Adoption_Report;
use Uncanny_Automator\App\Infrastructure\Connections\Connection;
use Uncanny_Automator\App\Infrastructure\Connections\Connection_Adoption;
use Uncanny_Automator\App\Infrastructure\Connections\Site_Copy_Record;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Message;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Screen;

/**
 * The admin notice for a copy's app connections — staging-copies handoff R2
 * and R4.
 *
 *  - The copy waits for an answer — the plugin recognised it by itself (a
 *    non-production site whose address changed registers as a copy without
 *    asking), or it was copied before the answer was recorded: ask "staging
 *    copy, use the live site's connections" or "separate site, keep them
 *    switched off". Never "moved": a copy that registered by itself is not a
 *    move. Asked while the copy still registers too — the answer is kept, and
 *    acted on once the copy holds its own secret.
 *  - The copy uses the live site's connections and some are still off, or
 *    still being attached: how many are in use, which are off and why, and
 *    Retry.
 *  - Otherwise (not a copy, keeping its connections off, or everything
 *    attached): nothing. The App Platform status tab always describes the copy.
 *
 * A production site whose address question is open sees the site-address
 * notice instead, which asks all three answers; and the App Platform status
 * tab shows the same content, so the notice is not repeated there.
 *
 * No dismiss while a question is open: the copy's connections stay off until
 * it is answered. Shown by the notice center.
 *
 * @since 8.0.0
 */
final class Site_Copy_Connections_Notice extends Notice {

	/**
	 * @var App_Connections_Actions
	 */
	private $actions;

	/**
	 * @var Connection_Adoption
	 */
	private $adoption;

	/**
	 * @var Site_Address_Drift
	 */
	private $address;

	/**
	 * @var Site_Copy_Record
	 */
	private $record;

	/**
	 * @var Copy_Connections_View
	 */
	private $view;

	/**
	 * @param App_Connections_Actions    $actions  Knows whether this site is a copy.
	 * @param Connection_Adoption        $adoption Reports the adoption.
	 * @param Site_Address_Drift         $address  Says whether the address question is open.
	 * @param Site_Copy_Record|null      $record   The copy's answer.
	 * @param Copy_Connections_View|null $view     Shared wording and forms.
	 */
	public function __construct(
		App_Connections_Actions $actions,
		Connection_Adoption $adoption,
		Site_Address_Drift $address,
		?Site_Copy_Record $record = null,
		?Copy_Connections_View $view = null
	) {
		$this->actions  = $actions;
		$this->adoption = $adoption;
		$this->address  = $address;
		$this->record   = $record ?? new Site_Copy_Record();
		$this->view     = $view ?? new Copy_Connections_View();
	}

	/**
	 * @return string
	 */
	public function id(): string {

		return 'platform-site-copy';
	}

	/**
	 * @param Notice_Screen $screen The screen.
	 *
	 * @return Notice_Message|null
	 */
	public function message( Notice_Screen $screen ): ?Notice_Message {

		if ( ! $this->should_render() ) {
			return null;
		}

		$answer = $this->record->answer();
		$source = $this->record->source_site_name();

		if ( Site_Copy_Record::ANSWER_ADOPT === $answer ) {
			$report = $this->adoption->report();

			return $report->is_waiting() || array() !== $report->not_attached() ? $this->view->report_message( $report, $source ) : null;
		}

		if ( Site_Copy_Record::ANSWER_KEEP_OFF === $answer ) {
			return null;
		}

		return $this->question( $answer, $source );
	}

	/**
	 * The question; the report while the rest are attached; the report with
	 * every reason a connection stays switched off.
	 *
	 * @return Notice_Message[]
	 */
	public function previews(): array {

		$source = 'https://shop.example.com';
		$off    = array();
		foreach ( array( Connection_Adoption::REASON_NOT_OFFERED, Connection_Adoption::REASON_ACCOUNT_GONE, Connection_Adoption::REASON_UNKNOWN_ACCOUNT, Connection_Adoption::REASON_REFUSED, Connection_Adoption::REASON_NOT_ANSWERED ) as $index => $reason ) {
			$off[] = array(
				'connection' => $this->sample_connection( 'preview-' . $reason, 0 === $index % 2 ? 'dropbox' : 'google_sheet', 'team@example.com' ),
				'reason'     => $reason,
			);
		}
		$attached = array( $this->sample_connection( 'preview-slack', 'slack', 'Example workspace' ) );

		$question = $this->question( Site_Copy_Record::ANSWER_PENDING, $source );
		if ( ! $this->view->offers_adoption() ) {
			return array( $question );
		}

		return array(
			$question,
			$this->view->report_message( new Adoption_Report( true, true, $attached, array() ), $source ),
			$this->view->report_message( new Adoption_Report( true, false, $attached, $off ), $source ),
		);
	}

	/**
	 * @param string $answer The copy's answer so far.
	 * @param string $source The address it was copied from.
	 *
	 * @return Notice_Message
	 */
	private function question( string $answer, string $source ): Notice_Message {

		$message = Notice_Message::warning( esc_html_x( 'This site is a copy', 'App connections', 'uncanny-automator' ) )
			->with_state( 'question' )
			->with_paragraph( $this->view->copy_sentence( $answer, $source ) );

		foreach ( $this->view->answer_actions( $source ) as $action ) {
			$message = $message->with_action( $action );
		}

		return $message;
	}

	/**
	 * A connection to show in a preview: never stored.
	 *
	 * @param string $connection_id Its id.
	 * @param string $integration   Its app.
	 * @param string $label         Its account.
	 *
	 * @return Connection
	 */
	private function sample_connection( string $connection_id, string $integration, string $label ): Connection {

		return new Connection( 0, $connection_id, null, null, $integration, $label, Connection::PLATFORM_VERSION_V3, array(), Connection::STATUS_QUARANTINED, false, '', '', null );
	}

	/**
	 * @return bool
	 */
	private function should_render(): bool {

		// L247 — a local site never adopts another site's accounts.
		if ( ( new Local_Site_Status() )->is_local() ) {
			return false;
		}

		return current_user_can( automator_get_admin_capability() )
			&& ! App_Platform_Status_Tab::is_current_screen()
			&& ! $this->is_address_question_open()
			&& $this->actions->is_copy();
	}



	/**
	 * The production address question (the site-address notice) is open: it
	 * asks first, with all three answers.
	 *
	 * @return bool
	 */
	private function is_address_question_open(): bool {

		return 'url' === $this->address->drift() && $this->address->is_production();
	}
}
