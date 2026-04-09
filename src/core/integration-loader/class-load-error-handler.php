<?php
/**
 * Load Error Handler
 *
 * Collects integration loading errors into a transient for batched admin notice display.
 * Replaces the old wp_mail()-based error reporting which fired during plugin loading
 * before SMTP plugins were ready.
 *
 * @package Uncanny_Automator\Integration_Loader
 * @since   7.2
 */

namespace Uncanny_Automator\Integration_Loader;

/**
 * Class Load_Error_Handler
 *
 * Single responsibility: collect integration load errors and display them
 * as a dismissible admin notice with a copyable support report.
 */
class Load_Error_Handler {

	/**
	 * Transient key for batched error storage.
	 *
	 * @var string
	 */
	const TRANSIENT_KEY = 'automator_load_errors';

	/**
	 * AJAX action name for dismissing the notice.
	 *
	 * @var string
	 */
	const DISMISS_ACTION = 'automator_dismiss_load_errors';

	/**
	 * How long to keep batched load errors before they expire on their own.
	 *
	 * Short window so a fix (plugin update, dependency restored) clears the
	 * notice quickly without requiring an explicit dismissal.
	 *
	 * @var int
	 */
	const TTL = 5 * MINUTE_IN_SECONDS;

	/**
	 * Whether the current request recorded any new load errors.
	 *
	 * Used by maybe_clear_on_clean_load() to wipe the transient when a
	 * subsequent request bootstraps cleanly.
	 *
	 * @var bool
	 */
	private static $recorded_error = false;

	/**
	 * Handle an error encountered during integration class loading.
	 *
	 * Logs the error and stores it in a transient array for batched admin notice display.
	 * Deduplicates by hashing class + message + file + line so the same error is only
	 * stored once per window.
	 *
	 * @param string     $class The fully qualified class name that failed to load.
	 * @param \Throwable $e     The caught error or exception.
	 *
	 * @return void
	 */
	public function handle( $class, \Throwable $e ) {

		$message = $e->getMessage();
		$file    = $e->getFile();
		$line    = $e->getLine();

		// Always log regardless of transient state.
		automator_log( $class . ': ' . $message . ' in ' . $file . ':' . $line, 'Integration load error' );

		$errors = get_transient( self::TRANSIENT_KEY );

		if ( ! is_array( $errors ) ) {
			$errors = array();
		}

		$error_hash = md5( $class . '|' . $message . '|' . $file . '|' . $line );

		// Mark this request as having produced a load error so the
		// post-bootstrap auto-clear hook leaves the transient in place.
		self::$recorded_error = true;

		// Deduplicate by hash.
		if ( ! isset( $errors[ $error_hash ] ) ) {
			$errors[ $error_hash ] = array(
				'class'   => $class,
				'message' => $message,
				'file'    => $file,
				'line'    => $line,
				'time'    => gmdate( 'Y-m-d H:i:s' ) . ' UTC',
			);
			set_transient( self::TRANSIENT_KEY, $errors, self::TTL );
		}
	}

	/**
	 * Clear the load-errors transient if the current request bootstrapped
	 * cleanly. Called after all integrations have been loaded.
	 *
	 * Hooked from Integration_Loader once bootstrap is complete. The early
	 * exit on $recorded_error means we never wipe a transient that the
	 * current request just populated.
	 *
	 * @return void
	 */
	public static function maybe_clear_on_clean_load() {

		if ( self::$recorded_error ) {
			return;
		}

		if ( false === get_transient( self::TRANSIENT_KEY ) ) {
			return;
		}

		delete_transient( self::TRANSIENT_KEY );
	}

	/**
	 * Register the AJAX dismiss handler.
	 *
	 * Call this once during initialization (e.g. from Integration_Loader).
	 *
	 * @return void
	 */
	public static function register_dismiss_handler() {
		add_action( 'wp_ajax_' . self::DISMISS_ACTION, array( __CLASS__, 'ajax_dismiss' ) );
	}

