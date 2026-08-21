# Ke hoach thiet ke Super Admin Telegram Inbox

## 1. Muc tieu tai lieu

Tai lieu nay mo ta thiet ke ky thuat de super admin co the chon mot admin con, chon Telegram account ma admin do da dang nhap vao TeleSender, sau do xem private chat, group va channel theo giao dien gan voi Telegram Web.

Tai lieu chi thiet ke phia super admin. Khong bo sung giao dien, notification, setting hay thong bao nao cho admin con.

Phien ban dau la read-only:

- Chon admin con va Telegram account.
- Xem danh sach private chat, group, supergroup va channel.
- Xem text, nguoi gui, thoi gian, reply va trang thai edited.
- Phan trang lich su tin nhan.
- Media chi hien placeholder; chi tai khi super admin bam xem.
- Khong gui, xoa, sua, forward hoac reaction.
- Khong danh dau tin nhan da doc tren Telegram.
- Dong bo bang cron co checkpoint, co the tiep tuc sau timeout hoac process bi kill.
- Gui tin hien tai luon co uu tien cao hon dong bo inbox va tai media.
- Tat ca route giao dien/API chi danh cho role `super_admin`.

## 2. Ket qua khao sat he thong hien tai

### 2.1 Nen tang va dependency

- Ung dung la PHP 8.3, MVC tu viet, MySQL va HTML/CSS/JavaScript thuan.
- Telegram account duoc dang nhap bang personal account, khong phai bot.
- Dependency dang cai la `danog/madelineproto` version `8.7.0`.
- Schema Telegram duoc bundle trong dependency la layer `225` tai `vendor/danog/madelineproto/src/TL_telegram_v225.tl`.
- Session Telegram cua moi account duoc luu tai `storage/telegram/{session_name}.madeline`.

### 2.2 Kha nang Telegram da co trong code

`app/Services/TelegramService.php` hien da:

- Tao va mo MadelineProto client tu session file.
- Dang nhap OTP/2FA.
- Gui tin nhan.
- Lay forum topics.
- Lay danh sach group bang `getFullDialogs()`.
- Download custom emoji.

Chua co:

- API lay danh sach tat ca dialog cho inbox.
- API lay lich su message.
- Chuan hoa Telegram message thanh du lieu database.
- Dong bo message theo job/cursor.
- Stream media cua message.
- Giao dien inbox.

### 2.3 Cron va lock hien tai

`SystemController::cron()` hien goi `SchedulerService::dispatchDueJobs()` va dat `set_time_limit(0)`.

`SchedulerService` dang dung hai lock co lease:

- `schedule_jobs.dispatch_locked_until` de lock schedule.
- `telegram_accounts.dispatch_locked_until` de lock Telegram account.

Account lock hien tai chi co thoi gian het han, chua co:

- Lock token de ngan process cu release lock cua process moi.
- Loai thao tac dang giu lock.
- Uu tien giua dispatch, inbox sync va media download.
- Shared service de cac module cung dung mot quy tac lock.

Vi inbox cung mo dung session file voi scheduler, inbox bat buoc phai tham gia cung mot account-level lock. Neu khong, hai process co the cung mo mot MadelineProto session va lam anh huong viec gui tin.

### 2.4 Phan quyen hien tai

Router da co middleware `super_admin`. Cac route inbox moi phai dung:

```php
['auth', 'super_admin']
```

Moi query lay admin con/account/dialog/message van phai kiem tra ownership o server. Middleware chi xac nhan nguoi dang truy cap la super admin, khong thay the kiem tra `account_id` thuoc mot user role `admin` hop le.

## 3. Tai lieu chinh thuc da doi chieu

### 3.1 Danh sach dialog

Tai lieu:

- https://docs.madelineproto.xyz/API_docs/methods/messages.getDialogs.html
- https://docs.madelineproto.xyz/docs/DIALOGS.html

Ket luan:

- `messages.getDialogs` tra ve `dialogs`, `messages`, `chats` va `users`.
- Co cac offset `offset_date`, `offset_id`, `offset_peer` de phan trang.
- `getFullDialogs()` tra day du dialog info, gom last message ID, unread count va mot so metadata khac, nhung co the ton thoi gian o lan dau.
- Inbox nen dung `messages.getDialogs` co gioi han va cursor thay vi goi `getFullDialogs()` toan bo trong moi lan cron.

### 3.2 Lich su message

Tai lieu:

- https://docs.madelineproto.xyz/API_docs/methods/messages.getHistory.html
- Local signature: `vendor/danog/madelineproto/src/Namespace/Messages.php`.

Ket luan:

- `messages.getHistory` tra lich su cua mot peer.
- Co `offset_id`, `offset_date`, `limit`, `max_id`, `min_id` va `hash`.
- Co `floodWaitLimit` de MadelineProto throw exception thay vi tu sleep khi Telegram yeu cau cho qua lau.
- Co `queueId` de dam bao thu tu server-side cho cac call cung queue ID, nhung `queueId` khong thay the database account lock cua ung dung.

Canh bao bat buoc dua vao thiet ke:

- Tai lieu MadelineProto canh bao `getHistory` de gap `FLOOD_WAIT`, co the dan den account ban neu bi goi day.
- Tai lieu khuyen dung updates event handler cho realtime.
- Yeu cau cua tinh nang nay la cron, do do thiet ke khong duoc quet tat ca chat lien tuc. Phai dung demand-driven jobs, batch nho, request budget, backoff va checkpoint.

### 3.3 Message fields can thiet

Nguon local da cai:

- `vendor/danog/madelineproto/src/TL_telegram_v225.tl`.

Constructor `message` co cac field phuc vu MVP:

- `id`: Telegram message ID.
- `out`: tin do account hien tai gui.
- `from_id`: nguoi gui.
- `peer_id`: dialog dich.
- `reply_to`: reply header.
- `date`: thoi gian gui.
- `message`: noi dung text/caption.
- `media`: photo/document/voice/video va cac loai media khac.
- `edit_date`: thoi gian sua.
- `post_author`: ten tac gia trong mot so channel.
- `grouped_id`: album/grouped media.

Constructor `messageReplyHeader` co:

- `reply_to_msg_id`.
- `reply_to_peer_id`.
- `reply_to_top_id` cho forum/thread.
- `quote_text` neu Telegram tra ve quote.

Constructor `dialog` co:

- `peer`.
- `top_message`.
- `read_inbox_max_id`.
- `read_outbox_max_id`.
- `unread_count`.
- `folder_id`.

Inbox chi luu unread count do Telegram tra ve; khong thay doi read state.

### 3.4 Khong danh dau da doc

Tai lieu:

- https://docs.madelineproto.xyz/API_docs/methods/messages.readHistory.html
- https://docs.madelineproto.xyz/API_docs/methods/channels.readHistory.html

Hai method tren moi la cac method dung de danh dau history da doc. Inbox read-only tuyet doi khong goi:

```text
messages.readHistory
channels.readHistory
messages.readMessageContents
```

Khong goi `messages.getMessagesViews` voi `increment=true`.

### 3.5 Media

Tai lieu:

- https://docs.madelineproto.xyz/docs/FILES.html
- https://docs.madelineproto.xyz/API_docs/methods/messages.getMessages.html
- https://docs.madelineproto.xyz/API_docs/methods/channels.getMessages.html

Ket luan:

- MadelineProto co the stream file thang ra browser bang `downloadToBrowser`, khong can tao temporary file.
- Co the dung Message, MessageMedia hoac Bot API file ID lam download source.
- `messages.getMessages` dung cho private chat/basic group.
- `channels.getMessages` dung cho channel/supergroup.
- Hai method lay message theo ID cung co canh bao rate limit, nen chi goi khi super admin bam media va chi fallback khi media descriptor/file ID da luu khong con dung duoc.

Khong luu mot Telegram URL cong khai vi private media khong co public URL on dinh. Frontend chi nhan URL noi bo cua TeleSender:

```text
/admin/inbox/media?message_id={local_message_id}
```

### 3.6 Exception va FLOOD_WAIT

Tai lieu:

- https://docs.madelineproto.xyz/docs/EXCEPTIONS.html
- https://docs.madelineproto.xyz/docs/FLOOD_WAIT.html

Dependency hien tai co:

- `danog\MadelineProto\RPCError\RateLimitError`.
- `RateLimitError::$waitTime`.
- `RateLimitError::$expires`.
- `danog\MadelineProto\RPCError\TimeoutError`.
- `Amp\TimeoutException` cho timeout phia client/runtime.

Khi gap `RateLimitError`, cron khong sleep theo toan bo wait time. Cron luu `next_attempt_at = expires`, release lock va chuyen sang job/account khac.

## 4. Quyet dinh kien truc

### 4.1 Mo hinh tong quan

```text
Super admin UI
    |
    |-- Doc cached dialogs/messages tu MySQL
    |-- Tao/upsert sync job khi mo account/chat/load older
    |-- Bam media thi goi media endpoint
    v
MySQL: dialogs + messages + durable sync jobs + account operation locks
    ^
    |
Cron /cron/inbox-sync
    |
    |-- Lay tung job nho
    |-- Kiem tra send priority
    |-- Acquire account lock
    |-- Goi toi da mot Telegram page/RPC unit
    |-- Upsert data + checkpoint trong mot transaction
    |-- Release account lock
    v
MadelineProto session
```

### 4.2 Vi sao chon demand-driven

Khong tu dong quet lich su cua tat ca dialog tren tat ca account.

Job chi duoc tao khi:

- Super admin mo mot Telegram account.
- Super admin mo mot dialog.
- Super admin bam tai lich su cu hon.
- Mot account/dialog vua duoc xem gan day can refresh cache.

Loi ich:

- Giam so call `getHistory`.
- Giam nguy co FLOOD_WAIT.
- Giam database size.
- Cron co the xu ly tung unit nho va resume.
- Account khong bao gio duoc super admin mo se khong bi backfill lich su vo ich.

### 4.3 Cron tach rieng khoi cron gui

Them endpoint:

```text
GET /cron/inbox-sync?token=CRON_TOKEN
```

Worker seeding job `dialogs_refresh` cho account active khi cache dialog đã cũ. Các thao tác làm mới trong UI vừa enqueue job bền vững vừa thử xử lý chính job đó ngay; nếu session lock đang thuộc luồng gửi tin thì job giữ trạng thái retry để cron tiếp tục.

Khong nen dua inbox sync vao cuoi `/cron/run` vi:

- Timeout cua inbox khong duoc lam response cron gui bi loi.
- Co the lap lich cron gui va cron inbox rieng.
- De theo doi ket qua va gioi han runtime rieng.
- Gui tin van dung luong cron hien tai ma khong bi thay doi.

Nguoi van hanh tu chon cron interval. Gia tri goi y, khong hard-code:

```cron
* * * * * curl -fsS "https://example.com/cron/run?token=..." >/dev/null
* * * * * sleep 20; curl -fsS "https://example.com/cron/inbox-sync?token=..." >/dev/null
```

`sleep 20` chi la vi du offset de cron gui co co hoi chay truoc; shared account lock moi la co che dam bao chinh.

## 5. Pham vi UI

### 5.1 Route trang

```text
GET /admin/inbox
```

Middleware:

```php
['auth', 'super_admin']
```

Them menu `Hoi thoai Telegram` trong nhom `Quan tri` cua sidebar.

### 5.2 Desktop layout

Giao dien hai cot, phia tren co selectors:

```text
+-------------------------------------------------------------------+
| [Admin con v] [Telegram account v] [Tim hoi thoai...] [Trang thai] |
+-------------------------+-----------------------------------------+
| Danh sach hoi thoai     | Header hoi thoai                        |
|                         +-----------------------------------------+
| Private chat A          |                20/08/2026               |
| Nhom B                  |  [sender] Tin nhan...          10:30    |
| Channel C               |                       Tin outgoing 10:31 |
|                         |  [Media: Photo] [Bam de tai]             |
+-------------------------+-----------------------------------------+
```

Yeu cau hien thi:

- Private chat, group, supergroup va channel co icon rieng.
- Ten dialog, username neu co, last message preview va last message time.
- Message bubble phan biet `is_outgoing`.
- Group/channel hien sender name.
- Hien timestamp theo timezone UI hien tai cua he thong.
- Neu `edited_at` co gia tri, hien `da sua`.
- Reply hien mot khoi nho voi sender/text preview neu message duoc reply da co trong cache.
- Neu reply target chua co trong cache, hien `Tra loi tin #ID`.
- Media hien placeholder theo `media_type`, file name va file size neu co.
- Khong co composer, context menu send/delete/edit/forward hay reaction.

### 5.3 Mobile layout

- Selector admin/account o tren cung.
- Man hinh dau hien dialog list.
- Bam dialog chuyen sang message panel.
- Nut back quay lai dialog list.
- Khong can mo rong thanh ba cot nhu Telegram Desktop.

### 5.4 Trang thai dong bo tren UI

UI phai phan biet:

- `not_synced`: chua co du lieu, da enqueue job.
- `queued`: dang cho cron.
- `syncing`: job co lease chua het han.
- `fresh`: cache con trong nguong fresh.
- `stale`: van hien cache cu va dang enqueue refresh.
- `rate_limited`: hien thoi diem thu lai.
- `error`: hien loi rut gon, khong hien raw session/path/stack trace.

Trang khong cho request HTTP chay lau de doi cron. UI poll sync status moi 5-10 giay trong luc dang queued/syncing.

## 6. Database design

Tao migration tiep theo:

```text
database/migrations/0018_add_super_admin_telegram_inbox.php
```

### 6.1 `telegram_inbox_dialogs`

```sql
CREATE TABLE telegram_inbox_dialogs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    telegram_account_id BIGINT UNSIGNED NOT NULL,
    peer_key VARCHAR(190) NOT NULL,
    peer_id BIGINT NULL,
    peer_type VARCHAR(30) NOT NULL,
    access_hash VARCHAR(40) NULL,
    title VARCHAR(255) NOT NULL,
    username VARCHAR(190) NULL,
    top_message_id BIGINT NULL,
    last_message_text TEXT NULL,
    last_message_at DATETIME NULL,
    unread_count INT UNSIGNED NOT NULL DEFAULT 0,
    oldest_message_id BIGINT NULL,
    newest_message_id BIGINT NULL,
    history_complete TINYINT(1) NOT NULL DEFAULT 0,
    last_opened_at DATETIME NULL,
    last_synced_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_inbox_dialog_account_peer (telegram_account_id, peer_key),
    KEY idx_inbox_dialog_user_account (user_id, telegram_account_id),
    KEY idx_inbox_dialog_account_last (telegram_account_id, last_message_at),
    CONSTRAINT fk_inbox_dialog_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_inbox_dialog_account
        FOREIGN KEY (telegram_account_id) REFERENCES telegram_accounts(id) ON DELETE CASCADE
);
```

Ghi chu:

- `peer_key` la canonical key do service tao, vi Telegram peer co the la user/chat/channel va khong nen chi dua vao mot signed integer ma khong giu type.
- Vi du: `user:123`, `chat:456`, `channel:789`.
- `access_hash` luu dang string de khong bi tran/khac biet signed 64-bit khi chuyen qua JSON/PHP.
- `last_message_text` chi la preview de render dialog list.
- Khong luu avatar binary trong MVP; dung initials/icon theo peer type.

### 6.2 `telegram_inbox_messages`

```sql
CREATE TABLE telegram_inbox_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    telegram_account_id BIGINT UNSIGNED NOT NULL,
    telegram_inbox_dialog_id BIGINT UNSIGNED NOT NULL,
    telegram_message_id BIGINT NOT NULL,
    sender_peer_key VARCHAR(190) NULL,
    sender_name VARCHAR(255) NULL,
    is_outgoing TINYINT(1) NOT NULL DEFAULT 0,
    message_text MEDIUMTEXT NULL,
    reply_to_message_id BIGINT NULL,
    reply_to_top_id BIGINT NULL,
    reply_quote_text TEXT NULL,
    grouped_id VARCHAR(40) NULL,
    media_type VARCHAR(40) NULL,
    media_file_id LONGTEXT NULL,
    media_file_name VARCHAR(255) NULL,
    media_mime_type VARCHAR(190) NULL,
    media_size BIGINT UNSIGNED NULL,
    media_meta_json LONGTEXT NULL,
    telegram_created_at DATETIME NOT NULL,
    edited_at DATETIME NULL,
    raw_type VARCHAR(80) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_inbox_message_dialog_tg_id
        (telegram_inbox_dialog_id, telegram_message_id),
    KEY idx_inbox_message_account_dialog
        (telegram_account_id, telegram_inbox_dialog_id, telegram_message_id),
    KEY idx_inbox_message_dialog_date
        (telegram_inbox_dialog_id, telegram_created_at),
    CONSTRAINT fk_inbox_message_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_inbox_message_account
        FOREIGN KEY (telegram_account_id) REFERENCES telegram_accounts(id) ON DELETE CASCADE,
    CONSTRAINT fk_inbox_message_dialog
        FOREIGN KEY (telegram_inbox_dialog_id) REFERENCES telegram_inbox_dialogs(id) ON DELETE CASCADE
);
```

Khong luu raw Telegram message payload day du. `media_meta_json` chi luu descriptor toi thieu can de hien placeholder/download, vi du:

```json
{
  "telegram_media_type": "messageMediaDocument",
  "document_id": "123456789",
  "dc_id": 4,
  "voice": false,
  "round": false,
  "video": true,
  "spoiler": false,
  "ttl_seconds": null
}
```

### 6.3 `telegram_inbox_sync_jobs`

```sql
CREATE TABLE telegram_inbox_sync_jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_key VARCHAR(255) NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    telegram_account_id BIGINT UNSIGNED NOT NULL,
    telegram_inbox_dialog_id BIGINT UNSIGNED NULL,
    job_type VARCHAR(40) NOT NULL,
    priority SMALLINT NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    cursor_json LONGTEXT NULL,
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    next_attempt_at DATETIME NULL,
    locked_until DATETIME NULL,
    lock_token VARCHAR(64) NULL,
    last_error_code VARCHAR(80) NULL,
    last_error_message TEXT NULL,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_inbox_sync_job_key (job_key),
    KEY idx_inbox_sync_pick
        (status, next_attempt_at, priority, locked_until),
    KEY idx_inbox_sync_account
        (telegram_account_id, status),
    CONSTRAINT fk_inbox_sync_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_inbox_sync_account
        FOREIGN KEY (telegram_account_id) REFERENCES telegram_accounts(id) ON DELETE CASCADE,
    CONSTRAINT fk_inbox_sync_dialog
        FOREIGN KEY (telegram_inbox_dialog_id) REFERENCES telegram_inbox_dialogs(id) ON DELETE CASCADE
);
```

`job_type` MVP:

- `dialogs_refresh`: lay/cap nhat danh sach dialog theo page.
- `history_refresh`: lay cac message moi va overlap mot so message gan nhat de cap nhat edited state.
- `history_backfill`: lay page cu hon khi super admin bam tai lich su.

`job_key` deterministic de upsert, vi du:

```text
dialogs:{account_id}
history-refresh:{dialog_id}
history-backfill:{dialog_id}:{before_message_id}
```

Neu job cung key dang pending/running, UI chi tang `priority` va khong tao duplicate.

### 6.4 `telegram_account_operation_locks`

```sql
CREATE TABLE telegram_account_operation_locks (
    telegram_account_id BIGINT UNSIGNED PRIMARY KEY,
    lock_type VARCHAR(30) NULL,
    lock_token VARCHAR(64) NULL,
    locked_until DATETIME NULL,
    acquired_at DATETIME NULL,
    heartbeat_at DATETIME NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_account_operation_lock
        FOREIGN KEY (telegram_account_id) REFERENCES telegram_accounts(id) ON DELETE CASCADE,
    KEY idx_account_operation_expiry (locked_until)
);
```

`lock_type`:

- `dispatch`: gui tin theo schedule/manual.
- `inbox_sync`: cron dong bo dialog/message.
- `media`: super admin bam tai media.

Mot row duoc tao bang `INSERT IGNORE` truoc khi acquire.

Release/heartbeat bat buoc kem `lock_token`. Process cu khong duoc clear lock neu lease cua no da het va process moi da acquire.

### 6.5 Quan he voi `dispatch_locked_until` cu

Khong de hai account lock source cung ton tai lau dai.

Thu tu rollout:

1. Tao `telegram_account_operation_locks`.
2. Tao `TelegramAccountLockService`.
3. Refactor `SchedulerService::lockAccount`, `refreshAccountLock`, `releaseAccountLock` sang service moi.
4. Giu `telegram_accounts.dispatch_locked_until` trong mot release de rollback/quan sat, nhung khong con la source of truth.
5. Inbox chi duoc enable sau khi scheduler da dung shared lock.

Khong drop column cu trong cung migration inbox de giam rui ro rollout.

## 7. Shared account lock va uu tien gui

### 7.1 Nguyen tac

- Dispatch co priority cao nhat.
- Inbox sync va media khong bao gio acquire khi account dang `dispatch`.
- Inbox sync khong acquire neu account co schedule dang due hoac sap due trong lookahead window.
- Moi sync unit chi thuc hien toi da mot Telegram RPC page roi release lock.
- Khong preempt mot RPC dang chay. Gui tin co the phai doi sync unit hien tai ket thuc, do do sync unit phai ngan va co flood wait limit thap.

### 7.2 Acquire dispatch

Scheduler acquire shared lock neu:

```text
locked_until IS NULL OR locked_until < UTC_TIMESTAMP()
```

Neu lock dang do inbox/media giu, scheduler bo qua account trong lan quet hien tai va thu lai o cron ke tiep. Vi sync/media unit ngan, do tre du kien nho hon mot unit.

### 7.3 Acquire inbox sync

Inbox chi acquire khi tat ca dieu kien dung:

- Shared account lock dang trong hoac het han.
- Account `session_status = active`. Khong bat buoc `is_active = 1`, vi `is_active` hien la co tam dung gui tin; mot account da tam dung gui nhung session van hop le van co the duoc super admin doc.
- Khong co `schedule_jobs` active cua account co `next_run_at <= UTC_TIMESTAMP() + lookahead`.
- Khong co dispatch log `processing` moi cua account.

Default de xuat:

```text
dispatch lookahead: 60 seconds
media dispatch lookahead: 300 seconds
sync lock lease: 120 seconds
media lock lease: 120 seconds
dispatch lock lease: giu theo config hien tai
```

Day la gia tri config, khong hard-code trong service.

### 7.4 Khi send dang chay

- Sync job giu `pending` hoac `retry`.
- `next_attempt_at` duoc dat ngan, vi du 30 giay sau.
- Khong tang `attempts` vi account busy khong phai loi Telegram.
- Cron tiep tuc chon job cua account khac.

### 7.5 Khi sync dang chay va send den han

