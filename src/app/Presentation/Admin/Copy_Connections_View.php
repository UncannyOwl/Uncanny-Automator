<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Presentation\Admin;

use Uncanny_Automator\App\Bridge\Automator_Integration_Registry_Bridge;
use Uncanny_Automator\App\Bridge\Integration_Registry_Bridge;
use Uncanny_Automator\App\Infrastructure\Connections\Adoption_Report;
use Uncanny_Automator\App\Infrastructure\Connections\Connection;
use Uncanny_Automator\App\Infrastructure\Connections\Connection_Adoption;
use Uncanny_Automator\App\Infrastructure\Connections\Connection_Sharing_Feature;
use Uncanny_Automator\App\Infrastructure\Connections\Site_Copy_Record;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Action;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Message;

/**
 * The words and the small forms every screen about a copy's app connections
 * shares — the copy notice, the site-address notice, the App Platform
 * status tab and the hint on an app's settings page — so a copy is
 * described the same way wherever the admin meets it (staging-copies handoff
 * §6).
 *
 * A row switched off on a copy is described as switched off, never as broken
 * or missing (R8). Every string this class returns is escaped HTML, ready to
 * echo; every form posts to {@see App_Connections_Actions}.
 *
 * @since 8.0.0
 */
final class Copy_Connections_View {

	/**
	 * @var Integration_Registry_Bridge
	 */
	private $integrations;

	/**
	 * @var Connection_Sharing_Feature
	 */
	private $sharing;

	/**
	 * @param Integration_Registry_Bridge|null $integrations Names integrations.
	 * @param Connection_Sharing_Feature|null  $sharing      Says whether a copy may use the live site's connections.
	 */
	public function __construct( ?Integration_Registry_Bridge $integrations = null, ?Connection_Sharing_Feature $sharing = null ) {

		$this->integrations = $integrations ?? new Automator_Integration_Registry_Bridge();
		$this->sharing      = $sharing ?? new Connection_Sharing_Feature();
	}

	/**
	 * Whether a copy is offered "use the live site's app connections here".
	 * It needs the live site's sharing, so it is offered only while sharing is.
	 *
	 * @return bool
	 */
	public function offers_adoption(): bool {

		return $this->sharing->is_enabled();
	}

	/**
	 * The site a copy was made from, for a sentence; "the live site" when the
	 * copy was recognised before its source was recorded.
	 *
	 * @param string $source_site_name The recorded source address.
	 *
	 * @return string Escaped.
	 */
	public function source( string $source_site_name ): string {

		return '' !== $source_site_name
			? esc_html( $source_site_name )
			: esc_html_x( 'the live site', 'App connections', 'uncanny-automator' );
	}

	/**
	 * "This site is a copy of %s and this app connection is switched off here."
	 *
	 * @param string $source_site_name The recorded source address.
	 *
	 * @return string Escaped.
	 */
	public function switched_off_sentence( string $source_site_name ): string {

		return sprintf(
			/* translators: %s: the address of the site this site was copied from */
			esc_html_x( 'This site is a copy of %s and this app connection is switched off here.', 'App connections', 'uncanny-automator' ),
			'<code>' . $this->source( $source_site_name ) . '</code>'
		);
	}

	/**
	 * "This site is a copy of %s." and what its copied connections do here,
	 * by the answer.
	 *
	 * @param string $answer           One of Site_Copy_Record's ANSWER_* constants, or ''.
	 * @param string $source_site_name The recorded source address.
	 *
	 * @return string Escaped.
	 */
	public function copy_sentence( string $answer, string $source_site_name ): string {

		$source = $this->source( $source_site_name );

		if ( Site_Copy_Record::ANSWER_KEEP_OFF === $answer ) {
			/* translators: %s: the address of the site this site was copied from */
			return sprintf( esc_html_x( 'This site is a copy of %s and its copied app connections are switched off here. Connect any apps you want to use on this site.', 'App connections', 'uncanny-automator' ), $source );
		}

		if ( Site_Copy_Record::ANSWER_ADOPT === $answer ) {
			/* translators: %s: the address of the site this site was copied from */
			return sprintf( esc_html_x( 'This site is a copy of %s and uses its app connections.', 'App connections', 'uncanny-automator' ), $source );
		}

		/* translators: %s: the address of the site this site was copied from */
		return sprintf( esc_html_x( 'This site is a copy of %s. Its copied app connections are switched off here until you choose what it does with them:', 'App connections', 'uncanny-automator' ), $source );
	}

