<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Editing;

/** Closed vocabulary for text block changes. Structural and interactive hosts stay intact. */
enum RichTextBlockTag: string
{
    case Paragraph = 'p';
    case Heading1 = 'h1';
    case Heading2 = 'h2';
    case Heading3 = 'h3';
    case Heading4 = 'h4';
    case Heading5 = 'h5';
    case Heading6 = 'h6';
    case Preformatted = 'pre';
    case Quote = 'blockquote';
    case Container = 'div';

    public function allowsBlockChildren(): bool
    {
        return $this === self::Container || $this === self::Quote;
    }
}
