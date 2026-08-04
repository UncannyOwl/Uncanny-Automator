<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Controls\Handlers;

use UncannyPageBuilder\Application\Controls\ControlHandlerInterface;
use UncannyPageBuilder\Application\Controls\ControlInvokeRequest;
use UncannyPageBuilder\Application\Controls\ControlInvokeResult;
use UncannyPageBuilder\Application\Controls\PageDetailsPortInterface;

/**
 * Saves the working page title and slug without changing public WordPress state.
 */
final class PageDetailsHandler implements ControlHandlerInterface
{
    public function __construct(
        private readonly PageDetailsPortInterface $pageDetails,
    ) {}

    public function __invoke(ControlInvokeRequest $request): ControlInvokeResult
    {
        if ($request->pageId() <= 0) {
            throw new \InvalidArgumentException('page_id is required.');
        }

        $value = $request->value();
        if (!is_array($value) || !array_key_exists('title', $value) || !array_key_exists('slug', $value)) {
            throw new \InvalidArgumentException('title and slug are required.');
        }

        if (!is_string($value['title']) || !is_string($value['slug'])) {
            throw new \InvalidArgumentException('title and slug must be strings.');
        }

        $details = $this->pageDetails->update(
            $request->pageId(),
            $value['title'],
            $value['slug'],
            max(0, $request->userId()),
        );

        $page = ['id' => $details->pageId(), ...$details->toArray()];

        return ControlInvokeResult::success(
            controlId: $request->controlId(),
            message: 'Draft page details saved',
            data: ['page' => $page],
            editorStatePatch: [
                'page.title' => $details->title(),
                'page.slug' => $details->slug(),
                'page.permalink' => $details->permalink(),
                'page.permalink_is_live' => $details->permalinkIsLive(),
            ],
            controlPatches: [
                [
                    'id' => 'page.details',
                    'value' => $details->toArray(),
                ],
                [
                    'id' => 'page.title',
                    'value' => $details->title(),
                ],
            ],
        );
    }
}
