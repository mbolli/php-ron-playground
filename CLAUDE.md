# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A single-page live playground that converts JSON ⇄ RON on every keystroke. It runs the real
`mbolli/php-ron` library server-side on top of `mbolli/php-via` (OpenSwoole + Datastar). Designed
to be embedded by `<iframe>` into the php-ron marketing page, and to also work standalone.

## Commands

```bash
composer install   # resolves php-via/php-ron/tempest-highlight-ron, then runs bin/copy-assets.php
php app.php        # run the server → http://localhost:3000  (alias: composer start)
```

Requires **PHP 8.4+** with the **OpenSwoole** extension. There is no test suite, linter, or static
analysis configured in this repo — do not invent `phpunit`/`phpstan` commands (the entries you may
see in `composer.lock` belong to the php-via dependency, not this project).

All dependencies resolve from Packagist; `mbolli/php-via` tracks `dev-master` (hence
`minimum-stability: dev`). To develop against local checkouts of the libraries, add a `path`
repository for them in `composer.json`. `OutputHighlighter`/`Highlighter` are long-lived because the
OpenSwoole process is long-running — restart `php app.php` to pick up code changes.

## Architecture (CQRS over one SSE stream)

The whole app is `app.php`. Datastar opens a single persistent SSE stream per client (`/_sse`) that
carries every update. The flow on each keystroke:

1. The textarea's `data-on:input__debounce.250ms` (and the mode buttons / pretty checkbox) POST the
   bound signals (`input`, `mode`, `pretty`) to the **`convert` action** — the command.
2. The `convert` action does nothing but call `$ctx->sync()`. Signal values arrive with the POST, so
   state is already current.
3. `sync()` re-runs the **callable view**, which calls `Converter::convert(...)`, highlights the
   result, and re-renders **only the `output` block** of `playground.html.twig`, patched down the
   existing SSE stream. `cacheUpdates: false` because every input is unique.

Read side = the SSE stream + callable view; command side = the `convert` action. OpenSwoole holds
per-tab state in-process — no manual SSE plumbing, no Redis.

### Files

- `app.php` — bootstrap, `Config`, the single `/` page: declares signals, the `convert` action, and
  the callable view. Signals are **TAB-scoped** (each visitor's editor is private to their tab).
- `src/Converter.php` — framework-free JSON ⇄ RON conversion + stats (bytes saved, XXH3-128 hash).
  Depends only on `mbolli/php-ron` so it's testable in isolation. Caps input at `MAX_BYTES` (64 KB)
  and turns any thrown `RonException`/`Throwable` into a short `error` string. Stats/hash are
  best-effort and must never hide a successful conversion.
- `src/OutputHighlighter.php` — server-side syntax highlighting via `tempest/highlight` (RON support
  from `mbolli/tempest-highlight-ron`). `parse()` returns HTML-escaped token spans, safe to emit with
  Twig `|raw`.
- `templates/playground.html.twig` — the UI. Only `{% block output %}` re-renders live; the rest is
  the static shell rendered once.
- `templates/shell.html` — custom php-via shell: the connection `<meta>` tags (do not remove — they
  seed `via_ctx`, open the SSE stream, and close the context on unload) plus the iframe
  **height-handshake** script that `postMessage`s content height to the embedding parent.
- `bin/copy-assets.php` — copies php-via's bundled `datastar.js` into `public/` on composer
  install/update. The copied file is git-ignored.

### Two gotchas in `app.php` / templates

- **`modeSignalId`**: TAB signals get a DOM id of `<name>_<contextId>`, not just `mode`. The view
  exposes `$result['modeSignalId']` under that distinct key because `$result['mode']` (a plain string)
  shadows the `mode` signal in auto-injected template data, making `{{ mode.id }}` unavailable.
  Templates reference the signal as `${{ modeSignalId }}`.
- **Output highlighting** (`toRon`): selects the language of the *output*, not the input — RON when
  converting JSON→RON, JSON when converting RON→JSON.

## Production / deploy

- `app.php` switches on `APP_ENV` (`prod` enables secure cookie, h2c, Brotli, `withEmbeddable()`).
- Env: `VIA_PORT` (3000), `VIA_PUBLIC_ORIGIN` (allowlists action POST Origin — CSRF defence),
  `VIA_EMBED_ORIGIN` (who may frame us — sets `frame-ancestors`). Per-IP action rate limit is 180/min.
- The runtime is the OpenSwoole process behind a TLS-terminating reverse proxy speaking h2c. There is
  no Dockerfile/proxy config in the repo yet — the deployment setup is done interactively later.

### Cross-site cookie caveat (relevant if changing embedding behaviour)

php-via ties the SSE stream to action requests via a session cookie. In dev it is `SameSite=Lax`,
which a cross-origin iframe will not send, so the live view never updates when embedded cross-site.
In prod, `withEmbeddable()` sets `SameSite=None; Secure; Partitioned` (CHIPS) so cross-origin
embedding works (requires HTTPS + `APP_ENV=prod`). Standalone use is unaffected. See README
"Cross-site cookies when embedding" for the full picture before touching this.