	/**
	 * Why one switched-off row was not attached, and what to do about it.
	 *
	 * @param string     $reason           One of Connection_Adoption's REASON_* constants.
	 * @param Connection $row              The switched-off row.
	 * @param string     $source_site_name The recorded source address.
	 *
	 * @return string Escaped.
	 */
	private function reason_message( string $reason, Connection $row, string $source_site_name ): string {

		$app     = $this->app_name( $row );
		$account = $this->account_label( $row );

		switch ( $reason ) {
			case Connection_Adoption::REASON_NOT_OFFERED:
				// The platform's 403 does not say why: not shared, or the
				// account is gone. Say only that it is not on offer here.
				/* translators: 1: app name, 2: account, 3: the address of the site this site was copied from */
				return sprintf( esc_html_x( "%1\$s (%2\$s) is not available from %3\$s. Turn on \"Share this site's app connections\" there and press Retry, or connect it on this site.", 'App connections', 'uncanny-automator' ), $app, $account, $this->source( $source_site_name ) );
			case Connection_Adoption::REASON_ACCOUNT_GONE:
				/* translators: 1: app name, 2: account */
				return sprintf( esc_html_x( '%1$s (%2$s): this account was removed. Connect it again here.', 'App connections', 'uncanny-automator' ), $app, $account );
			case Connection_Adoption::REASON_UNKNOWN_ACCOUNT:
				/* translators: 1: app name, 2: account */
				return sprintf( esc_html_x( '%1$s (%2$s): this connection does not say which account it uses, so it cannot be attached. Connect it again here.', 'App connections', 'uncanny-automator' ), $app, $account );
			case Connection_Adoption::REASON_REFUSED:
				/* translators: 1: app name, 2: account */
				return sprintf( esc_html_x( '%1$s (%2$s): the Automator App Platform did not attach this account. Press Retry; if it keeps happening, connect it again here.', 'App connections', 'uncanny-automator' ), $app, $account );
			default:
				/* translators: 1: app name, 2: account */
				return sprintf( esc_html_x( '%1$s (%2$s): waiting for the Automator App Platform. We keep trying; you can also press Retry.', 'App connections', 'uncanny-automator' ), $app, $account );
		}
	}

	/**
	 * The words of a copy's answer (R2: answers 2 and 3), shared with the
	 * site-address notice, which asks them beside "This site moved here".
	 *
	 * @param string $answer Site_Copy_Record::ANSWER_ADOPT or ANSWER_KEEP_OFF.
	 *
	 * @return string Escaped.
	 */
	public function answer_label( string $answer ): string {

		return Site_Copy_Record::ANSWER_ADOPT === $answer
			? esc_html_x( "This is a staging copy: use the live site's app connections here.", 'App connections', 'uncanny-automator' )
			: esc_html_x( 'This is a separate site: keep the copied connections switched off.', 'App connections', 'uncanny-automator' );
	}

	/**
	 * The copy's answers as a notice's choices, each beside what it does.
	 *
	 * @since 8.0.0
	 *
	 * @param string $source_site_name The recorded source address.
	 * @param bool   $offer_keep_off   Whether to offer "keep them switched off".
	 *
	 * @return Notice_Action[]
	 */
	public function answer_actions( string $source_site_name, bool $offer_keep_off = true ): array {

		$answers = $offer_keep_off ? array( Site_Copy_Record::ANSWER_ADOPT, Site_Copy_Record::ANSWER_KEEP_OFF ) : array( Site_Copy_Record::ANSWER_ADOPT );
		if ( ! $this->offers_adoption() ) {
			$answers = array_values( array_diff( $answers, array( Site_Copy_Record::ANSWER_ADOPT ) ) );
		}

		return array_map(
			function ( string $answer ) use ( $source_site_name ): Notice_Action {
				$action = Notice_Action::post( $this->answer_label( $answer ), App_Connections_Actions::ACTION_ANSWER, App_Connections_Actions::NONCE_ACTION, array( 'answer' => $answer ) )
					->with_description( $this->answer_description( $answer, $source_site_name ) );

				return Site_Copy_Record::ANSWER_KEEP_OFF === $answer ? $action->primary() : $action;
			},
			$answers
		);
	}

