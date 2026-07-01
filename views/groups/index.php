<section class="stack">
    <div class="topbar">
        <div>
            <h1 class="page-title">Telegram Groups</h1>
            <p class="page-subtitle">Khai báo các nhóm hoặc channel đích cho từng account. `Peer identifier` có thể là `@username`, `-100...` hoặc bất kỳ định danh chat nào mà account đó có quyền gửi.</p>
        </div>
        <span class="badge info">Per Account Mapping</span>
    </div>

    <div class="grid grid-2">
        <section class="card">
            <h2 class="section-title"><?= $editGroup ? 'Cập nhật group' : 'Thêm group mới' ?></h2>
            <form class="form-grid" method="post" action="<?= e(url($editGroup ? '/groups/update' : '/groups')) ?>">
                <?= csrf_field() ?>
                <?php if ($editGroup): ?>
                    <input type="hidden" name="id" value="<?= e((string) $editGroup['id']) ?>">
                <?php endif; ?>
                <div class="field">
                    <label for="telegram_account_id">Telegram account</label>
                    <select class="select" id="telegram_account_id" name="telegram_account_id" required>
                        <option value="">Chọn account</option>
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
                <div class="field">
                    <label for="peer_identifier">Id Nhóm</label>
                    <input class="input mono" id="peer_identifier" type="text" name="peer_identifier" value="<?= e($editGroup['peer_identifier'] ?? '') ?>" placeholder="Ví dụ: -1001234567890" required>
                </div>
                <div class="field">
                    <label for="topic_selector">Chọn topic (tùy chọn)</label>
                    <div class="helper-row">
                        <button class="button secondary" id="load_topics_button" type="button">Tải topic từ Telegram</button>
                        <span class="helper-text">Chọn account + nhập ID nhóm trước, rồi bấm tải danh sách topic thật. Hệ thống sẽ lưu đúng mã `top message` để gửi vào đúng topic.</span>
                    </div>
                    <select
                        class="select"
                        id="topic_selector"
                        data-current-topic-id="<?= e(isset($editGroup['topic_id']) && $editGroup['topic_id'] !== null ? (string) $editGroup['topic_id'] : '') ?>"
                        data-current-topic-title="<?= e($editGroup['topic_title'] ?? '') ?>"
                    >
                        <option value="">Topic chung / General</option>
                        <?php if (!empty($editGroup['topic_id'])): ?>
                            <option value="<?= e((string) $editGroup['topic_id']) ?>" selected>
                                <?= e($editGroup['topic_title'] ?: ('Topic #' . $editGroup['topic_id'])) ?>
                            </option>
                        <?php endif; ?>
                    </select>
                    <input type="hidden" id="topic_id" name="topic_id" value="<?= e(isset($editGroup['topic_id']) && $editGroup['topic_id'] !== null ? (string) $editGroup['topic_id'] : '') ?>">
                </div>
                <div class="field">
                    <label for="topic_title">Tên topic (tùy chọn)</label>
                    <input class="input" id="topic_title" type="text" name="topic_title" value="<?= e($editGroup['topic_title'] ?? '') ?>" placeholder="Ví dụ: Chợ Mới">
                </div>
                <div class="field">
                    <label for="notes">Ghi chú</label>
                    <textarea class="textarea" id="notes" name="notes"><?= e($editGroup['notes'] ?? '') ?></textarea>
                </div>
                <label class="checkbox-row">
                    <input type="checkbox" name="is_active" value="1" <?= !isset($editGroup['is_active']) || (int) $editGroup['is_active'] === 1 ? 'checked' : '' ?>>
                    <span>Kích hoạt group này</span>
                </label>
                <div class="actions">
                    <button class="button primary" type="submit"><?= $editGroup ? 'Cập nhật group' : 'Lưu group' ?></button>
                    <?php if ($editGroup): ?>
                        <a class="button secondary" href="<?= e(url('/groups')) ?>">Tạo mới</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="card">
            <h2 class="section-title">Gợi ý cấu hình</h2>
            <div class="list">
                <div class="list-item">Mỗi group nên gắn với đúng account thực tế đã được add vào nhóm.</div>
                <div class="list-item">Nếu một account phụ bị giới hạn quyền, schedule gắn với group đó sẽ log lỗi chi tiết.</div>
                <div class="list-item">Nếu group là forum có nhiều topic, hãy tạo nhiều target cùng `Id Nhóm`, mỗi target dùng một `Topic ID / top_msg_id` khác nhau.</div>
                <div class="list-item">Bạn có thể dán luôn link topic dạng `t.me/.../2780362`, hệ thống sẽ tự lấy số cuối làm Topic ID.</div>
            </div>
        </section>
    </div>

    <section class="panel">
        <div class="panel-header">
            <h2 class="panel-title">Danh sách groups</h2>
        </div>
        <div class="panel-body table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Account</th>
                        <th>Peer</th>
                        <th>Topic</th>
                        <th>State</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($groups as $group): ?>
                    <tr>
                        <td>
                            <strong><?= e($group['title']) ?></strong>
                            <div class="small muted"><?= e($group['notes']) ?></div>
                        </td>
                        <td><?= e($group['account_name']) ?></td>
                        <td class="mono"><?= e($group['peer_identifier']) ?></td>
                        <td>
                            <?php if (!empty($group['topic_id'])): ?>
                                <div class="mono"><?= e((string) $group['topic_id']) ?></div>
                                <div class="small muted"><?= e($group['topic_title'] ?: 'Topic cụ thể') ?></div>
                            <?php else: ?>
                                <span class="muted">Topic chung / General</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge <?= (int) $group['is_active'] === 1 ? 'success' : 'warning' ?>"><?= (int) $group['is_active'] === 1 ? 'active' : 'inactive' ?></span></td>
                        <td>
                            <div class="inline-actions">
                                <a class="button secondary" href="<?= e(url('/groups?edit=' . $group['id'])) ?>">Sửa</a>
                                <form method="post" action="<?= e(url('/groups/delete')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= e((string) $group['id']) ?>">
                                    <button class="button danger" type="submit">Xóa</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($groups === []): ?>
                    <tr><td colspan="6" class="muted">Chưa có group nào.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
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
        alert('Hãy chọn account và nhập ID nhóm trước khi tải topic.');
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
        generalOption.textContent = 'Topic chung / General';
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
