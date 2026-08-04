<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\History;

use UncannyPageBuilder\Domain\History\OperationEntry;

/**
 * Dispatches only history operation types with complete working-source restore
 * paths. Adding an operation to history requires adding its inverse here.
 */
final class HistoryOperationRestorer
{
    public const PAGE_DETAILS_CHANGED = 'page.details_changed';

    /** @var string[] */
    private const SECTION_OPERATIONS = [
        'section.apply_proposal',
        'section.create',
        'section.delete',
        'section.patch_html',
        'section.reorder',
        'section.replace_binding_contract',
        'section.restore',
        'section.update',
    ];

    public function __construct(
        private readonly SectionHistoryRestorerInterface $sections,
        private readonly PageDetailsHistoryRestorerInterface $pageDetails,
    ) {}

    public function restore(OperationEntry $entry, bool $undo, int $actorUserId): HistoryRestoreResult
    {
        $target = $undo ? $entry->beforePayload() : $entry->afterPayload();
        $expected = $undo ? $entry->afterPayload() : $entry->beforePayload();

        if (in_array($entry->operation(), self::SECTION_OPERATIONS, true)) {
            return HistoryRestoreResult::sections(
                $this->sections->restoreFromHistory($entry->scopeId(), $target, $expected),
            );
        }

        if ($entry->operation() === self::PAGE_DETAILS_CHANGED) {
            return HistoryRestoreResult::pageDetails($this->pageDetails->restoreFromHistory(
                $entry->scopeId(),
                $this->pageDetailsPayload($target),
                $this->pageDetailsPayload($expected),
                max(0, $actorUserId),
            ));
        }

        throw new \LogicException(sprintf(
            'History operation "%s" has no working-source restore path.',
            $entry->operation(),
        ));
    }

    public static function supports(string $operation): bool
    {
        return self::isSectionOperation($operation)
            || self::isPageDetailsOperation($operation);
    }

    public static function isSectionOperation(string $operation): bool
    {
        return in_array($operation, self::SECTION_OPERATIONS, true);
    }

    public static function isPageDetailsOperation(string $operation): bool
    {
        return $operation === self::PAGE_DETAILS_CHANGED;
    }

    /**
     * Build paint-only data without mutating source or moving the history
     * cursor. Commit paths must reload the operation rather than trusting this
     * client-visible target.
     *
     * @return array{kind: string, target: array<string, mixed>}
     */
    public static function previewTarget(OperationEntry $entry, bool $undo): array
    {
        $target = $undo ? $entry->beforePayload() : $entry->afterPayload();

        if (self::isSectionOperation($entry->operation())) {
            return [
                'kind' => 'sections',
                'target' => ['sections' => array_values($target)],
            ];
        }

        if (self::isPageDetailsOperation($entry->operation())) {
            return [
                'kind' => 'page_details',
                'target' => self::pageDetailsPayload($target),
            ];
        }

        throw new \LogicException(sprintf(
            'History operation "%s" has no preview path.',
            $entry->operation(),
        ));
    }

    /**
     * @param array<int, array<string, mixed>> $payload
     * @return array{title: string, slug: string}
     */
    private static function pageDetailsPayload(array $payload): array
    {
        $details = $payload[0] ?? null;
        if (!is_array($details) || !is_string($details['title'] ?? null) || !is_string($details['slug'] ?? null)) {
            throw new \UnexpectedValueException('Page-details history payload is invalid.');
        }

        return [
            'title' => $details['title'],
            'slug' => $details['slug'],
        ];
    }
}
