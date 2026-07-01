<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class DispatchLog extends Model
{
    protected string $table = 'dispatch_logs';
    protected array $fillable = [
        'user_id',
        'schedule_job_id',
        'telegram_account_id',
        'telegram_group_id',
        'message_template_id',
        'label_id',
        'request_id',
        'status',
        'message_preview',
        'response_payload',
        'error_message',
        'sent_at',
        'created_at',
    ];

    public function recentForUser(int $userId, int $limit = 50): array
    {
        $logs = $this->db()->fetchAll(
            "SELECT dl.*, ta.name AS account_name, tg.title AS group_title, mt.name AS template_name, ml.name AS label_name
                    , tg.topic_id AS target_topic_id, tg.topic_title AS target_topic_title
             FROM dispatch_logs dl
             LEFT JOIN telegram_accounts ta ON ta.id = dl.telegram_account_id
             LEFT JOIN telegram_groups tg ON tg.id = dl.telegram_group_id
             LEFT JOIN message_templates mt ON mt.id = dl.message_template_id
             LEFT JOIN message_labels ml ON ml.id = dl.label_id
             WHERE dl.user_id = :user_id
             ORDER BY dl.id DESC
            LIMIT {$limit}",
            ['user_id' => $userId]
        );

        return array_map(fn (array $log): array => $this->decorateTopicMeta($log), $logs);
    }

    private function decorateTopicMeta(array $log): array
    {
        $targetTopicId = isset($log['target_topic_id']) && $log['target_topic_id'] !== null
            ? (int) $log['target_topic_id']
            : null;
        $targetTopicTitle = trim((string) ($log['target_topic_title'] ?? ''));
        $actualTopicId = $this->extractActualTopicId((string) ($log['response_payload'] ?? ''));

        $log['target_topic_label'] = $this->topicLabel($targetTopicId, $targetTopicTitle, true);
        $log['actual_topic_id'] = $actualTopicId;
        $log['actual_topic_label'] = $actualTopicId !== null
            ? $this->topicLabel(
                $actualTopicId,
                $actualTopicId === $targetTopicId ? $targetTopicTitle : '',
                false
            )
            : null;
        $log['topic_mismatch'] = $actualTopicId !== null
            && $targetTopicId !== null
            && $actualTopicId !== $targetTopicId;

        return $log;
    }

    private function extractActualTopicId(string $payload): ?int
    {
        if ($payload === '') {
            return null;
        }

        $decoded = json_decode($payload, true);
        if (!is_array($decoded)) {
            return null;
        }

        $topicId = $decoded['result']['topicId']
            ?? $decoded['topicId']
            ?? null;

        return is_numeric($topicId) ? (int) $topicId : null;
    }

    private function topicLabel(?int $topicId, string $topicTitle = '', bool $allowGeneralFallback = false): string
    {
        if ($topicId === null) {
            return $allowGeneralFallback ? 'General' : 'Không rõ';
        }

        if ($topicId === 1) {
            return 'General';
        }

        $label = $topicTitle !== '' ? $topicTitle : ('Topic #' . $topicId);

        return $label . ' (#' . $topicId . ')';
    }
}
