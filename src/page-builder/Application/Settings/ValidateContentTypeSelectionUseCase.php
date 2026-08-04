<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Settings;

use UncannyPageBuilder\Domain\ContentType\PageBuilderDisplayPolicy;

/**
 * Validates the complete settings-form decision before persistence.
 *
 * The browser is not the trust boundary. A forged or stale request must not
 * enable a plugin-owned custom post type that the settings page no longer
 * offers, and a submitted selection must come from the rendered form snapshot.
 */
final class ValidateContentTypeSelectionUseCase
{
    public function __construct(
        private readonly PageBuilderDisplayPolicy $displayPolicy,
    ) {}

    /**
     * @param list<mixed> $submittedSlugs
     * @param list<mixed> $presentedSlugs
     *
     * @throws InvalidContentTypeSelectionException
     */
    public function __invoke(array $submittedSlugs, array $presentedSlugs): void
    {
        $submitted = $this->supportedSlugs($submittedSlugs);
        $presented = $this->supportedSlugs($presentedSlugs);

        foreach ($submitted as $slug) {
            if (!in_array($slug, $presented, true)) {
                throw new InvalidContentTypeSelectionException(
                    'A selected post type was not part of the presented settings form.',
                );
            }
        }
    }

    /**
     * @param list<mixed> $slugs
     * @return list<string>
     *
     * @throws InvalidContentTypeSelectionException
     */
    private function supportedSlugs(array $slugs): array
    {
        $supported = [];

        foreach ($slugs as $slug) {
            if (!is_string($slug)) {
                throw new InvalidContentTypeSelectionException('Post type selections must be strings.');
            }

            $slug = strtolower(trim($slug));
            if ($slug === '' || preg_match('/^[a-z0-9_-]+$/', $slug) !== 1) {
                throw new InvalidContentTypeSelectionException('A post type selection was malformed.');
            }

            if (!$this->displayPolicy->supportsSlug($slug)) {
                throw new InvalidContentTypeSelectionException(
                    sprintf('The post type "%s" is not supported by Page Builder.', $slug),
                );
            }

            $supported[$slug] = true;
        }

        $normalized = array_keys($supported);
        sort($normalized, SORT_STRING);

        return array_values($normalized);
    }
}
