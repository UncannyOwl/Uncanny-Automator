# page_date — Page Date

Render the current page/post publish date.

## Rendered HTML

This binding renders **plain text only** — no elements are added. The text
replaces the region element's children, so CSS targets the element you
authored:

```html
<!-- You author (or the mask form <!-- upb:bindings:dynamic_data:page_date -->): -->
<span data-ai-dynamic="page_date" class="your-class"></span>

<!-- Renders as: -->
<span data-ai-dynamic="page_date" class="your-class">The resolved text value</span>
```

Style it by styling your own element (`.your-class`). There are no
binding-generated classes to target.

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `page_date` | Marks this as a page_date region |

## Bind Keys

**None.** This is a self-rendering binding. The content is replaced automatically.

## Example

```html
<span data-ai-dynamic="page_date">Placeholder</span>
```

## Notes

- Uses get_the_date(). Must be used within The Loop or on a page/post context.
