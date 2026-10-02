<?php

declare(strict_types=1);

namespace App;

/**
 * The business knowledge base, edited from the admin panel.
 * The live copy is storage/knowledge.md (not in git, so panel edits never
 * block "Update from Remote"); kb/knowledge.md is only the starting template.
 */
final class KnowledgeBase
{
    public const MAX_BYTES = 60_000;

    public static function path(): string
    {
        $path = Env::get('KB_PATH', 'storage/knowledge.md');
        return str_starts_with($path, '/') ? $path : BASE_PATH . '/' . $path;
    }

    public static function templatePath(): string
    {
        return BASE_PATH . '/kb/knowledge.md';
    }

    public static function read(): string
    {
        if (is_file(self::path())) {
            return (string) file_get_contents(self::path());
        }
        return is_file(self::templatePath()) ? (string) file_get_contents(self::templatePath()) : '';
    }

    /** Text the AI sees: HTML comments (editing notes) removed. */
    public static function forPrompt(): string
    {
        return trim((string) preg_replace('/<!--.*?-->/s', '', self::read()));
    }

    /** True once the owner has added real content beyond the template headings. */
    public static function isFilled(): bool
    {
        $content = preg_replace('/^#.*$|^\(.*\)$/m', '', self::forPrompt());
        return mb_strlen(trim((string) $content)) >= 40;
    }

    public static function write(string $content): void
    {
        $content = str_replace("\r\n", "\n", $content);
        if (strlen($content) > self::MAX_BYTES) {
            throw new \RuntimeException('Knowledge base is too long.');
        }
        if (file_put_contents(self::path(), $content, LOCK_EX) === false) {
            throw new \RuntimeException('Could not write storage/knowledge.md. Check folder permissions.');
        }
    }
}
