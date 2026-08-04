# page_author — Page Author

Render the author name of the current page/post.

## Rendered HTML

This binding renders **plain text only** — no elements are added. The text
replaces the region element's children, so CSS targets the element you
authored:

```html
<!-- You author (or the mask form <!-- upb:bindings:dynamic_data:page_author -->): -->
<span data-ai-dynamic="page_author" class="your-class"></span>

<!-- Renders as: -->
<span data-ai-dynamic="page_author" class="your-class">The resolved text value</span>
```

Style it by styling your own element (`.your-class`). There are no
binding-generated classes to target.

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `page_author` | Marks this as a page_author region |

## Bind Keys

**None.** Self-rendering binding.

## Example

```html
<span data-ai-dynamic="page_author">Placeholder</span>
```

## Notes

- Uses get_the_author(). Returns the display name of the post author.