	/**
	 * How the adoption went, as a notice: how many connections are in use,
	 * which are off and why, each with Remove, then Retry and the way to the
	 * App Platform status tab.
	 *
	 * @since 8.0.0
	 *
	 * @param Adoption_Report $report           The adoption's report.
	 * @param string          $source_site_name The recorded source address.
	 *
	 * @return Notice_Message
	 */
	public function report_message( Adoption_Report $report, string $source_site_name ): Notice_Message {

		$attached = count( $report->attached() );
		$summary  = sprintf(
			/* translators: 1: number of app connections, 2: the address of the site this site was copied from */
			esc_html( _nx( '%1$d app connection of %2$s is in use on this copy.', '%1$d app connections of %2$s are in use on this copy.', $attached, 'App connections', 'uncanny-automator' ) ),
			(int) $attached,
			$this->source( $source_site_name )
		);
		if ( $report->is_waiting() ) {
			$summary .= ' ' . esc_html_x( "We're still attaching the rest. Reload this page to check.", 'App connections', 'uncanny-automator' );
		}

		$message = Notice_Message::info( esc_html_x( 'App connections on this copy', 'App connections', 'uncanny-automator' ) )
			->with_state( $report->is_waiting() ? 'attaching' : 'switched_off' )
			->with_paragraph( $summary );

		if ( array() !== $report->not_attached() ) {
			$message = $message->with_paragraph( esc_html_x( 'These are switched off here:', 'App connections', 'uncanny-automator' ) );
			foreach ( $report->not_attached() as $item ) {
				$message = $message->with_item( $this->reason_message( $item['reason'], $item['connection'], $source_site_name ), $this->remove_action( $item['connection'] ) );
			}
			$message = $message->with_action( Notice_Action::post( esc_html_x( 'Retry', 'App connections', 'uncanny-automator' ), App_Connections_Actions::ACTION_RETRY, App_Connections_Actions::NONCE_ACTION )->primary() );
		}

		return $message->with_action( Notice_Action::link( esc_html_x( 'App Platform status', 'Admin notice', 'uncanny-automator' ), App_Platform_Status_Tab::url() ) );
	}

	/**
	 * A switched-off row's Remove, as a notice action.
	 *
	 * @param Connection $row The row.
	 *
	 * @return Notice_Action
	 */
	private function remove_action( Connection $row ): Notice_Action {

		return Notice_Action::post( esc_html_x( 'Remove from this site', 'App connections', 'uncanny-automator' ), App_Connections_Actions::ACTION_REMOVE, App_Connections_Actions::NONCE_ACTION, array( 'connection_id' => $row->connection_id() ) );
	}

	/**
	 * What an answer does, in the admin's terms.
	 *
	 * @param string $answer           Site_Copy_Record::ANSWER_ADOPT or ANSWER_KEEP_OFF.
	 * @param string $source_site_name The recorded source address.
	 *
	 * @return string Escaped.
	 */
	public function answer_description( string $answer, string $source_site_name ): string {

		$source = $this->source( $source_site_name );

		if ( Site_Copy_Record::ANSWER_ADOPT === $answer ) {
			/* translators: %s: the address of the site this site was copied from */
			return sprintf( esc_html_x( 'Recipes on this copy will run with the same accounts as %s, including app triggers. Anything they send or upload is real.', 'App connections', 'uncanny-automator' ), $source );
		}

		/* translators: %s: the address of the site this site was copied from */
		return sprintf( esc_html_x( 'Connect any apps you want to use here. %s is not affected.', 'App connections', 'uncanny-automator' ), $source );
	}

	/**
	 * The two answers a copy has (R2: answers 2 and 3). The staging-copy
	 * answer is never the default-looking one: it makes recipes act on real
	 * accounts.
	 *
	 * @param string $source_site_name The recorded source address.
	 * @param bool   $offer_keep_off   Whether to offer answer 3 as well.
	 *
	 * @return void
	 */
	public function render_answer_forms( string $source_site_name, bool $offer_keep_off = true ): void {

		if ( $this->offers_adoption() ) {
			$this->render_answer_form( Site_Copy_Record::ANSWER_ADOPT, $source_site_name, 'secondary', 'automator-copy-answer-adopt' );
		}

		if ( $offer_keep_off ) {
			$this->render_answer_form( Site_Copy_Record::ANSWER_KEEP_OFF, $source_site_name, 'primary', 'automator-copy-answer-keep-off' );
		}
	}

