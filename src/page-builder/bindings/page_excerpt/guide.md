# page_excerpt — Page Excerpt

Render the current page/post excerpt.

## Rendered HTML

This binding renders **plain text only** — no elements are added. The text
replaces the region element's children, so CSS targets the element you
authored:

```html
<!-- You author (or the mask form <!-- upb:bindings:dynamic_data:page_excerpt -->): -->
<span data-ai-dynamic="page_excerpt" class="your-class"></span>

<!-- Renders as: -->
<span data-ai-dynamic="page_excerpt" class="your-class">The resolved text value</span>
```

Style it by styling your own element (`.your-class`). There are no
binding-generated classes to target.

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `page_excerpt` | Marks this as a page_excerpt region |

## Bind Keys

**None.** Self-rendering binding.

## Example

```html
<span data-ai-dynamic="page_excerpt">Placeholder</span>
```

## Notes

- Uses get_the_excerpt(). Returns the manual excerpt or an auto-generated one.
