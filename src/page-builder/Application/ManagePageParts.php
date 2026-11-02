<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application;

use UncannyPageBuilder\Application\Canvas\CanvasPortInterface;
use UncannyPageBuilder\Application\Concurrency\GlobalSourceMutation;
use UncannyPageBuilder\Application\Concurrency\PageSourceMutation;
use UncannyPageBuilder\Application\Publishing\WorkingCanvasRefresherInterface;
use UncannyPageBuilder\Domain\Canvas\CanvasKind;
use UncannyPageBuilder\Domain\Concurrency\SourceGenerationStoreInterface;
use UncannyPageBuilder\Domain\Exception\CanvasNotFoundException;
use UncannyPageBuilder\Domain\Exception\StaleSourceGenerationException;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartRepositoryInterface;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartType;
use UncannyPageBuilder\Domain\GlobalPart\PageGlobalPartSelection;

/** Reads and changes explicit shell selections without publication. */
final class ManagePageParts
{
    public function __construct(
        private readonly CanvasPortInterface $canvases,
        private readonly PageGlobalPartSelectionService $selections,
        private readonly GlobalPartDefaultsService $defaults,
        private readonly GlobalPartRepositoryInterface $parts,
        private readonly SourceGenerationStoreInterface $generations,
        private readonly PageSourceMutation $pageSource,
        private readonly GlobalSourceMutation $globalSource,
        private readonly ?WorkingCanvasRefresherInterface $workingCanvas = null,
    ) {}

    public function read(string $scope, int $pageId = 0): array
    {
        $this->assertScope($scope, $pageId);
        $generation = $this->generation($scope, $pageId);
        $globalGeneration = $this->generations->globalGeneration();
        $canvas = $scope === 'page' ? $this->page($pageId) : null;
        $selection = $scope === 'page' ? $this->selections->selectionForPage($pageId) : PageGlobalPartSelection::siteDefaults();
        $defaults = [];
        $overrides = [];
        $effective = [];
        foreach ([GlobalPartType::Header, GlobalPartType::Footer] as $slot) {
            $defaults[$slot->value] = $this->defaults->getDefaultId($slot);
            $override = $slot === GlobalPartType::Header ? $selection->headerOverrideId() : $selection->footerOverrideId();
            $overrides[$slot->value] = $override;
            $effective[$slot->value] = $this->effective($slot, $override, $defaults[$slot->value]);
        }
        $this->assertGeneration($scope, $pageId, $generation);
        if ($globalGeneration !== $this->generations->globalGeneration()) {
            throw new StaleSourceGenerationException('global', (int) $globalGeneration, (int) $this->generations->globalGeneration());
        }
        return [
            'scope' => $scope,
            'page_id' => $pageId,
            'expected_generation' => $generation,
            'global_generation' => $globalGeneration,
            'page_mode' => $canvas?->shellMode()?->value,
            'site_defaults' => $defaults,
            'overrides' => $overrides,
            'effective' => $effective,
        ];
    }

