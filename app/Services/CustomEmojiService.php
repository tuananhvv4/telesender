<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CustomEmoji;
use RuntimeException;

class CustomEmojiService
{
    private const TOKEN_PATTERN = '/\{\{\s*ce:([a-z0-9._-]+)\s*\}\}/i';

    public function __construct(
        private readonly CustomEmoji $customEmojis = new CustomEmoji()
    ) {
    }

    public function pickerLibrary(int $userId): array
    {
        return $this->customEmojis->activeForUser($userId);
    }

    public function allForUser(int $userId): array
    {
        return $this->customEmojis->listForUser($userId);
    }

    public function analyzeTemplate(string $body, string $parseMode, int $userId): array
    {
        $tokenSlugs = $this->extractTokenSlugs($body);
        $emojiMap = $this->emojiMap($userId);
        $used = [];
        $missing = [];

        foreach ($tokenSlugs as $slug) {
            if (!isset($emojiMap[$slug])) {
                $missing[] = $slug;
                continue;
            }

            $used[$slug] = $emojiMap[$slug];
        }

        $requiresHtml = $tokenSlugs !== [];
        $parseMode = strtoupper(trim($parseMode));
        $issues = [];

        if ($requiresHtml && $parseMode !== 'HTML') {
            $issues[] = 'Template có custom emoji thì parse mode phải là HTML.';
        }

        if ($missing !== []) {
            $issues[] = 'Không tìm thấy custom emoji cho token: ' . implode(', ', array_map(
                static fn (string $slug): string => '{{ce:' . $slug . '}}',
                $missing
            ));
        }

        return [
            'tokens' => $tokenSlugs,
            'used_emojis' => array_values($used),
            'missing_tokens' => array_values(array_unique($missing)),
            'requires_html' => $requiresHtml,
            'issues' => $issues,
            'compiled_html' => $this->compileBodyWithMap($body, $emojiMap, false),
            'fallback_preview' => $this->replaceTokensWithFallbackMap($body, $emojiMap),
        ];
    }

    public function ensureTemplateIsValid(string $body, string $parseMode, int $userId): array
    {
        $analysis = $this->analyzeTemplate($body, $parseMode, $userId);

        if ($analysis['issues'] !== []) {
            throw new RuntimeException(implode(' ', $analysis['issues']));
        }

        return $analysis;
    }

    public function compileForTelegram(string $body, int $userId): string
    {
        return $this->compileBodyWithMap($body, $this->emojiMap($userId), true);
    }

    public function replaceTokensWithFallback(string $body, int $userId): string
    {
        return $this->replaceTokensWithFallbackMap($body, $this->emojiMap($userId));
    }

    /**
     * @return array<int, string>
     */
    private function extractTokenSlugs(string $body): array
    {
        if (preg_match_all(self::TOKEN_PATTERN, $body, $matches) !== 1 && empty($matches[1])) {
            return [];
        }

        $slugs = array_map(
            static fn (string $slug): string => strtolower(trim($slug)),
            $matches[1] ?? []
        );

        return array_values(array_unique(array_filter($slugs, static fn (string $slug): bool => $slug !== '')));
    }

    private function compileBodyWithMap(string $body, array $emojiMap, bool $strict): string
    {
        return (string) preg_replace_callback(
            self::TOKEN_PATTERN,
            function (array $matches) use ($emojiMap, $strict): string {
                $slug = strtolower(trim((string) ($matches[1] ?? '')));

                if (!isset($emojiMap[$slug])) {
                    if ($strict) {
                        throw new RuntimeException('Không tìm thấy custom emoji cho token {{ce:' . $slug . '}}.');
                    }

                    return (string) $matches[0];
                }

                $emoji = $emojiMap[$slug];
                $fallback = htmlspecialchars((string) ($emoji['fallback_emoji'] ?? ''), ENT_QUOTES, 'UTF-8');
                $identifier = htmlspecialchars((string) ($emoji['emoji_identifier'] ?? ''), ENT_QUOTES, 'UTF-8');

                return '<tg-emoji emoji-id="' . $identifier . '">' . $fallback . '</tg-emoji>';
            },
            $body
        );
    }

    private function replaceTokensWithFallbackMap(string $body, array $emojiMap): string
    {
        return (string) preg_replace_callback(
            self::TOKEN_PATTERN,
            static function (array $matches) use ($emojiMap): string {
                $slug = strtolower(trim((string) ($matches[1] ?? '')));

                if (!isset($emojiMap[$slug])) {
                    return (string) $matches[0];
                }

                $emoji = $emojiMap[$slug];

                return (string) ($emoji['fallback_emoji'] ?? ('[' . $slug . ']'));
            },
            $body
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function emojiMap(int $userId): array
    {
        $map = [];

        foreach ($this->customEmojis->listForUser($userId) as $emoji) {
            $map[strtolower((string) $emoji['slug'])] = $emoji;
        }

        return $map;
    }
}
