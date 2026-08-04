<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Operation;

/**
 * The Page Builder jobs whose policy requirements differ.
 *
 * Administrator intent and runtime support are separate facts. Explicit intent
 * gates every active Page Builder surface, while runtime support gates only
 * ordinary authoring. Recovery and cleanup remain available so disabled
 * content can be returned safely to WordPress.
 */
enum PageBuilderOperation: string
{
    case Adopt = 'adopt';
    case Edit = 'edit';
    case SavePostMeta = 'save_post_meta';
    case Publish = 'publish';
    case RenderPublic = 'render_public';
    case Recover = 'recover';
    case Cleanup = 'cleanup';

    public function requiresAdministratorIntent(): bool
    {
        return match ($this) {
            self::Adopt,
            self::Edit,
            self::SavePostMeta,
            self::Publish,
            self::RenderPublic => true,

            self::Recover,
            self::Cleanup => false,
        };
    }

    public function requiresRuntimeSupport(): bool
    {
        return match ($this) {
            self::Adopt,
            self::Edit,
            self::SavePostMeta,
            self::Publish => true,

            self::RenderPublic,
            self::Recover,
            self::Cleanup => false,
        };
    }
}
