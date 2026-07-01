<section class="stack">
    <div class="topbar">
        <div>
            <h1 class="page-title">Message Templates</h1>
            <p class="page-subtitle">Nội dung tin nhắn được tách riêng thành template để có thể tái sử dụng cho nhiều group hoặc nhiều lịch khác nhau.</p>
        </div>
        <span class="badge info">Template Library</span>
    </div>

    <div class="grid grid-2">
        <section class="card">
            <h2 class="section-title"><?= $editTemplate ? 'Cập nhật template' : 'Tạo template mới' ?></h2>
            <div class="field" style="margin: 16px 0 18px;">
                <label for="template_preset">Preset template</label>
                <select class="select" id="template_preset">
                    <option value="">Chọn preset để tự động điền form</option>
                    <?php foreach ($templatePresets as $preset): ?>
                        <option value="<?= e($preset['key']) ?>"><?= e($preset['name']) ?> · <?= e($preset['description']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="chip-row" style="margin-bottom: 18px;">
                <?php foreach (array_slice($templatePresets, 0, 5) as $preset): ?>
                    <button class="chip" type="button" data-template-chip="<?= e($preset['key']) ?>"><?= e($preset['name']) ?></button>
                <?php endforeach; ?>
            </div>
            <form class="form-grid" method="post" action="<?= e(url($editTemplate ? '/templates/update' : '/templates')) ?>">
                <?= csrf_field() ?>
                <?php if ($editTemplate): ?>
                    <input type="hidden" name="id" value="<?= e((string) $editTemplate['id']) ?>">
                <?php endif; ?>
                <div class="field">
                    <label for="name">Tên template</label>
                    <input class="input" id="name" type="text" name="name" value="<?= e($editTemplate['name'] ?? '') ?>" required>
                </div>
                <div class="field">
                    <label for="label_id">Label</label>
                    <select class="select" id="label_id" name="label_id">
                        <option value="">Không gắn label</option>
                        <?php foreach ($labels as $label): ?>
                            <option value="<?= e((string) $label['id']) ?>" <?= (string) ($editTemplate['label_id'] ?? '') === (string) $label['id'] ? 'selected' : '' ?>>
                                <?= e($label['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="parse_mode">Parse mode</label>
                    <select class="select" id="parse_mode" name="parse_mode">
                        <?php foreach (['HTML', 'Markdown'] as $mode): ?>
                            <option value="<?= e($mode) ?>" <?= ($editTemplate['parse_mode'] ?? 'HTML') === $mode ? 'selected' : '' ?>><?= e($mode) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="body">Nội dung</label>
                    <textarea class="textarea" id="body" name="body" required><?= e($editTemplate['body'] ?? '') ?></textarea>
                </div>
                <label class="checkbox-row">
                    <input type="checkbox" name="is_active" value="1" <?= !isset($editTemplate['is_active']) || (int) $editTemplate['is_active'] === 1 ? 'checked' : '' ?>>
                    <span>Cho phép sử dụng template này</span>
                </label>
                <div class="actions">
                    <button class="button primary" type="submit"><?= $editTemplate ? 'Cập nhật' : 'Tạo template' ?></button>
                    <?php if ($editTemplate): ?>
                        <a class="button secondary" href="<?= e(url('/templates')) ?>">Tạo mới</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="panel">
            <div class="panel-header">
                <h2 class="panel-title">Danh sách templates</h2>
            </div>
            <div class="panel-body list">
                <?php foreach ($templates as $template): ?>
                    <article class="list-item">
                        <div class="inline-actions">
                            <strong><?= e($template['name']) ?></strong>
                            <?php if (!empty($template['label_name'])): ?>
                                <span class="badge"><?= e($template['label_name']) ?></span>
                            <?php endif; ?>
                            <span class="badge <?= (int) $template['is_active'] === 1 ? 'success' : 'warning' ?>"><?= (int) $template['is_active'] === 1 ? 'active' : 'inactive' ?></span>
                        </div>
                        <div class="small muted mono"><?= e($template['parse_mode']) ?></div>
                        <p><?= nl2br(e(mb_substr($template['body'], 0, 240))) ?></p>
                        <div class="inline-actions">
                            <a class="button secondary" href="<?= e(url('/templates?edit=' . $template['id'])) ?>">Sửa</a>
                            <form method="post" action="<?= e(url('/templates/delete')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e((string) $template['id']) ?>">
                                <button class="button danger" type="submit">Xóa</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
                <?php if ($templates === []): ?>
                    <div class="muted">Chưa có template nào.</div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</section>
<script>
const templatePresets = <?= json_encode($templatePresets, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const labelOptions = <?= json_encode(array_map(static fn ($label) => ['id' => $label['id'], 'slug' => $label['slug']], $labels), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

const templatePresetSelect = document.getElementById('template_preset');
const templateNameInput = document.getElementById('name');
const templateBodyInput = document.getElementById('body');
const templateParseModeInput = document.getElementById('parse_mode');
const templateLabelInput = document.getElementById('label_id');

function applyTemplatePreset(key) {
    const preset = templatePresets.find((item) => item.key === key);
    if (!preset) {
        return;
    }

    templateNameInput.value = preset.name;
    templateBodyInput.value = preset.body;
    templateParseModeInput.value = preset.parse_mode;

    const labelMatch = labelOptions.find((item) => item.slug === preset.label_slug);
    templateLabelInput.value = labelMatch ? String(labelMatch.id) : '';
}

templatePresetSelect?.addEventListener('change', (event) => {
    applyTemplatePreset(event.target.value);
});

document.querySelectorAll('[data-template-chip]').forEach((button) => {
    button.addEventListener('click', () => {
        const key = button.getAttribute('data-template-chip');
        templatePresetSelect.value = key;
        applyTemplatePreset(key);
    });
});
</script>
