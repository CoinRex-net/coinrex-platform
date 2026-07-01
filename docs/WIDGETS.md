# CoinRex Embeddable Widgets

CoinRex now supports embeddable rating widgets powered by the existing CoinRex rating layouts.

## Available layouts

- `single` → existing single-line rating row
- `glass` → existing multiline glassmorphism rating card

## Basic embed

```html
<script src="https://coinrex.xyz/widget.js" async></script>

<div
  class="coinrex-widget"
  data-project="dropkings"
  data-layout="single"
  data-bg="#111111"
  data-opacity="0.85"
  data-radius="18"
></div>
```

## Hardened embed with signed widget token

Use a signed token when embedding on third-party domains in production.

```html
<script src="https://coinrex.xyz/widget.js" async></script>

<div
  class="coinrex-widget"
  data-project="dropkings"
  data-layout="glass"
  data-token="PASTE_SIGNED_WIDGET_TOKEN_HERE"
  data-bg="#0B1220"
  data-opacity="0.88"
  data-blur="18"
  data-radius="20"
  data-shadow="strong"
  data-spacing="8"
></div>
```

## Supported `data-*` attributes

Only container-level theming can be customized.

| Attribute | Purpose | Allowed values |
|---|---|---|
| `data-project` | Project slug | lower-case slug |
| `data-layout` | Widget layout | `single`, `glass` |
| `data-token` | Signed widget token for secured mode | signed token string |
| `data-bg` | Background color | hex only, e.g. `#111111` |
| `data-opacity` | Background opacity | `0.2` to `1` |
| `data-blur` | Backdrop blur | `0` to `36` |
| `data-radius` | Border radius | `0` to `32` |
| `data-shadow` | Shadow preset | `none`, `soft`, `medium`, `strong` |
| `data-spacing` | Outer spacing in pixels | `0` to `48` |
| `data-refresh` | Auto-refresh interval in seconds | `60` to `3600` |

## Locked brand identity

The widget runtime intentionally protects these brand tokens:

- CoinRex royal blue
- Gold stars
- White typography
- Logo styling
- Verification badge colors

## API endpoints

### Public rating endpoint

```http
GET /api/v1/project/{slug}/rating
```

Example response:

```json
{
  "project_name": "DropKings",
  "slug": "dropkings",
  "rating": 4.8,
  "reviews": 1243,
  "verified": true,
  "updated_at": "2026-05-07T12:00:00Z"
}
```

### Signed widget endpoint

```http
GET /api/v1/project/{slug}/widget?token=SIGNED_TOKEN
```

Example response:

```json
{
  "project_name": "DropKings",
  "slug": "dropkings",
  "rating": 4.8,
  "reviews": 1243,
  "verified": true,
  "updated_at": "2026-05-07T12:00:00Z",
  "widget": {
    "provider": "coinrex",
    "allowed_layouts": ["single", "glass"],
    "theme": {
      "customizable": ["bg", "opacity", "blur", "radius", "shadow", "spacing"]
    },
    "refresh_seconds": 300,
    "isolation": "shadow-dom",
    "render_mode": "remote-data"
  },
  "secured": {
    "token_valid": true,
    "allowed_domains": ["partner.example.com"],
    "expires_at": "2026-05-08T12:00:00Z"
  }
}
```

## Generating a widget token

Generate the token server-side using the PHP helper:

```php
<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

$token = coinrexGenerateWidgetToken('dropkings', ['partner.example.com'], 86400);
echo $token;
```

## Security model

### Implemented

- Shadow DOM style isolation
- Sanitized frontend theme inputs
- Signed widget token validation
- Domain validation using `Origin` / `Referer`
- Rate limiting on rating + widget endpoints
- Strict JSON responses
- CORS handling for embed endpoints
- No trust in frontend-provided project data

### Recommended production setup

1. Set `COINREX_WIDGET_SECRET` in `.env`
2. Generate signed tokens per partner domain
3. Embed widgets with `data-token`
4. Serve `widget.js` via CDN with long cache TTL
5. Keep API responses cached for short intervals only
6. Ensure Apache `mod_rewrite` is enabled for clean `/api/v1/...` routes

## Performance notes

- `widget.js` uses Vanilla JavaScript only
- widgets lazy-initialize with `IntersectionObserver`
- auto-refresh is lightweight and visibility-aware
- SVG icons are inline, so there is no Font Awesome dependency
- widget styles are isolated from the host page
