# total_posts_count — Total Posts Count

Total number of published posts on the site.

## Rendered HTML

This binding renders **plain text only** — no elements are added. The text
replaces the region element's children, so CSS targets the element you
authored:

```html
<!-- You author (or the mask form <!-- upb:bindings:dynamic_data:total_posts_count -->): -->
<span data-ai-dynamic="total_posts_count" class="your-class"></span>

<!-- Renders as: -->
<span data-ai-dynamic="total_posts_count" class="your-class">The resolved text value</span>
```

Style it by styling your own element (`.your-class`). There are no
binding-generated classes to target.

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `total_posts_count` | Marks this as a total_posts_count region |

## Bind Keys

**None.** Self-rendering binding.

## Example

```html
<span data-ai-dynamic="total_posts_count">0</span>
```

## Notes

- Uses wp_count_posts() to return the count of published posts. Useful for site statistics or social proof sections.
