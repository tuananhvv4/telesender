<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Models\UserNotification;

class NotificationController extends Controller
{
    public function index(Request $request): void
    {
        $result = (new UserNotification())->paginateForUser(
            (int) auth()->id(),
            (int) $request->query('page', 1),
            pagination_per_page(20)
        );

        $this->render('notifications/index', [
            'title' => 'Thông báo',
            'notifications' => $result['items'],
            'pagination' => $result['pagination'],
        ]);
    }

    public function markRead(Request $request): void
    {
        app()->db()->update('user_notifications', [
            'read_at' => gmdate('Y-m-d H:i:s'),
        ], 'id = :id AND user_id = :user_id', [
            'id' => (int) $request->input('notification_id'),
            'user_id' => (int) auth()->id(),
        ]);

        $this->redirectWith('/notifications', success: 'Đã đánh dấu thông báo là đã đọc.');
    }

    public function markAllRead(Request $request): void
    {
        app()->db()->query(
            'UPDATE user_notifications SET read_at = UTC_TIMESTAMP() WHERE user_id = :user_id AND read_at IS NULL',
            ['user_id' => (int) auth()->id()]
        );

        $this->redirectWith('/notifications', success: 'Đã đánh dấu tất cả thông báo là đã đọc.');
    }
}
