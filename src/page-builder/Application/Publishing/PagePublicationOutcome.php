<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Publishing;

enum PagePublicationOutcome: string
{
    case Published = 'published';
    case StaleSource = 'stale_source';
    case StaticSafetyFailed = 'static_safety_failed';
    case NothingToPublish = 'nothing_to_publish';
    case SlugConflict = 'slug_conflict';
    case NotAuthorized = 'not_authorized';
    case ArtifactPersistFailed = 'artifact_persist_failed';
    case PublicStateCommitFailed = 'public_state_commit_failed';
}
