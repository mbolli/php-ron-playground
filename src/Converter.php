<?php

declare(strict_types=1);

namespace RonPlayground;

use Mbolli\Ron\Ron;

/**
 * Pure JSON <-> RON conversion + stats for the playground.
 *
 * Deliberately framework-free (depends only on mbolli/php-ron) so it can be unit
 * tested and reasoned about in isolation; the php-via page just calls convert().
 */
final class Converter {
    /** Hard cap on input size. A public endpoint must bound the work it accepts. */
    public const int MAX_BYTES = 65_536;

    /** Sample shown on first load so the playground is never empty. */
    public const string SAMPLE_JSON = <<<'JSON'
        {
          "name": "web-server",
          "enabled": true,
          "replicas": 3,
          "tags": ["prod", "edge"],
          "limits": { "cpu": "500m", "memory": "256Mi" },
          "greeting": "hello world"
        }
        JSON;

    /**
     * @param string $src     the source text (JSON or RON depending on $mode)
     * @param string $mode    'json2ron' (default) or 'ron2json'
     * @param bool   $pretty  multiline output when true
     *
     * @return array{
     *     output: string, error: ?string, mode: string, toRon: bool,
     *     inBytes: int, outBytes: int,
     *     compactJsonBytes: ?int, compactRonBytes: ?int, savedPct: ?int,
     *     hash: ?string
     * }
     */
    public static function convert(string $src, string $mode, bool $pretty): array {
        $mode = $mode === 'ron2json' ? 'ron2json' : 'json2ron';
        $empty = self::empty($mode);

        if (\strlen($src) > self::MAX_BYTES) {
            return [...$empty, 'error' => 'Input exceeds the ' . (self::MAX_BYTES / 1024) . ' KB limit.'];
        }

        $trimmed = trim($src);
        if ($trimmed === '') {
            return $empty;
        }

        // The conversion itself: any malformed input throws RonException (caught here).
        try {
            if ($mode === 'ron2json') {
                $output = Ron::toJson($trimmed, pretty: $pretty);
                $json = Ron::toJson($trimmed); // compact, valid JSON for the stats pass
            } else {
                $output = Ron::fromJson($trimmed, pretty: $pretty);
                $json = $trimmed;
            }
        } catch (\Throwable $e) {
            return [...$empty, 'error' => self::message($e)];
        }

        $result = [...$empty, 'output' => $output, 'inBytes' => \strlen($src), 'outBytes' => \strlen($output)];

        // Stats + hash are best-effort: a failure here must never hide a good conversion.
        try {
            $compactJson = Ron::canonicalJson($json);
            $compactRon = Ron::canonicalRon($json);
            $cj = \strlen($compactJson);
            $cr = \strlen($compactRon);

            $result['compactJsonBytes'] = $cj;
            $result['compactRonBytes'] = $cr;
            $result['savedPct'] = $cj > 0 ? (int) round(100 * ($cj - $cr) / $cj) : null;
            $result['hash'] = Ron::canonicalHash($json);
        } catch (\Throwable) {
            // leave stats null
        }

        return $result;
    }

    /**
     * @return array{
     *     output: string, error: ?string, mode: string, toRon: bool,
     *     inBytes: int, outBytes: int,
     *     compactJsonBytes: ?int, compactRonBytes: ?int, savedPct: ?int, hash: ?string
     * }
     */
    private static function empty(string $mode): array {
        return [
            'output' => '',
            'error' => null,
            'mode' => $mode,
            'toRon' => $mode === 'json2ron',
            'inBytes' => 0,
            'outBytes' => 0,
            'compactJsonBytes' => null,
            'compactRonBytes' => null,
            'savedPct' => null,
            'hash' => null,
        ];
    }

    /** Keep error text short and free of stack noise for display in the output pane. */
    private static function message(\Throwable $e): string {
        $msg = trim($e->getMessage());

        return $msg === '' ? 'Invalid input.' : $msg;
    }
}
