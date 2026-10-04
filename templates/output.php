<?php
/**
 * The output pane, re-rendered on every convert.
 *
 * @var \Closure(mixed): string $e
 * @var bool                    $toRon
 * @var ?string                 $error
 * @var string                  $output
 * @var string                  $outputHtml highlighted, already escaped
 * @var ?int                    $savedPct
 * @var ?int                    $compactJsonBytes
 * @var ?int                    $compactRonBytes
 * @var ?string                 $hash
 */
?>
<div id="pg-out" class="pg-out">
    <div class="pg-pane-head">
        <span class="pg-label"><?= $toRon ? 'RON output' : 'JSON output' ?></span>
        <?php if ($toRon && $savedPct !== null && !$error): ?>
            <span class="pg-badge" title="Canonical RON vs canonical JSON"><?= $e($savedPct) ?>% smaller</span>
        <?php endif ?>
    </div>

    <?php if ($error): ?>
        <pre class="pg-output is-error"><?= $e($error) ?></pre>
    <?php elseif ($output === ''): ?>
        <pre class="pg-output is-muted">Start typing on the left…</pre>
    <?php else: ?>
        <pre id="pg-code" class="pg-output"><?= $outputHtml ?></pre>
    <?php endif ?>

    <?php if (!$error && $hash): ?>
        <dl class="pg-stats">
            <?php if ($compactJsonBytes && $compactRonBytes): ?>
                <div><dt>canonical</dt><dd><?= $e($compactJsonBytes) ?> B JSON &middot; <?= $e($compactRonBytes) ?> B RON</dd></div>
            <?php endif ?>
            <div><dt>sha-256</dt><dd><code><?= $e($hash) ?></code></dd></div>
        </dl>
    <?php endif ?>
</div>
