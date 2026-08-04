# post_reading_time — Post Reading Time

Estimated reading time for the current post.

## Rendered HTML

This binding renders **plain text only** — no elements are added. The text
replaces the region element's children, so CSS targets the element you
authored:

```html
<!-- You author (or the mask form <!-- upb:bindings:dynamic_data:post_reading_time -->): -->
<span data-ai-dynamic="post_reading_time" class="your-class"></span>

<!-- Renders as: -->
<span data-ai-dynamic="post_reading_time" class="your-class">The resolved text value</span>
```

Style it by styling your own element (`.your-class`). There are no
binding-generated classes to target.

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `post_reading_time` | Marks this as a post_reading_time region |

## Bind Keys

**None.** Self-rendering binding.

## Example

```html
<span data-ai-dynamic="post_reading_time">5 min read</span>
```

## Notes

- Calculates reading time based on word count at ~200 words per minute. Minimum is "1 min read".
