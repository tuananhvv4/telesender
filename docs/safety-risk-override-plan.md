# Safety Limit & Risk Override - Product and Technical Specification

## 1. Tổng quan

Tài liệu này mô tả phương án mở rộng cơ chế giới hạn an toàn của TeleSender theo hướng:

- hệ thống vẫn bảo vệ Telegram account bằng giới hạn mặc định theo giờ và theo 24 giờ;
- super admin có thể cấu hình các ngưỡng chung và quyết định admin nào được quyền chấp nhận rủi ro;
- admin được cấp quyền có thể chọn chế độ an toàn cho từng Telegram account;
- khi chấp nhận rủi ro, scheduler có thể tiếp tục gửi vượt ngưỡng nội bộ nhưng không được bỏ qua các lỗi hoặc cooldown bắt buộc từ Telegram;
- mọi thay đổi và lượt gửi vượt ngưỡng đều được ghi nhận để truy vết.

Đây là tài liệu phân tích và đặc tả để review. Tài liệu chưa phải là thay đổi code và chưa làm thay đổi hành vi production.

## 2. Bối cảnh hiện tại

Scheduler hiện áp dụng chung cho mọi Telegram account các giới hạn trong `config/safety.php`:

- tối đa `6` lượt gửi thành công trong một giờ;
- tối đa `30` lượt gửi thành công trong cửa sổ trượt 24 giờ;
- tối thiểu `8` phút giữa hai lượt chạy schedule trên cùng account;
- cooldown sau khi gửi;
- cooldown dài hơn khi Telegram trả về tín hiệu spam hoặc rate limit.

Mỗi lần gửi thành công đến một Telegram group được tính là một lượt. Vì vậy một schedule gửi đến nhiều group có thể tiêu thụ nhiều lượt trong cùng một lần chạy.

Khi account chạm giới hạn, scheduler cập nhật `next_run_at` và đưa schedule vào hàng đợi. Cách làm hiện tại an toàn nhưng chưa đáp ứng trường hợp khách hàng chủ động chấp nhận rủi ro để gửi với mật độ cao hơn.

## 3. Mục tiêu

### 3.1. Mục tiêu sản phẩm

- Giữ chế độ an toàn làm mặc định cho mọi account mới và account hiện có.
- Cho phép tăng giới hạn mặc định mà không cần sửa source code và deploy lại.
- Cho phép vượt giới hạn nội bộ khi người có quyền đã xác nhận chấp nhận rủi ro.
- Áp dụng chính sách theo từng Telegram account thay vì áp dụng đồng loạt cho toàn bộ admin.
- Hiển thị rõ account đang ở chế độ nào, đã dùng bao nhiêu lượt và có đang vượt ngưỡng hay không.
- Cho phép đưa các schedule đang bị giữ vì giới hạn trở lại hàng đợi hoạt động một cách có kiểm soát.
- Có đầy đủ audit log để biết ai đã bật, tắt hoặc thay đổi chính sách.

### 3.2. Mục tiêu kỹ thuật

- Phân loại rõ guard nội bộ có thể override và guard bắt buộc không thể override.
- Không sử dụng lại trực tiếp cơ chế `force_send` hiện tại cho lịch tự động.
- Không làm mất khả năng chống gửi trùng và chống hai worker gửi đồng thời.
- Không gửi bù hàng loạt các lần cron đã bỏ lỡ.
- Không làm gián đoạn lịch hiện có khi migration được triển khai.
- Cho phép mở rộng thêm chính sách hoặc ngưỡng theo gói dịch vụ trong tương lai.

## 4. Ngoài phạm vi

Phiên bản đầu tiên không nhằm:

- bảo đảm Telegram account sẽ không bị khóa khi đã bật chấp nhận rủi ro;
- tự động tạo hoặc luân chuyển nhiều Telegram account;
- tối ưu nội dung để tránh spam detection của Telegram;
- gửi bù toàn bộ lần chạy đã bị bỏ lỡ trong thời gian account bị queue;
- dự đoán chính xác giới hạn riêng mà Telegram áp dụng cho từng account;
- vô hiệu hóa `FLOOD_WAIT`, `PEER_FLOOD` hoặc các phản hồi bắt buộc khác từ Telegram;
- thay đổi cơ chế cron endpoint bên ngoài hiện tại.

## 5. Thuật ngữ

- **Giới hạn nội bộ**: ngưỡng do TeleSender đặt ra, ví dụ số lượt thành công theo giờ hoặc trong 24 giờ.
- **Guard mềm**: guard có thể được nới hoặc bỏ qua khi account được phép chấp nhận rủi ro.
- **Guard cứng**: guard bắt buộc luôn được áp dụng, không phụ thuộc chế độ rủi ro.
- **Risk override**: quyền cho phép scheduler vượt qua một hoặc nhiều guard mềm.
- **Cửa sổ trượt 24 giờ**: khoảng thời gian từ thời điểm hiện tại lùi lại đúng 24 giờ, không reset vào 00:00.
- **Giải phóng hàng đợi**: tính lại thời điểm chạy cho schedule đang bị giữ bởi giới hạn nội bộ.
- **Circuit breaker**: cơ chế tự động dừng hoặc hạ chế độ khi phát hiện tín hiệu nguy hiểm từ Telegram.

## 6. Vai trò và phân quyền

### 6.1. Super admin

Super admin có thể:

