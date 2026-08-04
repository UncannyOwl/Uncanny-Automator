# page_modified_date — Page Modified Date

Render the last modified date of the current page/post.

## Rendered HTML

This binding renders **plain text only** — no elements are added. The text
replaces the region element's children, so CSS targets the element you
authored:

```html
<!-- You author (or the mask form <!-- upb:bindings:dynamic_data:page_modified_date -->): -->
<span data-ai-dynamic="page_modified_date" class="your-class"></span>

<!-- Renders as: -->
<span data-ai-dynamic="page_modified_date" class="your-class">The resolved text value</span>
```

Style it by styling your own element (`.your-class`). There are no
binding-generated classes to target.

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `page_modified_date` | Marks this as a page_modified_date region |

## Bind Keys

**None.** Self-rendering binding.

## Example

```html
<span data-ai-dynamic="page_modified_date">Placeholder</span>
```

## Notes

- Uses get_the_modified_date(). Output format depends on WordPress date settings.
