<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Api\AgentPageController\PartRead;

use UncannyPageBuilder\Domain\Binding\BindingRegistry;
use UncannyPageBuilder\Domain\Section\Section;
use UncannyPageBuilder\Infrastructure\Section\DynamicRegionToken;

/**
 * Presents canonical section source in the agent-visible mask space.
 *
 * Dynamic source projection is kept here so every read surface exposes the
 * same representation that source-patch operations expect.
 */
final class PartSourcePresenter
{
    /** @var list<string>|null */
    private ?array $maskableBindingIds = null;

    public function __construct(
        private readonly ?BindingRegistry $bindingRegistry = null,
    ) {}

    public function maskedHtml(Section $section): string
    {
        return $this->maskForAgent($section->content()->html());
    }

    /**
     * @return list<string>
     */
    public function detailLines(Section $section): array
    {
        $lines = [
            'SOURCE',
            '--- HTML ---',
            $this->maskedHtml($section),
        ];

        $this->appendDynamicRegionAdvisory($lines, $section->content()->html());

        return [
            ...$lines,
            '',
            '--- CSS ---',
            $section->content()->css(),
            '',
            'NEXT STEP',
            'Use edit_part mode=source_patch or mode=source_replace.',
            '',
        ];
    }

    private function maskForAgent(string $html): string
    {
        $this->maskableBindingIds ??= $this->bindingRegistry?->fullyProjectedBindingIds() ?? [];

        return DynamicRegionToken::encodeForCodeEditor(
            $html,
            $this->maskableBindingIds,
            payloadMasks: false,
        );
    }

    /**
     * @param list<string> $lines
     */
    private function appendDynamicRegionAdvisory(array &$lines, string $html): void
    {
        $bindingIds = DynamicRegionToken::bindingIdsIn($html);
        if ($bindingIds === []) {
            return;
        }

        $lines[] = 'DYNAMIC_REGIONS (system-controlled — rendered output is projected by Uncanny Page Builder):';

        foreach ($bindingIds as $bindingId) {
            $contract = $this->bindingRegistry?->regionContractFor($bindingId);
            $lines[] = sprintf(
                '- %s: %s. You may edit the region element and its attributes per the binding guide, but never author content meant to be final inside it.',
                $bindingId,
                $contract === null
                    ? 'contract unknown'
                    : sprintf('replaces=%s template=%s', $contract->replaces->value, $contract->template->value),
            );
        }

        $lines[] = '';
    }
}