- Sync hoan thanh RPC/page hien tai.
- Transaction checkpoint.
- Release lock ngay, khong xu ly page tiep theo trong cung lock.
- Neu job con cursor thi giu pending cho cron sau.
- Scheduler acquire o lan cron tiep theo.

Khong giu account lock trong luc render HTML, doc cache MySQL hoac cho frontend polling.

## 8. Durable cron va resume sau timeout

### 8.1 Nguyen tac

Khong the dam bao process PHP se tiep tuc sau khi web server/proxy kill request. Thiet ke dam bao cong viec se duoc khoi phuc o lan cron ke tiep.

Ba co che bat buoc:

1. Durable job trong MySQL.
2. Lease `locked_until` tu het han.
3. Cursor chi cap nhat cung transaction voi du lieu da upsert.

### 8.2 Runtime budget

Them config `config/inbox.php`:

```php
return [
    'cron_runtime_seconds' => 40,
    'cron_shutdown_reserve_seconds' => 5,
    'jobs_per_run' => 10,
    'dialogs_page_size' => 50,
    'history_page_size' => 30,
    'history_refresh_overlap' => 20,
    'flood_wait_limit_seconds' => 3,
    'sync_lock_seconds' => 60,
    'manual_sync_lock_seconds' => 60,
    'manual_dispatch_lookahead_seconds' => 0,
    'media_lock_seconds' => 120,
    'dispatch_lookahead_seconds' => 180,
    'media_dispatch_lookahead_seconds' => 300,
    'fresh_dialog_seconds' => 120,
    'fresh_history_seconds' => 60,
];
```

Day la default de xuat. Can dieu chinh theo hosting timeout va so account thuc te.

Cron ghi nhan monotonic deadline luc bat dau. Truoc khi bat dau moi RPC unit:

```text
neu remaining_time <= shutdown_reserve_seconds thi dung sach
```

Khong bat dau RPC moi khi sap cham deadline.

### 8.3 Job leasing

Pick job bang atomic update/transaction:

- `status IN ('pending', 'retry', 'running')`.
- `next_attempt_at IS NULL OR next_attempt_at <= UTC_TIMESTAMP()`.
- `locked_until IS NULL OR locked_until < UTC_TIMESTAMP()`.
- Order `priority DESC, created_at ASC`.

Vi he thong yeu cau MySQL 8, implementation nen dung `SELECT ... FOR UPDATE SKIP LOCKED` trong transaction pick job. Hai cron request chay chong nhau se bo qua row ma worker khac da lock thay vi cung xu ly mot job.

Sau khi pick:

- Tao random `lock_token`.
- Set `status = running`.
- Set `locked_until = now + job lease`.
- Set `started_at` neu null.

Moi update/release job phai co `WHERE id = ? AND lock_token = ?`.

### 8.4 Checkpoint transaction

Cho mot history page:

1. Goi Telegram ngoai database transaction.
2. Bat dau MySQL transaction.
3. Upsert users/senders can thiet vao message snapshot fields.
4. Upsert messages bang unique key.
5. Cap nhat dialog `oldest_message_id`, `newest_message_id`, `last_synced_at`.
6. Cap nhat `cursor_json` hoac mark complete.
7. Commit.
8. Release account lock.

Neu process bi kill:

- Truoc commit: cursor cu con nguyen; cron sau goi lai cung page.
- Sau commit nhung truoc complete/release: unique key ngan duplicate; lease het han va cron resume.
- Sau Telegram response nhung truoc commit: page duoc fetch lai; van idempotent.

### 8.5 Job state machine

```text
pending -> running -> completed
                |-> pending   (con cursor/page tiep theo)
                |-> retry     (timeout/rate limit/transient error)
                |-> failed    (session invalid/permanent validation error)
```

Quy tac:

- `account_busy`: pending, khong tang attempt.
- `RateLimitError`: retry tai `$exception->expires`.
- Telegram/Amp timeout: exponential backoff co gioi han.
- Session unauthorized/revoked: failed, khong tu login lai.
- Peer khong con truy cap: failed cho dialog job; dialog van hien cache cu kem status.
- Validation/ownership error: failed vinh vien.

### 8.6 Khong sleep FLOOD_WAIT trong cron

Moi Telegram call truyen `floodWaitLimit` nho. Neu Telegram yeu cau doi lau hon, MadelineProto throw `RateLimitError`.

Khong goi `$exception->wait()` trong cron. Luu `next_attempt_at` va ket thuc unit.

## 9. Dong bo dialog

### 9.1 Tao job

Khi super admin chon account:

- Validate account thuoc user role `admin`.
- Upsert `dialogs:{account_id}`.
- Neu cache chua co, UI hien empty state `Dang cho cron dong bo`.
- Neu cache cu, UI van hien cache va badge `Dang cap nhat`.

### 9.2 Cursor

`cursor_json`:

```json
{
  "offset_date": 0,
  "offset_id": 0,
  "offset_peer": null,
  "page": 1
}
```

Sau moi page, cursor duoc tao tu dialog/message cuoi theo quy tac Telegram offsets.

MVP khong can tu dong tai tat ca page dialog. Gioi han ban dau de xuat:

- Lay 50 dialog dau.
- Neu response con page va super admin scroll het danh sach, enqueue page tiep theo.

### 9.3 Upsert dialog

Tu response `messages.Dialogs`:

- Build map `users` va `chats` theo ID.
- Normalize peer thanh `peer_key`, `peer_id`, `peer_type`.
- Resolve title/username tu user/chat/channel.
- Resolve top message tu response `messages`.
- Luu `unread_count` chi de hien thong tin.
- Khong goi bat ky read API nao.

Private chat, basic group, supergroup va channel phai duoc giu. Secret chat khong nam trong MVP.

## 10. Dong bo message

### 10.1 Mo dialog

Khi super admin bam dialog:

- API tra ngay messages trong MySQL.
- Upsert `history-refresh:{dialog_id}` neu cache stale.
- Update `last_opened_at` de cron uu tien dialog dang duoc xem.