- cấu hình các ngưỡng mặc định toàn hệ thống;
- bật hoặc tắt khả năng admin tự chấp nhận rủi ro;
- cấp hoặc thu hồi quyền risk override cho từng admin;
- thay đổi chế độ của bất kỳ Telegram account nào;
- xem toàn bộ lịch sử thay đổi chính sách;
- xem danh sách account đang ở chế độ nới giới hạn hoặc chấp nhận rủi ro;
- ép account quay lại chế độ an toàn;
- cấu hình circuit breaker và thời gian cooldown tối thiểu.
- cấu hình safe/elevated limit và minimum gap trực tiếp trên giao diện, không cần sửa source code;
- nhận cảnh báo khi account ở chế độ risk accepted gặp lỗi Telegram hoặc mở circuit breaker.

### 6.2. Admin

Admin không được cấp quyền risk override:

- chỉ sử dụng chế độ an toàn;
- có thể xem mức sử dụng và lý do schedule bị queue;
- không nhìn thấy hoặc không thao tác được nút bật chấp nhận rủi ro.

Admin được cấp quyền risk override:

- có thể chọn chế độ cho Telegram account thuộc quyền sở hữu của mình;
- phải xác nhận cảnh báo trước khi bật chế độ rủi ro;
- có thể chọn cách xử lý schedule đang bị queue;
- có thể tắt risk override bất kỳ lúc nào;
- không thể vô hiệu hóa guard cứng;
- không thể thay đổi ngưỡng hệ thống nếu super admin không cho phép.
- quyền tự bật/tắt có hiệu lực liên tục, không cần super admin duyệt lại cho từng lần thay đổi;
- nhận cảnh báo khi account của mình gặp lỗi Telegram hoặc mở circuit breaker.

### 6.3. Ma trận quyền

| Hành động | Super admin | Admin có quyền | Admin không có quyền |
|---|---:|---:|---:|
| Xem trạng thái an toàn của account | Có | Có, account của mình | Có, account của mình |
| Cấu hình ngưỡng toàn hệ thống | Có | Không | Không |
| Cấp quyền risk override | Có | Không | Không |
| Bật chế độ nới giới hạn | Có | Có | Không |
| Bật chấp nhận rủi ro | Có | Có | Không |
| Tắt risk override | Có | Có | Không |
| Xem audit toàn hệ thống | Có | Không | Không |
| Xem audit account của mình | Có | Có | Có, chỉ đọc |
| Bỏ qua Telegram cooldown | Không | Không | Không |

## 7. Các chế độ an toàn

### 7.1. Chế độ `safe`

Đây là chế độ mặc định.

Hành vi:

- áp dụng đầy đủ giới hạn theo giờ;
- áp dụng đầy đủ giới hạn trong 24 giờ;
- áp dụng khoảng cách tối thiểu giữa hai schedule;
- áp dụng cooldown sau gửi;
- dừng khi Telegram báo rate limit hoặc spam;
- queue schedule khi account hết công suất.

Giá trị đề xuất ban đầu:

- `8` lượt thành công/giờ;
- `40` lượt thành công/24 giờ;
- tối thiểu `8` phút giữa hai schedule.

Safe daily limit mặc định được chốt là `40` lượt thành công trong cửa sổ trượt 24 giờ. Super admin có thể thay đổi giá trị này trên trang cấu hình hệ thống mà không cần sửa source code hoặc deploy lại.

Giá trị `40` là mặc định vận hành của TeleSender, không phải cam kết an toàn từ Telegram.

### 7.2. Chế độ `elevated`

Đây là chế độ nới giới hạn nhưng vẫn có trần rõ ràng.

Hành vi:

- sử dụng bộ ngưỡng elevated do super admin cấu hình;
- vẫn queue khi chạm trần elevated;
- vẫn áp dụng mọi Telegram cooldown;
- hiển thị cảnh báo account đang hoạt động trên mức khuyến nghị.

Giá trị đề xuất ban đầu:

- `10` lượt thành công/giờ;
- `80` lượt thành công/24 giờ;
- tối thiểu `5` phút giữa hai schedule.

Chế độ elevated nằm trong phạm vi triển khai chính thức, không phải tính năng để dành cho giai đoạn sau. Các ngưỡng elevated do super admin cấu hình.

### 7.3. Chế độ `risk_accepted`

Đây là chế độ người dùng chủ động chấp nhận rủi ro.

Hành vi:

- không chặn chỉ vì vượt giới hạn thành công theo giờ;
- không chặn chỉ vì vượt giới hạn thành công trong 24 giờ;
- tiếp tục đếm và hiển thị mức sử dụng;
- vẫn giữ khoảng cách tối thiểu giữa các schedule;
- vẫn tuần tự hóa việc gửi trên cùng account;
- vẫn áp dụng Telegram cooldown và circuit breaker;
- đánh dấu các lượt gửi vượt ngưỡng trong log;
- hiển thị badge cảnh báo rõ ràng trên account và schedule.

Khoảng cách tối thiểu đề xuất:

- mặc định `1` phút;
- super admin có thể cấu hình giá trị cao hơn;
- giá trị nhỏ nhất hệ thống chấp nhận là `1` phút;
- không cho phép đặt bằng `0`.

Risk override có hiệu lực vô thời hạn và chỉ kết thúc khi admin, super admin hoặc một thao tác thu hồi quyền chủ động tắt chế độ. Circuit breaker không tự thay đổi safety mode.

## 8. Phân loại guard

### 8.1. Guard mềm có thể override

