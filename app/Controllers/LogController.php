<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Models\DispatchLog;
use App\Services\CustomEmojiService;

class LogController extends Controller
{
    public function __construct(
        private readonly DispatchLog $logs = new DispatchLog(),
        private readonly CustomEmojiService $customEmojiService = new CustomEmojiService()
    ) {
    }

    public function index(Request $request): void
    {
        $searchQuery = trim((string) $request->query('q', ''));
        $perPage = pagination_per_page(50, [20, 50, 100, 200]);
        $result = $this->logs->paginateForUser((int) auth()->id(), (int) $request->query('page', 1), $perPage, $searchQuery);

        $this->render('logs/index', [
            'title' => 'Nhật ký gửi tin',
            'logs' => $result['items'],
            'customEmojis' => $this->customEmojiService->pickerLibrary((int) auth()->id()),
            'pagination' => $result['pagination'],
            'searchQuery' => $searchQuery,
        ]);
    }
}