### 10.2 Initial history

Neu dialog chua co message:

```text
getHistory(peer, offset_id=0, limit=history_page_size)
```

Chi mot page moi sync unit.

### 10.3 Refresh tin moi va edited state

Vi khong dung realtime updates, refresh phai lay mot overlap nho quanh cac message moi nhat de cap nhat `edit_date`.

MVP:

- Fetch page moi nhat voi limit = `history_refresh_overlap` hoac `history_page_size`.
- Upsert message cu de cap nhat text/edit_date.
- Sau do fetch them message co ID lon hon `newest_message_id` neu can.

Gioi han can ghi ro:

- Mot message rat cu bi sua sau khi no nam ngoai overlap co the khong duoc cap nhat ngay.
- Khi super admin tai lai khu vuc lich su chua message do, upsert se cap nhat edited state.
- Phat hien delete day du khong nam trong MVP cron-based.

### 10.4 Backfill/phan trang cu

Frontend doc local database voi:

```text
GET /admin/inbox/messages?dialog_id=...&before_message_id=...&limit=30
```

Neu database con message cu hon, tra ngay.

Neu het cache nhung `history_complete = 0`:

- Upsert `history-backfill:{dialog_id}:{oldest_message_id}`.
- Response co `sync_queued = true`.
- UI hien `Dang tai lich su cu, cho cron...` va poll.

Khi Telegram tra page rong hoac khong con message cu hon:

- Set `history_complete = 1`.
- Job completed.

## 11. Message normalization

Tao mot normalizer tach rieng, khong parse raw payload trong controller.

### 11.1 Sender

- `from_id` user: lay first_name/last_name/username tu users map.
- Channel post co the dung channel title hoac `post_author`.
- Tin `out = true`: sender hien ten Telegram account dang xem.
- Neu sender khong resolve duoc: `Unknown sender` + peer key, khong bo message.

### 11.2 Text

- Luu `message` nguyen ban dang UTF-8.
- Render bang escaped text, khong render Telegram HTML thang vao DOM.
- Entities khong can format trong MVP; co the luu entity summary trong `media_meta_json` hoac bo qua.
- Caption cua photo/document nam trong field `message`, van hien nhu text cua bubble.

### 11.3 Reply

- Luu `reply_to_msg_id`, `reply_to_top_id`, `quote_text` neu co.
- Khi query messages, self join trong cung dialog de lay reply preview.
- Neu reply sang peer khac hoac target chua cache, chi hien ID/quote_text.

### 11.4 Edited

- `edited_at` lay tu `edit_date`.
- Neu co gia tri thi UI hien `da sua`.
- Upsert luon cap nhat `message_text`, `edited_at`, reply va media metadata.

### 11.5 Service messages

Telegram co `messageService`. MVP khong can render moi action chi tiet.

- Luu `raw_type = messageService`.
- Chuyen cac action pho bien thanh text ngan neu can.
- Action khong ho tro hien `Su kien Telegram`.

## 12. Media lazy loading

### 12.1 Du lieu luu

Khong luu file binary va khong auto download.

Luu toi thieu:

- `media_type`: photo, video, voice, audio, document, sticker, animation, round_video, contact, location, poll, unsupported.
- `media_file_id`: Bot API file ID neu convert duoc.
- `media_file_name`.
- `media_mime_type`.
- `media_size`.
- `media_meta_json` cho flags/IDs.

### 12.2 Media endpoint

```text
GET /admin/inbox/media?message_id={local_id}
```

Quy trinh:

1. Middleware `auth`, `super_admin`.
2. Load local message + dialog + account bang join.
3. Validate account owner role la `admin`.
4. Acquire account lock type `media` voi priority thap hon dispatch.
5. Neu dispatch dang giu lock hoac co schedule sap due trong media lookahead window, khong chen vao session.
6. Thu stream tu `media_file_id` da luu bang MadelineProto `downloadToBrowser`.
7. Neu descriptor het han/khong dung, fallback lay lai message:
   - private/basic group: `messages.getMessages`.
   - channel/supergroup: `channels.getMessages`.
8. Cap nhat media descriptor neu refresh thanh cong.
9. Stream thang ve browser, khong tao permanent file.
10. Release lock trong `finally` neu method quay ve; can dam bao wrapper download khong `exit` truoc khi release.

De dam bao release lock chac chan, implementation nen tach:

- Lay/refresh Telegram media descriptor trong lock.
- Sau do dung stream API ma code co the quan ly lifecycle.

Neu `downloadToBrowser` thoat process som trong version thuc te, can dung `downloadToReturnedStream` va bo sung `Response::stream()` de release lock truoc/hoac qua shutdown-safe owner token. Phai viet integration spike de xac minh truoc khi chot implementation media.

### 12.3 Khi account busy

Khong de HTTP request cho vo han.

- Co the poll lock trong toi da 5-10 giay.
- Neu van busy, tra `423 Locked` hoac JSON `account_busy`.
- Frontend hien `Account dang uu tien gui tin, bam thu lai sau`.

Media view khong duoc lam scheduler cham qua mot Telegram RPC/download unit.

Can ghi ro gioi han: account lock khong preempt duoc mot download dang chay. De giu uu tien gui, media acquire phai kiem tra schedule lookahead va co gioi han thoi gian/kich thuoc phu hop. Neu sau spike thay direct stream file lon co the giu session qua lau, MVP phai chuyen media lon sang job tai tam low-priority hoac tu choi tai khi gan gio gui; khong duoc bo shared lock de tranh xung dot session.

### 12.4 Cache HTTP

MVP khong luu disk cache. Response co the dung:

```text
Cache-Control: private, max-age=300
X-Content-Type-Options: nosniff
Content-Disposition: inline hoac attachment theo mime type
```

Khong dung `public` cache.

## 13. API design

