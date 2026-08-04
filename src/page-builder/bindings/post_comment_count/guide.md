# post_comment_count — Post Comment Count

Comment count for the current post.

## Rendered HTML

This binding renders **plain text only** — no elements are added. The text
replaces the region element's children, so CSS targets the element you
authored:

```html
<!-- You author (or the mask form <!-- upb:bindings:dynamic_data:post_comment_count -->): -->
<span data-ai-dynamic="post_comment_count" class="your-class"></span>

<!-- Renders as: -->
<span data-ai-dynamic="post_comment_count" class="your-class">The resolved text value</span>
```

Style it by styling your own element (`.your-class`). There are no
binding-generated classes to target.

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `post_comment_count` | Marks this as a post_comment_count region |

## Bind Keys

**None.** Self-rendering binding.

## Example

```html
<span data-ai-dynamic="post_comment_count">0</span>
```

## Notes

- Returns the approved comment count via get_comments_number(). Returns "0" when comments are disabled or none exist.
