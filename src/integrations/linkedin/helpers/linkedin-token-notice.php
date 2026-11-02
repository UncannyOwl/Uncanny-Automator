<?php

namespace Uncanny_Automator\Integrations\Linkedin;

use Uncanny_Automator\App\Presentation\Admin\Notices\Notice;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Message;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Screen;

/**
 * LinkedIn's refresh token expired, or expires within the notice window,
 * while LinkedIn actions are live ({@see Linkedin_App_Helpers::is_reauthorize_due()}):
 * reauthorize. Added to the notice center on every admin request by the
 * integration. No dismiss: it goes once reauthorized.
 *
 * @since 8.0.0
 */
class Linkedin_Token_Notice extends Notice {

	/**
	 * @var Linkedin_App_Helpers
	 */
	private $helpers;

	/**
	 * @param Linkedin_App_Helpers $helpers Knows the token and the settings page.
	 */
	public function __construct( Linkedin_App_Helpers $helpers ) {
		$this->helpers = $helpers;
	}

	/**
	 * @return string
	 */
	public function id(): string {
		return 'linkedin-token';
	}

	/**
	 * @return string
	 */
	public function capability(): string {
		return automator_get_capability();
	}

	/**
	 * @param Notice_Screen $screen The screen.
	 *
	 * @return Notice_Message|null
	 */
	public function message( Notice_Screen $screen ): ?Notice_Message {

		if ( ! $this->helpers->is_reauthorize_due() ) {
			return null;
		}

		$days = $this->helpers->get_refresh_token_remaining_days();

		return 0 >= $days ? $this->expired() : $this->expiring( $days );
	}

	/**
	 * Expired, and expiring.
	 *
	 * @return Notice_Message[]
	 */
	public function previews(): array {

		return array( $this->expired(), $this->expiring( 5 ) );
	}

	/**
	 * @return Notice_Message
	 */
	private function expired(): Notice_Message {

		$link = '<a href="' . esc_url( $this->helpers->get_settings_page_url() ) . '">%s</a>';

		return Notice_Message::error( esc_html_x( 'LinkedIn needs to be reconnected', 'Linkedin', 'uncanny-automator' ) )
			->with_paragraph(
				esc_html_x( 'Your LinkedIn access and refresh tokens have expired.', 'Linkedin', 'uncanny-automator' )
				. ' ' . sprintf( $link, esc_html_x( 'Click here', 'Linkedin', 'uncanny-automator' ) )
				. ' ' . esc_html_x( 'to reauthorize Uncanny Automator and continue using LinkedIn actions in your recipes.', 'Linkedin', 'uncanny-automator' )
			);
	}

	/**
	 * @param int $days Days until the tokens expire.
	 *
	 * @return Notice_Message
	 */
	private function expiring( int $days ): Notice_Message {

		$link = '<a href="' . esc_url( $this->helpers->get_settings_page_url() ) . '">%s</a>';

		return Notice_Message::warning( esc_html_x( 'LinkedIn connection expires soon', 'Linkedin', 'uncanny-automator' ) )
			->with_paragraph(
				esc_html(
					sprintf(
						/* translators: %s: number of days */
						_n( 'Your LinkedIn access and refresh tokens will expire in %s day.', 'Your LinkedIn access and refresh tokens will expire in %s days.', $days, 'uncanny-automator' ),
						number_format_i18n( $days )
					)
				)
				. ' ' . sprintf( $link, esc_html_x( 'Click here to reauthorize', 'Linkedin', 'uncanny-automator' ) )
				. ' ' . esc_html_x( 'to continue using your LinkedIn account in your recipes.', 'Linkedin', 'uncanny-automator' )
			);
	}
}