| Guard | `safe` | `elevated` | `risk_accepted` |
|---|---|---|---|
| Giới hạn thành công/giờ | Chặn theo safe limit | Chặn theo elevated limit | Không chặn, vẫn ghi nhận |
| Giới hạn thành công/24 giờ | Chặn theo safe limit | Chặn theo elevated limit | Không chặn, vẫn ghi nhận |
| Khoảng cách giữa hai schedule | Safe gap | Elevated gap | Risk gap |

### 8.2. Guard cứng không thể override

- Telegram account bị tạm dừng.
- Telegram session không ở trạng thái active.
- User bị khóa hoặc hết quyền sử dụng.
- Message template bị tắt.
- Không còn Telegram group active.
- Account hoặc schedule đang có dispatch lock hợp lệ.
- Telegram trả `FLOOD_WAIT`.
- Telegram trả `PEER_FLOOD`.
- Telegram trả `TOO_MANY_REQUESTS`.
- Phát hiện tín hiệu spam/rate limit tương đương.
- Idempotency phát hiện group đã được xử lý trong cùng schedule run.
- Circuit breaker đang mở.

## 9. Cấu hình hệ thống

### 9.1. Settings đề xuất

Các giá trị nên được lưu trong `system_settings` và có default trong code:

```text
safety_safe_hourly_limit
safety_safe_daily_limit
safety_safe_min_gap_minutes

safety_elevated_hourly_limit
safety_elevated_daily_limit
safety_elevated_min_gap_minutes

safety_risk_min_gap_minutes
safety_admin_self_override_enabled
safety_risk_acknowledgement_required

safety_circuit_breaker_error_count
safety_circuit_breaker_window_minutes
safety_circuit_breaker_cooldown_minutes
```

### 9.2. Validation cấu hình

- Giới hạn theo giờ phải lớn hơn `0`.
- Giới hạn theo ngày phải lớn hơn hoặc bằng giới hạn theo giờ.
- Elevated limit phải lớn hơn hoặc bằng safe limit.
- Khoảng cách safe phải lớn hơn hoặc bằng khoảng cách elevated.
- Khoảng cách elevated phải lớn hơn hoặc bằng khoảng cách risk.
- Risk gap tối thiểu phải lớn hơn `0`.
- Risk gap phải là số nguyên và không được nhỏ hơn `1` phút.
- Circuit breaker count, window và cooldown phải lớn hơn `0`.
- Không lưu một phần cấu hình nếu có bất kỳ giá trị nào không hợp lệ.

### 9.3. Cache settings

Helper `system_settings_map()` hiện cache kết quả trong một request. Scheduler cần đọc cấu hình đã resolve trong mỗi cron request, không cache vĩnh viễn giữa các process.

## 10. Thay đổi dữ liệu

### 10.1. Bảng `users`

Thêm cột:

```text
can_override_safety_limits TINYINT(1) NOT NULL DEFAULT 0
```

Ý nghĩa:

- super admin không phụ thuộc cột này;
- admin chỉ được bật `elevated` hoặc `risk_accepted` khi giá trị bằng `1`;
- khi bị thu hồi quyền, tất cả account `elevated` hoặc `risk_accepted` của admin phải tự động quay về `safe` trong cùng thao tác thu hồi quyền;
- thao tác thu hồi quyền và chuyển mode phải chạy trong transaction và được ghi audit.

### 10.2. Bảng `telegram_accounts`

Thêm các cột dự kiến:

```text
safety_mode VARCHAR(30) NOT NULL DEFAULT 'safe'
safety_mode_changed_at DATETIME NULL
safety_mode_changed_by BIGINT UNSIGNED NULL
risk_acknowledged_at DATETIME NULL
risk_acknowledged_by BIGINT UNSIGNED NULL
circuit_breaker_until DATETIME NULL
circuit_breaker_reason VARCHAR(255) NULL
```

Giá trị hợp lệ của `safety_mode`:

- `safe`
- `elevated`
- `risk_accepted`

Không nên chỉ dùng boolean `allow_risky_dispatch` vì sẽ khó mở rộng chế độ elevated hoặc các chính sách khác sau này.

Không cần cột thời hạn cho risk override trong phiên bản này vì mode có hiệu lực cho đến khi được tắt chủ động.

### 10.3. Bảng audit mới

Tạo bảng `account_safety_policy_events`:

```text
id
user_id
telegram_account_id
actor_user_id
event_type
previous_mode
new_mode
reason
metadata_json
created_at
```

Các `event_type` dự kiến:

- `mode_changed`
- `risk_acknowledged`
- `permission_revoked`
- `queue_released`
- `circuit_breaker_opened`
- `circuit_breaker_closed`
- `forced_safe_mode`

### 10.4. Dispatch log

Bổ sung metadata để truy vết lượt gửi vượt ngưỡng. Có hai phương án:

1. Thêm các cột trực tiếp vào `dispatch_logs`.
2. Thêm một cột JSON chứa snapshot chính sách.

Phương án đề xuất là các cột rõ ràng kết hợp JSON tùy chọn:

```text
safety_mode_snapshot VARCHAR(30) NULL
safety_override_used TINYINT(1) NOT NULL DEFAULT 0
safety_usage_snapshot_json LONGTEXT NULL
```

Ví dụ snapshot:

```json
{
  "hourly_count_before": 11,
  "hourly_limit": 8,
  "daily_count_before": 57,
  "daily_limit": 40,
  "override_reasons": ["hourly_limit", "daily_limit"]
}
```

### 10.5. Bảng thông báo mới

