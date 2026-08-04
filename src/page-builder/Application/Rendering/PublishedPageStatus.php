<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Rendering;

/**
 * Public-read outcomes for one Page Builder page.
 */
enum PublishedPageStatus: string
{
    case Ready = 'ready';
    case NotManaged = 'not_managed';
    case Unpublished = 'unpublished';
    case MissingArtifact = 'missing_artifact';
    case CrossPageArtifact = 'cross_page_artifact';
    case InvalidArtifact = 'invalid_artifact';
    case PublicIdentityMismatch = 'public_identity_mismatch';
    case RuntimeUnavailable = 'runtime_unavailable';
    case ReadFailed = 'read_failed';

    public function requiresOperatorAttentionForManagedPage(): bool
    {
        return !in_array($this, [self::Ready, self::Unpublished], true);
    }
}