Tat ca route `/admin/inbox/*` dung `['auth', 'super_admin']`.

### 13.1 Page va selectors

```text
GET /admin/inbox
GET /admin/inbox/accounts?user_id={admin_id}
```

`accounts` chi tra account cua user role `admin`, gom:

```json
{
  "id": 10,
  "name": "Account Sales",
  "phone_masked": "+84***123",
  "username": "sales_account",
  "session_status": "active",
  "is_active": true
}
```

### 13.2 Dialog list

```text
GET /admin/inbox/dialogs?account_id=10&cursor=...&q=...
POST /admin/inbox/sync-account
```

POST chi enqueue/upsert job, khong goi Telegram trong web request.

### 13.3 Messages

```text
GET /admin/inbox/messages?dialog_id=20&before_message_id=500&limit=30
POST /admin/inbox/sync-dialog
POST /admin/inbox/load-older
```

`messages` tra order phu hop UI va co:

```json
{
  "items": [],
  "has_more_cached": true,
  "history_complete": false,
  "sync_status": "fresh",
  "sync_queued": false,
  "oldest_message_id": 400,
  "newest_message_id": 550
}
```

### 13.4 Sync status

```text
GET /admin/inbox/sync-status?account_id=10&dialog_id=20
```

Frontend dung endpoint nay khi queued/syncing, khong can reload toan trang.

### 13.5 Media

```text
GET /admin/inbox/media?message_id=123
```

Khong nhan `account_id`, `peer_id`, `telegram_message_id` rieng tu client neu co the suy ra tu local `message_id`. Cach nay giam IDOR va mismatch parameters.

### 13.6 Cron

```text
GET /cron/inbox-sync?token=CRON_TOKEN
```

Response tom tat:

```json
{
  "ok": true,
  "executed_at": "2026-08-21T00:00:00+00:00",
  "deadline_reached": false,
  "processed": 3,
  "completed": 2,
  "rescheduled": 1,
  "rate_limited": 0,
  "busy_accounts": 1,
  "errors": []
}
```

Khong tra raw message content, session path hoac full exception trace trong cron response.

## 14. Service va file can tao/sua

### 14.1 File moi

```text
database/migrations/0018_add_super_admin_telegram_inbox.php
config/inbox.php

app/Controllers/SuperAdminInboxController.php

app/Models/TelegramInboxDialog.php
app/Models/TelegramInboxMessage.php
app/Models/TelegramInboxSyncJob.php

app/Services/TelegramAccountLockService.php
app/Services/TelegramInboxService.php
app/Services/TelegramInboxSyncService.php
app/Services/TelegramMessageNormalizer.php
app/Services/TelegramInboxMediaService.php

views/admin/inbox.php
public/assets/admin-inbox.js

tests/telegram_inbox_smoke.php
tests/fixtures/telegram/dialogs_page.json
tests/fixtures/telegram/private_history_page.json
tests/fixtures/telegram/channel_history_page.json
tests/fixtures/telegram/media_messages.json
```

### 14.2 File sua

```text
routes/web.php
app/Controllers/SystemController.php
app/Services/TelegramService.php
app/Services/SchedulerService.php
views/layouts/app.php
public/assets/app.css
README.md
```

### 14.3 Trach nhiem tung service

`TelegramAccountLockService`:

- Acquire/heartbeat/release bang owner token.
- Acquire dispatch/inbox/media.
- Kiem tra send lookahead cho low-priority lock.

`TelegramService` bo sung method Telegram thuan:

- `getDialogsPage(array $account, array $cursor, int $limit): array`.
- `getHistoryPage(array $account, string|int|array $peer, array $cursor, int $limit): array`.
- `getMessagesByIds(...)` cho media fallback.
- `downloadMedia(...)` hoac tra stream.
- Moi method nhan `floodWaitLimit`/`queueId` tu caller/config.

`TelegramMessageNormalizer`:

- Normalize peer/dialog/message/media.
- Khong query database va khong mo Telegram client.
- Test bang JSON fixtures.

`TelegramInboxService`:

- Query admin/account/dialog/message cho UI.
- Ownership validation.
- Enqueue/upsert job.
- Pagination tu MySQL.

`TelegramInboxSyncService`:

- Pick durable job.
- Runtime deadline.
- Shared account lock.
- Telegram RPC unit.
- Transaction upsert/checkpoint.
- Retry/backoff/rate limit.

`TelegramInboxMediaService`:

- Validate local message/account.
- Low-priority account lock.
- Resolve/refresh descriptor.
- Stream media.

## 15. Ownership va bao ve route

Khong chi dung ID tu client de load Model bang primary key.

Moi query phai join:

```text
telegram_inbox_message
 -> telegram_inbox_dialog
 -> telegram_account
 -> users
```

Va xac nhan:

- Current user role `super_admin`.
- Target owner role `admin`.
- Dialog `user_id` trung account `user_id`.
- Message account/dialog relationship hop le.
- Account session name/path khong bao gio tra ra JSON/HTML.

POST endpoints dung CSRF middleware hien tai.

Khong them bat ky endpoint inbox nao vao route cua admin con.

## 16. Error handling

### 16.1 Error classes

| Error | Xu ly job | UI |
|---|---|---|
| Account lock busy | Pending ngan, khong tang attempt | Dang uu tien gui tin |
| FLOOD_WAIT/rate limit | Retry tai Telegram expiry | Tam dung den thoi diem thu lai |
| Telegram RPC timeout | Exponential retry | Du lieu cache co the cu |
| PHP/Amp timeout | Exponential retry | Du lieu cache co the cu |
| Session revoked | Failed | Account mat ket noi |
| Peer private/left/banned | Failed dialog job | Khong con quyen truy cap |
| Message/media deleted | Media response 404/410 | Media khong con kha dung |
| Invalid local ownership | 404/403, khong enqueue | Khong hien chi tiet |
| Cron hard kill | Lease expiry + resume | Queued/syncing den lan sau |

