<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\TelegramAccount;
use App\Models\TelegramGroup;
use App\Services\TelegramService;
use RuntimeException;

class TelegramGroupController extends Controller
{
    public function __construct(
        private readonly TelegramGroup $groups = new TelegramGroup(),
        private readonly TelegramAccount $accounts = new TelegramAccount()
    ) {
    }

    public function index(Request $request): void
    {
        $userId = (int) auth()->id();
        $searchQuery = trim((string) $request->query('q', ''));
        $selectedAccountId = (int) $request->query('telegram_account_id', 0);
        $selectedStatus = trim((string) $request->query('status', ''));
        $perPage = pagination_per_page(20);
        $result = $this->groups->paginateForUser(
            $userId,
            (int) $request->query('page', 1),
            $perPage,
            [
                'query' => $searchQuery,
                'telegram_account_id' => $selectedAccountId,
                'status' => $selectedStatus,
            ]
        );

        $this->render('groups/index', [
            'title' => 'Telegram Groups',
            'groups' => $result['items'],
            'accounts' => $this->accounts->listForUser($userId),
            'pagination' => $result['pagination'],
            'searchQuery' => $searchQuery,
            'selectedAccountId' => $selectedAccountId,
            'selectedStatus' => $selectedStatus,
        ]);
    }

