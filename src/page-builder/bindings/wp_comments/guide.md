# wp_comments — WordPress Comments

Renders approved comments for a post as repeating cards. Each comment is rendered using a card template you define. Use `data-ai-bind` keys to place comment data inside the template.

## Rendered HTML

Your first child element is the **card template**; it is cloned once per
result with `data-ai-bind` values filled in. The region element and your
template markup ARE the rendered structure — CSS targets your own classes:

```html
<!-- You author: -->
<div data-ai-dynamic="wp_comments" class="comment-list">
  <article class="comment-card">
    <h3 data-ai-bind="name">Name</h3>
    <p data-ai-bind="comment">Comment</p>
  </article>
</div>

<!-- Renders as (one .comment-card per result): -->
<div data-ai-dynamic="wp_comments" class="comment-list">
  <article class="comment-card">…result 1…</article>
  <article class="comment-card">…result 2…</article>
</div>
```

No binding-generated classes exist; the loop reuses your template verbatim.
Style `.comment-list` for layout (grid/flex) and `.comment-card` for items.

## Parameters

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `wp_comments` | Marks this as a comments region |
| `data-post-id` | e.g. `0`, `42` | Optional post ID to fetch comments for. `0` = current post |
| `data-count` | e.g. `5`, `10` | Number of comments to fetch |
| `data-orderby` | `comment_date_gmt`, `comment_date`, `comment_author`, `comment_type`, `comment_ID` | Sort order |

## Bind Keys

Use `data-ai-bind` on elements inside the card template to place comment data:

| Key | Description | Use on |
|-----|-------------|--------|
| `author` | Comment author name | Text elements (`<strong>`, `<span>`, etc.) |
| `avatar` | Author avatar URL (Gravatar) | `<img>` element (`src` attribute) |
| `content` | Comment text (HTML) | Text elements (`<p>`, `<div>`, etc.) |
| `date` | Comment date | Text elements (`<time>`, `<span>`, etc.) |
| `url` | Comment permalink | `<a>` element (`href` attribute) |

## Structure Rules

1. The dynamic region must contain **exactly one** direct child element — the card template
2. The renderer clones this template for each comment
3. Bind keys inside the template are replaced with actual comment data

## Example — Comments List

```html
<section class="comments-section py-5">
  <div class="container">
    <h3>Recent Comments</h3>
    <div class="d-flex flex-column gap-3"
         data-ai-dynamic="wp_comments"
         data-post-id="0"
         data-count="5"
         data-orderby="comment_date_gmt">
      <div class="d-flex gap-3 p-3 bg-light rounded">
        <img class="rounded-circle flex-shrink-0" style="width:48px;height:48px" data-ai-bind="avatar" alt="">
        <div>
          <strong data-ai-bind="author"></strong>
          <small class="text-muted ms-2" data-ai-bind="date"></small>
          <p class="mb-0 mt-1" data-ai-bind="content"></p>
        </div>
      </div>
    </div>
  </div>
</section>
```

## CSS Tips

```css
/* Subtle separator between comments */
.comments-section .d-flex + .d-flex {
  border-top: 1px solid rgba(0,0,0,0.05);
  padding-top: 1rem;
}
```

## Common Mistakes

- Forgetting that `data-post-id="0"` means current post — set a specific ID to show comments from another post
- Using `text` instead of `content` — the bind key is `content`
- Multiple direct children in the dynamic region — only the first is used as the template