	/**
	 * Where the adoption stands (R4): how many were attached, which were not
	 * and why, and Retry.
	 *
	 * @param Adoption_Report $report           The adoption's state.
	 * @param string          $source_site_name The recorded source address.
	 *
	 * @return void
	 */
	public function render_report( Adoption_Report $report, string $source_site_name ): void {

		$attached = count( $report->attached() );
		?>
		<div class="uap-settings-panel-content-paragraph automator-copy-report-summary">
			<?php
			echo sprintf(
				/* translators: 1: number of app connections, 2: the address of the site this site was copied from */
				esc_html( _nx( '%1$d app connection of %2$s is in use on this copy.', '%1$d app connections of %2$s are in use on this copy.', $attached, 'App connections', 'uncanny-automator' ) ),
				(int) $attached,
				$this->source( $source_site_name ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in source().
			);
			?>
			<?php if ( $report->is_waiting() ) : ?>
				<?php echo esc_html_x( "We're still attaching the rest. Reload this page to check.", 'App connections', 'uncanny-automator' ); ?>
			<?php endif; ?>
		</div>
		<?php if ( array() !== $report->not_attached() ) : ?>
		<div class="uap-settings-panel-content-paragraph uap-spacing-top"><?php echo esc_html_x( 'These are switched off here:', 'App connections', 'uncanny-automator' ); ?></div>
		<ul class="automator-copy-report-not-attached" style="list-style:disc;margin-left:20px">
			<?php foreach ( $report->not_attached() as $item ) : ?>
			<li data-reason="<?php echo esc_attr( $item['reason'] ); ?>">
				<?php echo $this->reason_message( $item['reason'], $item['connection'], $source_site_name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in reason_message(). ?>
				<?php $this->render_remove_form( $item['connection'] ); ?>
			</li>
			<?php endforeach; ?>
		</ul>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="automator-copy-retry uap-spacing-top--small">
			<input type="hidden" name="action" value="<?php echo esc_attr( App_Connections_Actions::ACTION_RETRY ); ?>" />
			<?php wp_nonce_field( App_Connections_Actions::NONCE_ACTION ); ?>
			<uas-button type="submit" size="sm" color="primary"><?php echo esc_html_x( 'Retry', 'App connections', 'uncanny-automator' ); ?></uas-button>
		</form>
		<?php endif; ?>
		<?php
	}

	/**
	 * Switched-off rows, each removable — a local delete that leaves the
	 * source site untouched (R7).
	 *
	 * @param array<int, Connection> $rows The switched-off rows.
	 *
	 * @return void
	 */
	public function render_switched_off_rows( array $rows ): void {

		if ( array() === $rows ) {
			return;
		}
		?>
		<ul class="automator-copy-switched-off" style="list-style:disc;margin-left:20px">
			<?php foreach ( $rows as $row ) : ?>
			<li>
				<?php echo $this->app_name( $row ) . ' (' . $this->account_label( $row ) . ')'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in app_name() and account_label(). ?>
				<?php $this->render_remove_form( $row ); ?>
			</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	/**
	 * @param Connection $row A row.
	 *
	 * @return string The app's name. Escaped.
	 */
	private function app_name( Connection $row ): string {

		$code = strtoupper( $row->integration() );
		$name = $this->integrations->get_integration_name_by_code( $code );

		return esc_html( $code !== $name ? $name : ucfirst( $row->integration() ) );
	}

	/**
	 * @param Connection $row A row.
	 *
	 * @return string Which account the row is for. Escaped.
	 */
	public function account_label( Connection $row ): string {

		$metadata = $row->metadata();

		foreach ( array( $row->label(), $metadata['external_user_label'] ?? null, $metadata['email'] ?? null ) as $candidate ) {
			if ( is_string( $candidate ) && '' !== trim( $candidate ) ) {
				return esc_html( trim( $candidate ) );
			}
		}

		return esc_html_x( 'unnamed account', 'App connections', 'uncanny-automator' );
	}

	/**
	 * "Remove from this site" for one switched-off row — a local delete that
	 * leaves the source site untouched (R7). Offered wherever a switched-off
	 * row is listed, so a row that can never be attached (its account was
	 * removed) can always be cleared.
	 *
	 * @param Connection $row A switched-off row.
	 *
	 * @return void
	 */
	private function render_remove_form( Connection $row ): void {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-left:8px">
			<input type="hidden" name="action" value="<?php echo esc_attr( App_Connections_Actions::ACTION_REMOVE ); ?>" />
			<input type="hidden" name="connection_id" value="<?php echo esc_attr( $row->connection_id() ); ?>" />
			<?php wp_nonce_field( App_Connections_Actions::NONCE_ACTION ); ?>
			<uas-button type="submit" size="sm" color="transparent"><?php echo esc_html_x( 'Remove from this site', 'App connections', 'uncanny-automator' ); ?></uas-button>
		</form>
		<?php
	}

	/**
	 * One answer form.
	 *
	 * @param string $answer           Site_Copy_Record::ANSWER_ADOPT or ANSWER_KEEP_OFF.
	 * @param string $source_site_name The recorded source address.
	 * @param string $color            The button's color: primary or secondary.
	 * @param string $form_class       The form's own class.
	 *
	 * @return void
	 */
	private function render_answer_form( string $answer, string $source_site_name, string $color, string $form_class ): void {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="automator-copy-answer <?php echo esc_attr( $form_class ); ?>" style="display:flex;flex-wrap:wrap;align-items:center;gap:12px;margin:12px 0 0">
			<input type="hidden" name="action" value="<?php echo esc_attr( App_Connections_Actions::ACTION_ANSWER ); ?>" />
			<input type="hidden" name="answer" value="<?php echo esc_attr( $answer ); ?>" />
			<?php wp_nonce_field( App_Connections_Actions::NONCE_ACTION ); ?>
			<uas-button type="submit" size="sm" color="<?php echo esc_attr( $color ); ?>"><?php echo $this->answer_label( $answer ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in answer_label(). ?></uas-button>
			<span class="description"><?php echo $this->answer_description( $answer, $source_site_name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in answer_description(). ?></span>
		</form>
		<?php
	}
}
