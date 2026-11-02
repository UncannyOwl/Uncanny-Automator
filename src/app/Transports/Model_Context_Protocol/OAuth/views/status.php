<?php
/**
 * OAuth recovery and error dialog. No untrusted app return links.
 *
 * @var string $dialog_title Plain heading.
 * @var string $body Escaped, server-composed explanation.
 * @var string $action_url Trusted WordPress recovery URL, or empty.
 * @var string $action_label Plain recovery label.
 * @var string $connection_context Plain stage caption.
 * @var string $site_url Current site's URL.
 * @var string $user_display_name Current WordPress account.
 * @package Uncanny_Automator
 */

defined( 'ABSPATH' ) || exit;
require __DIR__ . '/page-start.php';
?>
			<div class="consent-heading">
				<h1><?php echo esc_html( $dialog_title ); ?></h1>
				<p class="site"><?php echo esc_html( $site_url ); ?></p>
				<?php if ( '' !== $user_display_name ) : ?>
					<p class="signed-in"><?php esc_html_e( 'Your WordPress account', 'uncanny-automator' ); ?> <span class="chip"><?php echo esc_html( $user_display_name ); ?></span></p>
				<?php endif; ?>
			</div>
			<?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Controller escapes every value while composing this trusted markup. ?>
		</div>
		<footer class="consent-footer">
			<?php if ( '' !== $action_url ) : ?>
				<div class="actions"><a class="approve" href="<?php echo esc_url( $action_url ); ?>"><?php echo esc_html( $action_label ); ?></a></div>
			<?php else : ?>
				<p class="fine"><?php esc_html_e( 'You can close this window.', 'uncanny-automator' ); ?></p>
			<?php endif; ?>
		</footer>
	</main>
</body>
</html>
