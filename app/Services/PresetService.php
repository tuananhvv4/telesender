<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

class PresetService
{
    public function __construct(private readonly Database $db)
    {
    }

    public function labelPresets(): array
    {
        return (array) config('presets.labels', []);
    }

    public function templatePresets(): array
    {
        return (array) config('presets.templates', []);
    }

    public function schedulePresets(): array
    {
        return (array) config('presets.schedules', []);
    }

    public function installStarterKit(int $userId): array
    {
        $createdLabels = 0;
        $createdTemplates = 0;
        $labelIdsBySlug = [];
        $existingLabels = $this->db->fetchAll(
            'SELECT id, slug FROM message_labels WHERE user_id = :user_id',
            ['user_id' => $userId]
        );

        foreach ($existingLabels as $label) {
            $labelIdsBySlug[$label['slug']] = (int) $label['id'];
        }

        foreach ($this->labelPresets() as $preset) {
            if (isset($labelIdsBySlug[$preset['slug']])) {
                continue;
            }

            $labelId = $this->db->insert('message_labels', [
                'user_id' => $userId,
                'name' => $preset['name'],
                'slug' => $preset['slug'],
                'color' => $preset['color'],
                'created_at' => gmdate('Y-m-d H:i:s'),
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]);

            $labelIdsBySlug[$preset['slug']] = $labelId;
            $createdLabels++;
        }

        $existingTemplateNames = $this->db->fetchAll(
            'SELECT name FROM message_templates WHERE user_id = :user_id',
            ['user_id' => $userId]
        );
        $nameSet = [];
        foreach ($existingTemplateNames as $template) {
            $nameSet[mb_strtolower((string) $template['name'])] = true;
        }

        foreach ($this->templatePresets() as $preset) {
            $templateNameKey = mb_strtolower((string) $preset['name']);
            if (isset($nameSet[$templateNameKey])) {
                continue;
            }

            $this->db->insert('message_templates', [
                'user_id' => $userId,
                'label_id' => $labelIdsBySlug[$preset['label_slug']] ?? null,
                'name' => $preset['name'],
                'body' => $preset['body'],
                'parse_mode' => $preset['parse_mode'],
                'is_active' => 1,
                'created_at' => gmdate('Y-m-d H:i:s'),
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]);

            $nameSet[$templateNameKey] = true;
            $createdTemplates++;
        }

        return [
            'created_labels' => $createdLabels,
            'created_templates' => $createdTemplates,
        ];
    }
}
