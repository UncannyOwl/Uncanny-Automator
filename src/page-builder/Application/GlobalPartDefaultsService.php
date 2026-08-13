<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application;

use UncannyPageBuilder\Application\Concurrency\GlobalSourceMutation;
use UncannyPageBuilder\Application\Observability\FailureReporterInterface;
use UncannyPageBuilder\Application\Publishing\WorkingCanvasRefreshScheduler;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartRepositoryInterface;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartType;
use UncannyPageBuilder\Domain\Settings\PageLayoutSettings;
use UncannyPageBuilder\Domain\Settings\Settings;
use UncannyPageBuilder\Domain\Settings\SettingsRepositoryInterface;

final class GlobalPartDefaultsService implements GlobalPartDefaultsResolverInterface
{
    public function __construct(
        private readonly GlobalPartRepositoryInterface $repository,
        private readonly SettingsRepositoryInterface $settingsRepository,
        private readonly GlobalSourceMutation $globalSource,
        private readonly ?WorkingCanvasRefreshScheduler $workingCanvasRefreshes = null,
        private readonly ?FailureReporterInterface $failureReporter = null,
    ) {}

    public function getDefaultId(GlobalPartType $type): ?int
    {
        $settings = $this->settingsRepository->load()->pageLayout();

        return match ($type) {
            GlobalPartType::Header => $settings->defaultHeaderId(),
            GlobalPartType::Footer => $settings->defaultFooterId(),
            default => null,
        };
    }

    /**
     * Persist the site default for a part type.
     *
     * Null clears the default explicitly. A non-null ID must pass
     * isAssignablePartId(); an unknown, unpublished, or wrong-type ID is
     * rejected without touching the stored default, so a stale form post
     * cannot wipe a good selection.
     */
    public function setDefaultId(GlobalPartType $type, ?int $postId): bool
    {
        return $this->setDefaultIdWithRefreshStatus($type, $postId)['accepted'];
    }

    /** @return array{accepted: bool, refresh_queued: bool} */
    public function setDefaultIdWithRefreshStatus(GlobalPartType $type, ?int $postId): array
    {
        if (!in_array($type, [GlobalPartType::Header, GlobalPartType::Footer], true)) {
            return ['accepted' => false, 'refresh_queued' => true];
        }

        if ($postId !== null && !$this->isAssignablePartId($type, $postId)) {
            return ['accepted' => false, 'refresh_queued' => true];
        }

        if ($this->getDefaultId($type) === $postId) {
            return ['accepted' => true, 'refresh_queued' => true];
        }

        $changed = false;
        $write = function () use ($type, $postId, &$changed): Settings {
            return $this->settingsRepository->mutate(function (Settings $settings) use ($type, $postId, &$changed): Settings {
                $layout = $settings->pageLayout();
                $next = $type === GlobalPartType::Header
                    ? new PageLayoutSettings($postId, $layout->defaultFooterId())
                    : new PageLayoutSettings($layout->defaultHeaderId(), $postId);

                $changed = $this->layoutChanged($layout, $next, $type);

                return $settings->withPageLayout($next);
            });
        };

        $this->globalSource->run($write);

        if (!$changed || !$this->workingCanvasRefreshes instanceof WorkingCanvasRefreshScheduler) {
            return ['accepted' => true, 'refresh_queued' => true];
        }

        try {
            $this->workingCanvasRefreshes->enqueueAll();
        } catch (\Throwable $failure) {
            try {
                $this->failureReporter?->report(
                    'global part default',
                    $postId ?? 0,
                    'working_canvas.enqueue',
                    $failure,
                );
            } catch (\Throwable) {
                // A report failure cannot change the completed setting result.
            }
            return ['accepted' => true, 'refresh_queued' => false];
        }

        return ['accepted' => true, 'refresh_queued' => true];
    }

    /**
     * Whether the ID may be persisted as a default or per-page selection.
     *
     * The repository only returns published parts, so this enforces
     * existence, publish status, and type in one check.
     */
    public function isAssignablePartId(GlobalPartType $type, int $postId): bool
    {
        if ($postId <= 0) {
            return false;
        }

        $part = $this->repository->findById($postId);

        return $part !== null && ($part['type'] ?? null) === $type->value;
    }

    public function resolveForType(GlobalPartType $type): ?array
    {
        $defaultId = $this->getDefaultId($type);
        if ($defaultId !== null) {
            $part = $this->repository->findById($defaultId);
            if ($part !== null && ($part['type'] ?? null) === $type->value) {
                return $part;
            }

            $this->setDefaultId($type, null);
        }

        return $this->repository->findByType($type);
    }

    /**
     * Resolve only the explicitly assigned default for agent/runtime flows that
     * must never fall back to the first matching reusable.
     */
    public function resolveAssignedForType(GlobalPartType $type): ?array
    {
        $defaultId = $this->getDefaultId($type);
        if ($defaultId === null) {
            return null;
        }

        $part = $this->repository->findById($defaultId);
        if ($part !== null && ($part['type'] ?? null) === $type->value) {
            return $part;
        }

        $this->setDefaultId($type, null);

        return null;
    }

    /**
     * @return array<int, array{post_id: int, type: string, title: string, sections: array, css: string}>
     */
    public function listByType(GlobalPartType $type): array
    {
        return $this->repository->findAllByType($type);
    }

    private function layoutChanged(PageLayoutSettings $before, PageLayoutSettings $after, GlobalPartType $type): bool
    {
        return match ($type) {
            GlobalPartType::Header => $before->defaultHeaderId() !== $after->defaultHeaderId(),
            GlobalPartType::Footer => $before->defaultFooterId() !== $after->defaultFooterId(),
            default => false,
        };
    }
}
