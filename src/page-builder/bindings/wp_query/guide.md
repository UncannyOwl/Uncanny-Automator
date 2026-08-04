# wp_query — WordPress Post Query

Render WordPress posts, pages, or custom post types as repeating cards.

Use this binding when the page needs live post data from WordPress and the
agent should control the card markup.

Do not use this binding for:

- a single already-known post value like title or permalink
- a real WordPress navigation menu
- static hand-authored cards with no live WordPress dependency

## Rendered HTML

Your first child element is the **card template**; it is cloned once per
result with `data-ai-bind` values filled in. The region element and your
template markup ARE the rendered structure — CSS targets your own classes:

```html
<!-- You author: -->
<div data-ai-dynamic="wp_query" class="post-grid">
  <article class="post-card">
    <h3 data-ai-bind="title">Title</h3>
    <p data-ai-bind="excerpt">Excerpt</p>
  </article>
</div>

<!-- Renders as (one .post-card per result): -->
<div data-ai-dynamic="wp_query" class="post-grid">
  <article class="post-card">…result 1…</article>
  <article class="post-card">…result 2…</article>
</div>
```

No binding-generated classes exist; the loop reuses your template verbatim.
Style `.post-grid` for layout (grid/flex) and `.post-card` for items.

## Binding Type

- Type: `card`
- Runtime: server-side
- Output shape: one rendered card per matched post
- Export / freeze safety: `request_sensitive`

## Authoring Workflow

1. Use `manage_binding operation=search` if the user asks for posts, blog cards, products, archive items, or similar looped content.
2. Use `manage_binding operation=guide` and read this guide before writing markup.
3. Create exactly one dynamic region with `data-ai-dynamic="wp_query"`.
4. Put exactly one direct child element inside that region. That child is the card template.
5. Put `data-ai-bind` keys inside the template.
6. Use `manage_binding operation=inspect` before changing an existing saved binding.
7. Use `manage_binding operation=update_query` to change query attributes.
8. Use `manage_binding operation=update_template` to change the card markup.

## Parameters

### Authoring-Required Parameters

| Attribute | Type | Required | Allowed / Example | Description |
|-----------|------|----------|-------------------|-------------|
| `data-ai-dynamic` | string | yes | `wp_query` | Marks the region as a WordPress post query binding. |
| `data-post-type` | string | yes | `post`, `page`, `product` | WordPress post type slug to query. |
| `data-count` | integer | yes | `3`, `6`, `12` | Number of posts to fetch. |
| `data-orderby` | string | yes | `date`, `title`, `rand`, `menu_order`, `modified`, `comment_count`, `meta_value` | Sort field. |

### Optional Parameters

| Attribute | Type | Default | Allowed / Example | Description |
|-----------|------|---------|-------------------|-------------|
| `data-order` | string | `DESC` | `ASC`, `DESC` | Sort direction. |
| `data-category` | string | empty | `news`, `tutorials` | Category slug filter. |
| `data-tag` | string | empty | `featured`, `popular` | Tag slug filter. |
| `data-author` | integer | empty | `1`, `5` | Author ID filter. |
| `data-offset` | integer | `0` | `3`, `6` | Skip the first N matched posts. |
| `data-exclude` | string | empty | `42,99,101` | Comma-separated post IDs to exclude. |
| `data-include` | string | empty | `10,20,30` | Comma-separated post IDs to include. |
| `data-parent` | integer | empty | `5`, `12` | Parent post ID filter for hierarchical post types. |
| `data-search` | string | empty | `recipe`, `pricing` | Search keyword filter. |
| `data-status` | string | `publish` | `publish`, `future`, `pending` | Post status filter. Non-public statuses make the binding request-sensitive. |
| `data-taxonomy` | string | empty | `genre`, `product_cat` | Custom taxonomy slug. Use with `data-term`. |
| `data-term` | string | empty | `jazz`, `featured` | Term slug. Use with `data-taxonomy`. |
| `data-meta-key` | string | empty | `price`, `event_date` | Meta key filter. |
| `data-meta-value` | string | empty | `featured`, `100` | Meta value filter. Use with `data-meta-key`. |
| `data-paginate` | boolean | `false` | `true`, `false` | Enable pagination output. |

## Sane Defaults

If you need a basic recent-posts loop and the user did not specify details,
these defaults are safe:

- `data-post-type="post"`
- `data-count="3"`
- `data-orderby="date"`
- `data-order="DESC"`
- omit taxonomy, meta, search, include, and exclude filters
- omit pagination unless the user explicitly asks for paged results

The runtime also falls back to these values when author markup omits them, but
the safe authoring contract is still to set them explicitly.

## Bind Keys

Use `data-ai-bind` only inside the card template.

### Standard Bind Keys

