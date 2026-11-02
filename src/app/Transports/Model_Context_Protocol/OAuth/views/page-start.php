<?php
/**
 * Shared standalone OAuth dialog shell.
 *
 * @var string $dialog_title Plain page title.
 * @var string $connection_context Plain connection-stage caption.
 * @var string $styles Trusted bundled CSS.
 * @var string $brand_mark Trusted bundled SVG.
 * @package Uncanny_Automator
 */

defined( 'ABSPATH' ) || exit;
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title><?php echo esc_html( $dialog_title ); ?></title>
	<style><?php echo $styles; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Only bundled stylesheet and base64-encoded bundled font. ?></style>
</head>
<body>
	<main class="consent-dialog">
		<header class="consent-header">
			<div class="brand">
				<?php echo $brand_mark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Validated bundled SVG markup. ?>
				<span><?php esc_html_e( 'Uncanny Automator', 'uncanny-automator' ); ?></span>
			</div>
			<p class="connection-context"><?php echo esc_html( $connection_context ); ?></p>
		</header>
		<div class="consent-body" role="region" aria-label="<?php esc_attr_e( 'Connection request details', 'uncanny-automator' ); ?>" tabindex="0">
