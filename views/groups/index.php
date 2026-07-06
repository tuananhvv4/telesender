<section class="stack">
    <div class="topbar">
        <h1 class="page-title">Nhóm Telegram</h1>
    </div>

    <div class="group-workspace">
        <section class="card group-editor-card">
            <div class="group-editor-head">
                <div>
                    <h2 class="section-title"><?= $editGroup ? 'Cập nhật nhóm' : 'Thêm nhóm mới' ?></h2>
                    <div class="small muted"><?= $editGroup ? 'Sửa nhanh thông tin đích gửi và topic tương ứng.' : 'Vui lòng chọn đúng tài khoản Telegram và nhóm tương ứng với tài khoản ( Yêu cầu tài khoản đã tham gia nhóm )' ?></div>
                </div>
                <?php if ($editGroup): ?>
                    <span class="badge info">Đang sửa</span>
                <?php endif; ?>
            </div>

            <form class="form-grid" method="post" action="<?= e(url($editGroup ? '/groups/update' : '/groups')) ?>">
                <?= csrf_field() ?>
                <?php if ($editGroup): ?>
                    <input type="hidden" name="id" value="<?= e((string) $editGroup['id']) ?>">
                <?php endif; ?>

                <div class="group-form-grid">
                    <div class="field group-field-span-2">
                        <label for="telegram_account_id">Tài khoản Telegram</label>
                        <div class="group-account-row">
                            <select class="select" id="telegram_account_id" name="telegram_account_id" required>
                                <option value="">Chọn tài khoản</option>
                                <?php foreach ($accounts as $account): ?>
                                    <option value="<?= e((string) $account['id']) ?>" <?= (string) ($editGroup['telegram_account_id'] ?? '') === (string) $account['id'] ? 'selected' : '' ?>>
                                    <?= e($account['name']) ?> (<?= e($account['phone_number']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button class="button secondary" id="load_dialogs_button" type="button">Tải danh sách nhóm</button>
                        </div>
                        <div class="helper-row group-account-feedback">
                            <span class="group-dialog-status helper-text" id="group_dialogs_status">Chọn account rồi bấm tải danh sách nhóm.</span>
                        </div>
                    </div>

                    <div class="field group-field-span-2">
                        <label for="peer_identifier">Nhóm Telegram</label>
                        <select class="select" id="peer_identifier" name="peer_identifier" required>
                            <option value="">Chọn account rồi bấm tải danh sách nhóm</option>
                            <?php if (!empty($editGroup['peer_identifier'])): ?>
                                <option value="<?= e($editGroup['peer_identifier']) ?>" selected>
                                    <?= e(($editGroup['title'] ?: 'Nhóm hiện tại') . ' · ' . $editGroup['peer_identifier']) ?>
                                </option>
                            <?php endif; ?>
                        </select>
                        <div class="small muted group-form-hint">Sau khi tải danh sách, dropdown này sẽ hiển thị toàn bộ nhóm mà tài khoản đã tham gia.</div>
                        <div class="helper-row group-peer-feedback">
                            <span class="helper-text" id="group_peer_summary">
                                <?php if (!empty($editGroup['peer_identifier'])): ?>
                                    Đang dùng peer hiện tại: <?= e($editGroup['peer_identifier']) ?>
                                <?php else: ?>
                                    Chưa chọn nhóm nào.
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>

                    <div class="field">
                        <label for="title">Tên hiển thị</label>
                        <div class="small muted group-form-hint">Tự điền theo nhóm đã chọn, bạn vẫn có thể sửa lại để dễ phân biệt trong hệ thống.</div>
                        <input class="input" id="title" type="text" name="title" value="<?= e($editGroup['title'] ?? '') ?>" required>
                    </div>

                    <div class="field">
                        <label for="notes">Ghi chú</label>
                        <input class="input" id="notes" type="text" name="notes" value="<?= e($editGroup['notes'] ?? '') ?>" placeholder="Ghi chú ngắn để dễ phân biệt">
                    </div>

                    <div class="field group-field-span-2">
                        <div class="group-topic-head">
                            <div>
                                <label for="topic_selector">Topic (tùy chọn)</label>
                                <div class="small muted group-form-hint">Chỉ cần tải topic sau khi đã chọn đúng nhóm ở dropdown phía trên.</div>
                            </div>
                            <button class="button secondary sm" id="load_topics_button" type="button">Tải topic từ Telegram</button>
                        </div>
                        <select
                            class="select"
                            id="topic_selector"
                            data-current-topic-id="<?= e(isset($editGroup['topic_id']) && $editGroup['topic_id'] !== null ? (string) $editGroup['topic_id'] : '') ?>"
                            data-current-topic-title="<?= e($editGroup['topic_title'] ?? '') ?>"
                        >
                            <option value="">Topic chung / mặc định</option>
                            <?php if (!empty($editGroup['topic_id'])): ?>
                                <option value="<?= e((string) $editGroup['topic_id']) ?>" selected>
                                    <?= e($editGroup['topic_title'] ?: ('Topic #' . $editGroup['topic_id'])) ?>
                                </option>
                            <?php endif; ?>
                        </select>
                        <input type="hidden" id="topic_id" name="topic_id" value="<?= e(isset($editGroup['topic_id']) && $editGroup['topic_id'] !== null ? (string) $editGroup['topic_id'] : '') ?>">
                    </div>

                    <div class="field group-field-span-2">
                        <label for="topic_title">Tên topic</label>
                        <input class="input" id="topic_title" type="text" name="topic_title" value="<?= e($editGroup['topic_title'] ?? '') ?>" placeholder="Ví dụ: Chợ Mới">
                    </div>
                </div>

                <label class="checkbox-row">
                    <input type="checkbox" name="is_active" value="1" <?= !isset($editGroup['is_active']) || (int) $editGroup['is_active'] === 1 ? 'checked' : '' ?>>
                    <span>Kích hoạt nhóm này</span>
                </label>

                <div class="actions">
                    <button class="button primary" type="submit"><?= $editGroup ? 'Cập nhật nhóm' : 'Lưu nhóm' ?></button>
                    <?php if ($editGroup): ?>
                        <a class="button secondary" href="<?= e(url('/groups')) ?>">Tạo mới</a>
                    <?php endif; ?>
                </div>
            </form>

            <details class="group-dialog-browser" id="group_dialog_browser">
                <summary class="group-dialog-browser-summary">
                    <div class="group-dialog-browser-summary-copy">
                        <h3 class="section-title" id="group_dialog_browser_title">Import nhanh nhiều nhóm</h3>
                        <div class="small muted">Tùy chọn: lọc, chọn nhiều nhóm rồi import hàng loạt khi cần.</div>
                    </div>
                    <div class="small muted group-dialog-browser-meta" id="group_dialogs_meta">Chưa tải danh sách nhóm.</div>
                </summary>

                <div class="group-dialog-browser-body">
                    <div class="small muted">Sau khi tải danh sách, bạn có thể chọn nhanh một nhóm ở dropdown phía trên, hoặc dùng khu vực này để import nhiều nhóm cùng lúc.</div>

                    <div class="group-dialog-toolbar" id="group_dialogs_toolbar" hidden>
                        <div class="field group-dialog-search-field">
                            <label class="sr-only" for="group_dialogs_search">Tìm nhóm đã tải</label>
                            <input class="input" id="group_dialogs_search" type="search" placeholder="Tìm theo tên nhóm, @username hoặc peer ID">
                        </div>

                        <div class="chip-row group-dialog-filter-row">
                            <button class="chip active" type="button" data-dialog-filter="all">Tất cả</button>
                            <button class="chip" type="button" data-dialog-filter="new">Chưa thêm</button>
                            <button class="chip" type="button" data-dialog-filter="existing">Đã thêm</button>
                            <button class="chip" type="button" data-dialog-filter="forum">Forum</button>
                        </div>

                        <div class="inline-actions group-dialog-toolbar-actions">
                            <button class="button secondary sm" id="select_visible_dialogs_button" type="button">Chọn tất cả đang hiển thị</button>
                            <button class="button secondary sm" id="clear_selected_dialogs_button" type="button">Bỏ chọn</button>
                        </div>
                    </div>

                    <form method="post" action="<?= e(url('/groups/import')) ?>" id="group_dialog_import_form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="telegram_account_id" id="group_dialog_import_account_id" value="<?= e((string) ($editGroup['telegram_account_id'] ?? '')) ?>">
                        <div id="group_dialog_selected_inputs"></div>

                        <div class="group-dialog-list" id="group_dialog_list">
                            <div class="group-dialog-empty">Chọn account rồi bấm "Tải danh sách nhóm" để lấy danh sách nhóm mà tài khoản đã tham gia. Sau đó bạn có thể chọn một nhóm trong dropdown phía trên hoặc tick nhiều nhóm để import nhanh.</div>
                        </div>

                        <div class="group-dialog-import-bar" id="group_dialog_import_bar" hidden>
                            <div class="small muted" id="group_dialog_selection_summary">Chưa chọn nhóm nào để import.</div>
                            <button class="button accent" id="import_selected_dialogs_button" type="submit" disabled>Import đã chọn</button>
                        </div>
                    </form>
                </div>
            </details>
        </section>

        <section class="panel group-library-panel">
            <div class="panel-header">
                <h2 class="panel-title">Danh sách nhóm</h2>
            </div>
            <div class="panel-body groups-feed">
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
                            <a class="button secondary sm" href="<?= e(url('/groups?edit=' . $group['id'])) ?>">Sửa</a>
                            <form method="post" action="<?= e(url('/groups/delete')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e((string) $group['id']) ?>">
                                <button class="button danger sm" type="submit">Xóa</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
                <?php if ($groups === []): ?>
                    <div class="muted">Chưa có nhóm nào.</div>
                <?php endif; ?>
            </div>
            <div class="panel-body" style="padding-top: 0;">
                <?php $perPageOptions = [10, 15, 20, 30, 50, 100]; ?>
                <?php require base_path('views/partials/pagination.php'); ?>
            </div>
        </section>
    </div>
</section>
<script>
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

const accountField = document.getElementById('telegram_account_id');
const titleField = document.getElementById('title');
const peerField = document.getElementById('peer_identifier');
const groupPeerSummary = document.getElementById('group_peer_summary');
const topicButton = document.getElementById('load_topics_button');
const topicSelector = document.getElementById('topic_selector');
const topicIdField = document.getElementById('topic_id');
const topicTitleField = document.getElementById('topic_title');
const loadDialogsButton = document.getElementById('load_dialogs_button');
const groupDialogsStatus = document.getElementById('group_dialogs_status');
const groupDialogBrowser = document.getElementById('group_dialog_browser');
const groupDialogsMeta = document.getElementById('group_dialogs_meta');
const groupDialogsToolbar = document.getElementById('group_dialogs_toolbar');
const groupDialogsSearch = document.getElementById('group_dialogs_search');
const groupDialogList = document.getElementById('group_dialog_list');
const groupDialogImportForm = document.getElementById('group_dialog_import_form');
const groupDialogImportAccountId = document.getElementById('group_dialog_import_account_id');
const groupDialogSelectedInputs = document.getElementById('group_dialog_selected_inputs');
const groupDialogImportBar = document.getElementById('group_dialog_import_bar');
const groupDialogSelectionSummary = document.getElementById('group_dialog_selection_summary');
const importSelectedDialogsButton = document.getElementById('import_selected_dialogs_button');
const selectVisibleDialogsButton = document.getElementById('select_visible_dialogs_button');
const clearSelectedDialogsButton = document.getElementById('clear_selected_dialogs_button');
const dialogFilterButtons = Array.from(document.querySelectorAll('[data-dialog-filter]'));

let loadedDialogs = [];
let activeDialogFilter = 'all';
let activePickedPeer = peerField?.value.trim() || '';

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function setGroupDialogsStatus(message, tone = 'muted') {
    if (!groupDialogsStatus) {
        return;
    }

    groupDialogsStatus.textContent = message;
    groupDialogsStatus.classList.remove('muted', 'text-success', 'text-danger');

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

function syncTopicFields() {
    const selected = topicSelector.options[topicSelector.selectedIndex];
    topicIdField.value = topicSelector.value;
    topicTitleField.value = topicSelector.value === '' ? '' : selected.text.replace(/^Topic:\s*/, '').trim();
}

function resetTopicSelection() {
    if (!topicSelector || !topicIdField || !topicTitleField) {
        return;
    }

    topicSelector.innerHTML = '';

    const generalOption = document.createElement('option');
    generalOption.value = '';
    generalOption.textContent = 'Topic chung / mặc định';
    topicSelector.appendChild(generalOption);

    topicSelector.value = '';
    topicSelector.dataset.currentTopicId = '';
    topicSelector.dataset.currentTopicTitle = '';
    topicIdField.value = '';
    topicTitleField.value = '';
}

function normalizeTopicTitle(value) {
    return String(value || '').trim().toLowerCase();
}

function dialogTypeLabel(type) {
    if (type === 'supergroup') {
        return 'Supergroup';
    }

    return 'Group';
}

function dialogFilterLabel(filter) {
    return {
        all: 'Tất cả',
        new: 'Chưa thêm',
        existing: 'Đã thêm',
        forum: 'Forum',
    }[filter] || 'Tất cả';
}

function updateDialogFilterButtons() {
    dialogFilterButtons.forEach((button) => {
        button.classList.toggle('active', button.dataset.dialogFilter === activeDialogFilter);
    });
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
    if (!groupDialogList) {
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
                    <button class="button ${isPicked ? 'accent' : 'secondary'} sm" type="button" data-fill-dialog-peer="${escapeHtml(dialog.peer_identifier)}">
                        ${isPicked ? 'Đang chọn' : 'Chọn nhóm này'}
                    </button>
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

function clearLoadedDialogs(
    emptyMessage,
    statusMessage = 'Chọn account rồi bấm tải danh sách nhóm.',
    options = {}
) {
    loadedDialogs = [];
    activeDialogFilter = 'all';

    if (groupDialogsSearch) {
        groupDialogsSearch.value = '';
    }

    if (options.resetPeerSelector) {
        resetPeerSelector(
            options.peerPlaceholder || 'Chọn account rồi bấm tải danh sách nhóm',
            Boolean(options.preservePeerSelection)
        );
    }

    activePickedPeer = peerField?.value.trim() || '';

    if (groupDialogImportAccountId) {
        groupDialogImportAccountId.value = accountField?.value || '';
    }

    if (groupDialogList) {
        groupDialogList.innerHTML = `<div class="group-dialog-empty">${escapeHtml(emptyMessage)}</div>`;
    }

    setGroupDialogsStatus(statusMessage);
    setGroupDialogsMeta('Chưa tải danh sách nhóm.');
    syncPeerSummary();
    renderDialogList();
}

function setActivePeer(peerIdentifier, options = {}) {
    const dialog = loadedDialogs.find((item) => item.peer_identifier === peerIdentifier);
    const previousPeer = activePickedPeer;

    if (!peerField) {
        return;
    }

    peerField.value = peerIdentifier;
    activePickedPeer = String(peerIdentifier || '').trim();

    if (
        dialog
        && titleField
        && options.syncTitle !== false
        && (options.forceTitleSync === true || titleField.value.trim() === '' || previousPeer !== activePickedPeer)
    ) {
        titleField.value = dialog.title;
    }

    if (options.resetTopics !== false && (options.forceTopicReset === true || previousPeer !== activePickedPeer)) {
        resetTopicSelection();
    }

    syncPeerSummary();

    if (loadedDialogs.length > 0) {
        renderDialogList();
    }

    if (options.announce === false) {
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

topicSelector?.addEventListener('change', syncTopicFields);

topicButton?.addEventListener('click', async () => {
    const accountId = accountField.value;
    const peerIdentifier = peerField.value.trim();

    if (!accountId || !peerIdentifier) {
        await requestAppModal('alert', {
            title: 'Thiếu thông tin',
            message: 'Hãy chọn tài khoản và chọn nhóm trước khi tải topic.',
            confirmText: 'Đã hiểu',
            confirmClass: 'primary',
        });
        return;
    }

    topicButton.disabled = true;
    topicButton.textContent = 'Đang tải...';

    try {
        const url = new URL('<?= e(url('/groups/topics')) ?>');
        url.searchParams.set('account_id', accountId);
        url.searchParams.set('peer_identifier', peerIdentifier);

        const response = await fetch(url.toString(), {
            headers: {
                'Accept': 'application/json',
            },
        });
        let payload = {};

        try {
            payload = await response.json();
        } catch (error) {
            payload = {};
        }

        if (!response.ok || !payload.ok) {
            throw new Error(payload.message || 'Không tải được danh sách topic.');
        }

        const currentTopicId = topicSelector.dataset.currentTopicId || topicIdField.value;
        const currentTopicTitle = topicSelector.dataset.currentTopicTitle || topicTitleField.value;
        const currentGeneral = topicSelector.value === '';
        topicSelector.innerHTML = '';

        const generalOption = document.createElement('option');
        generalOption.value = '';
        generalOption.textContent = 'Topic chung / mặc định';
        topicSelector.appendChild(generalOption);

        for (const topic of payload.topics) {
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
    } catch (error) {
        await requestAppModal('alert', {
            title: 'Không tải được topic',
            message: error.message || 'Không tải được danh sách topic.',
            confirmText: 'Đã hiểu',
            confirmClass: 'primary',
        });
    } finally {
        topicButton.disabled = false;
        topicButton.textContent = 'Tải topic từ Telegram';
    }
});

loadDialogsButton?.addEventListener('click', async () => {
    const accountId = accountField?.value || '';

    if (!accountId) {
        await requestAppModal('alert', {
            title: 'Thiếu tài khoản',
            message: 'Hãy chọn tài khoản Telegram trước khi tải danh sách nhóm.',
            confirmText: 'Đã hiểu',
            confirmClass: 'primary',
        });
        return;
    }

    loadDialogsButton.disabled = true;
    loadDialogsButton.textContent = 'Đang tải...';
    setGroupDialogsStatus('Đang tải danh sách nhóm từ Telegram...');
    setGroupDialogsMeta('Đang đồng bộ dữ liệu nhóm từ Telegram.');

    try {
        const url = new URL('<?= e(url('/groups/dialogs')) ?>');
        url.searchParams.set('account_id', accountId);

        const response = await fetch(url.toString(), {
            headers: {
                'Accept': 'application/json',
            },
        });
        let payload = {};

        try {
            payload = await response.json();
        } catch (error) {
            payload = {};
        }

        if (!response.ok || !payload.ok) {
            throw new Error(payload.message || 'Không tải được danh sách nhóm.');
        }

        loadedDialogs = Array.isArray(payload.dialogs) ? payload.dialogs.map((dialog) => ({
            title: String(dialog.title || ''),
            peer_identifier: String(dialog.peer_identifier || ''),
            type: String(dialog.type || 'chat'),
            is_forum: Boolean(dialog.is_forum),
            username: dialog.username ? String(dialog.username) : null,
            public_link: dialog.public_link ? String(dialog.public_link) : null,
            invite_link: dialog.invite_link ? String(dialog.invite_link) : null,
            participants_count: Number.isInteger(dialog.participants_count) ? dialog.participants_count : (dialog.participants_count === null ? null : Number(dialog.participants_count) || null),
            already_added: Boolean(dialog.already_added),
            existing_count: Number(dialog.existing_count || 0),
            selected: false,
        })) : [];

        activeDialogFilter = 'all';
        activePickedPeer = peerField?.value.trim() || '';

        if (groupDialogsSearch) {
            groupDialogsSearch.value = '';
        }

        if (groupDialogImportAccountId) {
            groupDialogImportAccountId.value = accountId;
        }

        if (loadedDialogs.length === 0) {
            resetPeerSelector('Account này chưa có nhóm khả dụng.', true);
            activePickedPeer = peerField?.value.trim() || '';
            syncPeerSummary();

            if (groupDialogList) {
                groupDialogList.innerHTML = '<div class="group-dialog-empty">Không tìm thấy group nào trên account này. Hãy chắc tài khoản đã tham gia nhóm cần gửi.</div>';
            }

            setGroupDialogsStatus('Telegram không trả về group nào cho account này.', 'danger');
            setGroupDialogsMeta('Không tìm thấy group nào để hiển thị.');
            renderDialogList();
            return;
        }

        populatePeerSelector();
        syncPeerSummary();
        setGroupDialogsStatus(
            activePickedPeer !== '' && loadedDialogs.some((dialog) => dialog.peer_identifier === activePickedPeer)
                ? `Đã tải ${loadedDialogs.length} nhóm và giữ nguyên nhóm đang chọn.`
                : activePickedPeer !== ''
                    ? `Đã tải ${loadedDialogs.length} nhóm. Nhóm hiện tại không còn trong danh sách Telegram, bạn có thể chọn lại nếu cần.`
                : `Đã tải ${loadedDialogs.length} nhóm. Chọn một nhóm trong dropdown phía trên.`,
            'success'
        );
        renderDialogList();
    } catch (error) {
        const message = error.message || 'Không tải được danh sách nhóm.';
        setGroupDialogsStatus(message, 'danger');
        setGroupDialogsMeta('Không tải được danh sách nhóm.');

        await requestAppModal('alert', {
            title: 'Không tải được nhóm',
            message,
            confirmText: 'Đã hiểu',
            confirmClass: 'primary',
        });
    } finally {
        loadDialogsButton.disabled = false;
        loadDialogsButton.textContent = 'Tải danh sách nhóm';
    }
});

accountField?.addEventListener('change', () => {
    resetTopicSelection();

    if (groupDialogBrowser) {
        groupDialogBrowser.open = false;
    }

    clearLoadedDialogs(
        accountField.value
            ? 'Đã đổi account. Bấm "Tải danh sách nhóm" để lấy lại danh sách phù hợp.'
            : 'Chọn account rồi bấm "Tải danh sách nhóm" để lấy danh sách nhóm mà tài khoản đã tham gia.',
        accountField.value
            ? 'Đã đổi account, cần tải lại danh sách nhóm.'
            : 'Chọn account rồi bấm tải danh sách nhóm.',
        {
            resetPeerSelector: true,
            peerPlaceholder: accountField.value
                ? 'Bấm "Tải danh sách nhóm" để nạp dropdown theo account này'
                : 'Chọn account rồi bấm tải danh sách nhóm',
        }
    );
});

peerField?.addEventListener('change', () => {
    setActivePeer(peerField.value.trim(), {
        syncTitle: true,
        resetTopics: true,
        announce: peerField.value.trim() !== '',
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
    const fillButton = event.target.closest('[data-fill-dialog-peer]');

    if (!fillButton) {
        return;
    }

    setActivePeer(fillButton.dataset.fillDialogPeer || '', {
        syncTitle: true,
        resetTopics: true,
        announce: true,
    });
});

groupDialogList?.addEventListener('change', (event) => {
    const checkbox = event.target.closest('[data-select-dialog-peer]');

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
    const selectedCount = loadedDialogs.filter((dialog) => dialog.selected && !dialog.already_added).length;

    if (!accountField?.value) {
        event.preventDefault();
        await requestAppModal('alert', {
            title: 'Thiếu tài khoản',
            message: 'Hãy chọn tài khoản Telegram trước khi import nhóm.',
            confirmText: 'Đã hiểu',
            confirmClass: 'primary',
        });
        return;
    }

    if (selectedCount <= 0) {
        event.preventDefault();
        await requestAppModal('alert', {
            title: 'Chưa chọn nhóm',
            message: 'Hãy tick ít nhất một nhóm trước khi import.',
            confirmText: 'Đã hiểu',
            confirmClass: 'primary',
        });
    }
});

clearLoadedDialogs(
    accountField?.value
        ? 'Bấm "Tải danh sách nhóm" để lấy danh sách nhóm của account đang chọn.'
        : 'Chọn account rồi bấm "Tải danh sách nhóm" để lấy danh sách nhóm mà tài khoản đã tham gia.'
);
</script>