| Key | Use On | Result |
|-----|--------|--------|
| `title` | text element | Post title |
| `excerpt` | text element | Post excerpt |
| `content` | container element | Full filtered post content |
| `thumbnail` | `<img>` | Featured image URL applied to `src` |
| `permalink` | `<a>` | Post URL applied to `href` |
| `date` | text element | Published date |
| `modified_date` | text element | Last modified date |
| `author` | text element | Author display name |
| `author_url` | `<a>` | Author archive URL applied to `href` |
| `author_avatar` | `<img>` | Author avatar URL applied to `src` |
| `categories` | text element | Comma-separated category names |
| `tags` | text element | Comma-separated tag names |
| `comment_count` | text element | Comment count |
| `post_id` | text element | Numeric post ID |

### Terms Bind Keys

Use `terms.<taxonomy>` when you need terms from a specific taxonomy.

Examples:

- `terms.category`
- `terms.product_cat`
- `terms.genre`

Output is comma-separated term names.

### Meta Bind Keys

Use `meta.<key>` for custom fields that are safe to expose.

Examples:

- `meta.price`
- `meta.event_date`
- `meta._price`

Protected underscore-prefixed keys are not generally allowed unless the system
explicitly permits them.

## Structure Rules

Safe authoring contract:

1. The dynamic region must contain exactly one direct child element.
2. That one direct child is the template that gets cloned for each result.
3. Put all `data-ai-bind` nodes inside that one template.
4. Do not place sibling template roots inside the same `wp_query` region.
5. Do not nest another `data-ai-dynamic` region inside the card template.
6. Do not place style tags inside the dynamic region if you expect them to survive template replacement workflows.

Current runtime behavior:

- If multiple direct children exist, the first direct child element becomes the template.

## Minimal Example

```html
<div data-ai-dynamic="wp_query"
     data-post-type="post"
     data-count="3"
     data-orderby="date">
  <article>
    <h3 data-ai-bind="title"></h3>
    <a data-ai-bind="permalink">Read more</a>
  </article>
</div>
```

## Production Example

```html
<div class="row g-4"
     data-ai-dynamic="wp_query"
     data-post-type="post"
     data-count="6"
     data-orderby="date"
     data-category="news"
     data-paginate="true">
  <article class="col-md-4">
    <div class="card h-100 border-0 shadow-sm">
      <img class="card-img-top" data-ai-bind="thumbnail" alt="">
      <div class="card-body">
        <h5 class="card-title" data-ai-bind="title"></h5>
        <p class="card-text text-secondary" data-ai-bind="excerpt"></p>
        <a class="btn btn-outline-primary" data-ai-bind="permalink">Read more</a>
      </div>
    </div>
  </article>
</div>
```

## Product Example

```html
<div class="row g-4"
     data-ai-dynamic="wp_query"
     data-post-type="product"
     data-count="4"
     data-orderby="menu_order"
     data-order="ASC"
     data-taxonomy="product_cat"
     data-term="featured">
  <article class="col-md-3">
    <div class="card">
      <img class="card-img-top" data-ai-bind="thumbnail" alt="">
      <div class="card-body">
        <h6 data-ai-bind="title"></h6>
        <span data-ai-bind="meta.price"></span>
        <a class="stretched-link" data-ai-bind="permalink"></a>
      </div>
    </div>
  </article>
</div>
```

## Failure Points

Expect bad or empty results when any of these are true:

- `data-post-type` points at a post type that does not exist.
- `data-orderby="meta_value"` is used without a governed `data-meta-key`.
- the template contains bind keys that are not valid for `wp_query`.
- the region has multiple direct children and the wrong node becomes the template.
- the query matches no posts.
- the agent tries to use `wp_query` where a single-value self-rendering binding would be simpler and safer.

Expected degradation instead of hard failure:

- invalid `data-orderby` falls back to `date`
- invalid `data-count` is clamped to a sane positive range
- `data-taxonomy` without `data-term`, or `data-term` without `data-taxonomy`, is ignored
- `data-meta-value` without `data-meta-key` is ignored
- `data-offset` plus `data-paginate="true"` is supported, but pagination is computed from the visible result window after the base offset
- `data-status="future"` or `data-status="pending"` degrades back to `publish` for visitors who cannot read non-public posts, and the binding is treated as request-sensitive for export safety

## Common Mistakes

- Using `data-ai-bind` on the outer dynamic region instead of inside the template.
- Using `terms.<taxonomy>` without knowing the real taxonomy slug.
- Using `meta.<key>` for sensitive or blocked keys.
- Using `wp_query` for navigation instead of `wp_menu`.
- Changing an existing saved query blindly instead of calling `inspect` first.

## Non-Goals

This binding does not:

- create or edit WordPress posts
- create taxonomies or terms
- author navigation menus
- guarantee that every requested custom field is safe or available
- behave like a single-value binding