	/**
	 * AJAX handler: clear the load errors transient when the notice is dismissed.
	 *
	 * @return void
	 */
	public static function ajax_dismiss() {

		check_ajax_referer( self::DISMISS_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		delete_transient( self::TRANSIENT_KEY );

		wp_send_json_success();
	}

	/**
	 * Display admin notice for integration loading errors.
	 *
	 * Hooked to `admin_notices`. Shows a dismissible warning listing all errors
	 * from the current transient window, with a copyable plain-text report that
	 * users can send to Automator support.
	 *
	 * @return void
	 */
	public static function display_notice() {

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$errors = get_transient( self::TRANSIENT_KEY );

		if ( empty( $errors ) || ! is_array( $errors ) ) {
			return;
		}

		$report_text = self::build_report( $errors );

		self::render_notice( $errors, $report_text );
	}

	/**
	 * Build a plain-text error report for support.
	 *
	 * @param array $errors The error entries.
	 *
	 * @return string The formatted report text.
	 */
	private static function build_report( $errors ) {

		$lines   = array();
		$lines[] = 'Uncanny Automator — Integration Load Errors';
		$lines[] = 'Site: ' . home_url();
		$lines[] = 'Automator: ' . ( defined( 'AUTOMATOR_PLUGIN_VERSION' ) ? AUTOMATOR_PLUGIN_VERSION : 'unknown' );
		$lines[] = 'PHP: ' . PHP_VERSION;
		$lines[] = 'WP: ' . get_bloginfo( 'version' );
		$lines[] = '';

		foreach ( $errors as $error ) {
			$lines[] = sprintf(
				'[%s] %s: %s (%s:%d)',
				$error['time'],
				$error['class'],
				$error['message'],
				$error['file'],
				$error['line']
			);
		}

		return implode( "\n", $lines );
	}

	/**
	 * Render the admin notice HTML.
	 *
	 * @param array  $errors      The error entries.
	 * @param string $report_text Plain-text report for the textarea.
	 *
	 * @return void
	 */
	private static function render_notice( $errors, $report_text ) {

		$count       = count( $errors );
		$report_rows = min( count( explode( "\n", $report_text ) ), 12 );
		$nonce       = wp_create_nonce( self::DISMISS_ACTION );

		echo '<div class="notice notice-warning is-dismissible" id="automator-load-errors-notice">';
		echo '<p><strong>Uncanny Automator:</strong> ';
		printf(
			/* translators: %d: Number of integration errors */
			esc_html(
				_n(
					'%d integration component failed to load. The site continues to work — the affected component was skipped.',
					'%d integration components failed to load. The site continues to work — the affected components were skipped.',
					$count,
					'uncanny-automator'
				)
			),
			(int) $count
		);
		echo '</p>';
		echo '<details><summary>' . esc_html__( 'Show error details', 'uncanny-automator' ) . '</summary>';
		echo '<ul style="list-style:disc;margin-left:20px;">';

		foreach ( $errors as $error ) {
			printf(
				'<li><code>%s</code>: %s <em>(%s:%d)</em></li>',
				esc_html( $error['class'] ),
				esc_html( $error['message'] ),
				esc_html( basename( $error['file'] ) ),
				(int) $error['line']
			);
		}

		echo '</ul>';
		echo '<p>' . esc_html__( 'If this issue persists, copy the error report below and send it to Automator support.', 'uncanny-automator' ) . '</p>';
		echo '<textarea readonly rows="' . esc_attr( $report_rows ) . '" style="width:100%;font-family:monospace;font-size:12px;background:#f6f7f7;border:1px solid #c3c4c7;" onclick="this.select();">' . esc_textarea( $report_text ) . '</textarea>';
		echo '</details>';
		echo '</div>';

		// Inline JS to clear transient on dismiss.
		?>
		<script>
		(function(){
			var notice = document.getElementById('automator-load-errors-notice');
			if ( ! notice ) return;
			notice.addEventListener('click', function(e) {
				if ( ! e.target.classList.contains('notice-dismiss') ) return;
				var xhr = new XMLHttpRequest();
				xhr.open('POST', '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>');
				xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
				xhr.send('action=<?php echo esc_js( self::DISMISS_ACTION ); ?>&nonce=<?php echo esc_js( $nonce ); ?>');
			});
		})();
		</script>
		<?php
	}
}