Hệ thống hiện chưa có notification center lưu trạng thái đã đọc/chưa đọc. Để đáp ứng yêu cầu cảnh báo cho cả admin và super admin, tạo bảng `user_notifications`:

```text
id
user_id
type
title
message
severity
telegram_account_id
dispatch_log_id
metadata_json
read_at
created_at
```

Yêu cầu:

- tạo một notification riêng cho từng người nhận;
- admin sở hữu account nhận một bản ghi;
- mỗi super admin active nhận một bản ghi;
- có unique/deduplication key trong metadata hoặc cột riêng để một Telegram error không tạo lặp thông báo qua nhiều cron request;
- `severity` hỗ trợ ít nhất `info`, `warning`, `critical`;
- `PEER_FLOOD` và circuit breaker dùng mức `critical`;
- `FLOOD_WAIT` và `TOO_MANY_REQUESTS` dùng ít nhất mức `warning`;
- hỗ trợ đánh dấu một thông báo hoặc tất cả thông báo là đã đọc;
- retention mặc định là 30 ngày.

## 11. Thay đổi backend

### 11.1. Tách chính sách khỏi scheduler

Nên tạo service riêng, ví dụ:

```text
app/Services/AccountSafetyPolicyService.php
```

Trách nhiệm:

- resolve chế độ của account;
- resolve ngưỡng tương ứng từ system settings;
- kiểm tra quyền thay đổi chế độ;
- đánh giá guard nào đang active;
- quyết định guard nào có thể bypass;
- tạo usage snapshot;
- ghi audit event;
- xử lý việc thu hồi quyền hoặc ép về safe mode.

`SchedulerService` chỉ nên hỏi policy service để nhận quyết định, thay vì tự đọc trực tiếp tất cả giá trị cấu hình.

### 11.2. Kết quả đánh giá guard có cấu trúc

Hiện tại scheduler chủ yếu sử dụng chuỗi `reason`. Cần chuyển sang cấu trúc có mã để tránh quyết định logic dựa trên nội dung tiếng Việt:

```php
[
    'code' => 'daily_success_limit',
    'category' => 'soft_volume',
    'retry_at' => $retryAt,
    'reason' => 'Account đã chạm giới hạn an toàn trong 24 giờ.',
    'bypass_allowed' => true,
    'override_used' => false,
]
```

Các code dự kiến:

- `minimum_gap`
- `hourly_success_limit`
- `daily_success_limit`
- `post_send_cooldown`
- `telegram_flood_wait`
- `telegram_peer_flood`
- `telegram_rate_limit`
- `circuit_breaker`
- `dispatch_lock`

### 11.3. Thay đổi `determineGuard`

Luồng mới:

1. Thu thập toàn bộ guard đang active.
2. Resolve safety policy của account.
3. Loại các guard mềm được policy cho phép bypass.
4. Ghi nhận guard nào đã được bypass vào usage snapshot.
5. Nếu còn guard cứng, trả về guard có `retry_at` xa nhất.
6. Nếu không còn guard chặn, cho phép dispatch.

### 11.4. Thay đổi `determineVolumeGuard`

Volume guard giữa các group trong cùng một schedule cũng phải dùng cùng policy. Nếu không, schedule có thể vượt guard ở đầu lượt nhưng lại bị chặn giữa chừng.

Yêu cầu:

- `safe`: dừng khi chạm safe limit;
- `elevated`: dừng khi chạm elevated limit;
- `risk_accepted`: không dừng chỉ vì hourly/daily limit;
- mọi mode vẫn dừng khi circuit breaker hoặc Telegram cooldown xuất hiện.

### 11.5. Không sử dụng `$force` cho automatic override

`force_send` hiện có mục đích gửi thủ công sau xác nhận. Risk override cho scheduler phải dựa trên policy code, không truyền `$force=true`.

Sau refactor, gửi thủ công cũng nên phân loại:

- có thể xác nhận vượt guard mềm;
- không thể vượt guard cứng;
- thông báo rõ guard nào được bỏ qua;
- ghi log actor đã xác nhận.

### 11.6. Đếm lượt gửi

Tiếp tục sử dụng dispatch log thành công làm nguồn dữ liệu. Cần bảo đảm:

- chỉ `status = success` được tính;
- mỗi group thành công là một lượt;
- lượt gửi thủ công cũng được tính;
- lượt gửi nhờ override vẫn được tính;
- truy vấn dùng UTC nhất quán;
- có index phù hợp trên `(telegram_account_id, status, sent_at)` để tránh chậm khi log lớn.

## 12. Xử lý hàng đợi khi thay đổi chế độ

### 12.1. Vấn đề

Khi account đã chạm daily limit, nhiều schedule có thể đã bị dời `next_run_at` sang ngày hôm sau. Nếu chỉ đổi `safety_mode`, các schedule này vẫn chờ đến thời điểm cũ và người dùng sẽ nghĩ chức năng không hoạt động.

### 12.2. Hành vi khi bật chế độ mới

Khi chuyển sang `elevated` hoặc `risk_accepted`, MVP hiện tự động tính lại lịch từ thời điểm hiện tại. UI không yêu cầu admin chọn cách xử lý hàng đợi.

#### Lựa chọn A: Tính lại từ thời điểm hiện tại

- tìm schedule bị queue bởi guard mềm;
- tính lần cron tiếp theo kể từ hiện tại;
- không replay các lần đã bỏ lỡ;
- xếp schedule theo account với khoảng cách của mode mới;
- đây là lựa chọn được chọn sẵn mặc định trên UI.

