# if_comments_open — Conditional: If Comments Open

Shows its children only when comments are open on the current post. If comments are closed, the wrapper produces no output.

## Rendered HTML

This binding renders **no markup of its own**. When the condition is true,
the wrapper element is removed and your children render in its place; when
false, the wrapper and everything inside it are removed entirely:

```html
<!-- You author: -->
<div data-ai-dynamic="if_comments_open"><p class="greeting">Hello!</p></div>

<!-- Condition true — renders as: -->
<p class="greeting">Hello!</p>

<!-- Condition false — renders as: nothing -->
```

IMPORTANT for CSS: the wrapper `<div>` does NOT exist in rendered output —
never target it. Style the children directly (`.greeting`).

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `if_comments_open` | Marks this as a comments-open conditional wrapper |

## How It Works

- The wrapper checks `comments_open()` to determine if comments are allowed on the current post.
- If comments are open, the wrapper and its children render normally.
- If comments are closed, the wrapper produces no output.

## Example

```html
<div data-ai-dynamic="if_comments_open">
  <div class="comment-prompt">
    <p>We would love to hear your thoughts. Leave a comment below!</p>
  </div>
</div>
```

## Usage Notes

- Use this to conditionally show comment forms, comment prompts, or discussion-related sections.
- Prevents showing "Leave a comment" prompts on posts where comments have been disabled.
- Comment status can be controlled per-post in the post editor or globally in Settings > Discussion.
