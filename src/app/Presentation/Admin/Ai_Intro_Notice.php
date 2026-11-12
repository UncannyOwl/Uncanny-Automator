<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Presentation\Admin;

use Uncanny_Automator\Ai_Connection_State;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Action;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Dismissal;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Message;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Screen;

/**
 * "There's a new way to manage and automate this site with Uncanny
 * Automator: connect the AI you already use", once, to sites that existed
 * before 8.0: the migration flags them ({@see mark_pending()}). Hidden
 * while an AI app is connected, however it was connected; it shows again
 * if the connection drops before anyone dismissed it.
 *
 * Dismissed for the whole site by its X, "Not now", or following "Connect
 * your AI". The site's state lives in its own option, which the migration
 * also reads, so a re-run never brings it back.
 *
 * @since 8.0.0
 */
final class Ai_Intro_Notice extends Notice {

	/**
	 * The site's state: pending until dismissed.
	 */
	public const OPTION_NAME = 'automator_ai_intro_notice';

	public const STATE_PENDING = 'pending';

	public const STATE_DISMISSED = 'dismissed';

	/**
	 * Flag the site; a dismissal stays.
	 *
	 * @return void
	 */
	public static function mark_pending(): void {

		if ( self::STATE_DISMISSED === automator_get_option( self::OPTION_NAME, null ) ) {
			return;
		}

		automator_update_option( self::OPTION_NAME, self::STATE_PENDING );
	}

	/**
	 * Close it for the site.
	 *
	 * @return void
	 */
	public static function dismiss(): void {

		automator_update_option( self::OPTION_NAME, self::STATE_DISMISSED );
	}

	/**
	 * @return string
	 */
	public function id(): string {

		return 'ai-intro';
	}

	/**
	 * @return string
	 */
	public function capability(): string {

		return automator_get_capability();
	}

	/**
	 * @return Notice_Dismissal
	 */
	public function dismissal(): Notice_Dismissal {

		return Notice_Dismissal::per_site();
	}

	/**
	 * @param Notice_Screen $screen The screen.
	 *
	 * @return Notice_Message|null
	 */
	public function message( Notice_Screen $screen ): ?Notice_Message {

		if ( self::STATE_PENDING !== automator_get_option( self::OPTION_NAME, null ) ) {
			return null;
		}

		if ( Ai_Connection_State::is_connected() ) {
			return null;
		}

		return $this->intro();
	}

	/**
	 * @return Notice_Message[]
	 */
	public function previews(): array {

		return array( $this->intro() );
	}

	/**
	 * @return Notice_Message
	 */
	private function intro(): Notice_Message {

		$connect_url = add_query_arg(
			array(
				'post_type' => AUTOMATOR_POST_TYPE_RECIPE,
				'page'      => 'uncanny-automator-dashboard',
			),
			admin_url( 'edit.php' )
		);

		return Notice_Message::info( esc_html__( "There's a new way to manage and automate this site with Uncanny Automator: connect the AI you already use.", 'uncanny-automator' ) )
			->with_mascot()
			->with_paragraph( esc_html__( 'Uncanny Automator is now an MCP server for WordPress with a built-in automation engine, so ChatGPT, Claude, Gemini or any AI app that supports remote MCP servers can connect.', 'uncanny-automator' ) )
			->with_paragraph( esc_html__( 'Every recipe you have built keeps working exactly as it does today.', 'uncanny-automator' ) )
			->with_action( Notice_Action::link( esc_html__( 'Connect your AI', 'uncanny-automator' ), $connect_url )->primary()->dismissing() )
			->with_action( Notice_Action::dismiss( esc_html__( 'Not now', 'uncanny-automator' ) ) );
	}

	/**
	 * The center stored the dismissal: close it for the site in its own
	 * state too, which the migration reads.
	 *
	 * @return void
	 */
	public function dismissed(): void {

		self::dismiss();
	}
}
