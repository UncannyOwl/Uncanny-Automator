<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Api;

use UncannyPageBuilder\Domain\ErrorMessage;
use UncannyPageBuilder\Domain\Exception\SectionValidationException;

final class ApiResponse
{
    private function __construct(
        private readonly array $data,
        private readonly int $status,
    ) {}

    // ── Success ────────────────────────────────

    public static function ok(array $data): self
    {
        return new self($data, 200);
    }

    public static function created(array $data): self
    {
        return new self($data, 201);
    }

    public static function noContent(): self
    {
        return new self([], 204);
    }

    public function data(): array
    {
        return $this->data;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function toResponse(): \WP_REST_Response
    {
        return new \WP_REST_Response($this->data, $this->status);
    }

    // ── Errors ─────────────────────────────────

    public static function error(ErrorMessage $error, array $extra = []): \WP_Error
    {
        return new \WP_Error(
            $error->name,
            $error->message(),
            array_merge(['status' => $error->httpStatus()], $extra),
        );
    }

    public static function validationError(SectionValidationException $e): \WP_Error
    {
        $error = match ($e->rule()) {
            'multiple_roots', 'no_root'    => ErrorMessage::ValidationMultipleRoots,
            'forbidden_tag',
            'forbidden_attribute'          => ErrorMessage::ValidationForbiddenTag,
            'editable_key_duplicate'       => ErrorMessage::ValidationEditableDuplicate,
            'editable_invalid_type'        => ErrorMessage::ValidationEditableInvalidType,
            'dynamic_source_invalid',
            'dynamic_missing_template',
            'dynamic_extra_templates',
            'dynamic_missing_attribute',
            'dynamic_invalid_attribute'    => ErrorMessage::ValidationDynamicInvalid,
            'bind_key_invalid'             => ErrorMessage::ValidationBindKeyInvalid,
            'preserved_key_not_in_source',
            'preserved_key_missing'        => ErrorMessage::ValidationPreservedKeyMissing,
            default                        => ErrorMessage::ValidationManifestInvalid,
        };

        return self::error($error, [
            'rule'   => $e->rule(),
            'detail' => $e->getMessage(),
            ...$e->context(),
        ]);
    }
}