#### Phương án reset và giải phóng ngay

- schedule đầu tiên có thể chạy ở cron request tiếp theo;
- các schedule còn lại được xếp theo minimum gap;
- không gửi đồng thời;
- xóa queue reason của các guard mềm đã được override;
- không xóa Telegram cooldown hoặc circuit breaker;
- cần cảnh báo rủi ro cao hơn trước khi xác nhận;
- backend vẫn có thể hỗ trợ để mở rộng sau, nhưng tạm thời không hiển thị trên UI.

Hành vi MVP: một lượt chạy được phép chờ trong hàng đợi tối đa `60` phút. Nếu cùng schedule bị dời quá ngưỡng này, hệ thống bỏ lượt đó, không gửi bù và chuyển sang mốc cron hợp lệ tiếp theo. Các schedule khác vẫn chạy tuần tự nếu lượt riêng của chúng chưa quá hạn.

### 12.3. Nhận diện schedule được phép giải phóng

Không nên dựa vào `last_error LIKE 'Queue:%'` lâu dài. Cần có mã trạng thái có cấu trúc, ví dụ thêm:

```text
queue_reason_code VARCHAR(60) NULL
```

Chỉ giải phóng các schedule có code:

- `hourly_success_limit`
- `daily_success_limit`
- `minimum_gap`

Không giải phóng schedule bị giữ bởi:

- Telegram cooldown;
- circuit breaker;
- account inactive;
- session inactive;
- user inactive hoặc hết hạn.

### 12.4. Khi tắt risk override

- chuyển account về `safe` ngay;
- không hủy lượt gửi đang thực thi;
- từ lần đánh giá tiếp theo, scheduler áp dụng safe limit;
- nếu số lượt trong 24 giờ đã vượt safe limit, các schedule tiếp theo được queue đến khi usage giảm dưới ngưỡng;
- ghi audit event `mode_changed`;
- giao diện hiển thị thời điểm dự kiến được gửi trở lại.

Khi super admin thu hồi quyền risk override của admin, hệ thống thực hiện cùng hành vi này cho toàn bộ account không ở safe mode của admin đó.

## 13. Circuit breaker

### 13.1. Mục đích

Risk acceptance chỉ cho phép vượt giới hạn nội bộ. Khi Telegram bắt đầu phản hồi tiêu cực, hệ thống phải ưu tiên bảo vệ account.

### 13.2. Trigger đề xuất

Mở circuit breaker khi xảy ra một trong các trường hợp:

- nhận `PEER_FLOOD`;
- nhận `FLOOD_WAIT` hoặc `TOO_MANY_REQUESTS`;
- nhận bất kỳ tín hiệu spam/rate-limit tương đương nào;
- có lỗi được TelegramService phân loại là account-level restriction.

### 13.3. Hành vi

- đặt `circuit_breaker_until`;
- không gửi schedule tự động hoặc thủ công trong thời gian breaker mở;
- không cho phép nút force bỏ qua;
- giữ nguyên safety mode để biết cấu hình người dùng, nhưng hiển thị trạng thái `Đang bị Telegram cooldown`;
- không tự chuyển `risk_accepted` hoặc `elevated` về `safe` khi gặp `PEER_FLOOD`;
- ghi audit và dispatch log;
- hiển thị thời gian thử lại.
- tạo cảnh báo cho admin sở hữu account và toàn bộ super admin đang active.

### 13.4. Khôi phục

- breaker tự đóng khi hết thời gian;
- super admin có thể đóng sớm sau khi kiểm tra, nhưng cần cảnh báo và audit;
- sau khi đóng, scheduler tiếp tục với safety mode đang được lưu trên account;
- account chỉ về safe khi admin/super admin chủ động tắt mode hoặc quyền override bị thu hồi.

## 14. Giao diện super admin

### 14.1. Cấu hình hệ thống

Thêm section `An toàn gửi Telegram` tại trang cấu hình:

- Safe hourly limit.
- Safe daily limit.
- Safe minimum gap.
- Elevated hourly limit.
- Elevated daily limit.
- Elevated minimum gap.
- Risk minimum gap.
- Cho phép admin được cấp quyền tự bật risk override.
- Số lỗi mở circuit breaker.
- Cửa sổ đếm lỗi.
- Thời gian cooldown mặc định.

Giao diện cần:

- mô tả rõ daily limit là cửa sổ trượt 24 giờ;
- cảnh báo khi cấu hình elevated quá cao;
- preview công suất lý thuyết theo ngày;
- validate trước khi submit.

### 14.2. Quản lý admin

Trong form giới hạn của admin, thêm:

- checkbox `Cho phép admin chấp nhận rủi ro gửi`;
- số Telegram account đang dùng elevated/risk mode;
- shortcut xem các account đó;
- cảnh báo khi thu hồi quyền sẽ đưa account về safe mode.

### 14.3. Trang giám sát rủi ro

Khuyến nghị có danh sách:

- account đang ở `elevated`;
- account đang ở `risk_accepted`;
- số lượt gửi 1 giờ/24 giờ;
- số lỗi Telegram gần đây;
- circuit breaker status;
- actor bật chế độ và thời điểm bật;
- nút ép về safe mode.

Trang riêng có thể triển khai sau màn hình cấu hình và danh sách account, nhưng cảnh báo cho super admin khi có lỗi Telegram thuộc phạm vi bắt buộc.

## 15. Giao diện admin

### 15.1. Trang Telegram account

Mỗi account hiển thị:

