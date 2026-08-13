<?php
/**
 * Editor shell styles.
 *
 * Layout for the chrome columns (panel, workspace, topbar, canvas), the
 * empty-canvas state, and the theme-shell placeholder strips. Printed only
 * when editor chrome renders.
 */

defined('ABSPATH') || exit;
?>
<style id="uncanny-page-builder-editor-shell">
    html,
    body.uncanny-canvas {
        min-height: 100%;
        margin: 0;
    }

    /*
     * Chrome document base. Bootstrap's unscoped reboot used to give the
     * editor document its body font and text resets for free; the canvas-
     * scoped Bootstrap build maps those onto #uncanny-pb-canvas instead, so
     * the chrome must own its base. The canvas is unaffected: the scoped
     * reboot re-establishes the configured design font at the canvas
     * boundary, which beats this inherited value.
     */
    body.uncanny-canvas {
        --upb-editor-panel-width: 285px;
        background: #f6f7f7;
        color: #1d2327;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
        font-size: 13px;
        line-height: 1.4;
        -webkit-text-size-adjust: 100%;
        -webkit-font-smoothing: antialiased;
    }

    /*
     * Chrome margin discipline — the other half of the reboot Bootstrap used
     * to provide. Panel and topbar layouts are gap/padding based; UA default
     * margins on headings and paragraphs misalign rows and push adjacent
     * panel borders apart. Zero-specificity so any real component style
     * wins, and scoped to chrome roots only — never the canvas, whose
     * scoped reboot owns content margins.
     */
    :where(#uncanny-pb-tab-panel-root, #uncanny-pb-topbar-root) :where(h1, h2, h3, h4, h5, h6, p, figure) {
        margin: 0;
    }

    /*
     * Chrome that lives INSIDE the canvas (empty state, section library
     * card, badges) sits past the canvas boundary where the scoped reboot
     * re-establishes the user's brand font — correct for sections, wrong
     * for editor UI. Re-assert the chrome font on chrome-marked islands.
     */
    #uncanny-pb-canvas .upb-empty-canvas-state,
    #uncanny-pb-canvas [data-upb-editor-chrome] {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
    }

    html,
    body.uncanny-canvas {
        margin-top: 0 !important;
    }

    /*
     * Uncanny Agent docks by forcing this host to flex with inline styles.
     * Keep the editor shell flex-native so that SDK-owned mutation is
     * compatible instead of something Page Builder has to undo.
     *
     * Keep the empty page inside the first editor viewport. The command bar
     * updates its measured height on this same element at runtime.
     */
    #uncanny-pb-editor-layout {
        --upb-command-bar-height: 60px;
        --upb-empty-canvas-min-height: max(560px, calc(100vh - var(--upb-command-bar-height) - 96px));
        display: flex;
        flex-direction: row;
        align-items: stretch;
        min-height: 100vh;
        width: 100%;
        min-width: 0;
        max-width: none;
        margin-left: 0;
        margin-right: 0;
        box-sizing: border-box;
    }

    #uncanny-pb-tab-panel-root[hidden] {
        display: none;
    }

    #uncanny-pb-tab-panel-root {
        flex: 0 0 var(--upb-editor-panel-width);
        position: sticky;
        top: 0;
        align-self: start;
        width: var(--upb-editor-panel-width);
        height: 100vh;
        min-width: 0;
        overflow: auto;
        scroll-padding-top: 96px;
        background: #fff;
        border-right: 1px solid #dcdcde;
        z-index: 10002;
    }

    .upb-workspace-panel__narrow-header {
        display: none;
    }

    #uncanny-pb-tab-panel-root [data-selected-target="true"] {
        position: sticky;
        top: 0;
        z-index: 2;
        background: #fff;
        border-top: 1px solid #dcdcde;
    }

    /*
     * Gutenberg hierarchy: PanelBody owns the visible heading while the
     * nested ToolsPanel keeps its native options and reset menu.
     */
    #uncanny-pb-tab-panel-root .upb-collapsible-tools-panel .upb-persistent-panel-content {
        width: 100%;
        zoom: 0.92;
    }

    #uncanny-pb-tab-panel-root .upb-collapsible-tools-panel .components-tools-panel {
        gap: 12px;
        margin-top: 0;
        padding: 8px 0 12px;
        border-top: 0;
    }

    #uncanny-pb-tab-panel-root .upb-collapsible-tools-panel .components-tools-panel-header {
        justify-content: flex-end;
    }

    #uncanny-pb-tab-panel-root .upb-collapsible-tools-panel .components-tools-panel-header h3 {
        display: none;
    }

    /*
     * Navigator hierarchy. These rows move the authoritative Design Lens
     * selection, so use WordPress link colour and a restrained selected state
     * to make that interaction clear without introducing a second UI system.
     */
    #uncanny-pb-tab-panel-root .upb-element-layer-row {
        box-sizing: border-box;
        width: 100%;
        max-width: 100%;
        min-width: 0;
        padding-inline-start: calc(12px + (var(--upb-layer-depth, 0) * 6px));
        overflow: hidden;
        color: var(--wp-admin-theme-color, #2271b1);
        cursor: pointer;
    }

    #uncanny-pb-tab-panel-root .upb-element-layer-row__content {
        display: flex;
        align-items: center;
        gap: 4px;
        width: 100%;
        min-width: 0;
    }

    #uncanny-pb-tab-panel-root .upb-element-layer-row__label {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    #uncanny-pb-tab-panel-root .upb-element-layer-row__status {
        flex: 0 0 auto;
        color: #50575e;
        font-size: 10px;
        font-weight: 500;
    }

    #uncanny-pb-tab-panel-root .upb-element-layer-row:hover .upb-element-layer-row__label {
        text-decoration: underline;
    }

    #uncanny-pb-tab-panel-root .upb-element-layer-row:focus-visible .upb-element-layer-row__label {
        text-decoration: underline;
    }

    #uncanny-pb-tab-panel-root .upb-element-layer-row.is-selected {
        background: #f0f6fc;
        box-shadow: inset 3px 0 var(--wp-admin-theme-color, #2271b1);
        font-weight: 600;
    }

    #uncanny-pb-workspace-root {
        flex: 1 1 auto;
        display: flex;
        flex-direction: column;
        position: relative;
        min-width: 0;
        min-height: 100vh;
        max-width: none;
        margin-left: 0;
        margin-right: 0;
        box-sizing: border-box;
    }

    .upb-agent-editing-shield {
        position: absolute;
        z-index: 10005;
        inset: 0;
        display: flex;
        align-items: flex-start;
        justify-content: center;
        padding: calc(var(--upb-command-bar-height) + 18px) 20px 20px;
        background: rgba(246, 247, 247, 0.64);
        box-sizing: border-box;
        cursor: wait;
        pointer-events: auto;
        touch-action: pan-y;
    }

    .upb-agent-editing-shield[hidden] {
        display: none;
    }

    .upb-agent-editing-shield__notice {
        position: sticky;
        top: calc(var(--upb-command-bar-height) + 18px);
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 12px;
        width: min(100%, 420px);
        padding: 14px 16px;
        border: 1px solid #c3c4c7;
        border-radius: 4px;
        background: #fff;
        box-shadow: 0 4px 18px rgba(0, 0, 0, 0.14);
        box-sizing: border-box;
        color: #1d2327;
    }

    .upb-agent-editing-shield__spinner {
        flex: 0 0 auto;
        width: 18px;
        height: 18px;
        border: 2px solid #c3c4c7;
        border-top-color: var(--wp-admin-theme-color, #2271b1);
        border-radius: 50%;
        animation: upb-agent-editing-spin 0.8s linear infinite;
    }

    .upb-agent-editing-shield__copy {
        flex: 1 1 220px;
        display: grid;
        gap: 2px;
        min-width: 0;
        line-height: 1.4;
    }

    .upb-agent-editing-shield__copy strong {
        font-size: 14px;
    }

    .upb-agent-editing-shield__copy span {
        color: #50575e;
    }

    .upb-agent-editing-shield__reload {
        flex: 0 0 auto;
        align-self: center;
        margin-left: 30px;
        white-space: nowrap;
    }

    @keyframes upb-agent-editing-spin {
        to {
            transform: rotate(360deg);
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .upb-agent-editing-shield__spinner {
            animation: none;
        }
    }

    #uncanny-pb-topbar-root,
    #uncanny-pb-topbar,
    #uncanny-pb-canvas-root {
        width: 100%;
        min-width: 0;
        max-width: none;
        margin-left: 0;
        margin-right: 0;
        box-sizing: border-box;
    }

    /*
     * The shell host owns command-bar stickiness. Making the nested custom
     * element sticky confines it to the height of this root and lets the
     * entire topbar scroll away. Canvas-owned sticky headers are offset below
     * this surface by the DOM adapter.
     */
    #uncanny-pb-topbar-root {
        position: sticky;
        top: 0;
        z-index: 10001;
    }

    #uncanny-pb-canvas-root {
        flex: 1 1 auto;
        display: flex;
        flex-direction: column;
        min-height: 0;
    }

    @media (max-width: 768px) {
        body.uncanny-canvas #uncanny-pb-editor-layout {
            flex-direction: column;
            flex-wrap: wrap;
            overflow-x: clip;
        }

        body.uncanny-canvas #uncanny-pb-tab-panel-root {
            flex: 0 0 auto;
            position: relative;
            top: auto;
            align-self: stretch;
            width: 100%;
            height: min(42vh, 360px);
            max-height: 360px;
            border-right: 0;
            border-bottom: 1px solid #dcdcde;
        }

        body.uncanny-canvas #uncanny-pb-tab-panel-root [data-selected-target="true"] {
            top: 41px;
        }

        body.uncanny-canvas #uncanny-pb-workspace-root {
            flex: 1 0 auto;
            width: 100%;
            min-height: 100vh;
        }

        body.uncanny-canvas .upb-workspace-panel__narrow-header {
            position: sticky;
            top: 0;
            z-index: 3;
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 41px;
            padding: 0 8px 0 16px;
            background: #fff;
            border-bottom: 1px solid #dcdcde;
        }
    }

    /*
     * Design Lens moves focus here after a pointing gesture so canvas
     * shortcuts do not run against a stale sidebar control. The canvas is not
     * a visible form control, so keep that programmatic focus ring neutral.
     */
    #uncanny-pb-canvas:focus {
        outline: none;
    }

    #uncanny-pb-canvas.upb-canvas--empty {
        flex: 1 1 auto;
        display: flex;
        flex-direction: column;
        min-height: var(--upb-empty-canvas-min-height);
    }

    .upb-empty-canvas-state {
        flex: 1 1 auto;
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: var(--upb-empty-canvas-min-height);
        padding: 48px 24px;
        background: linear-gradient(180deg, #fff 0%, #fff 62%, #fafafa 100%);
        text-align: center;
    }

    .upb-empty-canvas-state__inner {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        width: min(100%, 760px);
        gap: 34px;
    }

    .upb-empty-canvas-state__surface {
        display: grid;
        gap: 24px;
        place-items: center;
        width: 100%;
    }

    .upb-empty-canvas-state__copy {
        display: grid;
        gap: 14px;
        place-items: center;
    }

    .upb-empty-canvas-state__icon {
        width: auto;
        height: 112px;
        margin-top: -10px;
        object-fit: contain;
    }

    .upb-empty-canvas-state__primary-action.components-button {
        min-height: 40px;
    }

    .upb-empty-canvas-state__primary-action.components-button[aria-busy="true"] {
        cursor: wait;
    }

    .upb-empty-canvas-state__actions {
        width: 100%;
        display: flex;
        justify-content: center;
    }

    .upb-empty-canvas-state__heading {
        color: #1d2327;
    }

    .upb-empty-canvas-state__body {
        color: #4b5563;
    }

    .upb-empty-canvas-state__copy .upb-empty-canvas-state__body {
        text-wrap: balance;
    }

    /*
     * Theme shell placeholders. Composition pages never render the
     * theme's header/footer in the editor — the strips say so honestly
     * and hand the user the faithful preview instead.
     */
    .upb-theme-shell-placeholder {
        box-sizing: border-box;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
        min-height: 48px;
        padding: 6px 16px;
        background: #f6f7f7;
        border: 0;
        border-block: 1px solid #e0e0e0;
        color: #757575;
        font: 13px/20px -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        letter-spacing: 0;
    }

    /*
     * The command bar already owns the divider above the header placeholder.
     * Avoid stacking a second line at that shared boundary.
     */
    #uncanny-pb-canvas-root > .upb-theme-shell-placeholder:first-child {
        border-top: 0;
    }

    .upb-theme-shell-placeholder__preview.components-button {
        flex: 0 0 auto;
    }
</style>
