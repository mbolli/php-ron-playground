<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Mbolli\PhpVia\Config;
use Mbolli\PhpVia\Context;
use Mbolli\PhpVia\Via;
use RonPlayground\Converter;
use RonPlayground\OutputHighlighter;

/*
 * Live RON playground.
 *
 * One php-via page. Datastar opens a single SSE stream (/_sse) that carries every
 * update; each keystroke POSTs the bound signals to the `convert` action (the
 * command), which calls $c->sync() so the callable view re-renders only the
 * `output` block with the freshly converted RON/JSON. Read stream + command
 * actions = the requested CQRS shape, with OpenSwoole holding state in-process.
 */

$isDev = getenv('APP_ENV') !== 'prod';
$port = (int) (getenv('VIA_PORT') ?: 3000);

// The playground's own public https origin, e.g. "https://ron-play.example.com".
// When set in prod it locks action POSTs to that origin (CSRF defence). The
// embedding marketing page is controlled separately via frame-ancestors (Caddyfile).
$publicOrigin = trim((string) getenv('VIA_PUBLIC_ORIGIN'));

$config = (new Config())
    ->withHost('0.0.0.0')
    ->withPort($port)
    ->withDevMode($isDev)
    ->withTemplateDir(__DIR__ . '/templates')
    ->withTwigCacheDir(sys_get_temp_dir() . '/ron-playground-twig')
    ->withStaticDir(__DIR__ . '/public')
    ->withShellTemplate(__DIR__ . '/templates/shell.html')
    ->withLogLevel($isDev ? 'debug' : 'info')
    // Public-endpoint hygiene: cap action requests per IP (180/min).
    ->withActionRateLimit(180, 60);

if (!$isDev) {
    // Production: behind a TLS-terminating reverse proxy (see Caddyfile) we speak
    // h2c, compress with Brotli, and mark the session cookie Secure.
    // withEmbeddable() makes the session cookie SameSite=None; Secure; Partitioned so the
    // SSE stream attaches inside a cross-origin <iframe> (e.g. the marketing page).
    // VIA_EMBED_ORIGIN (optional) restricts who may frame us via frame-ancestors; this is
    // unrelated to VIA_PUBLIC_ORIGIN below, which allowlists the action POST Origin.
    $config->withSecureCookie(true)->withH2c()->withBrotli()
        ->withEmbeddable(getenv('VIA_EMBED_ORIGIN') ?: null);
    if ($publicOrigin !== '') {
        $config->withTrustedOrigins([$publicOrigin]);
    }
}

$app = new Via($config);

$app->page('/', function (Context $c): void {
    // TAB scope (default): each visitor's editor is private to their tab.
    $c->signal(Converter::SAMPLE_JSON, 'input');
    $c->signal('json2ron', 'mode');
    $c->signal(true, 'pretty');

    // The command: just re-render. Signal values arrive with the POST, so the
    // callable view below already sees the latest input/mode/pretty.
    $c->action(function (Context $ctx): void {
        $ctx->sync();
    }, 'convert');

    // The read side: a callable view re-runs on every sync and patches only the
    // `output` block. cacheUpdates:false because every input is unique.
    $c->view(function () use ($c): string {
        $result = Converter::convert(
            $c->getSignal('input')->string(),
            $c->getSignal('mode')->string(),
            $c->getSignal('pretty')->bool(),
        );

        // Syntax-highlight the converted output (RON or JSON depending on direction).
        // Empty on error/empty so the template falls through to its plain branches.
        $result['outputHtml'] = $result['error'] === null && $result['output'] !== ''
            ? OutputHighlighter::render($result['output'], $result['toRon'])
            : '';

        // The Datastar id of the 'mode' signal (TAB signals are id'd "<name>_<contextId>").
        // Exposed under a non-colliding key because $result['mode'] (a string) shadows the
        // 'mode' signal in the auto-injected template data, so {{ mode.id }} is unavailable.
        $result['modeSignalId'] = $c->getSignal('mode')?->id() ?? 'mode';

        return $c->render('playground.html.twig', $result);
    }, block: 'output', cacheUpdates: false);
});

$app->start();
