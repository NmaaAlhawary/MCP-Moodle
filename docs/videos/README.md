# Videos for the guide site

Drop your screen recordings here, then wire them into `../index.html`.

## Suggested files
| File | Shows |
|---|---|
| `demo.mp4` | Full walkthrough (the big hero video) |
| `01-install.mp4` | Installing the plugin in Moodle |
| `02-token.mp4` | Enabling web services + creating a token |
| `03-connect.mp4` | Connecting the MCP server to Claude Desktop |
| `04-build.mp4` | Chatting to build a course |

## How to embed a video

In `index.html`, replace a placeholder block like this:

```html
<div class="video">
  <div class="play"></div>
  <div class="cap">Full walkthrough — add videos/demo.mp4</div>
</div>
```

…with a real video:

```html
<div class="video">
  <video controls poster="videos/poster.jpg">
    <source src="videos/demo.mp4" type="video/mp4">
  </video>
</div>
```

Or embed from YouTube (no large files in the repo):

```html
<div class="video">
  <iframe width="100%" height="100%" src="https://www.youtube.com/embed/VIDEO_ID"
          frameborder="0" allowfullscreen></iframe>
</div>
```

> Tip: keep local `.mp4` files small (trim + compress) so the page loads fast.
> For longer videos, YouTube/Vimeo embeds are better than committing big files.
