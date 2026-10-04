# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A single-page live playground that converts JSON ⇄ RON on every keystroke. It runs the real
`mbolli/php-ron` library server-side on top of `mbolli/php-via` (OpenSwoole + Datastar). Designed
to be embedded by `<iframe>` into the php-ron marketing page, and to also work standalone.

## Commands

```bash
composer install   # resolves php-via (../php-via-014 for now), php-ron and tempest-highlight-ron
php app.php        # run the server → http://localhost:3000  (alias: composer start)
```

Requires **PHP 8.4+** with **OpenSwoole 26**. There is no test suite, linter, or static
analysis configured in this repo — do not invent `phpunit`/`phpstan` commands (the entries you may
see in `composer.lock` belong to the php-via dependency, not this project).

php-via 0.14 is unreleased: `composer.json` requires `mbolli/php-via: @dev` from a `path` repository
at `../php-via-014`. After the release, require `^0.14` and delete the repository entry. The app uses no
template engine: `src/Template.php` renders plain PHP templates.
`OutputHighlighter`/`Highlighter` are long-lived because the OpenSwoole process is
long-running — restart `php app.php` to pick up code changes.

## Architecture (CQRS over one SSE stream)

The whole app is `app.php`. Datastar opens a single persistent SSE stream per client (`/_sse`) that
carries every update. The flow on each keystroke:

1. The textarea's `data-on:input__debounce.250ms` (and the mode buttons / pretty checkbox) POST the
   bound signals (`input`, `mode`, `pretty`) to the **`convert` action** — the command.
2. The `convert` action does nothing but call `$ctx->sync()`. Signal values arrive with the POST, so
   state is already current.
3. `sync()` re-runs the **view** with `$isUpdate = true`, which calls `Converter::convert(...)`,
   highlights the result and returns **only `templates/output.php`** (`#pg-out`), patched down the
   existing SSE stream. The page load renders `templates/playground.php` around it.

Read side = the SSE stream + view; command side = the `convert` action. OpenSwoole holds
per-tab state in-process — no manual SSE plumbing, no Redis.

### Files

- `app.php` — bootstrap, `Config`, the single `/` page: declares signals, the `convert` action, and
  the view. Signals are **TAB-scoped** (each visitor's editor is private to their tab).
- `src/Converter.php` — framework-free JSON ⇄ RON conversion + stats (bytes saved, SHA-256 hash).
  Depends only on `mbolli/php-ron` so it's testable in isolation. Caps input at `MAX_BYTES` (64 KB)
  and turns any thrown `RonException`/`Throwable` into a short `error` string. Stats/hash are
  best-effort and must never hide a successful conversion.
  The `pretty` signal maps to `RonMode::Pretty` / `RonMode::Compact` (php-ron >= 0.5). Both
  **preserve the source member order**, so the output mirrors what the user typed; the third mode,
  `RonMode::Canonical`, is what sorts keys, and it is used only for the stats/hash pass via
  `Ron::canonicalJson()` / `Ron::canonicalRon()` / `Ron::canonicalHash()`.
- `src/OutputHighlighter.php` — server-side syntax highlighting via `tempest/highlight` (RON support
  from `mbolli/tempest-highlight-ron`). `parse()` returns HTML-escaped token spans, which `output.php`
  prints unescaped.
- `src/Template.php`: renders `templates/<name>.php` with the data as variables and `$e()` for
  escaping. Escape everything except `outputHtml`.
- `templates/playground.php`: the page, rendered once. `templates/output.php`: the output pane, the
  only part that re-renders live.
- `templates/shell.html` — custom php-via shell: `{{ via_head }}` right after `<meta charset>` and
  `{{ via_foot }}` before `</body>` (do not remove: they seed `via_ctx`, open the SSE stream, close
  the context on unload and load php-via's own `/datastar.js`) plus the iframe
  **height-handshake** script that `postMessage`s content height to the embedding parent.

### Two gotchas in `app.php` / templates

- **Signal ids**: templates write `$signal->ref()` / `$signal->id()`. Never hard-code a signal id:
  TAB ids carry the context id.
- **Arrow functions** capture only the names they reference, so the view passes its data as an
  explicit array, not `compact()`.
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
