<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Presentation\Admin;

use Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration\Registration_Outcomes;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Dismissal;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Message;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Screen;

/**
 * "This site moved here from {old}" notice (move contract §10.4), for every
 * move to a new address that landed.
 *
 * A move with the old secret is told where it moved from. A move without the
 * old secret lands only on evidence from the old address — it no longer
 * resolves, it redirects here, or it now serves this install — and is told
 * which. Either way the app connections came with it. The move lands on
 * whichever request polls completion — often a cron tick — so the engine keeps
 * the outcome site-wide ({@see Registration_Outcomes::take_move()}) and the
 * first admin to load a page is told. Shown once: the notice center reads the
 * move, and it is forgotten once the notice was drawn.
 *
 * @since 8.0.0
 */
final class Site_Moved_Notice extends Notice {

	/**
	 * @var Registration_Outcomes
	 */
	private $outcomes;

	/**
	 * @param Registration_Outcomes $outcomes Owner of the outcome.
	 */
	public function __construct( Registration_Outcomes $outcomes ) {

		$this->outcomes = $outcomes;
	}

	/**
	 * @return string
	 */
	public function id(): string {

		return 'platform-site-moved';
	}

	/**
	 * Shown once; its X closes it.
	 *
	 * @return Notice_Dismissal
	 */
	public function dismissal(): Notice_Dismissal {

		return Notice_Dismissal::for_this_view();
	}

	/**
	 * @param Notice_Screen $screen The screen.
	 *
	 * @return Notice_Message|null
	 */
	public function message( Notice_Screen $screen ): ?Notice_Message {

		$moved = $this->outcomes->peek_move();
		if ( null === $moved ) {
			return null;
		}

		return $this->moved( $moved['from'], $moved['evidence'] );
	}

	/**
	 * One for each way the move was confirmed.
	 *
	 * @return Notice_Message[]
	 */
	public function previews(): array {

		return array_map(
			function ( string $evidence ): Notice_Message {
				return $this->moved( 'https://old.example.com', $evidence );
			},
			array( 'does_not_resolve', 'redirects_here', 'serves_this_install', '' )
		);
	}

	/**
	 * @param string $from     The previous address.
	 * @param string $evidence How the move was confirmed; '' with the secret.
	 *
	 * @return Notice_Message
	 */
	private function moved( string $from, string $evidence ): Notice_Message {

		$old = '<code>' . esc_html( $from ) . '</code>';

		return Notice_Message::success( esc_html_x( 'This site moved here', 'Admin notice', 'uncanny-automator' ) )
			->with_state( '' !== $evidence ? $evidence : 'with_secret' )
			->with_paragraph( $this->sentence( $evidence, $old ) . ' ' . esc_html__( 'Your app connections came with it.', 'uncanny-automator' ) );
	}

	/**
	 * Drawn: forget the move, so it is shown once.
	 *
	 * @return void
	 */
	public function shown(): void {

		$this->outcomes->take_move();
	}

	/**
	 * Where the site moved from and, for a move without the old secret, how
	 * the old address showed it left. Escaped for HTML.
	 *
	 * @param string $evidence The platform's `old_address`; '' for a move with the secret.
	 * @param string $old      The old address, escaped and wrapped for HTML.
	 *
	 * @return string
	 */
	private function sentence( string $evidence, string $old ): string {
		$sentences = array(
			/* translators: %1$s: previous site address */
			'does_not_resolve'    => esc_html__( 'This site moved here from %1$s: %1$s no longer exists.', 'uncanny-automator' ),
			/* translators: %1$s: previous site address */
			'redirects_here'      => esc_html__( 'This site moved here from %1$s: %1$s now redirects here.', 'uncanny-automator' ),
			/* translators: %1$s: previous site address */
			'serves_this_install' => esc_html__( 'This site moved here from %1$s: %1$s now serves this site.', 'uncanny-automator' ),
		);

		/* translators: %1$s: previous site address */
		$sentence = $sentences[ $evidence ] ?? esc_html__( 'This site moved here from %1$s.', 'uncanny-automator' );

		return sprintf( $sentence, $old );
	}
}