- safety mode hiện tại;
- usage theo giờ và 24 giờ;
- ngưỡng tương ứng;
- lần gửi thành công gần nhất;
- cooldown/circuit breaker nếu có;
- cảnh báo nếu đang vượt safe limit;
- nút thay đổi chế độ nếu có quyền.

Ví dụ:

```text
Chế độ: Chấp nhận rủi ro
Trong 1 giờ: 11 / 8
Trong 24 giờ: 73 / 40
Trạng thái: Đang gửi vượt ngưỡng với xác nhận rủi ro
```

### 15.2. Modal xác nhận risk override

Nội dung tối thiểu:

- account sẽ gửi vượt giới hạn khuyến nghị;
- Telegram có thể rate limit hoặc khóa account;
- TeleSender không thể bảo đảm an toàn;
- Telegram cooldown vẫn được áp dụng;
- lịch cũ bị dời được bỏ qua và lần chạy tiếp theo được tính từ hiện tại;
- checkbox xác nhận đã hiểu;
- tùy chọn nhập tên account để xác nhận cho thao tác rủi ro cao.

UI hiển thị rõ rằng hệ thống không gửi bù lịch cũ và các lịch tương lai tiếp tục chạy bình thường.

### 15.3. Danh sách schedule

Hiển thị bổ sung:

- badge safety mode của account;
- usage `x/y` theo giờ và 24 giờ;
- trạng thái `Được gửi nhờ risk override` khi phù hợp;
- queue reason cụ thể;
- thời gian dự kiến chạy lại;
- không dùng badge `An toàn` khi account đang risk accepted và đã vượt ngưỡng.

### 15.4. Tạo và sửa schedule

Risk analysis cần phân biệt:

- mật độ của riêng schedule;
- tổng mật độ của toàn account;
- công suất safe/elevated;
- ước tính số lượt có thể bị queue;
- risk mode không biến một lịch dày thành “an toàn”; chỉ cho biết hệ thống sẽ cho phép chạy.

Thông điệp ví dụ:

```text
Tổng nhu cầu dự kiến: 173 lượt/24 giờ.
Giới hạn an toàn hiện tại: 40 lượt/24 giờ.
Account đang chấp nhận rủi ro nên scheduler không chặn theo daily limit.
```

## 16. API và controller dự kiến

### 16.1. Super admin

```text
POST /admin/settings/safety
POST /admin/users/safety-permission
POST /admin/accounts/safety-mode
GET  /admin/safety-events
```

### 16.2. Admin

```text
POST /accounts/safety-mode
GET  /accounts/safety-status
GET  /accounts/safety-events
GET  /notifications
POST /notifications/read
POST /notifications/read-all
```

Payload đổi mode dự kiến:

```json
{
  "telegram_account_id": 11,
  "safety_mode": "risk_accepted",
  "acknowledged": true,
  "queue_action": "recalculate_from_now",
  "reason": "Account chuyên dùng cho chiến dịch mật độ cao"
}
```

### 16.3. Validation và authorization

- account phải thuộc user hiện tại, trừ super admin;
- mode phải nằm trong allowlist;
- admin phải có quyền risk override;
- global setting phải cho phép self override nếu actor không phải super admin;
- `risk_accepted` bắt buộc acknowledgement;
- queue action phải nằm trong allowlist;
- mọi thay đổi mode và queue phải nằm trong transaction phù hợp;
- request phải có CSRF protection như các form hiện tại.
- user chỉ được đọc hoặc đánh dấu notification thuộc chính mình;
- link trong notification phải được kiểm tra quyền lại khi mở, không tin tưởng `telegram_account_id` từ metadata.

## 17. Audit và quan sát hệ thống

### 17.1. Audit bắt buộc

Ghi audit cho:

- cấp hoặc thu hồi quyền admin;
- thay đổi safety mode;
- xác nhận rủi ro;
- giải phóng hàng đợi;
- circuit breaker mở hoặc đóng;
- super admin ép account về safe;
- thay đổi ngưỡng hệ thống.

### 17.2. Chỉ số nên hiển thị

- success count trong 1 giờ;
- success count trong 24 giờ;
- số lượt vượt safe limit;
- số lượt sử dụng override;
- số Telegram rate-limit error;
- số account theo từng mode;
- số account đang circuit breaker;
- độ dài hàng đợi theo account.

### 17.3. Thông báo

Thông báo lỗi Telegram cho account elevated/risk accepted là yêu cầu bắt buộc.

Người nhận:

- admin sở hữu Telegram account;
- tất cả super admin đang active.

Sự kiện tạo thông báo:

- `PEER_FLOOD`;
- `FLOOD_WAIT`;
- `TOO_MANY_REQUESTS`;
- circuit breaker được mở;
- account-level restriction khác do TelegramService phân loại.

Nội dung tối thiểu:

- tên account;
- safety mode hiện tại;
- loại lỗi;
- thời điểm xảy ra;
- thời gian thử lại nếu có;
- trạng thái circuit breaker;
- đường dẫn đến account hoặc dispatch log liên quan.

MVP cần có thông báo trong ứng dụng và trạng thái đã đọc/chưa đọc. Email hoặc kênh ngoài ứng dụng có thể bổ sung sau.

Có thể bổ sung cảnh báo usage 80% hoặc 100% sau khi luồng cảnh báo lỗi Telegram đã ổn định.

### 17.4. Thời gian lưu audit

