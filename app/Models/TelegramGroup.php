<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class TelegramGroup extends Model
{
    protected string $table = 'telegram_groups';
    protected array $fillable = [
        'user_id',
        'telegram_account_id',
        'title',
        'peer_identifier',
        'topic_id',
        'topic_title',
        'notes',
        'is_active',
        'created_at',
        'updated_at',
    ];

    public function listForUser(int $userId): array
    {
        return $this->db()->fetchAll(
            'SELECT tg.*, ta.name AS account_name
             FROM telegram_groups tg
             INNER JOIN telegram_accounts ta ON ta.id = tg.telegram_account_id
             WHERE tg.user_id = :user_id
             ORDER BY tg.id DESC',
            ['user_id' => $userId]
        );
    }

    public function paginateForUser(int $userId, int $page = 1, int $perPage = 20): array
    {
        return $this->paginateQuery(
            'SELECT COUNT(*) AS aggregate
             FROM telegram_groups
             WHERE user_id = :user_id',
            'SELECT tg.*, ta.name AS account_name
             FROM telegram_groups tg
             INNER JOIN telegram_accounts ta ON ta.id = tg.telegram_account_id
             WHERE tg.user_id = :user_id
             ORDER BY tg.id DESC',
            ['user_id' => $userId],
            $page,
            $perPage
        );
    }

    public function findDuplicateForUser(
        int $userId,
        int $accountId,
        string $peerIdentifier,
        ?int $topicId,
        ?int $ignoreId = null
    ): ?array {
        $bindings = [
            'user_id' => $userId,
            'telegram_account_id' => $accountId,
            'peer_identifier' => $peerIdentifier,
            'topic_id' => $topicId,
        ];

        $sql = 'SELECT *
                FROM telegram_groups
                WHERE user_id = :user_id
                  AND telegram_account_id = :telegram_account_id
                  AND peer_identifier = :peer_identifier
                  AND topic_id <=> :topic_id';

        if ($ignoreId !== null) {
            $sql .= ' AND id != :ignore_id';
            $bindings['ignore_id'] = $ignoreId;
        }

        $sql .= ' LIMIT 1';

        return $this->db()->fetch($sql, $bindings);
    }

    public function peerUsageSummaryForAccount(int $userId, int $accountId): array
    {
        $rows = $this->db()->fetchAll(
            'SELECT peer_identifier,
                    COUNT(*) AS existing_count,
                    SUM(CASE WHEN topic_id IS NULL THEN 1 ELSE 0 END) AS root_count
             FROM telegram_groups
             WHERE user_id = :user_id
               AND telegram_account_id = :telegram_account_id
             GROUP BY peer_identifier',
            [
                'user_id' => $userId,
                'telegram_account_id' => $accountId,
            ]
        );

        $summary = [];

        foreach ($rows as $row) {
            $peerIdentifier = (string) ($row['peer_identifier'] ?? '');

            if ($peerIdentifier === '') {
                continue;
            }

            $summary[$peerIdentifier] = [
                'existing_count' => (int) ($row['existing_count'] ?? 0),
                'has_root' => (int) ($row['root_count'] ?? 0) > 0,
            ];
        }

        return $summary;
    }
}
