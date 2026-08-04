# wp_sticky_posts — WordPress Sticky Posts

Renders posts marked as "sticky" in WordPress. Uses the same renderer as `wp_query`. Use `data-ai-bind` keys to place post data inside the template.

## Rendered HTML

Your first child element is the **card template**; it is cloned once per
result with `data-ai-bind` values filled in. The region element and your
template markup ARE the rendered structure — CSS targets your own classes:

```html
<!-- You author: -->
<div data-ai-dynamic="wp_sticky_posts" class="post-grid">
  <article class="post-card">
    <h3 data-ai-bind="title">Title</h3>
    <p data-ai-bind="excerpt">Excerpt</p>
  </article>
</div>

<!-- Renders as (one .post-card per result): -->
<div data-ai-dynamic="wp_sticky_posts" class="post-grid">
  <article class="post-card">…result 1…</article>
  <article class="post-card">…result 2…</article>
</div>
```

No binding-generated classes exist; the loop reuses your template verbatim.
Style `.post-grid` for layout (grid/flex) and `.post-card` for items.

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `wp_sticky_posts` | Marks this as a sticky posts region |
| `data-count` | e.g. `5`, `10` | Maximum number of sticky posts to fetch |

## Bind Keys

Use `data-ai-bind` on elements inside the card template to place post data:

| Key | Description | Use on |
|-----|-------------|--------|
| `title` | Post title | Text elements (`<h3>`, `<span>`, etc.) |
| `excerpt` | Post excerpt (20 words) | Text elements (`<p>`, etc.) |
| `content` | Full post content (filtered) | Container elements (`<div>`, etc.) |
| `thumbnail` | Featured image URL | `<img>` element (`src` attribute) |
| `permalink` | Post URL | `<a>` element (`href` attribute) |
| `date` | Published date (site format) | Text elements (`<span>`, `<time>`, etc.) |
| `modified_date` | Last modified date | Text elements |
| `author` | Author display name | Text elements |
| `author_url` | Author archive URL | `<a>` element (`href` attribute) |
| `author_avatar` | Author avatar image URL | `<img>` element (`src` attribute) |
| `categories` | Comma-separated category names | Text elements |
| `tags` | Comma-separated tag names | Text elements |
| `comment_count` | Number of comments | Text elements |
| `post_id` | Post ID | Text elements or data attributes |

Taxonomy terms: Use `terms.<taxonomy>` for a post's terms from any taxonomy (e.g. `terms.product_cat`). Renders as comma-separated names.

Meta keys: Use `meta.<key>` for custom field values (e.g. `meta.highlight_color`).

## Structure Rules

1. The dynamic region must contain **exactly one** direct child element — the card template
2. The renderer clones this template for each sticky post
3. Bind keys inside the template are replaced with actual post data

## Example — Featured/Sticky Posts Banner

```html
<section class="featured-posts py-5 bg-light">
  <div class="container">
    <h2>Featured</h2>
    <div class="row g-4"
         data-ai-dynamic="wp_sticky_posts"
         data-count="3">
      <div class="col-md-4">
        <div class="card h-100 border-0 shadow-sm">
          <img class="card-img-top" data-ai-bind="thumbnail" alt="">
          <div class="card-body">
            <h5 class="card-title" data-ai-bind="title"></h5>
            <p class="card-text text-secondary" data-ai-bind="excerpt"></p>
            <a class="btn btn-primary" data-ai-bind="permalink">Read More</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
```

## CSS Tips

```css
.featured-posts .card {
  border-left: 4px solid var(--bs-primary);
}
```

## Common Mistakes

- No sticky posts set in WordPress — the region will render empty
- Multiple direct children in the dynamic region — only the first is used as the template
