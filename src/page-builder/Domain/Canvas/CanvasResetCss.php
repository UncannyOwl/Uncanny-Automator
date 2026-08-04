<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Canvas;

/**
 * Shared inherited-style barrier for every rendered canvas surface.
 */
final class CanvasResetCss
{
    public static function render(): string
    {
        return 'body.uncanny-canvas{margin:0;padding:0}'
            . '#uncanny-pb-canvas{'
            . 'font-family:var(--bs-body-font-family,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif);'
            . 'font-size:16px;font-weight:400;font-style:normal;line-height:1.5;color:#1a1a1a;'
            . 'letter-spacing:normal;word-spacing:normal;text-transform:none;text-align:start;'
            . 'text-indent:0;white-space:normal;word-break:normal;overflow-wrap:normal;'
            . '-webkit-text-size-adjust:100%;tab-size:4'
            . '}'
            . ':where(#uncanny-pb-canvas) h1,:where(#uncanny-pb-canvas) h2,'
            . ':where(#uncanny-pb-canvas) h3,:where(#uncanny-pb-canvas) h4,'
            . ':where(#uncanny-pb-canvas) h5,:where(#uncanny-pb-canvas) h6'
            . '{font-size:inherit;font-weight:inherit}'
            . ':where(#uncanny-pb-canvas) ul,:where(#uncanny-pb-canvas) ol'
            . '{list-style:none}'
            . ':where(#uncanny-pb-canvas) img,:where(#uncanny-pb-canvas) video'
            . '{max-width:100%;height:auto;display:block}'
            . ':where(#uncanny-pb-canvas) a{text-decoration:none;color:inherit}'
            // Preserve the link affordance when an editor surface intercepts
            // navigation. Zero specificity lets authored cursor styles win.
            . ':where(#uncanny-pb-canvas) :where(a[href]){cursor:pointer}'
            . ':where(#uncanny-pb-canvas) button,:where(#uncanny-pb-canvas) input,'
            . ':where(#uncanny-pb-canvas) select,:where(#uncanny-pb-canvas) textarea'
            . '{font:inherit;color:inherit}'
            . ':where(#uncanny-pb-canvas) table{border-collapse:collapse;border-spacing:0}'
            . ':where(#uncanny-pb-canvas) fieldset{border:none}';
    }
}
