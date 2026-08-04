# author_box — Author Bio Box

Renders an author bio box including avatar, display name, biographical info, and author archive link.

## Rendered HTML

The region's children are replaced with the markup below (captured from a real render). Classes shown here are the stable CSS hooks:

```html
<div class="upb-author-box"><img alt="" src="https://example.com/avatar" srcset="https://example.com/avatar-2x 2x" class="avatar avatar-96 photo" height="96" width="96" /><div class="upb-author-box__body"><h3 class="upb-author-box__name">Author Name</h3><p class="upb-author-box__bio">Author biography text.</p><a class="upb-author-box__link" href="https://example.com/author/admin/">View all posts</a></div></div>
```

## Required Attributes

| Attribute | Required | Default | Description |
|-----------|----------|---------|-------------|
| `data-ai-dynamic` | yes | — | Set to `author_box` |
| `data-user-id` | no | `0` (current post author) | Specific user ID; `0` uses the current post's author |

## Example

```html
<div data-ai-dynamic="author_box"></div>
<div data-ai-dynamic="author_box" data-user-id="3"></div>
```

## Usage Notes

- When `data-user-id` is `0`, the binding uses the author of the current post.
- On non-singular pages without a specific user ID, the element renders empty.
- The rendered box includes the Gravatar avatar, display name, bio, and a link to the author archive.
- Style the output with CSS to match your theme design.
