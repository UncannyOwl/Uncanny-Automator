<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Settings;

use UncannyPageBuilder\Domain\Personalization\SiteCustomInstructions;

/**
 * Design-direction subsection of the sitewide settings aggregate.
 */
final class DesignDirectionSettings
{
    public function __construct(
        private readonly SiteCustomInstructions $customInstructions,
    ) {}

    public static function defaults(): self
    {
        return new self(SiteCustomInstructions::fromString(''));
    }

    public static function fromArray(mixed $data): self
    {
        if (!is_array($data)) {
            return self::defaults();
        }

        return new self(
            SiteCustomInstructions::fromString((string) ($data['custom_instructions'] ?? '')),
        );
    }

    /**
     * @return array{custom_instructions: string}
     */
    public function toArray(): array
    {
        return [
            'custom_instructions' => $this->customInstructions->text(),
        ];
    }

    public function customInstructions(): SiteCustomInstructions
    {
        return $this->customInstructions;
    }

    public function withCustomInstructions(SiteCustomInstructions $customInstructions): self
    {
        return new self($customInstructions);
    }

    public function clearCustomInstructions(): self
    {
        return new self(SiteCustomInstructions::fromString(''));
    }
}
