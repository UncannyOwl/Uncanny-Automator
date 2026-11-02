<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Presentation\Admin;

use Uncanny_Automator\App\Presentation\Admin\Notices\Notice;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Dismissal;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Message;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Screen;
use Uncanny_Automator\Integration_Loader\Load_Error_Handler;

/**
 * Integration components that failed to load in the last few minutes
 * ({@see Load_Error_Handler}), with a plain-text report the admin can copy
 * for support. Its X clears the list, as before: the dismissal is tied to
 * the list, so a new failure shows it again. The list also clears itself
 * after a clean load.
 *
 * @since 8.0.0
 */
final class Load_Errors_Notice extends Notice {

	/**
	 * @return string
	 */
	public function id(): string {

		return 'integration-load-errors';
	}

	/**
	 * @return string
	 */
	public function capability(): string {

		return 'manage_options';
	}

	/**
	 * For the site, while the list is the one dismissed.
	 *
	 * @return Notice_Dismissal
	 */
	public function dismissal(): Notice_Dismissal {

		return Notice_Dismissal::per_site( 0, md5( (string) wp_json_encode( $this->errors() ) ) );
	}

	/**
	 * @param Notice_Screen $screen The screen.
	 *
	 * @return Notice_Message|null
	 */
	public function message( Notice_Screen $screen ): ?Notice_Message {

		$errors = $this->errors();
		if ( array() === $errors ) {
			return null;
		}

		return $this->failed( $errors );
	}

	/**
	 * @return Notice_Message[]
	 */
	public function previews(): array {

		return array(
			$this->failed(
				array(
					array(
						'class'   => 'Uncanny_Automator\\Integrations\\Woocommerce\\Wc_Purchases_Product',
						'message' => 'Class "WooCommerce" not found',
						'file'    => 'src/integrations/woocommerce/triggers/wc-purchases-product.php',
						'line'    => 42,
						'time'    => '2026-10-28 14:00:00 UTC',
					),
					array(
						'class'   => 'Uncanny_Automator\\Integrations\\Learndash\\Ld_Enrol_User',
						'message' => 'Call to undefined function learndash_get_course_id()',
						'file'    => 'src/integrations/learndash/actions/ld-enrol-user.php',
						'line'    => 88,
						'time'    => '2026-10-28 14:00:00 UTC',
					),
				)
			),
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $errors The components that failed.
	 *
	 * @return Notice_Message
	 */
	private function failed( array $errors ): Notice_Message {

		$count = count( $errors );

		return Notice_Message::warning( esc_html_x( 'Some integration components failed to load', 'Admin notice', 'uncanny-automator' ) )
			->with_paragraph(
				sprintf(
					/* translators: %d: Number of integration errors */
					esc_html( _n( '%d integration component failed to load and was skipped. The rest of the site keeps working.', '%d integration components failed to load and were skipped. The rest of the site keeps working.', $count, 'uncanny-automator' ) ),
					(int) $count
				)
			)
			->with_paragraph( esc_html__( 'If this issue persists, copy the error report below and send it to Automator support.', 'uncanny-automator' ) )
			->with_report( esc_html__( 'Show error details', 'uncanny-automator' ), Load_Error_Handler::build_report( $errors ) );
	}

	/**
	 * Dismissed: clear the list, as the old dismiss did.
	 *
	 * @return void
	 */
	public function dismissed(): void {

		delete_transient( Load_Error_Handler::TRANSIENT_KEY );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function errors(): array {

		$errors = get_transient( Load_Error_Handler::TRANSIENT_KEY );

		return is_array( $errors ) ? array_values( $errors ) : array();
	}
}
