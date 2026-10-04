<?php

declare(strict_types=1);

namespace RonPlayground;

/**
 * Renders the plain PHP templates in templates/. A template sees its data as variables and $e()
 * for escaping. Templates do no I/O, so a coroutine never yields inside the output buffer.
 */
final class Template {
    /**
     * @param array<string, mixed> $data
     */
    public static function render(string $name, array $data = []): string {
        $file = \dirname(__DIR__) . "/templates/{$name}.php";
        $data['e'] = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        ob_start();
        try {
            (static function (string $__file, array $__data): void {
                extract($__data, EXTR_SKIP);
                require $__file;
            })($file, $data);

            return (string) ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();

            throw $e;
        }
    }
}