    public function store(Request $request): void
    {
        $userId = (int) auth()->id();
        $payload = $this->validatedGroupInput($request, $userId, '/groups');

        $this->groups->create([
            'user_id' => $userId,
            ...$payload,
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $this->redirectWith('/groups', success: 'Đã thêm nhóm Telegram.');
    }

    public function update(Request $request): void
    {
        $userId = (int) auth()->id();
        $groupId = (int) $request->input('id');
        $group = $this->groups->findForUser($groupId, $userId);

        if ($group === null) {
            abort404();
        }

        $payload = $this->validatedGroupInput($request, $userId, '/groups', $groupId);

        $this->groups->updateById($groupId, [
            ...$payload,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $this->redirectWith('/groups', success: 'Đã cập nhật nhóm Telegram.');
    }

    public function delete(Request $request): void
    {
        $group = $this->groups->findForUser((int) $request->input('id'), (int) auth()->id());

        if ($group === null) {
            abort404();
        }

        $this->groups->deleteById((int) $group['id']);
        $this->redirectWith('/groups', success: 'Đã xóa nhóm Telegram.');
    }

    public function topics(Request $request): void
    {
        $userId = (int) auth()->id();
        $accountId = (int) $request->query('account_id');
        $peer = trim((string) $request->query('peer_identifier'));

        if ($accountId <= 0 || $peer === '') {
            Response::json([
                'ok' => false,
                'message' => 'Bạn cần chọn account và nhập ID nhóm trước khi tải topic.',
            ], 422);
        }

        $account = $this->ownedAccount($accountId, $userId);

        try {
            $this->ensureReadyAccountSession($account);
            $topics = (new TelegramService())->getForumTopics($account, $peer);
            Response::json([
                'ok' => true,
                'topics' => $topics,
            ]);
        } catch (\Throwable $exception) {
            Response::json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function dialogs(Request $request): void
    {
        $userId = (int) auth()->id();
        $accountId = (int) $request->query('account_id');

        if ($accountId <= 0) {
            Response::json([
                'ok' => false,
                'message' => 'Bạn cần chọn account trước khi tải danh sách nhóm.',
            ], 422);
        }

        $account = $this->ownedAccount($accountId, $userId);

        try {
            $this->ensureReadyAccountSession($account);

            $dialogs = (new TelegramService())->getAvailableGroups($account);
            $usageSummary = $this->groups->peerUsageSummaryForAccount($userId, $accountId);

            foreach ($dialogs as &$dialog) {
                $peerIdentifier = (string) ($dialog['peer_identifier'] ?? '');
                $usage = $usageSummary[$peerIdentifier] ?? [
                    'existing_count' => 0,
                    'has_root' => false,
                ];

                $dialog['already_added'] = (bool) ($usage['has_root'] ?? false);
                $dialog['existing_count'] = (int) ($usage['existing_count'] ?? 0);
            }
            unset($dialog);

            Response::json([
                'ok' => true,
                'dialogs' => $dialogs,
            ]);
        } catch (\Throwable $exception) {
            Response::json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function import(Request $request): void
    {
        $userId = (int) auth()->id();
        $accountId = (int) $request->input('telegram_account_id');

        if ($accountId <= 0) {
            $this->redirectWith('/groups', error: 'Bạn cần chọn account trước khi import nhóm.');
        }

        $this->ownedAccount($accountId, $userId);

        $dialogs = $this->decodeSelectedDialogs($request->input('selected_dialogs', []));

        if ($dialogs === []) {
            $this->redirectWith('/groups', error: 'Hãy chọn ít nhất một nhóm hợp lệ để import.');
        }

        $usageSummary = $this->groups->peerUsageSummaryForAccount($userId, $accountId);
        $now = gmdate('Y-m-d H:i:s');

        $result = app()->db()->transaction(function () use ($dialogs, $usageSummary, $userId, $accountId, $now): array {
            $created = 0;
            $skipped = 0;

            foreach ($dialogs as $dialog) {
                $peerIdentifier = (string) $dialog['peer_identifier'];
                $usage = $usageSummary[$peerIdentifier] ?? [
                    'existing_count' => 0,
                    'has_root' => false,
                ];

                if ((bool) ($usage['has_root'] ?? false)) {
                    $skipped++;
                    continue;
                }

                $this->groups->create([
                    'user_id' => $userId,
                    'telegram_account_id' => $accountId,
                    'title' => (string) $dialog['title'],
                    'peer_identifier' => $peerIdentifier,
                    'topic_id' => null,
                    'topic_title' => null,
                    'notes' => null,
                    'is_active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $usageSummary[$peerIdentifier] = [
                    'existing_count' => (int) ($usage['existing_count'] ?? 0) + 1,
                    'has_root' => true,
                ];

                $created++;
            }

            return [
                'created' => $created,
                'skipped' => $skipped,
            ];
        });

        $created = (int) ($result['created'] ?? 0);
        $skipped = (int) ($result['skipped'] ?? 0);

        if ($created > 0 && $skipped > 0) {
            $this->redirectWith('/groups', success: 'Đã import ' . $created . ' nhóm từ Telegram. Bỏ qua ' . $skipped . ' nhóm đã có sẵn.');
        }

        if ($created > 0) {
            $this->redirectWith('/groups', success: 'Đã import ' . $created . ' nhóm từ Telegram.');
        }

        $this->redirectWith('/groups', success: 'Các nhóm đã chọn đã tồn tại sẵn, không cần import thêm.');
    }

    private function ownedAccount(int $accountId, int $userId): array
    {
        $account = $this->accounts->findForUser($accountId, $userId);

        if ($account === null) {
            abort404();
        }

        return $account;
    }

    private function validatedGroupInput(Request $request, int $userId, string $errorPath, ?int $ignoreGroupId = null): array
    {
        $title = trim((string) $request->input('title'));
        $peer = trim((string) $request->input('peer_identifier'));
        $topicId = $this->normalizeTopicId((string) $request->input('topic_id'), $errorPath);
        $topicTitle = trim((string) $request->input('topic_title'));
        $notes = trim((string) $request->input('notes'));
        $accountId = (int) $request->input('telegram_account_id');

        if ($title === '' || $peer === '' || $accountId <= 0) {
            $this->redirectWith($errorPath, error: 'Bạn cần chọn account, tên nhóm và peer identifier.');
        }

        $this->ownedAccount($accountId, $userId);

        if ($this->groups->findDuplicateForUser($userId, $accountId, $peer, $topicId, $ignoreGroupId) !== null) {
            $this->redirectWith($errorPath, error: $this->duplicateGroupMessage($topicId));
        }

        return [
            'telegram_account_id' => $accountId,
            'title' => $title,
            'peer_identifier' => $peer,
            'topic_id' => $topicId,
            'topic_title' => $topicTitle !== '' ? $topicTitle : null,
            'notes' => $notes !== '' ? $notes : null,
            'is_active' => $request->input('is_active') ? 1 : 0,
        ];
    }

    private function ensureReadyAccountSession(array $account): void
    {
        if ((string) ($account['session_status'] ?? '') !== 'active') {
            throw new RuntimeException('Tài khoản Telegram này chưa đăng nhập xong. Hãy hoàn tất OTP / 2FA trước khi tải dữ liệu từ Telegram.');
        }
    }

    private function duplicateGroupMessage(?int $topicId): string
    {
        if ($topicId !== null) {
            return 'Nhóm Telegram với topic này đã tồn tại cho account đã chọn.';
        }

        return 'Nhóm Telegram này đã tồn tại cho account đã chọn.';
    }

    private function normalizeTopicId(string $raw, string $errorPath = '/groups'): ?int
    {
        $raw = trim($raw);

        if ($raw === '') {
            return null;
        }

        if (preg_match('/(\d+)(?:\/)?$/', $raw, $matches) === 1) {
            return (int) $matches[1];
        }

        $this->redirectWith($errorPath, error: 'Topic ID không hợp lệ. Bạn có thể nhập số ID hoặc dán link topic.');
    }

    private function decodeSelectedDialogs(mixed $rawDialogs): array
    {
        if (!is_array($rawDialogs)) {
            return [];
        }

        $dialogs = [];
        $seenPeers = [];

        foreach ($rawDialogs as $rawDialog) {
            if (!is_string($rawDialog) || trim($rawDialog) === '') {
                continue;
            }

            $dialog = json_decode($rawDialog, true);

            if (!is_array($dialog)) {
                continue;
            }

            $title = trim((string) ($dialog['title'] ?? ''));
            $peerIdentifier = trim((string) ($dialog['peer_identifier'] ?? ''));

            if ($title === '' || $peerIdentifier === '' || isset($seenPeers[$peerIdentifier])) {
                continue;
            }

            $dialogs[] = [
                'title' => $title,
                'peer_identifier' => $peerIdentifier,
            ];
            $seenPeers[$peerIdentifier] = true;
        }

        return $dialogs;
    }
}
