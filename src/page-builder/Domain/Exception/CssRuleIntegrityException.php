<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Exception;

/**
 * Raised before persistence when a source write would rewrite CSS or stored
 * source outside the requested change.
 */
final class CssRuleIntegrityException extends \RuntimeException
{
    public const SANITIZATION = 'sanitization';
    public const MALFORMED_SOURCE = 'malformed_source';
    public const AMBIGUOUS_COMMENT = 'ambiguous_comment';
    public const AMBIGUOUS_DECLARATION_BOUNDARY = 'ambiguous_declaration_boundary';
    public const MULTIPLE_GLOBAL_PART_SOURCE_ROWS = 'multiple_global_part_source_rows';
    public const UNPRESERVABLE_GLOBAL_PART_SOURCE_ROWS = 'unpreservable_global_part_source_rows';

    public function __construct(
        string $message = 'CSS sanitization would change existing stylesheet content outside the requested edit. Nothing was saved.',
        private readonly string $reason = self::SANITIZATION,
    ) {
        parent::__construct($message);
    }

    public static function malformedSource(): self
    {
        return new self(
            'The existing CSS is structurally incomplete, so the requested declaration cannot be located safely. Nothing was saved.',
            self::MALFORMED_SOURCE,
        );
    }

    public static function ambiguousComment(): self
    {
        return new self(
            'The target declaration contains an inline CSS comment that cannot be moved or removed safely. Nothing was saved.',
            self::AMBIGUOUS_COMMENT,
        );
    }

    public static function ambiguousDeclarationBoundary(): self
    {
        return new self(
            'The target declaration contains another top-level property boundary, usually because a semicolon is missing. Replacing it could remove unrelated CSS. Nothing was saved.',
            self::AMBIGUOUS_DECLARATION_BOUNDARY,
        );
    }

    public static function multipleGlobalPartSourceRows(): self
    {
        return new self(
            'This legacy global part contains multiple source rows, so this write could remove unrelated source and CSS. Nothing was saved.',
            self::MULTIPLE_GLOBAL_PART_SOURCE_ROWS,
        );
    }

    public static function unpreservableGlobalPartSourceRows(): self
    {
        return new self(
            'The stored global-part source rows cannot be preserved exactly by this write. Nothing was saved.',
            self::UNPRESERVABLE_GLOBAL_PART_SOURCE_ROWS,
        );
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
