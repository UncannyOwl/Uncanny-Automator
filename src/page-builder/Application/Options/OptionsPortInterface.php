<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Options;

use UncannyPageBuilder\Domain\Options\Enum;

/**
 * Application-facing port for plugin-owned site options.
 *
 * The port stays generic on purpose: the options domain already has a canonical
 * key inventory, but the save/load use cases will be introduced separately.
 * For now this boundary only describes the supported value shapes.
 */
interface OptionsPortInterface
{
    // ── Option read ─────────────────────────────────────────────────────────

    public function load(
        Enum $option,
        array|string|int|float|bool|null $default = null,
    ): array|string|int|float|bool|null;

    // ── Option write ────────────────────────────────────────────────────────

    public function save(Enum $option, array|string|int|float|bool $value): void;

    public function delete(Enum $option): void;
}
