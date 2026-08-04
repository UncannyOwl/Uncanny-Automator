<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Controls;

final class ControlInvokeResult
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $editorStatePatch
     * @param array<int, array<string, mixed>> $controlPatches
     * @param array<string, mixed>|null $layoutPatch
     */
    public function __construct(
        private readonly bool $success,
        private readonly string $controlId,
        private readonly ?string $message = null,
        private readonly array $data = [],
        private readonly array $editorStatePatch = [],
        private readonly array $controlPatches = [],
        private readonly ?array $layoutPatch = null,
        private readonly bool $reload = false,
    ) {}

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $editorStatePatch
     * @param array<int, array<string, mixed>> $controlPatches
     * @param array<string, mixed>|null $layoutPatch
     */
    public static function success(
        string $controlId,
        ?string $message = null,
        array $data = [],
        array $editorStatePatch = [],
        array $controlPatches = [],
        ?array $layoutPatch = null,
        bool $reload = false,
    ): self {
        return new self(true, $controlId, $message, $data, $editorStatePatch, $controlPatches, $layoutPatch, $reload);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'success'      => $this->success,
            'control_id'   => $this->controlId,
            'message'      => $this->message,
            'data'         => $this->recordForJson($this->data),
            'controls'     => [
                'replace' => [],
                'patch'   => $this->controlPatches,
            ],
            'editor_state' => [
                'patch' => $this->recordForJson($this->editorStatePatch),
            ],
            'layout'       => $this->layoutPatch,
            'reload'       => $this->reload,
        ];
    }

    /**
     * Preserve object-map JSON shape for empty associative payloads.
     *
     * PHP encodes [] as a JSON array, but the browser contract treats data and
     * editor_state.patch as records. Returning stdClass for the empty case keeps
     * the wire shape as {} without changing non-empty associative arrays.
     */
    private function recordForJson(array $record): array|\stdClass
    {
        return $record === [] ? new \stdClass() : $record;
    }
}