### 16.2 Backoff de xuat

Transient timeout:

```text
attempt 1: +30 seconds
attempt 2: +2 minutes
attempt 3: +5 minutes
attempt 4+: +15 minutes, max attempts theo config
```

Rate limit dung dung `$exception->expires`, khong dung exponential backoff thay cho thoi gian Telegram tra ve.

## 17. Gioi han cua MVP

- Khong realtime.
- Do tre phu thuoc cron interval.
- Khong full-text search tren Telegram; chi search cache MySQL.
- Khong dong bo/xu ly delete day du.
- Edit cua message rat cu co the chi cap nhat khi page do duoc fetch lai.
- Khong secret chat.
- Khong story.
- Khong call history.
- Khong auto download avatar/media.
- Khong render day du Telegram entities/reactions/polls.
- Khong send/reply/edit/delete/forward.
- Khong them notification hay setting phia admin con.

Neu can realtime va delete/edit chinh xac sau MVP, huong chinh thuc duoc MadelineProto khuyen nghi la Event Handler. Huong do can mot worker architecture rieng va khong nam trong pham vi cron-only nay.

## 18. Thu tu trien khai

### Phase 1 - Shared account lock

1. Migration lock table.
2. Implement `TelegramAccountLockService`.
3. Refactor scheduler sang shared lock.
4. Test gui cron/manual, lost lease va owner token.
5. Chua enable inbox.

### Phase 2 - Schema va normalizer

1. Tao dialogs/messages/jobs tables.
2. Tao Models.
3. Implement/test peer, dialog, message, reply, edited va media normalization bang fixtures.

### Phase 3 - Cron sync

1. Them Telegram page methods.
2. Implement job queue, deadline, lease va checkpoint.
3. Implement dialogs refresh.
4. Implement history refresh/backfill.
5. Implement rate limit/timeout retry.

### Phase 4 - Super admin UI

1. Them route/page/menu.
2. Admin/account selectors.
3. Dialog list.
4. Message panel va local pagination.
5. Auto enqueue/poll status.
6. Responsive mobile.

### Phase 5 - Media

1. Spike `downloadToBrowser` lifecycle voi shared lock.
2. Chot stream implementation.
3. Placeholder + click-to-load.
4. Busy/rate limit/deleted handling.

### Phase 6 - Hardening

1. IDOR/role tests.
2. Cron hard-kill/resume tests.
3. Duplicate page/idempotency tests.
4. Dispatch priority tests.
5. FLOOD_WAIT tests bang fake Telegram adapter.
6. Mobile/desktop UI test.

## 19. Ke hoach test

### 19.1 Unit tests

- Normalize user/chat/channel peer key.
- Sender mapping private/group/channel.
- Text/caption.
- Reply header.
- Edited date.
- Photo/document/voice/video placeholder metadata.
- Service message fallback.
- Cursor encode/decode.
- Retry calculation.

### 19.2 Database/integration tests

- Upsert cung message hai lan khong duplicate.
- Page cu duoc fetch lai sau crash khong duplicate.
- Cursor chi doi khi transaction commit.
- Lease het han duoc worker sau reclaim.
- Process cu khong release lock cua process moi.
- Job deterministic key khong duplicate.
- Delete account/user cascade dung.

### 19.3 Priority tests

- Dispatch giu lock: inbox khong mo Telegram session.
- Due schedule trong lookahead: inbox khong acquire.
- Inbox hoan thanh mot RPC unit va release truoc page ke tiep.
- Media dang cho khi dispatch active.
- Scheduler thu lai sau low-priority unit.

### 19.4 Authorization tests

- Admin con truy cap `/admin/inbox` nhan 403.
- Guest nhan 401/login redirect.
- Super admin khong the dung account/dialog/message ID bi mismatch.
- Media endpoint khong stream khi ownership invalid.
- Cron token sai nhan 401.

### 19.5 Timeout/resume tests

- Kill sau Telegram response, truoc DB commit.
- Kill sau DB commit, truoc job release.
- Kill khi job dang running va lock lease chua het.
- Cron sau lease expiry resume dung cursor.
- Deadline reached khong bat dau RPC unit moi.

## 20. Tieu chi nghiem thu

Tinh nang duoc xem la dat MVP khi:

1. Chi super admin thay menu va truy cap inbox.
2. Super admin chon duoc admin con va Telegram account active.
3. Dialog list hien private/group/supergroup/channel tu cache da cron sync.
4. Message panel hien text, sender, time, reply va edited.
5. Scroll/tai lich su cu dung local pagination va enqueue backfill khi can.
6. Media khong tu tai; bam moi stream qua endpoint noi bo.
7. Code khong goi `messages.readHistory`, `channels.readHistory`, `messages.readMessageContents`.
8. Dispatch va inbox/media cung dung shared account lock.
9. Inbox khong acquire khi send active hoac schedule sap due.
10. Cron bi timeout/kill co the resume sau lease expiry ma khong duplicate message.
11. FLOOD_WAIT khong lam cron sleep lau; job duoc reschedule theo Telegram expiry.
12. Khong co route, notice hay UI inbox nao phia admin con.
13. Session path/auth data khong xuat hien trong HTML, JSON hoac error response.

## 21. Quyet dinh can giu co dinh khi implementation

- Cron-only, demand-driven; khong gia lap realtime bang getHistory polling day.
- Shared account lock la prerequisite, khong lam sau inbox.
- Dispatch priority cao hon inbox/media.
- Mot lock chi bao mot Telegram RPC/page unit cho sync.
- Durable cursor/checkpoint trong MySQL.
- Text va metadata duoc luu; media binary khong luu.
- Media chi tai theo hanh dong super admin.
- Read-only; khong goi bat ky method thay doi read state.
- Khong thiet ke notification/admin-side disclosure trong module nay.
