<?php

declare(strict_types=1);

$groupRecords = [];

foreach ($groups as $group) {
    $groupRecords[(int) $group['id']] = [
        'id' => (int) $group['id'],
        'telegram_account_id' => (int) $group['telegram_account_id'],
        'title' => (string) $group['title'],
        'peer_identifier' => (string) $group['peer_identifier'],
        'topic_id' => isset($group['topic_id']) && $group['topic_id'] !== null ? (int) $group['topic_id'] : null,
        'topic_title' => (string) ($group['topic_title'] ?? ''),
        'notes' => (string) ($group['notes'] ?? ''),
        'is_active' => (int) ($group['is_active'] ?? 1),
    ];
}
?>
<section class="stack">
    <div class="topbar">
        <h1 class="page-title">Nhóm Telegram</h1>
        <div class="inline-actions">
            <button class="button primary" type="button" id="open_group_create">
                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                Tạo nhóm mới
            </button>
            <button class="button secondary" type="button" id="open_group_import">
                <i class="fa-solid fa-table-cells-large" aria-hidden="true"></i>
                Import nhanh nhiều nhóm
            </button>
        </div>
    </div>

    <section class="panel group-library-panel listing-panel" data-live-region="groups-panel">
        <script type="application/json" data-group-records><?= json_encode($groupRecords, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?></script>
        <div class="panel-header">
            <div>
                <h2 class="panel-title">Danh sách nhóm</h2>
                <p class="panel-copy">Lọc theo tài khoản, trạng thái hoặc từ khóa để quản lý danh sách nhóm lớn dễ hơn.</p>
            </div>

            <form class="toolbar-form" method="get" action="<?= e(url('/groups')) ?>">
                <?php if ((int) request()->query('per_page', 0) > 0): ?>
                    <input type="hidden" name="per_page" value="<?= e((string) request()->query('per_page')) ?>">
                <?php endif; ?>
                <div class="toolbar-search group-toolbar-search">
                    <select class="select" name="telegram_account_id">
                        <option value="">Tất cả tài khoản</option>
                        <?php foreach ($accounts as $account): ?>
                            <option value="<?= e((string) $account['id']) ?>" <?= (int) ($selectedAccountId ?? 0) === (int) $account['id'] ? 'selected' : '' ?>>
                                <?= e($account['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select class="select" name="status">
                        <option value="">Mọi trạng thái</option>
                        <option value="active" <?= ($selectedStatus ?? '') === 'active' ? 'selected' : '' ?>>Đang bật</option>
                        <option value="inactive" <?= ($selectedStatus ?? '') === 'inactive' ? 'selected' : '' ?>>Tạm tắt</option>
                    </select>

                    <input class="input" type="text" name="q" value="<?= e($searchQuery ?? '') ?>" placeholder="Tìm theo tên nhóm, peer ID, topic, ghi chú hoặc tài khoản...">
                    <button class="button secondary" type="submit">Lọc</button>
                    <?php if (($searchQuery ?? '') !== '' || (int) ($selectedAccountId ?? 0) > 0 || ($selectedStatus ?? '') !== ''): ?>
                        <a class="button secondary" href="<?= e(url('/groups')) ?>">Xóa lọc</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        <div class="panel-body groups-feed listing-body">
            <?php foreach ($groups as $group): ?>
                <article class="group-card">
                    <div class="group-card-head">
                        <div class="group-card-title">
                            <strong><?= e($group['title']) ?></strong>
                            <?php if (!empty($group['notes'])): ?>
                                <div class="small muted"><?= e($group['notes']) ?></div>
                            <?php endif; ?>
                        </div>
                        <span class="badge <?= (int) $group['is_active'] === 1 ? 'success' : 'warning' ?>"><?= (int) $group['is_active'] === 1 ? 'Đang bật' : 'Tạm tắt' ?></span>
                    </div>

                    <div class="group-meta-grid">
                        <div class="group-meta-card">
                            <span class="group-meta-label">Tài khoản</span>
                            <strong><?= e($group['account_name']) ?></strong>
                        </div>
                        <div class="group-meta-card">
                            <span class="group-meta-label">Peer / ID nhóm</span>
                            <span class="mono"><?= e($group['peer_identifier']) ?></span>
                        </div>
                        <div class="group-meta-card">
                            <span class="group-meta-label">Topic</span>
                            <?php if (!empty($group['topic_id'])): ?>
                                <div class="mono"><?= e((string) $group['topic_id']) ?></div>
                                <div class="small muted"><?= e($group['topic_title'] ?: 'Topic cụ thể') ?></div>
                            <?php else: ?>
                                <span class="muted">Topic chung / mặc định</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="inline-actions group-card-actions">
                        <button class="button secondary sm" type="button" data-group-edit="<?= e((string) $group['id']) ?>">Sửa</button>
                        <form method="post" action="<?= e(url('/groups/delete')) ?>" data-ajax-form data-ajax-refresh="groups-panel">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= e((string) $group['id']) ?>">
                            <button class="button danger sm" type="submit">Xóa</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
            <?php if ($groups === []): ?>
                <div class="muted">
                    <?= (($searchQuery ?? '') !== '' || (int) ($selectedAccountId ?? 0) > 0 || ($selectedStatus ?? '') !== '')
                        ? 'Không có nhóm nào khớp với bộ lọc hiện tại.'
                        : 'Chưa có nhóm nào.' ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="panel-body" style="padding-top: 0;">
            <?php $perPageOptions = [10, 15, 20, 30, 50, 100]; ?>
            <?php require base_path('views/partials/pagination.php'); ?>
        </div>
    </section>
</section>

<template id="group_editor_template">
    <div class="stack" data-group-editor-root>
        <form class="form-grid" method="post" action="<?= e(url('/groups')) ?>" data-group-editor-form>
            <?= csrf_field() ?>
            <div class="form-feedback" data-form-feedback hidden></div>

            <div class="group-form-grid">
                <div class="field group-field-span-2">
                    <label for="group_modal_account">Tài khoản Telegram</label>
                    <div class="group-account-row">
                        <select class="select" id="group_modal_account" name="telegram_account_id" required data-group-account>
                            <option value="">Chọn tài khoản</option>
                            <?php foreach ($accounts as $account): ?>
                                <option value="<?= e((string) $account['id']) ?>">
                                    <?= e($account['name']) ?> (<?= e($account['phone_number']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button class="button secondary" type="button" data-group-load-dialogs>
                            <i class="fa-solid fa-cloud-arrow-down" aria-hidden="true"></i>
                            <span>Tải danh sách nhóm</span>
                        </button>
                    </div>
                    <div class="helper-row group-account-feedback">
                        <span class="group-dialog-status helper-text" data-group-dialogs-status role="status" aria-live="polite">Chọn account rồi bấm tải danh sách nhóm.</span>
                    </div>
                </div>

                <div class="field group-field-span-2">
                    <label for="group_modal_peer">Nhóm Telegram</label>
                    <select class="select" id="group_modal_peer" name="peer_identifier" required data-group-peer>
                        <option value="">Chọn account rồi bấm tải danh sách nhóm</option>
                    </select>
                    <div class="small muted group-form-hint">Sau khi tải danh sách, dropdown này sẽ hiển thị toàn bộ nhóm mà tài khoản đã tham gia.</div>
                    <div class="helper-row group-peer-feedback">
                        <span class="helper-text" data-group-peer-summary>Chưa chọn nhóm nào.</span>
                    </div>
                </div>

                <div class="field">
                    <label for="group_modal_title">Tên hiển thị</label>
                    <div class="small muted group-form-hint">Tự điền theo nhóm đã chọn, bạn vẫn có thể sửa lại để dễ phân biệt trong hệ thống.</div>
                    <input class="input" id="group_modal_title" type="text" name="title" value="" required data-group-title>
                </div>

                <div class="field">
                    <label for="group_modal_notes">Ghi chú</label>
                    <input class="input" id="group_modal_notes" type="text" name="notes" value="" placeholder="Ghi chú ngắn để dễ phân biệt" data-group-notes>
                </div>

                <div class="field group-field-span-2">
                    <div class="group-topic-head">
                        <div>
                            <div class="small muted group-form-hint">Tải topic sau khi đã chọn nhóm ở phía trên.</div>
                        </div>
                        <button class="button secondary sm" type="button" data-group-load-topics>
                            <i class="fa-solid fa-comments" aria-hidden="true"></i>
                            <span>Tải topic từ Telegram</span>
                        </button>
                    </div>
                    <select
                        class="select"
                        id="group_modal_topic_selector"
                        data-current-topic-id=""
                        data-current-topic-title=""
                        data-group-topic-selector
                    >
                        <option value="">Hãy tải topic từ Telegram trước</option>
                    </select>
                    <input type="hidden" name="topic_id" value="" data-group-topic-id>
                    <div class="helper-row group-topic-feedback">
                        <span class="helper-text" data-group-topics-status role="status" aria-live="polite">Chọn nhóm rồi tải topic để xác nhận đích gửi.</span>
                    </div>
                </div>

                <div class="field group-field-span-2">
                    <label for="group_modal_topic_title">Tên topic</label>
                    <input class="input" id="group_modal_topic_title" type="text" name="topic_title" value="" placeholder="Ví dụ: Chợ Mới" data-group-topic-title>
                </div>
            </div>

            <label class="checkbox-row">
                <input type="checkbox" name="is_active" value="1" checked data-group-is-active>
                <span>Kích hoạt nhóm này</span>
            </label>

            <div class="actions">
                <div class="group-save-requirement" data-group-save-requirement aria-live="polite">
                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                    <span>Cần tải danh sách nhóm và topic trước khi lưu.</span>
                </div>
                <button class="button primary" type="submit" data-group-submit data-loading-text="Đang lưu..." disabled>Lưu nhóm</button>
                <button class="button secondary" type="button" data-crud-modal-close>Hủy</button>
            </div>
        </form>
    </div>
</template>

<template id="group_import_template">
    <div class="stack" data-group-import-root>
        <section class="group-import-shell">
            <div class="group-import-head">
                <div class="group-import-head-copy">
                    <h3 class="section-title">Import nhanh nhiều nhóm</h3>
                    <div class="small muted">Chọn account, tải danh sách nhóm đã tham gia rồi tick nhiều nhóm để import hàng loạt.</div>
                </div>
                <div class="small muted group-dialog-browser-meta" data-group-dialogs-meta>Chưa tải danh sách nhóm.</div>
            </div>

            <div class="group-dialog-browser-body">
                <div class="field">
                    <label for="group_import_account">Tài khoản Telegram</label>
                    <div class="group-account-row">
                        <select class="select" id="group_import_account" name="telegram_account_id" required data-group-account>
                            <option value="">Chọn tài khoản</option>
                            <?php foreach ($accounts as $account): ?>
                                <option value="<?= e((string) $account['id']) ?>">
                                    <?= e($account['name']) ?> (<?= e($account['phone_number']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button class="button secondary" type="button" data-group-load-dialogs>
                            <i class="fa-solid fa-cloud-arrow-down" aria-hidden="true"></i>
                            <span>Tải danh sách nhóm</span>
                        </button>
                    </div>
                    <div class="helper-row group-account-feedback">
                        <span class="group-dialog-status helper-text" data-group-dialogs-status role="status" aria-live="polite">Chọn account rồi bấm tải danh sách nhóm.</span>
                    </div>
                </div>

                <div class="small muted">Nếu Telegram trả về `@username` hoặc `invite link`, hệ thống sẽ hiển thị ngay trong danh sách để bạn dễ đối chiếu trước khi import.</div>

                <div class="form-feedback" data-form-feedback data-group-import-feedback hidden></div>

                <div class="group-dialog-toolbar" hidden data-group-dialogs-toolbar>
                    <div class="field group-dialog-search-field">
                        <label class="sr-only" for="group_import_dialogs_search">Tìm nhóm đã tải</label>
                        <input class="input" id="group_import_dialogs_search" type="search" placeholder="Tìm theo tên nhóm, @username hoặc peer ID" data-group-dialogs-search>
                    </div>

                    <div class="chip-row group-dialog-filter-row">
                        <button class="chip active" type="button" data-dialog-filter="all">Tất cả</button>
                        <button class="chip" type="button" data-dialog-filter="new">Chưa thêm</button>
                        <button class="chip" type="button" data-dialog-filter="existing">Đã thêm</button>
                        <button class="chip" type="button" data-dialog-filter="forum">Forum</button>
                    </div>

                    <div class="inline-actions group-dialog-toolbar-actions">
                        <button class="button secondary sm" type="button" data-group-select-visible>Chọn tất cả đang hiển thị</button>
                        <button class="button secondary sm" type="button" data-group-clear-selected>Bỏ chọn</button>
                    </div>
                </div>

                <form method="post" action="<?= e(url('/groups/import')) ?>" data-group-dialog-import-form>
                    <?= csrf_field() ?>
                    <input type="hidden" name="telegram_account_id" value="" data-group-dialog-import-account>
                    <div data-group-dialog-selected-inputs></div>

                    <div class="group-dialog-list" data-group-dialog-list>
                        <div class="group-dialog-empty">Chọn account rồi bấm "Tải danh sách nhóm" để lấy danh sách nhóm mà tài khoản đã tham gia, sau đó tick các nhóm cần import nhanh.</div>
                    </div>

                    <div class="group-dialog-import-bar" hidden data-group-dialog-import-bar>
                        <div class="small muted" data-group-dialog-selection-summary>Chưa chọn nhóm nào để import.</div>
                        <button class="button accent" type="submit" disabled data-group-import-selected data-loading-text="Đang import...">Import đã chọn</button>
                    </div>
                </form>
            </div>
        </section>
    </div>
</template>

<script>
document.addEventListener('DOMContentLoaded', () => {
(function () {
    const createButton = document.getElementById('open_group_create');
    const importButton = document.getElementById('open_group_import');
    const editorTemplate = document.getElementById('group_editor_template');
    const importTemplate = document.getElementById('group_import_template');
    const createUrl = <?= json_encode(url('/groups'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const updateUrl = <?= json_encode(url('/groups/update'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const dialogsUrl = <?= json_encode(url('/groups/dialogs'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const topicsUrl = <?= json_encode(url('/groups/topics'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    let groupRecords = window.TeleSenderApp?.readJsonScript('[data-group-records]', {}) || {};

    if (!editorTemplate || !importTemplate || !window.TeleSenderCrudModal || !window.TeleSenderApp) {
        return;
    }

    function requestAppModal(mode, options = {}) {
        return new Promise((resolve) => {
            const responseEvent = `app:modal:response:${Date.now()}:${Math.random().toString(16).slice(2)}`;
            const handleResponse = (event) => {
                document.removeEventListener(responseEvent, handleResponse);
                resolve(Boolean(event.detail?.confirmed));
            };

            document.addEventListener(responseEvent, handleResponse, { once: true });
            document.dispatchEvent(new CustomEvent('app:modal:open', {
                detail: {
                    mode,
                    options,
                    responseEvent,
                },
            }));
        });
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function createPeerOption(peerField, record) {
        peerField.innerHTML = '';

        const placeholderOption = document.createElement('option');
        placeholderOption.value = '';
        placeholderOption.textContent = 'Chọn account rồi bấm tải danh sách nhóm';
        peerField.appendChild(placeholderOption);

        if (!record.peer_identifier) {
            return;
        }

        const currentOption = document.createElement('option');
        currentOption.value = record.peer_identifier;
        currentOption.textContent = `${record.title || 'Nhóm hiện tại'} · ${record.peer_identifier}`;
        currentOption.selected = true;
        peerField.appendChild(currentOption);
        peerField.value = record.peer_identifier;
    }

    function createTopicOption(topicSelector, topicIdField, topicTitleField, record) {
        topicSelector.innerHTML = '';

        const generalOption = document.createElement('option');
        generalOption.value = '';
        generalOption.textContent = record.topic_id === null
            ? 'Hãy tải topic từ Telegram trước'
            : 'Topic chung / mặc định';
        topicSelector.appendChild(generalOption);

        if (record.topic_id !== null) {
            const currentOption = document.createElement('option');
            currentOption.value = String(record.topic_id);
            currentOption.textContent = record.topic_title || `Topic #${record.topic_id}`;
            currentOption.selected = true;
            topicSelector.appendChild(currentOption);
            topicSelector.value = String(record.topic_id);
            topicSelector.dataset.currentTopicId = String(record.topic_id);
            topicSelector.dataset.currentTopicTitle = record.topic_title || '';
            topicIdField.value = String(record.topic_id);
            topicTitleField.value = record.topic_title || '';
            return;
        }

        topicSelector.value = '';
        topicSelector.dataset.currentTopicId = '';
        topicSelector.dataset.currentTopicTitle = '';
        topicIdField.value = '';
        topicTitleField.value = '';
    }

    function normalizeTopicTitle(value) {
        return String(value || '').trim().toLowerCase();
    }

    function setAsyncButtonState(button, isLoading, loadingLabel) {
        if (!button) {
            return;
        }

        if (!button.dataset.originalHtml) {
            button.dataset.originalHtml = button.innerHTML;
        }

        button.disabled = isLoading;
        button.classList.toggle('is-loading', isLoading);
        button.setAttribute('aria-busy', isLoading ? 'true' : 'false');
        button.innerHTML = isLoading
            ? `<i class="fa-solid fa-circle-notch fa-spin" aria-hidden="true"></i><span>${escapeHtml(loadingLabel)}</span>`
            : button.dataset.originalHtml;
    }

    function dialogTypeLabel(type) {
        return type === 'supergroup' ? 'Supergroup' : 'Group';
    }

    function dialogFilterLabel(filter) {
        return {
            all: 'Tất cả',
            new: 'Chưa thêm',
            existing: 'Đã thêm',
            forum: 'Forum',
        }[filter] || 'Tất cả';
    }

    function formatDialogLink(link) {
        return String(link || '')
            .replace(/^https?:\/\//i, '')
            .replace(/\/+$/, '')
            .trim();
    }

    function dialogAccessLabels(dialog) {
        const labels = [];

        if (dialog.username) {
            labels.push('@' + dialog.username);
        }

        if (dialog.invite_link) {
            labels.push('Invite: ' + formatDialogLink(dialog.invite_link));
        }

        return labels;
    }

    function dialogOptionLabel(dialog) {
        const parts = [
            dialog.title,
            ...dialogAccessLabels(dialog),
            dialog.peer_identifier,
        ];

        if (dialog.already_added) {
            parts.push('Đã thêm');
        }

        return parts.filter(Boolean).join(' · ');
    }

    function mapLoadedDialogs(dialogs) {
        return Array.isArray(dialogs) ? dialogs.map((dialog) => ({
            title: String(dialog.title || ''),
            peer_identifier: String(dialog.peer_identifier || ''),
            type: String(dialog.type || 'chat'),
            is_forum: Boolean(dialog.is_forum),
            username: dialog.username ? String(dialog.username) : null,
            public_link: dialog.public_link ? String(dialog.public_link) : null,
            invite_link: dialog.invite_link ? String(dialog.invite_link) : null,
            participants_count: Number.isInteger(dialog.participants_count)
                ? dialog.participants_count
                : (dialog.participants_count === null ? null : Number(dialog.participants_count) || null),
            already_added: Boolean(dialog.already_added),
            existing_count: Number(dialog.existing_count || 0),
            selected: false,
        })) : [];
    }

    function initGroupDialogLoader(root, options = {}) {
        const accountField = options.accountField || root.querySelector('[data-group-account]');
        const peerField = options.peerField || root.querySelector('[data-group-peer]');
        const titleField = options.titleField || root.querySelector('[data-group-title]');
        const groupPeerSummary = root.querySelector('[data-group-peer-summary]');
        const loadDialogsButton = root.querySelector('[data-group-load-dialogs]');
        const groupDialogsStatus = root.querySelector('[data-group-dialogs-status]');
        const groupDialogsMeta = root.querySelector('[data-group-dialogs-meta]');
        const groupDialogsToolbar = root.querySelector('[data-group-dialogs-toolbar]');
        const groupDialogsSearch = root.querySelector('[data-group-dialogs-search]');
        const groupDialogList = root.querySelector('[data-group-dialog-list]');
        const groupDialogImportForm = root.querySelector('[data-group-dialog-import-form]');
        const groupDialogImportAccountId = root.querySelector('[data-group-dialog-import-account]');
        const groupDialogSelectedInputs = root.querySelector('[data-group-dialog-selected-inputs]');
        const groupDialogImportBar = root.querySelector('[data-group-dialog-import-bar]');
        const groupDialogSelectionSummary = root.querySelector('[data-group-dialog-selection-summary]');
        const importSelectedDialogsButton = root.querySelector('[data-group-import-selected]');
        const selectVisibleDialogsButton = root.querySelector('[data-group-select-visible]');
        const clearSelectedDialogsButton = root.querySelector('[data-group-clear-selected]');
        const dialogFilterButtons = Array.from(root.querySelectorAll('[data-dialog-filter]'));
        const feedbackTarget = root.querySelector('[data-group-import-feedback]');
        const supportsImportList = Boolean(groupDialogList && groupDialogImportForm && groupDialogsToolbar && groupDialogImportBar);

        let loadedDialogs = [];
        let activeDialogFilter = 'all';
        let activePickedPeer = String(peerField?.value || '').trim();
        let dialogsRequestSequence = 0;

        function setGroupDialogsStatus(message, tone = 'muted') {
            if (!groupDialogsStatus) {
                return;
            }

            groupDialogsStatus.textContent = message;
            groupDialogsStatus.classList.remove('muted', 'text-success', 'text-danger', 'is-loading');

            if (tone === 'loading') {
                groupDialogsStatus.classList.add('muted', 'is-loading');
                return;
            }

            if (tone === 'success') {
                groupDialogsStatus.classList.add('text-success');
                return;
            }

            if (tone === 'danger') {
                groupDialogsStatus.classList.add('text-danger');
                return;
            }

            groupDialogsStatus.classList.add('muted');
        }

        function setGroupDialogsMeta(message) {
            if (groupDialogsMeta) {
                groupDialogsMeta.textContent = message;
            }
        }

        function clearImportFeedback() {
            if (!feedbackTarget) {
                return;
            }

            feedbackTarget.hidden = true;
            feedbackTarget.className = 'form-feedback';
            feedbackTarget.textContent = '';
        }

        function updateDialogFilterButtons() {
            dialogFilterButtons.forEach((button) => {
                button.classList.toggle('active', button.dataset.dialogFilter === activeDialogFilter);
            });
        }

        function selectedPeerOptionLabel(peerIdentifier) {
            if (!peerField || !peerIdentifier) {
                return '';
            }

            const option = Array.from(peerField.options).find((item) => item.value === peerIdentifier);
            return option ? option.textContent.trim() : '';
        }

        function resetPeerSelector(placeholder, preserveCurrent = false) {
            if (!peerField) {
                return;
            }

            const currentValue = String(peerField.value || '').trim();
            const currentLabel = selectedPeerOptionLabel(currentValue) || currentValue;
            peerField.innerHTML = '';

            const placeholderOption = document.createElement('option');
            placeholderOption.value = '';
            placeholderOption.textContent = placeholder;
            peerField.appendChild(placeholderOption);

            if (preserveCurrent && currentValue !== '') {
                const currentOption = document.createElement('option');
                currentOption.value = currentValue;
                currentOption.textContent = currentLabel;
                currentOption.selected = true;
                peerField.appendChild(currentOption);
                peerField.value = currentValue;
                return;
            }

            peerField.value = '';
        }

        function populatePeerSelector() {
            if (!peerField) {
                return;
            }

            const preferredPeer = activePickedPeer;
            const preservedLabel = selectedPeerOptionLabel(preferredPeer) || preferredPeer;
            const sortedDialogs = [...loadedDialogs].sort((left, right) => {
                return left.title.localeCompare(right.title, 'vi', { sensitivity: 'base' })
                    || left.peer_identifier.localeCompare(right.peer_identifier, 'vi', { numeric: true });
            });

            peerField.innerHTML = '';

            const placeholderOption = document.createElement('option');
            placeholderOption.value = '';
            placeholderOption.textContent = 'Chọn một nhóm từ danh sách đã tải';
            peerField.appendChild(placeholderOption);

            sortedDialogs.forEach((dialog) => {
                const option = document.createElement('option');
                option.value = dialog.peer_identifier;
                option.textContent = dialogOptionLabel(dialog);
                if (dialog.peer_identifier === preferredPeer) {
                    option.selected = true;
                }
                peerField.appendChild(option);
            });

            if (preferredPeer !== '' && !sortedDialogs.some((dialog) => dialog.peer_identifier === preferredPeer)) {
                const currentOption = document.createElement('option');
                currentOption.value = preferredPeer;
                currentOption.textContent = preservedLabel;
                currentOption.selected = true;
                peerField.appendChild(currentOption);
                peerField.value = preferredPeer;
                return;
            }

            peerField.value = preferredPeer;
        }

        function syncPeerSummary() {
            if (!groupPeerSummary || !peerField) {
                return;
            }

            const peerIdentifier = String(peerField.value || '').trim();

            if (peerIdentifier === '') {
                groupPeerSummary.textContent = loadedDialogs.length > 0
                    ? `Đã tải ${loadedDialogs.length} nhóm. Chọn một nhóm trong dropdown phía trên.`
                    : (accountField?.value
                        ? 'Bấm "Tải danh sách nhóm" để nạp dropdown theo account đang chọn.'
                        : 'Chọn tài khoản rồi bấm tải danh sách nhóm để hiện dropdown.');
                return;
            }

            const dialog = loadedDialogs.find((item) => item.peer_identifier === peerIdentifier);

            if (!dialog) {
                const optionLabel = selectedPeerOptionLabel(peerIdentifier);
                groupPeerSummary.textContent = optionLabel
                    ? `Đang dùng cấu hình hiện tại: ${optionLabel}`
                    : `Đang dùng peer hiện tại: ${peerIdentifier}`;
                return;
            }

            const summaryParts = [
                `Đã chọn "${dialog.title}"`,
                dialog.peer_identifier,
                ...dialogAccessLabels(dialog),
                dialog.is_forum ? 'Forum' : dialogTypeLabel(dialog.type),
                dialog.already_added ? 'Đã có sẵn trong cấu hình' : '',
            ].filter(Boolean);

            groupPeerSummary.textContent = summaryParts.join(' · ');
        }

        function getFilteredDialogs() {
            const searchTerm = String(groupDialogsSearch?.value || '').trim().toLowerCase();

            return loadedDialogs.filter((dialog) => {
                if (activeDialogFilter === 'new' && dialog.already_added) {
                    return false;
                }

                if (activeDialogFilter === 'existing' && !dialog.already_added) {
                    return false;
                }

                if (activeDialogFilter === 'forum' && !dialog.is_forum) {
                    return false;
                }

                if (searchTerm === '') {
                    return true;
                }

                const haystack = [
                    dialog.title,
                    dialog.peer_identifier,
                    dialog.username ? '@' + dialog.username : '',
                    dialog.public_link || '',
                    dialog.invite_link || '',
                    dialogTypeLabel(dialog.type),
                ].join(' ').toLowerCase();

                return haystack.includes(searchTerm);
            });
        }

        function syncSelectedDialogInputs() {
            if (!supportsImportList) {
                return;
            }

            const selectedDialogs = loadedDialogs.filter((dialog) => dialog.selected && !dialog.already_added);

            if (groupDialogSelectedInputs) {
                groupDialogSelectedInputs.innerHTML = selectedDialogs.map((dialog) => {
                    const payload = escapeHtml(JSON.stringify({
                        title: dialog.title,
                        peer_identifier: dialog.peer_identifier,
                    }));

                    return `<input type="hidden" name="selected_dialogs[]" value="${payload}">`;
                }).join('');
            }

            if (groupDialogSelectionSummary) {
                groupDialogSelectionSummary.textContent = selectedDialogs.length > 0
                    ? `Đã chọn ${selectedDialogs.length} nhóm để import nhanh.`
                    : 'Chưa chọn nhóm nào để import.';
            }

            if (importSelectedDialogsButton) {
                importSelectedDialogsButton.disabled = selectedDialogs.length === 0;
            }
        }

        function renderDialogList() {
            if (!supportsImportList) {
                return;
            }

            if (loadedDialogs.length === 0) {
                groupDialogsToolbar.hidden = true;
                groupDialogImportBar.hidden = true;
                syncSelectedDialogInputs();
                updateDialogFilterButtons();
                return;
            }

            groupDialogsToolbar.hidden = false;
            groupDialogImportBar.hidden = false;

            const filteredDialogs = getFilteredDialogs();
            setGroupDialogsMeta(`Hiển thị ${filteredDialogs.length}/${loadedDialogs.length} nhóm theo bộ lọc "${dialogFilterLabel(activeDialogFilter)}".`);

            if (filteredDialogs.length === 0) {
                groupDialogList.innerHTML = '<div class="group-dialog-empty">Không có nhóm nào khớp với bộ lọc hiện tại.</div>';
                syncSelectedDialogInputs();
                updateDialogFilterButtons();
                return;
            }

            groupDialogList.innerHTML = filteredDialogs.map((dialog) => {
                const isPicked = dialog.peer_identifier === activePickedPeer;
                const isSelected = Boolean(dialog.selected);
                const isDisabled = Boolean(dialog.already_added);
                const badges = [
                    `<span class="badge info">${escapeHtml(dialogTypeLabel(dialog.type))}</span>`,
                    dialog.is_forum ? '<span class="badge success">Forum</span>' : '',
                    isDisabled ? '<span class="badge warning">Đã thêm</span>' : '',
                    !isDisabled && dialog.existing_count > 0 ? `<span class="badge">${escapeHtml(String(dialog.existing_count))} cấu hình</span>` : '',
                ].filter(Boolean).join('');

                const participants = dialog.participants_count !== null
                    ? `${escapeHtml(String(dialog.participants_count))} thành viên`
                    : 'Nhóm đã tham gia';
                const accessMeta = [
                    dialog.username ? `@${escapeHtml(dialog.username)}` : '',
                    dialog.invite_link ? `Invite: ${escapeHtml(formatDialogLink(dialog.invite_link))}` : '',
                ].filter(Boolean);
                const pickButton = peerField
                    ? `
                            <button class="button ${isPicked ? 'accent' : 'secondary'} sm" type="button" data-fill-dialog-peer="${escapeHtml(dialog.peer_identifier)}">
                                ${isPicked ? 'Đang chọn' : 'Dùng nhóm này'}
                            </button>
                        `
                    : '';

                return `
                    <article class="group-dialog-card${isPicked ? ' is-picked' : ''}${isSelected ? ' is-selected' : ''}${isDisabled ? ' is-disabled' : ''}">
                        <div class="group-dialog-card-head">
                            <div class="group-dialog-card-copy">
                                <strong>${escapeHtml(dialog.title)}</strong>
                                <div class="small muted mono">${escapeHtml(dialog.peer_identifier)}</div>
                            </div>
                            <div class="chip-row group-dialog-card-badges">${badges}</div>
                        </div>

                        <div class="group-dialog-card-meta">
                            <span>${participants}</span>
                            ${accessMeta.map((item) => `<span>${item}</span>`).join('')}
                        </div>

                        <div class="inline-actions group-dialog-card-actions">
                            ${pickButton}
                            <label class="group-dialog-check${isDisabled ? ' disabled' : ''}">
                                <input type="checkbox" data-select-dialog-peer="${escapeHtml(dialog.peer_identifier)}" ${isSelected ? 'checked' : ''} ${isDisabled ? 'disabled' : ''}>
                                <span>${isDisabled ? 'Đã có sẵn' : 'Chọn import'}</span>
                            </label>
                        </div>
                    </article>
                `;
            }).join('');

            syncSelectedDialogInputs();
            updateDialogFilterButtons();
        }

        function clearLoadedDialogs(emptyMessage, statusMessage = 'Chọn account rồi bấm tải danh sách nhóm.', config = {}) {
            loadedDialogs = [];
            activeDialogFilter = 'all';
            setLoadDialogsButtonLabel(false);

            if (groupDialogsSearch) {
                groupDialogsSearch.value = '';
            }

            if (config.resetPeerSelector) {
                resetPeerSelector(
                    config.peerPlaceholder || 'Chọn account rồi bấm tải danh sách nhóm',
                    Boolean(config.preservePeerSelection)
                );
            }

            activePickedPeer = String(peerField?.value || '').trim();

            if (groupDialogImportAccountId) {
                groupDialogImportAccountId.value = accountField?.value || '';
            }

            if (groupDialogList) {
                groupDialogList.innerHTML = `<div class="group-dialog-empty">${escapeHtml(emptyMessage)}</div>`;
            }

            clearImportFeedback();
            setGroupDialogsStatus(statusMessage);
            setGroupDialogsMeta('Chưa tải danh sách nhóm.');
            syncPeerSummary();
            renderDialogList();
        }

        function setActivePeer(peerIdentifier, config = {}) {
            if (!peerField) {
                return;
            }

            const dialog = loadedDialogs.find((item) => item.peer_identifier === peerIdentifier);
            const previousPeer = activePickedPeer;

            peerField.value = peerIdentifier;
            activePickedPeer = String(peerIdentifier || '').trim();

            if (
                dialog
                && titleField
                && config.syncTitle !== false
                && (config.forceTitleSync === true || titleField.value.trim() === '' || previousPeer !== activePickedPeer)
            ) {
                titleField.value = dialog.title;
            }

            if (typeof options.onPeerChanged === 'function' && (config.forcePeerReset === true || previousPeer !== activePickedPeer)) {
                options.onPeerChanged();
            }

            syncPeerSummary();
            renderDialogList();

            if (config.announce === false) {
                return;
            }

            if (dialog) {
                setGroupDialogsStatus(`Đã chọn nhóm "${dialog.title}" trong dropdown.`, 'success');
                return;
            }

            if (activePickedPeer !== '') {
                setGroupDialogsStatus('Đã cập nhật nhóm đang dùng cho form.', 'success');
            }
        }

        function setLoadDialogsButtonLabel(hasLoadedDialogs) {
            if (!loadDialogsButton) {
                return;
            }

            const label = hasLoadedDialogs ? 'Tải lại danh sách nhóm' : 'Tải danh sách nhóm';
            const icon = hasLoadedDialogs ? 'fa-arrows-rotate' : 'fa-cloud-arrow-down';
            const html = `<i class="fa-solid ${icon}" aria-hidden="true"></i><span>${label}</span>`;
            loadDialogsButton.dataset.originalHtml = html;

            if (loadDialogsButton.getAttribute('aria-busy') !== 'true') {
                loadDialogsButton.innerHTML = html;
            }
        }

        function cacheRemainingMinutes(expiresAt) {
            const expiresAtMs = Date.parse(String(expiresAt || ''));

            if (!Number.isFinite(expiresAtMs)) {
                return 5;
            }

            return Math.max(1, Math.ceil((expiresAtMs - Date.now()) / 60000));
        }

        async function loadDialogs(config = {}) {
            const accountId = accountField?.value || '';
            const cacheOnly = config.cacheOnly === true;
            const forceRefresh = config.forceRefresh === true;
            const quiet = config.quiet === true;
            const requestSequence = ++dialogsRequestSequence;

            if (!accountId) {
                if (quiet) {
                    return false;
                }

                await requestAppModal('alert', {
                    title: 'Thiếu tài khoản',
                    message: 'Hãy chọn tài khoản Telegram trước khi tải danh sách nhóm.',
                    confirmText: 'Đã hiểu',
                    confirmClass: 'primary',
                });
                return false;
            }

            if (!cacheOnly && typeof options.onDialogsLoading === 'function') {
                options.onDialogsLoading();
            }

            setAsyncButtonState(loadDialogsButton, true, cacheOnly ? 'Đang kiểm tra cache...' : 'Đang tải nhóm...');
            setGroupDialogsStatus(
                cacheOnly ? 'Đang kiểm tra danh sách nhóm đã tải gần đây...' : 'Đang tải danh sách nhóm mới nhất từ Telegram...',
                'loading'
            );
            setGroupDialogsMeta(cacheOnly ? 'Đang kiểm tra cache 30 phút của tài khoản.' : 'Đang đồng bộ dữ liệu nhóm từ Telegram.');

            try {
                const query = new URLSearchParams({ account_id: accountId });

                if (cacheOnly) {
                    query.set('cache_only', '1');
                }

                if (forceRefresh) {
                    query.set('refresh', '1');
                    query.set('_', String(Date.now()));
                }

                const payload = await window.TeleSenderApp.fetchJson(`${dialogsUrl}?${query.toString()}`, {
                    cache: 'no-store',
                });

                if (String(accountField?.value || '') !== String(accountId)) {
                    return false;
                }

                if (cacheOnly && payload.cache_available === false) {
                    setLoadDialogsButtonLabel(false);
                    setGroupDialogsStatus('Chưa có danh sách nhóm được cache cho tài khoản này. Bấm "Tải danh sách nhóm" để lấy từ Telegram.');
                    setGroupDialogsMeta('Cache sẽ được giữ trong 30 phút sau lần tải đầu tiên.');
                    return false;
                }

                loadedDialogs = mapLoadedDialogs(payload.dialogs);
                activeDialogFilter = 'all';
                activePickedPeer = String(peerField?.value || '').trim();

                if (groupDialogsSearch) {
                    groupDialogsSearch.value = '';
                }

                if (groupDialogImportAccountId) {
                    groupDialogImportAccountId.value = accountId;
                }

                clearImportFeedback();

                if (loadedDialogs.length === 0) {
                    if (peerField) {
                        resetPeerSelector('Account này chưa có nhóm khả dụng.', true);
                        activePickedPeer = String(peerField?.value || '').trim();
                        syncPeerSummary();
                    }

                    if (groupDialogList) {
                        groupDialogList.innerHTML = '<div class="group-dialog-empty">Không tìm thấy group nào trên account này. Hãy chắc tài khoản đã tham gia các nhóm cần dùng.</div>';
                    }

                    setGroupDialogsStatus('Telegram không trả về group nào cho account này.', 'danger');
                    setGroupDialogsMeta('Không tìm thấy group nào để hiển thị.');
                    setLoadDialogsButtonLabel(true);
                    renderDialogList();

                    if (typeof options.onDialogsLoaded === 'function') {
                        options.onDialogsLoaded(loadedDialogs, {
                            cacheHit: Boolean(payload.cache_hit),
                            announce: !quiet,
                        });
                    }

                    return true;
                }

                const cacheMessage = payload.cache_hit
                    ? `Đang dùng dữ liệu tạm thời còn khoảng ${cacheRemainingMinutes(payload.expires_at)} phút. Bấm "Tải lại danh sách nhóm" để lấy dữ liệu mới nhất.`
                    : 'Đã lấy dữ liệu mới nhất từ Telegram và lưu tạm thời trong 30 phút.';

                if (peerField) {
                    populatePeerSelector();
                    syncPeerSummary();
                    setGroupDialogsStatus(
                        activePickedPeer !== '' && loadedDialogs.some((dialog) => dialog.peer_identifier === activePickedPeer)
                            ? `Đã tải ${loadedDialogs.length} nhóm và giữ nguyên nhóm đang chọn. ${cacheMessage}`
                            : activePickedPeer !== ''
                                ? `Đã tải ${loadedDialogs.length} nhóm. Nhóm hiện tại không còn trong danh sách Telegram, bạn có thể chọn lại nếu cần. ${cacheMessage}`
                                : `Đã tải ${loadedDialogs.length} nhóm. Chọn một nhóm trong dropdown phía trên. ${cacheMessage}`,
                        'success'
                    );
                } else {
                    setGroupDialogsStatus(`Đã tải ${loadedDialogs.length} nhóm. Tick các nhóm cần import nhanh. ${cacheMessage}`, 'success');
                }

                setLoadDialogsButtonLabel(true);
                renderDialogList();

                if (typeof options.onDialogsLoaded === 'function') {
                    options.onDialogsLoaded(loadedDialogs, {
                        cacheHit: Boolean(payload.cache_hit),
                        announce: !quiet,
                    });
                }

                return true;
            } catch (error) {
                if (String(accountField?.value || '') !== String(accountId)) {
                    return false;
                }

                const message = error.message || 'Không tải được danh sách nhóm.';
                setGroupDialogsStatus(message, 'danger');
                setGroupDialogsMeta('Không tải được danh sách nhóm.');

                if (!quiet && typeof options.onDialogsFailed === 'function') {
                    options.onDialogsFailed(error);
                }

                if (!quiet) {
                    await requestAppModal('alert', {
                        title: 'Không tải được nhóm',
                        message,
                        confirmText: 'Đã hiểu',
                        confirmClass: 'primary',
                    });
                }

                return false;
            } finally {
                if (requestSequence === dialogsRequestSequence) {
                    setAsyncButtonState(loadDialogsButton, false, cacheOnly ? 'Đang kiểm tra cache...' : 'Đang tải nhóm...');
                }
            }
        }

        loadDialogsButton?.addEventListener('click', () => {
            void loadDialogs({ forceRefresh: true });
        });

        accountField?.addEventListener('change', () => {
            if (typeof options.onAccountChanged === 'function') {
                options.onAccountChanged();
            }

            clearLoadedDialogs(
                supportsImportList
                    ? (accountField.value
                        ? 'Đã đổi account. Bấm "Tải danh sách nhóm" để nạp lại danh sách import.'
                        : 'Chọn account rồi bấm "Tải danh sách nhóm" để lấy danh sách nhóm mà tài khoản đã tham gia.')
                    : (accountField.value
                        ? 'Đã đổi account. Bấm "Tải danh sách nhóm" để nạp lại dropdown phù hợp.'
                        : 'Chọn account rồi bấm "Tải danh sách nhóm" để lấy danh sách nhóm mà tài khoản đã tham gia.'),
                accountField.value
                    ? 'Đã đổi account, cần tải lại danh sách nhóm.'
                    : 'Chọn account rồi bấm tải danh sách nhóm.',
                {
                    resetPeerSelector: Boolean(peerField),
                    peerPlaceholder: accountField.value
                        ? 'Bấm "Tải danh sách nhóm" để nạp dropdown theo account này'
                        : 'Chọn account rồi bấm tải danh sách nhóm',
                }
            );

            if (accountField.value) {
                void loadDialogs({ cacheOnly: true, quiet: true });
            }
        });

        peerField?.addEventListener('change', () => {
            setActivePeer(String(peerField.value || '').trim(), {
                syncTitle: true,
                announce: String(peerField.value || '').trim() !== '',
            });
        });

        groupDialogsSearch?.addEventListener('input', () => {
            renderDialogList();
        });

        dialogFilterButtons.forEach((button) => {
            button.addEventListener('click', () => {
                activeDialogFilter = button.dataset.dialogFilter || 'all';
                renderDialogList();
            });
        });

        groupDialogList?.addEventListener('click', (event) => {
            const target = event.target instanceof Element ? event.target : null;
            const fillButton = target ? target.closest('[data-fill-dialog-peer]') : null;

            if (!fillButton || !peerField) {
                return;
            }

            setActivePeer(fillButton.dataset.fillDialogPeer || '', {
                syncTitle: true,
                announce: true,
            });
        });

        groupDialogList?.addEventListener('change', (event) => {
            const target = event.target instanceof Element ? event.target : null;
            const checkbox = target ? target.closest('[data-select-dialog-peer]') : null;

            if (!checkbox) {
                return;
            }

            const dialog = loadedDialogs.find((item) => item.peer_identifier === (checkbox.dataset.selectDialogPeer || ''));

            if (!dialog) {
                return;
            }

            dialog.selected = checkbox.checked;
            renderDialogList();
        });

        selectVisibleDialogsButton?.addEventListener('click', () => {
            for (const dialog of getFilteredDialogs()) {
                if (!dialog.already_added) {
                    dialog.selected = true;
                }
            }

            renderDialogList();
        });

        clearSelectedDialogsButton?.addEventListener('click', () => {
            loadedDialogs.forEach((dialog) => {
                dialog.selected = false;
            });

            renderDialogList();
        });

        groupDialogImportForm?.addEventListener('submit', async (event) => {
            event.preventDefault();

            clearImportFeedback();

            const selectedCount = loadedDialogs.filter((dialog) => dialog.selected && !dialog.already_added).length;

            if (!accountField?.value) {
                await requestAppModal('alert', {
                    title: 'Thiếu tài khoản',
                    message: 'Hãy chọn tài khoản Telegram trước khi import nhóm.',
                    confirmText: 'Đã hiểu',
                    confirmClass: 'primary',
                });
                return;
            }

            if (selectedCount <= 0) {
                await requestAppModal('alert', {
                    title: 'Chưa chọn nhóm',
                    message: 'Hãy tick ít nhất một nhóm trước khi import.',
                    confirmText: 'Đã hiểu',
                    confirmClass: 'primary',
                });
                return;
            }

            await window.TeleSenderApp.submitAjaxForm(groupDialogImportForm, {
                closeCrudModalOnSuccess: true,
                refreshRegionsOnSuccess: ['[data-live-region="groups-panel"]'],
                onError(error) {
                    if (feedbackTarget) {
                        feedbackTarget.hidden = false;
                        feedbackTarget.className = 'form-feedback error';
                        feedbackTarget.textContent = error.message || 'Không thể import nhóm.';
                    }
                },
            });
        });

        clearLoadedDialogs(
            supportsImportList
                ? (accountField?.value
                    ? 'Bấm "Tải danh sách nhóm" để hiện các nhóm có thể import nhanh.'
                    : 'Chọn account rồi bấm "Tải danh sách nhóm" để lấy danh sách nhóm mà tài khoản đã tham gia.')
                : (accountField?.value
                    ? 'Bấm "Tải danh sách nhóm" để lấy danh sách nhóm của account đang chọn.'
                    : 'Chọn account rồi bấm "Tải danh sách nhóm" để lấy danh sách nhóm mà tài khoản đã tham gia.'),
            accountField?.value ? 'Sẵn sàng tải danh sách nhóm.' : 'Chọn account rồi bấm tải danh sách nhóm.'
        );
        syncPeerSummary();

        if (accountField?.value) {
            void loadDialogs({ cacheOnly: true, quiet: true });
        }
    }

    function initGroupEditor(root) {
        const form = root.querySelector('[data-group-editor-form]');
        const accountField = root.querySelector('[data-group-account]');
        const titleField = root.querySelector('[data-group-title]');
        const peerField = root.querySelector('[data-group-peer]');
        const topicButton = root.querySelector('[data-group-load-topics]');
        const topicSelector = root.querySelector('[data-group-topic-selector]');
        const topicIdField = root.querySelector('[data-group-topic-id]');
        const topicTitleField = root.querySelector('[data-group-topic-title]');
        const topicsStatus = root.querySelector('[data-group-topics-status]');
        const submitButton = root.querySelector('[data-group-submit]');
        const saveRequirement = root.querySelector('[data-group-save-requirement]');

        if (!form || !accountField || !titleField || !peerField || !topicButton || !topicSelector || !topicIdField || !topicTitleField || !submitButton) {
            return;
        }

        // An existing peer in edit mode is already a valid source for loading topics.
        let dialogsLoaded = peerField.value.trim() !== '';
        let topicsLoaded = false;

        function setTopicsStatus(message, tone = 'muted') {
            if (!topicsStatus) {
                return;
            }

            topicsStatus.textContent = message;
            topicsStatus.classList.remove('muted', 'text-success', 'text-danger', 'is-loading');

            if (tone === 'loading') {
                topicsStatus.classList.add('muted', 'is-loading');
            } else if (tone === 'success') {
                topicsStatus.classList.add('text-success');
            } else if (tone === 'danger') {
                topicsStatus.classList.add('text-danger');
            } else {
                topicsStatus.classList.add('muted');
            }
        }

        function updateSaveAvailability() {
            const hasAccount = accountField.value !== '';
            const hasPeer = peerField.value.trim() !== '';
            const isReady = hasAccount && hasPeer && dialogsLoaded && topicsLoaded;
            const topicIsLoading = topicButton.getAttribute('aria-busy') === 'true';

            submitButton.disabled = !isReady;
            submitButton.title = isReady ? '' : 'Hãy tải danh sách nhóm và topic trước khi lưu.';
            topicButton.disabled = topicIsLoading || !hasAccount || !hasPeer || !dialogsLoaded;
            topicButton.title = topicButton.disabled && !topicIsLoading
                ? 'Hãy tải danh sách nhóm và chọn nhóm trước.'
                : '';

            if (!saveRequirement) {
                return;
            }

            saveRequirement.classList.toggle('is-ready', isReady);
            const icon = saveRequirement.querySelector('i');
            const label = saveRequirement.querySelector('span');

            if (icon) {
                icon.className = isReady ? 'fa-solid fa-circle-check' : 'fa-solid fa-circle-info';
            }

            if (!label) {
                return;
            }

            if (!hasAccount) {
                label.textContent = 'Chọn tài khoản Telegram để bắt đầu.';
            } else if (!dialogsLoaded) {
                label.textContent = 'Bước 1/2: Tải danh sách nhóm từ Telegram.';
            } else if (!hasPeer) {
                label.textContent = 'Chọn một nhóm trong danh sách vừa tải.';
            } else if (!topicsLoaded) {
                label.textContent = 'Bước 2/2: Tải topic của nhóm đã chọn.';
            } else {
                label.textContent = 'Đã tải đủ nhóm và topic. Có thể lưu cấu hình.';
            }
        }

        function syncTopicFields() {
            const selected = topicSelector.options[topicSelector.selectedIndex];
            topicIdField.value = topicSelector.value;
            topicTitleField.value = topicSelector.value === '' ? '' : selected.text.replace(/^Topic:\s*/, '').trim();
        }

        function resetTopicSelection(message = 'Chọn nhóm rồi tải topic để xác nhận đích gửi.') {
            topicsLoaded = false;
            topicSelector.innerHTML = '';

            const generalOption = document.createElement('option');
            generalOption.value = '';
            generalOption.textContent = 'Hãy tải topic từ Telegram trước';
            topicSelector.appendChild(generalOption);

            topicSelector.value = '';
            topicSelector.dataset.currentTopicId = '';
            topicSelector.dataset.currentTopicTitle = '';
            topicIdField.value = '';
            topicTitleField.value = '';
            setTopicsStatus(message);
            updateSaveAvailability();
        }

        initGroupDialogLoader(root, {
            accountField,
            peerField,
            titleField,
            onAccountChanged() {
                dialogsLoaded = false;
                resetTopicSelection('Đã đổi tài khoản. Hãy tải lại nhóm, sau đó tải topic.');
            },
            onPeerChanged() {
                resetTopicSelection('Đã đổi nhóm. Hãy tải topic của nhóm này trước khi lưu.');
            },
            onDialogsLoading() {
                dialogsLoaded = false;
                resetTopicSelection('Đang chờ tải nhóm hoàn tất...');
                updateSaveAvailability();
            },
            onDialogsLoaded(dialogs, metadata = {}) {
                dialogsLoaded = true;
                setTopicsStatus(
                    peerField.value.trim() !== ''
                        ? 'Danh sách nhóm đã sẵn sàng. Hãy tải topic của nhóm đang chọn.'
                        : 'Danh sách nhóm đã sẵn sàng. Chọn một nhóm rồi tải topic.'
                );
                updateSaveAvailability();

                if (metadata.announce !== false) {
                    if (dialogs.length > 0) {
                        window.TeleSenderApp.showFlash(
                            'success',
                            metadata.cacheHit
                                ? `Đã dùng danh sách ${dialogs.length} nhóm trong cache.`
                                : `Đã tải mới ${dialogs.length} nhóm từ Telegram.`
                        );
                    } else {
                        window.TeleSenderApp.showFlash('error', 'Đã tải xong nhưng tài khoản này không có nhóm khả dụng.');
                    }
                }
            },
            onDialogsFailed(error) {
                dialogsLoaded = false;
                updateSaveAvailability();
                window.TeleSenderApp.showFlash('error', error.message || 'Không tải được danh sách nhóm từ Telegram.');
            },
        });

        topicSelector.addEventListener('change', () => {
            syncTopicFields();
            updateSaveAvailability();
        });

        peerField.addEventListener('change', updateSaveAvailability);

        topicButton.addEventListener('click', async () => {
            const accountId = accountField.value || '';
            const peerIdentifier = peerField.value.trim() || '';

            if (!accountId || !peerIdentifier) {
                await requestAppModal('alert', {
                    title: 'Thiếu thông tin',
                    message: 'Hãy chọn tài khoản và chọn nhóm trước khi tải topic.',
                    confirmText: 'Đã hiểu',
                    confirmClass: 'primary',
                });
                return;
            }

            topicsLoaded = false;
            updateSaveAvailability();
            setAsyncButtonState(topicButton, true, 'Đang tải topic...');
            setTopicsStatus('Đang tải danh sách topic từ Telegram...', 'loading');

            try {
                const payload = await window.TeleSenderApp.fetchJson(`${topicsUrl}?account_id=${encodeURIComponent(accountId)}&peer_identifier=${encodeURIComponent(peerIdentifier)}`);

                if (accountField.value !== accountId || peerField.value.trim() !== peerIdentifier) {
                    return;
                }

                const currentTopicId = topicSelector.dataset.currentTopicId || topicIdField.value;
                const currentTopicTitle = topicSelector.dataset.currentTopicTitle || topicTitleField.value;
                const currentGeneral = topicSelector.value === '';
                topicSelector.innerHTML = '';

                const generalOption = document.createElement('option');
                generalOption.value = '';
                generalOption.textContent = 'Topic chung / mặc định';
                topicSelector.appendChild(generalOption);

                for (const topic of payload.topics || []) {
                    const option = document.createElement('option');
                    option.value = String(topic.id);
                    option.textContent = topic.title;
                    if (String(topic.id) === String(currentTopicId)) {
                        option.selected = true;
                    }
                    topicSelector.appendChild(option);
                }

                if (topicSelector.value === '' && currentTopicTitle) {
                    const matchedByTitle = Array.from(topicSelector.options).find((option) => {
                        return normalizeTopicTitle(option.textContent) === normalizeTopicTitle(currentTopicTitle);
                    });

                    if (matchedByTitle) {
                        topicSelector.value = matchedByTitle.value;
                    }
                }

                if (currentGeneral) {
                    topicSelector.value = '';
                }

                syncTopicFields();
                topicsLoaded = true;
                updateSaveAvailability();

                const topicCount = Array.isArray(payload.topics) ? payload.topics.length : 0;
                const successMessage = topicCount > 0
                    ? `Đã tải xong ${topicCount} topic từ Telegram.`
                    : 'Nhóm không có topic riêng. Hệ thống sẽ dùng Topic chung / mặc định.';

                setTopicsStatus(successMessage, 'success');
                window.TeleSenderApp.showFlash('success', successMessage);
            } catch (error) {
                if (accountField.value !== accountId || peerField.value.trim() !== peerIdentifier) {
                    return;
                }

                topicsLoaded = false;
                updateSaveAvailability();
                setTopicsStatus(error.message || 'Không tải được danh sách topic.', 'danger');
                window.TeleSenderApp.showFlash('error', error.message || 'Không tải được danh sách topic.');

                await requestAppModal('alert', {
                    title: 'Không tải được topic',
                    message: error.message || 'Không tải được danh sách topic.',
                    confirmText: 'Đã hiểu',
                    confirmClass: 'primary',
                });
            } finally {
                setAsyncButtonState(topicButton, false, 'Đang tải topic...');
                updateSaveAvailability();
            }
        });

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            if (!dialogsLoaded || !topicsLoaded || !accountField.value || !peerField.value.trim()) {
                updateSaveAvailability();
                window.TeleSenderApp.showFlash('error', 'Hãy tải danh sách nhóm và topic trước khi lưu.');
                return;
            }

            await window.TeleSenderApp.submitAjaxForm(form, {
                closeCrudModalOnSuccess: true,
                refreshRegionsOnSuccess: ['[data-live-region="groups-panel"]'],
            });
        });

        updateSaveAvailability();
    }

    function initGroupImport(root) {
        initGroupDialogLoader(root);
    }

    function openGroupModal(mode, groupId = null) {
        const fragment = editorTemplate.content.cloneNode(true);
        const wrapper = document.createElement('div');
        wrapper.appendChild(fragment);

        const root = wrapper.querySelector('[data-group-editor-root]');
        const form = wrapper.querySelector('[data-group-editor-form]');
        const accountField = wrapper.querySelector('[data-group-account]');
        const titleField = wrapper.querySelector('[data-group-title]');
        const notesField = wrapper.querySelector('[data-group-notes]');
        const peerField = wrapper.querySelector('[data-group-peer]');
        const topicSelector = wrapper.querySelector('[data-group-topic-selector]');
        const topicIdField = wrapper.querySelector('[data-group-topic-id]');
        const topicTitleField = wrapper.querySelector('[data-group-topic-title]');
        const activeField = wrapper.querySelector('[data-group-is-active]');
        const submitButton = wrapper.querySelector('[data-group-submit]');

        if (!root || !form || !accountField || !titleField || !notesField || !peerField || !topicSelector || !topicIdField || !topicTitleField || !activeField || !submitButton) {
            return;
        }

        if (mode === 'edit') {
            const record = groupRecords[String(groupId)] || null;

            if (!record) {
                window.TeleSenderApp.showFlash('error', 'Không tìm thấy nhóm để chỉnh sửa.');
                return;
            }

            form.action = updateUrl;
            accountField.value = String(record.telegram_account_id || '');
            titleField.value = record.title || '';
            notesField.value = record.notes || '';
            activeField.checked = Number(record.is_active || 0) === 1;
            submitButton.textContent = 'Cập nhật nhóm';

            const idField = document.createElement('input');
            idField.type = 'hidden';
            idField.name = 'id';
            idField.value = String(record.id || '');
            form.prepend(idField);

            createPeerOption(peerField, record);
            createTopicOption(topicSelector, topicIdField, topicTitleField, record);
        } else {
            form.action = createUrl;
            accountField.value = '';
            titleField.value = '';
            notesField.value = '';
            activeField.checked = true;
            submitButton.textContent = 'Lưu nhóm';
            createPeerOption(peerField, {
                title: '',
                peer_identifier: '',
            });
            createTopicOption(topicSelector, topicIdField, topicTitleField, {
                topic_id: null,
                topic_title: '',
            });
        }

        initGroupEditor(root);

        window.TeleSenderCrudModal.open({
            title: mode === 'edit' ? 'Cập nhật nhóm' : 'Thêm nhóm mới',
            description: mode === 'edit'
                ? 'Sửa nhanh thông tin đích gửi, topic và đồng bộ lại dữ liệu từ Telegram khi cần.'
                : 'Chọn đúng tài khoản Telegram, tải danh sách group đã tham gia rồi lưu cấu hình gửi.',
            size: 'full',
            content: wrapper,
        });
    }

    function openGroupImportModal() {
        const fragment = importTemplate.content.cloneNode(true);
        const wrapper = document.createElement('div');
        wrapper.appendChild(fragment);

        const root = wrapper.querySelector('[data-group-import-root]');

        if (!root) {
            return;
        }

        initGroupImport(root);

        window.TeleSenderCrudModal.open({
            title: 'Import nhanh nhiều nhóm',
            description: 'Tách riêng luồng import hàng loạt để bạn lọc, đối chiếu và chọn nhiều nhóm dễ hơn.',
            size: 'full',
            content: wrapper,
        });
    }

    createButton?.addEventListener('click', () => {
        openGroupModal('create');
    });

    importButton?.addEventListener('click', () => {
        openGroupImportModal();
    });

    document.addEventListener('click', (event) => {
        const button = event.target instanceof Element ? event.target.closest('[data-group-edit]') : null;

        if (!button) {
            return;
        }

        openGroupModal('edit', button.getAttribute('data-group-edit'));
    });

    document.addEventListener('app:regions:refreshed', () => {
        groupRecords = window.TeleSenderApp.readJsonScript('[data-group-records]', {});
    });
})();
});
</script>
