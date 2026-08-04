# wp_gallery — Image Gallery

Renders images attached to a post as a gallery grid.

## Rendered HTML

This binding renders a small image gallery container. Output depends on whether
the current post has image attachments. The implementation builds markup from
`get_attached_media('image', $postId)` and wraps each image in:

```html
<div class="upb-gallery">
  <figure class="upb-gallery-item">…item 1…</figure>
  <figure class="upb-gallery-item">…item 2…</figure>
  <figure class="upb-gallery-item">…item 3…</figure>
  <!-- …repeats… -->
</div>
```

(Output captured from renderer source, not execution.)

## Required Attributes

| Attribute | Required | Default | Description |
|-----------|----------|---------|-------------|
| `data-ai-dynamic` | yes | — | Set to `wp_gallery` |
| `data-post-id` | no | `0` (current post) | Post whose attached images to display |
| `data-count` | no | `10` | Maximum number of images to render |
| `data-size` | no | `medium` | WordPress image size (`thumbnail`, `medium`, `medium_large`, `large`, `full`) |

## Example

```html
<div data-ai-dynamic="wp_gallery" data-count="6" data-size="large"></div>
<div data-ai-dynamic="wp_gallery" data-post-id="99" data-count="4" data-size="thumbnail"></div>
```

## Usage Notes

- Renders images that are attached (uploaded) to the specified post.
- The output is an image grid rendered directly by the binding.
- Use `data-size` to control image dimensions and loading performance.
- If the post has no attached images, the element renders empty.
