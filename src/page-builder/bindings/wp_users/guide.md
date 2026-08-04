# wp_users — WordPress Users Query

Render published-site users as repeating cards.

Use this binding for team pages, author directories, or member listings where
the card markup is agent-authored but the user data comes from WordPress.

## Rendered HTML

Your first child element is the **card template**; it is cloned once per
result with `data-ai-bind` values filled in. The region element and your
template markup ARE the rendered structure — CSS targets your own classes:

```html
<!-- You author: -->
<div data-ai-dynamic="wp_users" class="user-grid">
  <article class="user-card">
    <h3 data-ai-bind="name">Name</h3>
    <p data-ai-bind="bio">Bio</p>
  </article>
</div>

<!-- Renders as (one .user-card per result): -->
<div data-ai-dynamic="wp_users" class="user-grid">
  <article class="user-card">…result 1…</article>
  <article class="user-card">…result 2…</article>
</div>
```

No binding-generated classes exist; the loop reuses your template verbatim.
Style `.user-grid` for layout (grid/flex) and `.user-card` for items.

## Binding Type

- Type: `card`
- Runtime: server-side
- Output shape: one rendered card per matched user

## Parameters

### Authoring-Required

| Attribute | Type | Authoring Rule | Runtime Default | Description |
|-----------|------|----------------|-----------------|-------------|
| `data-ai-dynamic` | string | required | — | Set to `wp_users`. |
| `data-count` | integer | recommended | `6` | Number of users to fetch. |
| `data-orderby` | string | recommended | `display_name` | Sort field. |

### Optional

| Attribute | Type | Default | Allowed / Example | Description |
|-----------|------|---------|-------------------|-------------|
| `data-role` | string | empty | `author`, `editor`, `administrator` | Optional role filter. Omit or use `any` for all roles. |

Allowed `data-orderby` values:

- `display_name`
- `user_nicename`
- `user_url`
- `user_registered`
- `ID`
- `include`

Invalid `data-orderby` values fail closed to `display_name`.

## Bind Keys

| Key | Use On | Result |
|-----|--------|--------|
| `display_name` | text element | User display name |
| `avatar` | `<img>` | Avatar URL applied to `src` |
| `bio` | text element | Trimmed user bio |
| `profile_url` | `<a>` | Author archive URL applied to `href` |

Meta keys: use `meta.<key>` for governed user meta only.

## Runtime Rules

- The query always uses `has_published_posts=true`.
- The renderer only exposes public user-facing fields.
- Email, login, and other private identity fields are not bindable.
- If a blocked or sensitive `meta.<key>` is requested, the binding is cleared to empty output instead of leaving placeholder text behind.

## Structure Rules

Safe contract:

1. Put exactly one direct child element inside the `wp_users` region.
2. That child is the user card template.
3. Put all `data-ai-bind` nodes inside that template.

Current runtime behavior:

- If multiple direct children exist, the first direct child element becomes the template.

## Example

```html
<div data-ai-dynamic="wp_users"
     data-role="author"
     data-count="4"
     data-orderby="display_name">
  <article>
    <img data-ai-bind="avatar" alt="">
    <h3 data-ai-bind="display_name"></h3>
    <p data-ai-bind="bio"></p>
    <a data-ai-bind="profile_url">View profile</a>
  </article>
</div>
```

## Failure Points

- No matched users renders `<!-- No users found -->`.
- Unsupported `data-orderby` silently degrades to `display_name`.
- Blocked or sensitive `meta.<key>` bindings are cleared to empty output.
- Multiple direct children can cause the wrong node to become the template.
