<section class="stack">
    <div class="topbar">
        <h1 class="page-title">Nhóm Telegram</h1>
    </div>

    <div class="group-workspace">
        <section class="card group-editor-card">
            <div class="group-editor-head">
                <div>
                    <h2 class="section-title"><?= $editGroup ? 'Cập nhật nhóm' : 'Thêm nhóm mới' ?></h2>
                    <div class="small muted"><?= $editGroup ? 'Sửa nhanh thông tin đích gửi và topic tương ứng.' : 'Khai báo nhóm đích, topic và gắn đúng với tài khoản gửi.' ?></div>
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
                    <div class="field">
                        <label for="telegram_account_id">Tài khoản Telegram</label>
                        <select class="select" id="telegram_account_id" name="telegram_account_id" required>
                            <option value="">Chọn tài khoản</option>
                            <?php foreach ($accounts as $account): ?>
                                <option value="<?= e((string) $account['id']) ?>" <?= (string) ($editGroup['telegram_account_id'] ?? '') === (string) $account['id'] ? 'selected' : '' ?>>
                                <?= e($account['name']) ?> (<?= e($account['phone_number']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field">
                        <label for="title">Tên nhóm</label>
                        <input class="input" id="title" type="text" name="title" value="<?= e($editGroup['title'] ?? '') ?>" required>
                    </div>

                    <div class="field group-field-span-2">
                        <label for="peer_identifier">Id Nhóm</label>
                        <input class="input mono" id="peer_identifier" type="text" name="peer_identifier" value="<?= e($editGroup['peer_identifier'] ?? '') ?>" placeholder="Ví dụ: -1001234567890" required>
                    </div>

                    <div class="field group-field-span-2">
                        <div class="group-topic-head">
                            <label for="topic_selector">Topic (tùy chọn)</label>
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

                    <div class="field">
                        <label for="topic_title">Tên topic</label>
                        <input class="input" id="topic_title" type="text" name="topic_title" value="<?= e($editGroup['topic_title'] ?? '') ?>" placeholder="Ví dụ: Chợ Mới">
                    </div>

                    <div class="field">
                        <label for="notes">Ghi chú</label>
                        <input class="input" id="notes" type="text" name="notes" value="<?= e($editGroup['notes'] ?? '') ?>" placeholder="Ghi chú ngắn để dễ phân biệt">
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
const accountField = document.getElementById('telegram_account_id');
const peerField = document.getElementById('peer_identifier');
const topicButton = document.getElementById('load_topics_button');
const topicSelector = document.getElementById('topic_selector');
const topicIdField = document.getElementById('topic_id');
const topicTitleField = document.getElementById('topic_title');

function syncTopicFields() {
    const selected = topicSelector.options[topicSelector.selectedIndex];
    topicIdField.value = topicSelector.value;
    topicTitleField.value = topicSelector.value === '' ? '' : selected.text.replace(/^Topic:\s*/, '').trim();
}

function normalizeTopicTitle(value) {
    return String(value || '').trim().toLowerCase();
}

topicSelector?.addEventListener('change', syncTopicFields);

topicButton?.addEventListener('click', async () => {
    const accountId = accountField.value;
    const peerIdentifier = peerField.value.trim();

    if (!accountId || !peerIdentifier) {
        alert('Hãy chọn tài khoản và nhập ID nhóm trước khi tải topic.');
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
        const payload = await response.json();

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
        alert(error.message || 'Không tải được danh sách topic.');
    } finally {
        topicButton.disabled = false;
        topicButton.textContent = 'Tải topic từ Telegram';
    }
});
</script>
