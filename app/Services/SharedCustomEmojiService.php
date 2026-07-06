<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CustomEmoji;
use App\Models\User;

class SharedCustomEmojiService
{
    public function __construct(
        private readonly CustomEmoji $customEmojis = new CustomEmoji(),
        private readonly User $users = new User()
    ) {
    }

    public function sourceSummaryForUser(int $targetUserId): array
    {
        $sourceUser = $this->users->firstSuperAdmin();

        if ($sourceUser === null) {
            return [
                'source_available' => false,
                'source_user_id' => null,
                'source_user_name' => null,
                'source_count' => 0,
                'is_source_owner' => false,
            ];
        }

        $sourceUserId = (int) $sourceUser['id'];

        return [
            'source_available' => true,
            'source_user_id' => $sourceUserId,
            'source_user_name' => (string) ($sourceUser['name'] ?? 'Super admin'),
            'source_count' => count($this->customEmojis->activeForUser($sourceUserId)),
            'is_source_owner' => $sourceUserId === $targetUserId,
        ];
    }

    public function sharedActiveForUser(int $targetUserId): array
    {
        $summary = $this->sourceSummaryForUser($targetUserId);

        if (!$summary['source_available'] || $summary['is_source_owner']) {
            return [];
        }

        $ownedSlugs = $this->targetSlugSet($targetUserId);
        $shared = [];

        foreach ($this->customEmojis->activeForUser((int) $summary['source_user_id']) as $emoji) {
            $slug = mb_strtolower((string) ($emoji['slug'] ?? ''));

            if ($slug === '') {
                continue;
            }

            $shared[] = $this->decorateSharedEmoji(
                $emoji,
                (string) $summary['source_user_name'],
                (int) $summary['source_user_id'],
                isset($ownedSlugs[$slug])
            );
        }

        return $shared;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function sharedMapForUser(int $targetUserId): array
    {
        $summary = $this->sourceSummaryForUser($targetUserId);

        if (!$summary['source_available'] || $summary['is_source_owner']) {
            return [];
        }

        $map = [];

        foreach ($this->customEmojis->listForUser((int) $summary['source_user_id']) as $emoji) {
            $slug = mb_strtolower((string) ($emoji['slug'] ?? ''));

            if ($slug === '') {
                continue;
            }

            $map[$slug] = $this->decorateSharedEmoji(
                $emoji,
                (string) $summary['source_user_name'],
                (int) $summary['source_user_id'],
                false
            );
        }

        return $map;
    }

    /**
     * @return array<string, bool>
     */
    private function targetSlugSet(int $targetUserId): array
    {
        $set = [];

        foreach ($this->customEmojis->allByUser($targetUserId, 'id DESC') as $emoji) {
            $slug = mb_strtolower((string) ($emoji['slug'] ?? ''));

            if ($slug === '') {
                continue;
            }

            $set[$slug] = true;
        }

        return $set;
    }

    private function decorateSharedEmoji(array $emoji, string $sourceUserName, int $sourceUserId, bool $isOverridden): array
    {
        $emoji['library_scope'] = 'shared';
        $emoji['scope_label'] = 'Dùng chung';
        $emoji['source_user_name'] = $sourceUserName;
        $emoji['source_user_id'] = $sourceUserId;
        $emoji['is_shared'] = 1;
        $emoji['is_overridden'] = $isOverridden ? 1 : 0;

        return $emoji;
    }
}
