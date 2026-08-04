# copyright_year — Copyright Year

Render the current year for copyright notices.

## Rendered HTML

This binding renders **plain text only** — no elements are added. The text
replaces the region element's children, so CSS targets the element you
authored:

```html
<!-- You author (or the mask form <!-- upb:bindings:dynamic_data:copyright_year -->): -->
<span data-ai-dynamic="copyright_year" class="your-class"></span>

<!-- Renders as: -->
<span data-ai-dynamic="copyright_year" class="your-class">The resolved text value</span>
```

Style it by styling your own element (`.your-class`). There are no
binding-generated classes to target.

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `copyright_year` | Marks this as a copyright_year region |

## Bind Keys

**None.** This is a self-rendering binding. The content is replaced automatically.

## Example

```html
<span data-ai-dynamic="copyright_year">Placeholder</span>
```

## Notes

- Uses `gmdate('Y')`. Ideal for footer copyright lines that should always show the current year.