- audit log của safety policy được lưu `30 ngày`;
- job dọn dữ liệu chạy mỗi ngày và xóa audit event cũ hơn 30 ngày;
- việc dọn audit không xóa `dispatch_logs`;
- thông báo có thể dùng cùng retention 30 ngày trong MVP;
- nếu cần điều tra dài hạn, super admin phải export trước khi hết retention.

## 18. Tương thích và migration

### 18.1. Account hiện có

- tất cả account hiện có được đặt `safety_mode = safe`;
- không account nào tự động được risk accepted;
- không thay đổi `next_run_at` trong lúc chạy migration;
- quyền risk override của admin mặc định là tắt.

### 18.2. Cấu hình hiện có

- nếu chưa có system setting mới, dùng default từ code;
- có thể giữ fallback về `config/safety.php` trong giai đoạn chuyển đổi;
- sau khi ổn định, system settings trở thành nguồn cấu hình chính.

### 18.3. Rollback

- khi rollback ứng dụng, cột mới không ảnh hưởng query cũ;
- account risk accepted phải được coi là safe bởi code cũ;
- không xóa audit log;
- không tự động giảm `next_run_at` trong rollback.

## 19. Rủi ro kỹ thuật

### 19.1. Xả hàng đợi đột ngột

Nếu nhiều schedule có cùng `next_run_at`, bật override có thể làm nhiều job cùng due. Cần queue normalization theo toàn bộ account, không chỉ nhóm job lấy được trong một batch `LIMIT 50`.

### 19.2. Va chạm với schedule tương lai

Khi dời schedule theo minimum gap, slot mới có thể trùng với schedule chưa đến hạn và chưa có trong query due jobs. Cần xây dựng queue dựa trên các schedule active sắp tới của account hoặc có cơ chế đặt slot tập trung.

### 19.3. Mất quyền giữa lúc dispatch

Nếu super admin thu hồi quyền khi một schedule đang chạy:

- không hủy request Telegram đang thực thi;
- lượt hiện tại hoàn tất theo lock;
- tất cả account của admin được chuyển về safe trong transaction thu hồi quyền;
- lần đánh giá scheduler tiếp theo dùng safe mode.

### 19.4. Log tăng nhanh

Risk accepted có thể tạo số lượng dispatch log lớn. Cần index và chính sách lưu trữ phù hợp.

### 19.5. Hiểu nhầm “chấp nhận rủi ro”

Người dùng có thể hiểu rằng hệ thống bảo đảm gửi hết schedule. UI phải nói rõ:

- override chỉ bỏ giới hạn nội bộ;
- Telegram vẫn có thể chặn;
- queue serialization và minimum gap vẫn tồn tại;
- các lần cron đã bỏ lỡ không được gửi bù.

## 20. Kế hoạch triển khai đề xuất

### Giai đoạn 1: Nền tảng policy

- migration users, telegram_accounts và audit table;
- system settings cho các ngưỡng;
- `AccountSafetyPolicyService`;
- guard code có cấu trúc;
- index phục vụ success window;
- bảng notification và service tạo cảnh báo có deduplication;
- chưa mở UI risk accepted cho admin.

### Giai đoạn 2: Super admin control

- UI cấu hình ngưỡng;
- UI cấp quyền admin;
- super admin đổi mode account;
- audit log;
- notification center cơ bản và badge số thông báo chưa đọc;
- monitoring cơ bản.

### Giai đoạn 3: Admin self-service

- admin đổi mode account;
- modal acknowledgement;
- lựa chọn xử lý queue;
- badge và usage counters;
- cập nhật schedule risk analysis.

### Giai đoạn 4: Circuit breaker và hardening

- phân loại Telegram error;
- circuit breaker;
- tự fallback về safe nếu cần;
- cảnh báo và dashboard giám sát;
- load test và tối ưu query.

## 21. Kịch bản kiểm thử

### 21.1. Policy và quyền

1. Account mới mặc định ở `safe`.
2. Admin không có quyền không thể bật elevated/risk bằng UI hoặc request trực tiếp.
3. Admin có quyền chỉ thay đổi được account của mình.
4. Super admin thay đổi được mọi account.
5. Thu hồi quyền tự động đưa toàn bộ account elevated/risk accepted về safe.
6. Mọi thay đổi đều có audit event đúng actor.
7. Admin được cấp quyền có thể tự bật/tắt mode mà không cần duyệt lại từng lần.
8. Risk override không tự hết hạn theo thời gian.

### 21.2. Safe mode

1. Dưới hourly/daily limit thì dispatch bình thường.
2. Chạm hourly limit thì schedule được queue đúng retry time.
3. Chạm daily limit thì retry bằng oldest success + 24 giờ.
4. Không đếm log guarded/error là success.
5. Multi-group schedule đếm đúng từng group thành công.

### 21.3. Elevated mode

1. Vượt safe limit nhưng dưới elevated limit vẫn gửi.
2. Chạm elevated limit thì queue.
3. UI hiển thị đang vượt safe threshold.
4. Dispatch log lưu policy snapshot.

### 21.4. Risk accepted mode

1. Vượt hourly limit vẫn gửi nếu không có guard cứng.
2. Vượt daily limit vẫn gửi nếu không có guard cứng.
3. Minimum gap risk vẫn được áp dụng.
4. Override usage được ghi log.
5. Telegram cooldown vẫn chặn.
6. Circuit breaker vẫn chặn.
7. Tắt override khiến lần đánh giá tiếp theo áp dụng safe mode.

### 21.5. Queue release

