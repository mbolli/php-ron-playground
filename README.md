# php-ron-playground

A live playground for [RON (Readable Object Notation)](https://github.com/starfederation/ron):
type JSON on the left, watch RON appear on the right (and back). It runs the **real**
[`mbolli/php-ron`](https://github.com/mbolli/php-ron) library server-side and updates on
keypress, built on [`mbolli/php-via`](https://via.zweiundeins.gmbh) (OpenSwoole + Datastar).

It is designed to be embedded by iframe into the php-ron marketing one-pager, and also works
standalone.

## How it works (CQRS over one SSE)

php-via opens a single persistent SSE stream per client (`/_sse`) that carries every update.
Each keystroke POSTs the bound signals to the `convert` **action** (the command); the action
calls `$c->sync()`, and the callable **view** re-renders only the `output` block with the freshly
converted RON/JSON, patched down the existing SSE stream. Read stream + command actions, with
OpenSwoole holding state in-process — no manual SSE plumbing or Redis.

- `app.php` — bootstrap + the single page (signals `input`/`mode`/`pretty`, the `convert` action, the callable view).
- `src/Converter.php` — framework-free JSON ⇄ RON conversion + stats (bytes saved, XXH3-128 hash). Caps input at 64 KB and catches `RonException`.
- `templates/shell.html` — custom php-via shell: the connection metas, the embedded layout, and the iframe **height-handshake** script.
- `templates/playground.html.twig` — the UI; the `{% block output %}` is what re-renders live.
- `public/playground.css` — standalone styling (modern CSS, light/dark via `prefers-color-scheme`).

## Local development

```bash
composer install      # resolves php-via + php-ron + tempest-highlight-ron from Packagist,
                      # and copies datastar.js into public/ (post-install hook)
php app.php           # → http://localhost:3000
```

Requires PHP 8.4+ with the OpenSwoole extension (same as php-via). All dependencies resolve from
Packagist as stable releases. To develop against local checkouts of the libraries, add a `path`
repository for them in `composer.json`.

## Configuration (env)

| Var | Default | Purpose |
|-----|---------|---------|
| `APP_ENV` | `dev` | `prod` enables secure cookie, h2c, Brotli, and the embeddable (cross-site) session cookie. |
| `VIA_PORT` | `3000` | Listen port. |
| `VIA_PUBLIC_ORIGIN` | — | The playground's own https origin (e.g. `https://ron-play.example.com`). In prod, locks action POSTs to this origin. |
| `VIA_EMBED_ORIGIN` | — | The marketing page origin allowed to frame us (sets `frame-ancestors`). Unset ⇒ no `frame-ancestors` restriction. |

## Deploy

The runtime is the OpenSwoole process (`php app.php`) behind a TLS-terminating reverse proxy that
speaks h2c to it; in prod the app sets `APP_ENV=prod` (secure + embeddable cookie, h2c, Brotli) and
emits `frame-ancestors` from `VIA_EMBED_ORIGIN`. Per-IP action rate limiting (180/min) is on by
default. The concrete deployment setup (container image, proxy config) will be added later.

## Embedding in the marketing one-pager

1. Deploy this service; note its public origin.
2. In the marketing page (`php-ron/docs/index.html`), set the iframe source:
   `data-playground-src="https://<your-playground-origin>/"` on the `#playground` div. The page
   reveals the iframe only once its height-handshake confirms it loaded, and stays on the static
   comparison otherwise.
3. Set `VIA_EMBED_ORIGIN` to the marketing page origin (e.g. `https://mbolli.github.io`) so the
   playground emits a matching `frame-ancestors`, and `VIA_PUBLIC_ORIGIN` to this service's origin.

### Cross-site cookies when embedding

php-via ties the SSE stream to action requests with a **session cookie** and gates SSE attach on a
session-authorization check. In a **cross-origin** iframe a `SameSite=Lax` cookie is treated as
cross-site and not sent, so the live view would never update.

php-via's `withEmbeddable()` (enabled in prod by `app.php`) sets the cookie
`SameSite=None; Secure; Partitioned` (CHIPS), which the browser *does* send inside the iframe — so
cross-origin embedding works out of the box in prod. Requirements: served over HTTPS (Secure) and
`APP_ENV=prod`. In dev the cookie is `SameSite=Lax`, so test embedding against a prod-mode deploy;
standalone use (opening the playground URL directly) works in either mode. If a browser still blocks
partitioned/third-party cookies entirely, the marketing page degrades gracefully to the static
comparison.

## Credits

[php-ron](https://github.com/mbolli/php-ron) · [php-via](https://via.zweiundeins.gmbh) ·
[Datastar](https://data-star.dev) · [OpenSwoole](https://openswoole.com). MIT.
