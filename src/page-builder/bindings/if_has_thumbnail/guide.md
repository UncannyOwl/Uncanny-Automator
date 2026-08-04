# if_has_thumbnail — Conditional: If Has Featured Image

Shows its children only when the current post has a featured image set. If the post has no featured image, the wrapper produces no output.

## Rendered HTML

This binding renders **no markup of its own**. When the condition is true,
the wrapper element is removed and your children render in its place; when
false, the wrapper and everything inside it are removed entirely:

```html
<!-- You author: -->
<div data-ai-dynamic="if_has_thumbnail"><p class="greeting">Hello!</p></div>

<!-- Condition true — renders as: -->
<p class="greeting">Hello!</p>

<!-- Condition false — renders as: nothing -->
```

IMPORTANT for CSS: the wrapper `<div>` does NOT exist in rendered output —
never target it. Style the children directly (`.greeting`).

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `if_has_thumbnail` | Marks this as a featured image conditional wrapper |

## How It Works

- The wrapper checks `has_post_thumbnail()` to determine if the current post has a featured image.
- If the post has a featured image, the wrapper and its children render normally.
- If the post has no featured image, the wrapper produces no output.

## Example

```html
<div data-ai-dynamic="if_has_thumbnail">
  <div class="featured-image-container">
    <p>This post has a featured image displayed above.</p>
  </div>
</div>
```

## Usage Notes

- Use this to conditionally show image containers, captions, or image-dependent layout sections.
- Prevents empty image placeholders from appearing when no featured image is set.
- Works on both posts and pages — any post type that supports featured images.
