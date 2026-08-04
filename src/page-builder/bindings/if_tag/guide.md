# if_tag — Conditional: If Tag Archive

Shows its children only when the current page is a tag archive page. If the visitor is on any other page type, the wrapper produces no output.

## Rendered HTML

This binding renders **no markup of its own**. When the condition is true,
the wrapper element is removed and your children render in its place; when
false, the wrapper and everything inside it are removed entirely:

```html
<!-- You author: -->
<div data-ai-dynamic="if_tag"><p class="greeting">Hello!</p></div>

<!-- Condition true — renders as: -->
<p class="greeting">Hello!</p>

<!-- Condition false — renders as: nothing -->
```

IMPORTANT for CSS: the wrapper `<div>` does NOT exist in rendered output —
never target it. Style the children directly (`.greeting`).

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `if_tag` | Marks this as a tag archive conditional wrapper |

## How It Works

- The wrapper checks `is_tag()` to determine if the current page is a tag archive.
- If the current page is a tag archive, the wrapper and its children render normally.
- On all other page types, the wrapper produces no output.
- This matches any tag archive page regardless of which tag is being viewed.

## Example

```html
<div data-ai-dynamic="if_tag">
  <div class="tag-intro">
    <p>You are browsing posts with this tag.</p>
  </div>
</div>
```

## Usage Notes

- `if_tag` matches all tag archive pages. It does not filter by specific tag.
- For broader archive matching that includes categories, authors, and dates, use `if_archive` instead.
- Use this for tag-specific layout elements within a global template.
