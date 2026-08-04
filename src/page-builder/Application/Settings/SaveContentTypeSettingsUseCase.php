<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Settings;

use UncannyPageBuilder\Domain\Settings\ContentTypeSettings;
use UncannyPageBuilder\Domain\Settings\Settings;
use UncannyPageBuilder\Domain\Settings\SettingsRepositoryInterface;

final class SaveContentTypeSettingsUseCase
{
    public function __construct(
        private readonly SettingsRepositoryInterface $settings,
        private readonly ValidateContentTypeSelectionUseCase $validateSelection,
    ) {}

    /**
     * @param list<mixed> $submittedSlugs
     * @param list<mixed> $presentedSlugs
     */
    public function __invoke(array $submittedSlugs, array $presentedSlugs): Settings
    {
        ($this->validateSelection)($submittedSlugs, $presentedSlugs);

        $submitted = new ContentTypeSettings($submittedSlugs);
        $presented = new ContentTypeSettings($presentedSlugs);

        return $this->settings->mutate(
            static function (Settings $current) use ($submitted, $presented): Settings {
                /*
                 * The rendered form is the user's decision boundary. WordPress
                 * registration may change between rendering and submission,
                 * so saving must not reinterpret that decision using newer
                 * host facts. Selections omitted from the rendered form stay
                 * dormant until they can be presented again.
                 */
                $enabled = array_values(array_filter(
                    $current->contentTypes()->enabledSlugs(),
                    static fn (string $slug): bool => !$presented->isEnabled($slug),
                ));

                foreach ($presented->enabledSlugs() as $slug) {
                    if ($submitted->isEnabled($slug)) {
                        $enabled[] = $slug;
                    }
                }

                return $current->withContentTypes(new ContentTypeSettings($enabled));
            },
        );
    }
}
