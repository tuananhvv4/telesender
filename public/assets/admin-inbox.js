(function () {
  'use strict';

  const app = document.getElementById('telegram_inbox_app');
  if (!app) return;

  const adminSelect = document.getElementById('inbox_admin_select');
  const accountSelect = document.getElementById('inbox_account_select');
  const searchInput = document.getElementById('inbox_search');
  const dialogList = document.getElementById('inbox_dialog_list');
  const dialogCount = document.getElementById('inbox_dialog_count');
  const messageList = document.getElementById('inbox_message_list');
  const messageScroll = document.getElementById('inbox_message_scroll');
  const loadOlderButton = document.getElementById('inbox_load_older');
  const refreshDialogsButton = document.getElementById('inbox_refresh_dialogs');
  const refreshMessagesButton = document.getElementById('inbox_refresh_messages');
  const topicField = document.getElementById('inbox_topic_field');
  const topicSelect = document.getElementById('inbox_topic_select');
  const mobileBackButton = document.getElementById('inbox_mobile_back');
  const messagePane = document.getElementById('inbox_message_pane');
  const statusTarget = document.getElementById('inbox_global_status');
  const titleTarget = document.getElementById('inbox_conversation_title');
  const metaTarget = document.getElementById('inbox_conversation_meta');
  const avatarTarget = document.getElementById('inbox_conversation_avatar');

  const urls = {
    accounts: app.dataset.accountsUrl,
    dialogs: app.dataset.dialogsUrl,
    topics: app.dataset.topicsUrl,
    messages: app.dataset.messagesUrl,
    syncAccount: app.dataset.syncAccountUrl,
    syncDialog: app.dataset.syncDialogUrl,
    loadOlder: app.dataset.loadOlderUrl,
    media: app.dataset.mediaUrl,
  };
  const csrfToken = app.dataset.csrfToken;
  const state = {
    accountId: null,
    dialogId: null,
    dialog: null,
    topicId: null,
    oldestMessageId: null,
    historyComplete: false,
    dialogPoll: null,
    messagePoll: null,
  };

  function escapeHtml(value) {
    return String(value ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  function initials(value) {
    return String(value || 'TG').trim().split(/\s+/).slice(0, 2).map((part) => part[0] || '').join('').toUpperCase() || 'TG';
  }

  function senderHue(value) {
    return Array.from(String(value || 'Telegram')).reduce((hash, character) => ((hash * 31) + character.codePointAt(0)) % 360, 0);
  }

  function formatTime(value) {
    if (!value) return '';
    const date = new Date(String(value).replace(' ', 'T') + 'Z');
    if (Number.isNaN(date.getTime())) return value;
    return new Intl.DateTimeFormat('vi-VN', {
      day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'
    }).format(date);
  }

  function syncLabel(sync) {
    const status = sync?.status || 'not_synced';
    const error = String(sync?.last_error_message || '').trim();
    if (status === 'running') return 'Đang đồng bộ...';
    if (status === 'pending') return 'Đang chờ cron đồng bộ...';
    if (status === 'retry') return sync.last_error_code === 'account_busy'
      ? 'Account đang ưu tiên gửi tin.'
      : (error ? `Đồng bộ lỗi, sẽ thử lại: ${error}` : 'Đồng bộ sẽ tự thử lại.');
    if (status === 'failed') return error ? `Không thể đồng bộ: ${error}` : 'Không thể đồng bộ account này.';
    if (status === 'completed') return sync.last_error_code === 'empty_dialogs'
      ? (error || 'Telegram trả về 0 hội thoại cho account này.')
      : 'Dữ liệu đã được cập nhật.';
    return 'Chưa đồng bộ.';
  }

  async function getJson(url) {
    const response = await fetch(url, {headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}});
    const payload = await response.json();
    if (!response.ok || !payload.ok) throw new Error(payload.message || 'Không thể tải dữ liệu.');
    return payload;
  }

  async function postForm(url, fields) {
    const body = new URLSearchParams({_token: csrfToken, ...fields});
    const response = await fetch(url, {
      method: 'POST',
      headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
      body,
    });
    const payload = await response.json();
    if (!response.ok || !payload.ok) throw new Error(payload.message || 'Không thể thực hiện yêu cầu.');
    return payload;
  }

  function showError(error) {
    statusTarget.textContent = error.message || 'Đã có lỗi xảy ra.';
    statusTarget.classList.add('error');
  }

  function clearPoll(name) {
    if (state[name]) window.clearTimeout(state[name]);
    state[name] = null;
  }

  async function loadAccounts() {
    clearPoll('dialogPoll');
    clearPoll('messagePoll');
    state.accountId = null;
    state.dialogId = null;
    accountSelect.innerHTML = '<option value="">Chọn account</option>';
    accountSelect.disabled = true;
    searchInput.disabled = true;
    refreshDialogsButton.disabled = true;
    resetDialogs('Đang tải Telegram account...');
    resetMessages();
    if (!adminSelect.value) {
      resetDialogs('Chưa chọn Telegram account.');
      return;
    }

    try {
      const payload = await getJson(`${urls.accounts}?user_id=${encodeURIComponent(adminSelect.value)}`);
      payload.items.forEach((account) => {
        const option = document.createElement('option');
        option.value = account.id;
        option.textContent = `${account.name}${account.tg_username ? ' · @' + account.tg_username : ''}${account.is_active == 0 ? ' · tạm dừng gửi' : ''}`;
        accountSelect.appendChild(option);
      });
      accountSelect.disabled = payload.items.length === 0;
      statusTarget.textContent = payload.items.length ? 'Chọn Telegram account.' : 'Admin này chưa có Telegram account active.';
      resetDialogs(payload.items.length ? 'Chọn Telegram account.' : 'Không có account đã đăng nhập.');
    } catch (error) {
      showError(error);
    }
  }

  async function selectAccount() {
    clearPoll('dialogPoll');
    clearPoll('messagePoll');
    state.accountId = Number(accountSelect.value) || null;
    state.dialogId = null;
    searchInput.disabled = !state.accountId;
    refreshDialogsButton.disabled = !state.accountId;
    resetMessages();
    if (!state.accountId) {
      resetDialogs('Chọn Telegram account.');
      return;
    }

    resetDialogs('Đang đọc dữ liệu đã đồng bộ...');
    statusTarget.textContent = 'Đang đồng bộ Telegram ngay...';
    try {
      const sync = await postForm(urls.syncAccount, {account_id: state.accountId});
      statusTarget.textContent = sync.message;
      await loadDialogs(true);
    } catch (error) {
      showError(error);
    }
  }

  async function loadDialogs(keepPolling) {
    if (!state.accountId) return;
    try {
      const payload = await getJson(`${urls.dialogs}?account_id=${state.accountId}&q=${encodeURIComponent(searchInput.value.trim())}`);
      renderDialogs(payload.items || []);
      statusTarget.classList.remove('error');
      statusTarget.textContent = syncLabel(payload.sync);
      const status = payload.sync?.status;
      clearPoll('dialogPoll');
      if (keepPolling && ['pending', 'running', 'retry'].includes(status)) {
        state.dialogPoll = window.setTimeout(() => loadDialogs(true), 5000);
      }
    } catch (error) {
      showError(error);
    }
  }

  function renderDialogs(items) {
    dialogCount.textContent = `${items.length} cuộc trò chuyện`;
    if (!items.length) {
      resetDialogs('Chưa có hội thoại trong cache. Cron inbox sẽ tự đồng bộ.');
      return;
    }
    dialogList.innerHTML = items.map((dialog) => `
      <button class="inbox-dialog-item ${Number(dialog.id) === state.dialogId ? 'active' : ''}" type="button" data-dialog-id="${dialog.id}">
        <span class="inbox-avatar ${escapeHtml(dialog.peer_type)}">${escapeHtml(initials(dialog.title))}</span>
        <span class="inbox-dialog-copy">
          <span class="inbox-dialog-line"><strong>${escapeHtml(dialog.title)}</strong><time>${escapeHtml(formatTime(dialog.last_message_at))}</time></span>
          <span class="inbox-dialog-line muted"><span>${escapeHtml(dialog.last_message_text || dialog.username || dialog.peer_type)}</span>${Number(dialog.unread_count) > 0 ? `<b>${dialog.unread_count}</b>` : ''}</span>
        </span>
      </button>
    `).join('');
    dialogList.querySelectorAll('[data-dialog-id]').forEach((button) => {
      button.addEventListener('click', () => openDialog(Number(button.dataset.dialogId), items.find((item) => Number(item.id) === Number(button.dataset.dialogId))));
    });
  }

  async function openDialog(dialogId, dialog) {
    clearPoll('messagePoll');
    state.dialogId = dialogId;
    state.dialog = dialog;
    state.topicId = null;
    resetTopics(Boolean(Number(dialog.is_forum)));
    titleTarget.textContent = dialog.title;
    metaTarget.textContent = `${dialog.peer_type}${dialog.username ? ' · @' + dialog.username : ''}`;
    avatarTarget.textContent = initials(dialog.title);
    refreshMessagesButton.disabled = false;
    messagePane.classList.add('mobile-open');
    dialogList.querySelectorAll('.inbox-dialog-item').forEach((item) => item.classList.toggle('active', Number(item.dataset.dialogId) === dialogId));
    messageList.innerHTML = '<div class="inbox-empty">Đang đọc tin nhắn đã đồng bộ...</div>';
    statusTarget.textContent = 'Đang đồng bộ hội thoại ngay...';
    try {
      const sync = await postForm(urls.syncDialog, {dialog_id: dialogId});
      statusTarget.textContent = sync.message;
      await loadTopics();
      await loadMessages(false, true);
    } catch (error) {
      showError(error);
    }
  }

  async function loadTopics() {
    if (!state.dialogId || !Number(state.dialog?.is_forum)) {
      resetTopics(false);
      return;
    }

    const payload = await getJson(`${urls.topics}?dialog_id=${state.dialogId}`);
    const selected = state.topicId;
    topicSelect.innerHTML = '<option value="">Tất cả topic</option>';
    (payload.items || []).forEach((topic) => {
      const option = document.createElement('option');
      option.value = topic.topic_id;
      option.textContent = topic.title;
      topicSelect.appendChild(option);
    });
    topicSelect.value = selected ? String(selected) : '';
    topicField.hidden = false;
    topicSelect.disabled = false;
  }

  async function loadMessages(before, keepPolling) {
    if (!state.dialogId) return;
    const beforePart = before ? `&before_message_id=${before}` : '';
    const topicPart = state.topicId ? `&topic_id=${state.topicId}` : '';
    try {
      const payload = await getJson(`${urls.messages}?dialog_id=${state.dialogId}&limit=40${topicPart}${beforePart}`);
      if (before) prependMessages(payload.items || []);
      else renderMessages(payload.items || []);
      state.oldestMessageId = payload.oldest_message_id || state.oldestMessageId;
      state.historyComplete = Boolean(payload.history_complete);
      loadOlderButton.hidden = state.historyComplete || !state.oldestMessageId;
      statusTarget.textContent = syncLabel(payload.sync);
      if (Number(state.dialog?.is_forum) && topicSelect.options.length <= 1) {
        await loadTopics();
      }
      clearPoll('messagePoll');
      if (keepPolling && ['pending', 'running', 'retry'].includes(payload.sync?.status)) {
        state.messagePoll = window.setTimeout(() => loadMessages(false, true), 5000);
      }
    } catch (error) {
      showError(error);
    }
  }

  function renderMessages(items) {
    if (!items.length) {
      messageList.innerHTML = '<div class="inbox-empty">Chưa có tin nhắn trong cache. Cron inbox đang xử lý.</div>';
      return;
    }
    messageList.innerHTML = items.map(messageHtml).join('');
    bindMediaButtons(messageList);
    messageScroll.scrollTop = messageScroll.scrollHeight;
  }

  function prependMessages(items) {
    const existingIds = new Set(Array.from(messageList.querySelectorAll('[data-message-id]')).map((item) => item.dataset.messageId));
    items = items.filter((item) => !existingIds.has(String(item.telegram_message_id)));
    if (!items.length) return;
    const oldHeight = messageScroll.scrollHeight;
    messageList.insertAdjacentHTML('afterbegin', items.map(messageHtml).join(''));
    bindMediaButtons(messageList);
    messageScroll.scrollTop = messageScroll.scrollHeight - oldHeight;
  }

  function messageHtml(message) {
    const incoming = !Number(message.is_outgoing);
    const senderName = message.sender_name || 'Telegram';
    const reply = message.reply_to_message_id ? `
      <div class="inbox-reply-preview">
        <strong>${escapeHtml(message.reply_sender_name || 'Tin nhắn #' + message.reply_to_message_id)}</strong>
        <span>${escapeHtml(message.reply_message_text || message.reply_quote_text || 'Nội dung chưa có trong cache')}</span>
      </div>` : '';
    const media = message.media_type ? `
      <button class="inbox-media-placeholder" type="button" data-media-id="${message.id}" data-media-type="${escapeHtml(message.media_type)}">
        <i class="fa-solid fa-paperclip"></i>
        <span><strong>${escapeHtml(message.media_type)}</strong><small>${escapeHtml(message.media_file_name || 'Bấm để tải media')}${message.media_size ? ' · ' + formatBytes(message.media_size) : ''}</small></span>
      </button>` : '';
    return `
      <article class="inbox-message ${Number(message.is_outgoing) ? 'outgoing' : 'incoming'}" data-message-id="${message.telegram_message_id}">
        ${incoming ? `<span class="inbox-message-avatar" style="--sender-hue:${senderHue(senderName)}">${escapeHtml(initials(senderName))}</span>` : ''}
        <div class="inbox-bubble">
          ${incoming ? `<strong class="inbox-sender">${escapeHtml(senderName)}</strong>` : ''}
          ${reply}${message.message_text ? `<div class="inbox-message-text">${escapeHtml(message.message_text).replaceAll('\n', '<br>')}</div>` : ''}${media}
          <div class="inbox-message-meta">${message.edited_at ? '<span>đã sửa</span>' : ''}<time>${escapeHtml(formatTime(message.telegram_created_at))}</time></div>
        </div>
      </article>`;
  }

  function bindMediaButtons(scope) {
    scope.querySelectorAll('[data-media-id]:not([data-bound])').forEach((button) => {
      button.dataset.bound = '1';
      button.addEventListener('click', () => loadMedia(button));
    });
  }

  async function loadMedia(button) {
    const original = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i><span>Đang tải media...</span>';
    try {
      const response = await fetch(`${urls.media}?message_id=${button.dataset.mediaId}`, {credentials: 'same-origin'});
      if (!response.ok) {
        let message = 'Không thể tải media.';
        try { message = (await response.json()).message || message; } catch (_) {}
        throw new Error(message);
      }
      const blob = await response.blob();
      const objectUrl = URL.createObjectURL(blob);
      const type = button.dataset.mediaType;
      if (type === 'photo' || type === 'sticker') {
        button.replaceWith(Object.assign(document.createElement('img'), {src: objectUrl, className: 'inbox-loaded-image', alt: 'Telegram media'}));
      } else if (['video', 'animation', 'video_note'].includes(type)) {
        const video = document.createElement('video');
        video.src = objectUrl; video.controls = true; video.className = 'inbox-loaded-video';
        button.replaceWith(video);
      } else if (['voice', 'audio'].includes(type)) {
        const audio = document.createElement('audio');
        audio.src = objectUrl; audio.controls = true; audio.className = 'inbox-loaded-audio';
        button.replaceWith(audio);
      } else {
        const link = document.createElement('a');
        link.href = objectUrl; link.download = ''; link.className = 'button secondary sm'; link.textContent = 'Tải file';
        button.replaceWith(link);
      }
    } catch (error) {
      button.disabled = false;
      button.innerHTML = original;
      showError(error);
    }
  }

  function formatBytes(value) {
    const bytes = Number(value) || 0;
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / 1048576).toFixed(1)} MB`;
  }

  async function loadOlder() {
    if (!state.dialogId || !state.oldestMessageId) return;
    loadOlderButton.disabled = true;
    try {
      const topicPart = state.topicId ? `&topic_id=${state.topicId}` : '';
      const payload = await getJson(`${urls.messages}?dialog_id=${state.dialogId}&limit=40${topicPart}&before_message_id=${state.oldestMessageId}`);
      if ((payload.items || []).length) {
        prependMessages(payload.items);
        state.oldestMessageId = payload.oldest_message_id;
      } else if (!payload.history_complete) {
        const sync = await postForm(urls.loadOlder, {
          dialog_id: state.dialogId,
          before_message_id: state.oldestMessageId,
          topic_id: state.topicId || '',
        });
        statusTarget.textContent = sync.message;
        await loadMessages(state.oldestMessageId, true);
        return;
      }
      state.historyComplete = Boolean(payload.history_complete);
      loadOlderButton.hidden = state.historyComplete;
    } catch (error) {
      showError(error);
    } finally {
      loadOlderButton.disabled = false;
    }
  }

  function resetDialogs(message) {
    dialogCount.textContent = '0 cuộc trò chuyện';
    dialogList.innerHTML = `<div class="inbox-empty">${escapeHtml(message)}</div>`;
  }

  function resetMessages() {
    state.dialogId = null;
    state.dialog = null;
    state.topicId = null;
    state.oldestMessageId = null;
    titleTarget.textContent = 'Chọn một hội thoại';
    metaTarget.textContent = 'Private chat, group hoặc channel';
    avatarTarget.textContent = 'TG';
    refreshMessagesButton.disabled = true;
    loadOlderButton.hidden = true;
    messagePane.classList.remove('mobile-open');
    messageList.innerHTML = '<div class="inbox-empty inbox-empty-large"><i class="fa-regular fa-comments"></i><strong>Hộp thư chỉ đọc</strong><span>Chọn một cuộc trò chuyện ở cột bên trái.</span></div>';
    resetTopics(false);
  }

  function resetTopics(show) {
    topicSelect.innerHTML = '<option value="">Tất cả topic</option>';
    topicSelect.disabled = !show;
    topicField.hidden = !show;
  }

  let searchTimer = null;
  adminSelect.addEventListener('change', loadAccounts);
  accountSelect.addEventListener('change', selectAccount);
  searchInput.addEventListener('input', () => {
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(() => loadDialogs(false), 300);
  });
  refreshDialogsButton.addEventListener('click', async () => {
    if (!state.accountId) return;
    refreshDialogsButton.disabled = true;
    statusTarget.textContent = 'Đang đồng bộ Telegram ngay...';
    try {
      const sync = await postForm(urls.syncAccount, {account_id: state.accountId});
      statusTarget.textContent = sync.message;
      await loadDialogs(true);
    } catch (error) {
      showError(error);
    } finally {
      refreshDialogsButton.disabled = !state.accountId;
    }
  });
  refreshMessagesButton.addEventListener('click', async () => {
    if (!state.dialogId) return;
    refreshMessagesButton.disabled = true;
    statusTarget.textContent = 'Đang đồng bộ hội thoại ngay...';
    try {
      const sync = await postForm(urls.syncDialog, {
        dialog_id: state.dialogId,
        topic_id: state.topicId || '',
      });
      statusTarget.textContent = sync.message;
      await loadTopics();
      await loadMessages(false, true);
    } catch (error) {
      showError(error);
    } finally {
      refreshMessagesButton.disabled = !state.dialogId;
    }
  });
  topicSelect.addEventListener('change', async () => {
    if (!state.dialogId) return;
    clearPoll('messagePoll');
    state.topicId = Number(topicSelect.value) || null;
    state.oldestMessageId = null;
    state.historyComplete = false;
    loadOlderButton.hidden = true;
    topicSelect.disabled = true;
    messageList.innerHTML = '<div class="inbox-empty">Đang đồng bộ topic...</div>';
    statusTarget.textContent = state.topicId ? 'Đang đồng bộ topic ngay...' : 'Đang tải tất cả topic...';
    try {
      const sync = await postForm(urls.syncDialog, {
        dialog_id: state.dialogId,
        topic_id: state.topicId || '',
      });
      statusTarget.textContent = sync.message;
      await loadMessages(false, true);
    } catch (error) {
      showError(error);
    } finally {
      topicSelect.disabled = false;
    }
  });
  loadOlderButton.addEventListener('click', loadOlder);
  mobileBackButton.addEventListener('click', () => messagePane.classList.remove('mobile-open'));
})();