    public function assign(string $scope, int $pageId, GlobalPartType $slot, string $selection, ?int $globalPartId, int $expectedGeneration): array
    {
        $this->assertScope($scope, $pageId);
        if (!in_array($slot, [GlobalPartType::Header, GlobalPartType::Footer], true)) {
            throw new \InvalidArgumentException('Only header and footer slots can be assigned.');
        }
        if (!in_array($selection, ['part', 'inherit', 'none'], true)) {
            throw new \InvalidArgumentException('Use selection=part, inherit, or none.');
        }
        if ($scope === 'site_defaults' && $selection === 'none') {
            throw new \InvalidArgumentException('Site defaults cannot suppress fallback parts. Use page selection=none, or inherit to clear an explicit site default.');
        }
        if (($selection === 'part' && ($globalPartId === null || $globalPartId <= 0)) || ($selection !== 'part' && $globalPartId !== null)) {
            throw new \InvalidArgumentException('Provide global_part_id only for selection=part.');
        }
        $this->assertGeneration($scope, $pageId, $expectedGeneration);
        $refreshWarning = false;
        if ($scope === 'page') {
            $this->page($pageId);
        }
        if ($selection === 'part') {
            $this->assertPart($slot, (int) $globalPartId);
        }
        $next = match ($selection) {
            'part' => $globalPartId,
            'none' => -1,
            default => null,
        };
        if ($scope === 'page') {
            $current = $this->selections->selectionForPage($pageId);
            $target = new PageGlobalPartSelection(
                $slot === GlobalPartType::Header ? $next : $current->headerOverrideId(),
                $slot === GlobalPartType::Footer ? $next : $current->footerOverrideId(),
            );
            $this->assertGeneration($scope, $pageId, $expectedGeneration);
            if (!$current->equals($target)) {
                $this->pageSource->runExpected($pageId, $expectedGeneration, function () use ($pageId, $slot, $selection, $globalPartId, $target): void {
                    if ($selection === 'part') {
                        $this->assertPart($slot, (int) $globalPartId);
                    }
                    $this->selections->saveForPage($pageId, $target);
                });
                try {
                    $this->workingCanvas?->refresh($pageId);
                } catch (\Throwable) {
                    // Derived refresh cannot turn a committed selection into a failed write.
                    $refreshWarning = true;
                }
            }
        } else {
            $current = $this->defaults->getDefaultId($slot);
            $this->assertGeneration($scope, $pageId, $expectedGeneration);
            if ($current !== $next) {
                $this->globalSource->runExpected($expectedGeneration, function () use ($slot, $next): void {
                    if (!$this->defaults->setDefaultId($slot, $next)) {
                        throw new \InvalidArgumentException('The selected part is not active or has the wrong type.');
                    }
                });
            }
        }
        return $this->read($scope, $pageId) + ['refresh_warning' => $refreshWarning];
    }

    private function assertScope(string $scope, int $pageId): void
    {
        if (!in_array($scope, ['page', 'site_defaults'], true) || ($scope === 'page' ? $pageId <= 0 : $pageId !== 0)) {
            throw new \InvalidArgumentException('Use scope=page with an explicit page_id, or scope=site_defaults without page_id.');
        }
    }

    private function page(int $pageId): \UncannyPageBuilder\Domain\Canvas\Canvas
    {
        $canvas = $this->canvases->find($pageId);
        if ($canvas === null || $canvas->kind() !== CanvasKind::Page || !$canvas->owned() || $canvas->status() === 'trash') {
            throw new CanvasNotFoundException((int) $pageId);
        }
        return $canvas;
    }

    private function generation(string $scope, int $pageId): int
    {
        return $scope === 'page' ? $this->generations->pageGeneration($pageId) : $this->generations->globalGeneration();
    }

    private function assertGeneration(string $scope, int $pageId, int $expected): void
    {
        $actual = $this->generation($scope, $pageId);
        if ($expected < 0 || $actual !== $expected) {
            throw new StaleSourceGenerationException($scope === 'page' ? 'page' : 'global', (int) $expected, (int) $actual);
        }
    }

    private function assertPart(GlobalPartType $slot, int $id): void
    {
        if (!$this->defaults->isAssignablePartId($slot, $id)) {
            throw new \InvalidArgumentException('The selected part is not active or has the wrong type.');
        }
    }

    private function effective(GlobalPartType $slot, ?int $override, ?int $default): ?array
    {
        if ($override === -1) {
            return null;
        }
        foreach ([$override, $default] as $id) {
            if ($id !== null && $id > 0) {
                $part = $this->parts->findById($id);
                if ($part !== null && ($part['type'] ?? null) === $slot->value) {
                    return $this->summary($part, $slot);
                }
            }
        }
        $part = $this->parts->findByType($slot);
        return $part === null ? null : $this->summary($part, $slot);
    }

    private function summary(array $part, GlobalPartType $slot): array
    {
        return ['id' => (int) $part['post_id'], 'type' => $slot->value, 'title' => (string) ($part['title'] ?? ''), 'status' => 'publish'];
    }
}