1. Recalculate from now không replay lần cũ.
2. UI không cung cấp thao tác reset/release ngay trong MVP hiện tại.
3. Đổi mode mặc định sử dụng `recalculate_from_now`.
4. Không giải phóng job bị Telegram cooldown.
5. Không giải phóng job có session/account inactive.
6. Các slot mới không bị trùng hoặc xếp sai thứ tự.

### 21.6. Concurrent dispatch

1. Hai cron request đồng thời chỉ một request giữ account lock.
2. Lock được refresh khi gửi nhiều group.
3. Process chết giữa chừng không gửi trùng group đã có run key.
4. Thay đổi policy trong lúc dispatch không phá transaction hiện tại.

### 21.7. Circuit breaker

1. `PEER_FLOOD` mở breaker ngay.
2. `FLOOD_WAIT` dùng đúng retry time.
3. Tín hiệu spam/rate-limit chung mở breaker ngay.
4. Force send không vượt breaker.
5. Breaker hết hạn cho phép đánh giá lại.
6. Audit ghi đúng lý do và thời gian.
7. `PEER_FLOOD` không tự đổi safety mode về safe.
8. Admin sở hữu account và super admin đều nhận được thông báo.

### 21.8. Retention

1. Audit event chưa đủ 30 ngày vẫn được giữ.
2. Audit event cũ hơn 30 ngày được cleanup job xóa.
3. Cleanup audit không xóa dispatch log.
4. Thông báo cũ được xử lý đúng retention đã cấu hình cho MVP.

### 21.9. Notification

1. Một lỗi Telegram tạo đúng một notification cho admin sở hữu account.
2. Mỗi super admin active nhận đúng một notification tương ứng.
3. Cron request lặp lại không tạo notification trùng cho cùng sự kiện.
4. User không đọc hoặc đánh dấu được notification của user khác.
5. Đánh dấu đã đọc cập nhật badge chưa đọc chính xác.
6. Link từ notification kiểm tra lại quyền truy cập account/log.

## 22. Tiêu chí nghiệm thu

Tính năng được xem là hoàn thiện khi:

- account hiện có tiếp tục chạy ở safe mode sau deploy;
- super admin cấu hình được safe/elevated/risk thresholds;
- super admin cấp và thu hồi được quyền risk override;
- admin được cấp quyền thay đổi được mode của account mình;
- scheduler vượt hourly/daily limit đúng policy;
- Telegram cooldown và circuit breaker không thể bị override;
- queue hiện có được xử lý đúng lựa chọn của người dùng;
- không replay hàng loạt lần cron đã bỏ lỡ;
- UI hiển thị usage, mode và trạng thái vượt ngưỡng chính xác;
- dispatch log cho biết lượt nào đã dùng override;
- audit log xác định được ai thay đổi gì và khi nào;
- audit log được lưu và tự dọn theo retention 30 ngày;
- lỗi Telegram trên account elevated/risk accepted gửi thông báo cho cả admin và super admin;
- notification center hiển thị đúng trạng thái đã đọc/chưa đọc và không tạo cảnh báo trùng;
- test concurrent dispatch không tạo tin nhắn trùng;
- các truy vấn success window vẫn đáp ứng tốt với dữ liệu production.

## 23. Các quyết định đã chốt

1. Safe daily limit mặc định là `40` và super admin có thể cấu hình lại.
2. Hệ thống có đủ ba mode: `safe`, `elevated`, `risk_accepted`.
3. Admin đã được cấp quyền có thể tự bật/tắt elevated hoặc risk accepted.
4. Risk override có hiệu lực đến khi được tắt, không tự hết hạn.
5. Khi thu hồi quyền admin, toàn bộ account của admin tự động về safe.
6. Khi bật mode, hệ thống bỏ qua lịch cũ bị dời và tính lịch hợp lệ tiếp theo từ hiện tại, không gửi bù.
7. Risk minimum gap nhỏ nhất là `1` phút và super admin có thể cấu hình.
8. `PEER_FLOOD` mở circuit breaker và cảnh báo nhưng không tự chuyển mode về safe.
9. Lỗi Telegram liên quan được thông báo cho cả admin sở hữu account và super admin.
10. Audit log được lưu `30 ngày`.

## 24. Phạm vi MVP đã chốt

Phạm vi MVP đã chốt gồm:

- ba mode `safe`, `elevated` và `risk_accepted`;
- safe limit cấu hình được, mặc định khởi điểm `8/giờ` và `40/24 giờ`;
- elevated limit do super admin cấu hình;
- risk accepted bỏ hourly/daily soft guard nhưng giữ minimum gap tối thiểu `1` phút;
- super admin cấp quyền cho từng admin;
- admin bật/tắt theo từng Telegram account;
- acknowledgement bắt buộc;
- risk override không tự hết hạn;
- khi bật, hệ thống tự tính lịch hợp lệ tiếp theo từ hiện tại và không gửi bù lịch cũ;
- khi thu hồi quyền, account tự động về safe;
- Telegram cooldown, account lock và idempotency luôn là hard guard;
- `PEER_FLOOD` hoặc bất kỳ tín hiệu spam/rate-limit nào mở circuit breaker ngay;
- circuit breaker không tự thay đổi safety mode;
- cảnh báo lỗi Telegram được gửi cho cả admin và super admin;
- audit mode changes và dispatch override usage;
- audit retention là 30 ngày;
- hiển thị usage 1 giờ/24 giờ trên trang account và schedule.

Dashboard giám sát riêng, email và các kênh thông báo ngoài ứng dụng có thể triển khai sau khi MVP đã có dữ liệu thực tế.
