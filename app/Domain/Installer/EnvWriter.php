<?php

namespace App\Domain\Installer;

use RuntimeException;

/**
 * Rewrites .env values in place: existing keys are replaced, missing keys
 * appended. Comments, ordering and unrelated lines are preserved.
 */
class EnvWriter
{
    public function __construct(private readonly string $path) {}

    public static function forApp(): self
    {
        return new self(base_path('.env'));
    }

    /**
     * @param  array<string, string|int|null>  $pairs
     */
    public function write(array $pairs): void
    {
        if (! file_exists($this->path)) {
            $example = dirname($this->path).DIRECTORY_SEPARATOR.'.env.example';
            if (! file_exists($example)) {
                throw new RuntimeException('Neither .env nor .env.example exists.');
            }
            copy($example, $this->path);
        }

        $contents = file_get_contents($this->path);
        if ($contents === false) {
            throw new RuntimeException('.env is not readable.');
        }

        foreach ($pairs as $key => $value) {
            $line = $key.'='.$this->formatValue($value);
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

            if (preg_match($pattern, $contents) === 1) {
                $contents = (string) preg_replace($pattern, $line, $contents, 1);
            } else {
                $contents = rtrim($contents, "\r\n").PHP_EOL.$line.PHP_EOL;
            }
        }

        if (file_put_contents($this->path, $contents) === false) {
            throw new RuntimeException('.env is not writable.');
        }
    }

    /**
     * Delete keys outright. Not the same as writing an empty value: `INSTALL=`
     * would still be a present key, and the installer gate reads absence.
     *
     * @param  list<string>  $keys
     */
    public function remove(array $keys): void
    {
        if (! file_exists($this->path)) {
            return;
        }

        $contents = file_get_contents($this->path);
        if ($contents === false) {
            throw new RuntimeException('.env is not readable.');
        }

        foreach ($keys as $key) {
            // Takes the line's own newline with it, so removing a key does not
            // leave a blank line behind on every install.
            $contents = (string) preg_replace('/^'.preg_quote($key, '/').'=.*(\R|$)/m', '', $contents);
        }

        if (file_put_contents($this->path, $contents) === false) {
            throw new RuntimeException('.env is not writable.');
        }
    }

    private function formatValue(string|int|null $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $value = (string) $value;

        // Quote anything that would break naive parsing.
        if (preg_match('/[\s#"\'=]/', $value) === 1) {
            return '"'.str_replace('"', '\\"', $value).'"';
        }

        return $value;
    }
}
