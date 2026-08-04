<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Automator;

use UncannyPageBuilder\Domain\Section\SectionRepositoryInterface;
use UncannyPageBuilder\Infrastructure\WordPress\AdminCanvasPage;

/**
 * Declares the conversation starters for Page Builder surfaces.
 *
 * Automator owns the starter mechanism (the automator_mcp_conversation_starters
 * filter, validation, and the chat SDK contract); Page Builder owns what the
 * starters SAY on its own canvas. Because the declaration lives here, it can
 * read canvas state the URL alone cannot express: an empty page teaches
 * creation verbs, a populated page teaches refinement verbs, and a reusable
 * part speaks in reusable language. Each starter names a concept of the
 * mental model — page, section, headline, brand styles — so the vocabulary
 * installs itself through use.
 */
final class McpConversationStarters
{
    public function __construct(
        private readonly SectionRepositoryInterface $sectionRepository,
    ) {
    }

    /**
     * @param array<int, array{id: int, label: string, prompt: string}> $starters
     * @return array<int, array{id: int, label: string, prompt: string}>
     */
    public function filter(array $starters, string $url): array
    {
        $canvasId = $this->canvasIdFromUrl($url);
        if ($canvasId === null) {
            return $starters;
        }

        if (get_post_type($canvasId) === 'upb_global_part') {
            return $this->reusableStarters();
        }

        return $this->sectionRepository->hasSections($canvasId)
            ? $this->refinementStarters()
            : $this->creationStarters();
    }

    /**
     * @return array<int, array{id: int, label: string, prompt: string}>
     */
    private function creationStarters(): array
    {
        return self::starters([
            [
                _x('Build this page for me', 'Page Builder', 'uncanny-automator'),
                _x("Help me build this page. Ask me a couple of quick questions about what it should be about and who it's for, then create the sections.", 'Page Builder', 'uncanny-automator'),
            ],
            [
                _x('Start with a homepage layout', 'Page Builder', 'uncanny-automator'),
                _x('Create a homepage layout on this page: a hero, key benefits, social proof, and a call to action. Use placeholder copy I can refine.', 'Page Builder', 'uncanny-automator'),
            ],
            [
                _x('Make a pricing page', 'Page Builder', 'uncanny-automator'),
                _x('Turn this page into a pricing page with three plans. Ask me about the plans before you build.', 'Page Builder', 'uncanny-automator'),
            ],
            [
                _x('Make a contact page', 'Page Builder', 'uncanny-automator'),
                _x('Turn this page into a contact page with our details and a clear way to get in touch.', 'Page Builder', 'uncanny-automator'),
            ],
            [
                _x('Match my brand styles', 'Page Builder', 'uncanny-automator'),
                _x('When you build this page, make sure the colors, fonts, and spacing follow my brand styles.', 'Page Builder', 'uncanny-automator'),
            ],
        ]);
    }

    /**
     * @return array<int, array{id: int, label: string, prompt: string}>
     */
    private function refinementStarters(): array
    {
        return self::starters([
            [
                _x('Add a section', 'Page Builder', 'uncanny-automator'),
                _x('Add a new section to this page. Ask me what it should contain, then design it to match the rest of the page.', 'Page Builder', 'uncanny-automator'),
            ],
            [
                _x('Improve the writing', 'Page Builder', 'uncanny-automator'),
                _x('Review the text on this page and rewrite the headlines and copy to be clearer and more confident. Keep the design exactly as it is.', 'Page Builder', 'uncanny-automator'),
            ],
            [
                _x('Match my brand styles', 'Page Builder', 'uncanny-automator'),
                _x('Update this page so the colors, fonts, and spacing follow my brand styles.', 'Page Builder', 'uncanny-automator'),
            ],
            [
                _x('Redesign a section', 'Page Builder', 'uncanny-automator'),
                _x('Ask me which section of this page to redesign, then give it a fresh look while keeping its text and images.', 'Page Builder', 'uncanny-automator'),
            ],
            [
                _x('Make it work on mobile', 'Page Builder', 'uncanny-automator'),
                _x('Review this page at phone sizes and fix any sections that look cramped, overflow, or break the layout.', 'Page Builder', 'uncanny-automator'),
            ],
        ]);
    }

    /**
     * @return array<int, array{id: int, label: string, prompt: string}>
     */
    private function reusableStarters(): array
    {
        return self::starters([
            [
                _x('Build this reusable part for me', 'Page Builder', 'uncanny-automator'),
                _x('Help me build this reusable part. Ask me where it will be used across my site, then create it.', 'Page Builder', 'uncanny-automator'),
            ],
            [
                _x('Polish the design', 'Page Builder', 'uncanny-automator'),
                _x('Refine the design of this reusable part so it looks polished and follows my brand styles. Remember it appears on every page that uses it.', 'Page Builder', 'uncanny-automator'),
            ],
            [
                _x('Make it work on mobile', 'Page Builder', 'uncanny-automator'),
                _x('Review this reusable part at phone sizes and fix anything that looks cramped or breaks the layout.', 'Page Builder', 'uncanny-automator'),
            ],
        ]);
    }

    /**
     * @param array<int, array{0: string, 1: string}> $pairs
     * @return array<int, array{id: int, label: string, prompt: string}>
     */
    private static function starters(array $pairs): array
    {
        $rows = [];

        foreach ($pairs as $index => [$label, $prompt]) {
            $rows[] = [
                'id'     => $index + 1,
                'label'  => $label,
                'prompt' => $prompt,
            ];
        }

        return $rows;
    }

    private function canvasIdFromUrl(string $url): ?int
    {
        $query = wp_parse_url($url, PHP_URL_QUERY);
        if (!is_string($query) || $query === '') {
            return null;
        }

        parse_str($query, $params);
        if (!is_string($params['page'] ?? null) || $params['page'] !== AdminCanvasPage::PAGE_SLUG) {
            return null;
        }

        $canvasId = absint($params['canvas_id'] ?? 0);

        return $canvasId > 0 ? $canvasId : null;
    }
}
