<?php
/**
 * Canvas shell state.
 *
 * Every shell decision the document partials consume, computed once at the
 * top of the template. The canvas is always Page Builder's standalone
 * document; theme composition only changes what the strips around the canvas
 * say (see theme-shell-placeholder.php).
 *
 * Inputs (template scope): $postId, $sections, $shellMode,
 *                          $emptyCanvasInvitation, $agentSetupUrl
 * Defines: $showEditorChrome, $isGlobalPartCanvas, $hasCanvasSections,
 *          $shellModeValue, $usesThemeCompositionShell, $editorScopeAttr,
 *          $emptyCanvasHeading, $emptyCanvasBody, $emptyCanvasActionKind,
 *          $emptyCanvasActionLabel, $emptyCanvasActionUrl,
 *          $showThemeShellPlaceholders, $themeShellPreviewUrl
 */

use UncannyPageBuilder\Domain\Canvas\EmptyCanvasInvitation;
use UncannyPageBuilder\Domain\Shell\ShellMode;
use UncannyPageBuilder\Infrastructure\WordPress\CanvasEditorChromeGate;

defined('ABSPATH') || exit;

$showEditorChrome = CanvasEditorChromeGate::shouldShow(
    (int) ($postId ?? 0),
    $_GET,
);
$isGlobalPartCanvas = is_singular('upb_global_part');
$hasCanvasSections = is_array($sections ?? null) && count($sections) > 0;
$shellModeValue = $shellMode instanceof ShellMode
    ? $shellMode->value
    : (string) ($shellMode ?? '');
$usesThemeCompositionShell = $shellModeValue === ShellMode::ThemeComposition->value;
$editorScopeAttr = $isGlobalPartCanvas ? 'global_part' : 'page';
$isFullScreenMode = isset($_GET['full_screen_mode']) && (string) wp_unslash($_GET['full_screen_mode']) === '1';
$isEmbeddedCanvasHost = !$isFullScreenMode;
$emptyCanvasInvitation = ($emptyCanvasInvitation ?? null) instanceof EmptyCanvasInvitation
    ? $emptyCanvasInvitation
    : EmptyCanvasInvitation::StartAgent;

if ($emptyCanvasInvitation === EmptyCanvasInvitation::SetupAgent) {
    $emptyCanvasHeading = _x('Connect to Uncanny Agent', 'Page Builder', 'uncanny-automator');
    $emptyCanvasBody = _x('Connect a free Uncanny Automator account to start creating pages with Uncanny Page Builder. It only takes a moment.', 'Page Builder', 'uncanny-automator');
    $emptyCanvasActionKind = 'setup';
    $emptyCanvasActionLabel = _x('Connect free account', 'Page Builder', 'uncanny-automator');
    $emptyCanvasActionUrl = (string) ($agentSetupUrl ?? '');
} else {
    $emptyCanvasHeading = $isGlobalPartCanvas
        ? _x('Start building a new section you can reuse across your Uncanny Page Builder pages', 'Page Builder', 'uncanny-automator')
        : _x('Start building with Uncanny Agent', 'Page Builder', 'uncanny-automator');
    $emptyCanvasBody = $isGlobalPartCanvas
        ? _x('Describe the reusable you want, then refine sections, copy, and layout with Agent. Changes here affect every page using it.', 'Page Builder', 'uncanny-automator')
        : _x('Describe the page you want, then refine sections, copy, and layout with Agent.', 'Page Builder', 'uncanny-automator');
    $emptyCanvasActionKind = 'chat';
    $emptyCanvasActionLabel = _x('Start chatting', 'Page Builder', 'uncanny-automator');
    $emptyCanvasActionUrl = '';
}

/*
 * Theme composition pages render the SAME standalone editor document as
 * native pages. The theme's header and footer are deliberately not rendered
 * here: themes gate their frontend bootstrap on is_admin(), so an admin-route
 * render can never be faithful — and a faithful preview already exists at the
 * permalink. The editor shows honest placeholder strips instead, and the
 * public render composes sections into the theme via ContentRenderer.
 */
$showThemeShellPlaceholders = $showEditorChrome && $usesThemeCompositionShell;
$themeShellPreviewUrl = '';
if ($showThemeShellPlaceholders) {
    $themeShellPreviewUrl = get_post_status($postId) === 'publish'
        ? (string) get_permalink($postId)
        : (string) (get_preview_post_link($postId) ?: get_permalink($postId));
}
