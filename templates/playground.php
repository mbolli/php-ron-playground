<?php
/**
 * The whole page, rendered on load. Updates send only templates/output.php.
 *
 * @var \Closure(mixed): string $e
 * @var \Mbolli\PhpVia\Signal  $input
 * @var \Mbolli\PhpVia\Signal  $mode
 * @var \Mbolli\PhpVia\Signal  $pretty
 * @var \Mbolli\PhpVia\Action  $convert
 * @var string                  $outputPane
 */
?>
<div class="pg">
    <header class="pg-top">
        <h1><span class="pg-logo">php-<b>ron</b></span> playground</h1>
        <div class="pg-controls">
            <div class="pg-modes" role="group" aria-label="Direction">
                <?php // Switching direction moves the current output into the input (panes swap), then re-converts. Clicking the active direction does nothing. ?>
                <button type="button" class="pg-mode"
                        data-class="{'is-active': <?= $e($mode->ref()) ?> === 'json2ron'}"
                        data-on:click="<?= $e($mode->ref()) ?> === 'json2ron' ? null : (<?= $e($input->ref()) ?> = document.getElementById('pg-code')?.textContent ?? <?= $e($input->ref()) ?>, <?= $e($mode->ref()) ?> = 'json2ron', @post('<?= $e($convert->url()) ?>'))">JSON &rarr; RON</button>
                <button type="button" class="pg-mode"
                        data-class="{'is-active': <?= $e($mode->ref()) ?> === 'ron2json'}"
                        data-on:click="<?= $e($mode->ref()) ?> === 'ron2json' ? null : (<?= $e($input->ref()) ?> = document.getElementById('pg-code')?.textContent ?? <?= $e($input->ref()) ?>, <?= $e($mode->ref()) ?> = 'ron2json', @post('<?= $e($convert->url()) ?>'))">RON &rarr; JSON</button>
            </div>
            <label class="pg-pretty">
                <input type="checkbox" data-bind="<?= $e($pretty->id()) ?>" data-on:change="@post('<?= $e($convert->url()) ?>')">
                Pretty
            </label>
        </div>
    </header>

    <div class="pg-grid">
        <section class="pg-pane">
            <div class="pg-pane-head">
                <span class="pg-label" data-text="<?= $e($mode->ref()) ?> === 'json2ron' ? 'JSON input' : 'RON input'">Input</span>
            </div>
            <textarea class="pg-input" spellcheck="false" autocomplete="off" autocapitalize="off"
                      aria-label="Source to convert"
                      data-bind="<?= $e($input->id()) ?>"
                      data-on:input__debounce.250ms="@post('<?= $e($convert->url()) ?>')"><?= $e($input->string()) ?></textarea>
        </section>

        <section class="pg-pane">
            <?= $outputPane ?>
        </section>
    </div>

    <footer class="pg-foot">
        Lossless JSON &harr; RON. Powered by
        <a href="https://github.com/mbolli/php-ron" target="_blank" rel="noopener">php-ron</a> +
        <a href="https://via.zweiundeins.gmbh" target="_blank" rel="noopener">php-via</a>.
    </footer>
</div>
