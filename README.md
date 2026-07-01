# TeleSender

TeleSender là bộ khung web PHP/MySQL theo mô hình MVC để:

- gửi tin nhắn Telegram vào nhóm bằng `tài khoản cá nhân`, không dùng bot
- hỗ trợ `multi-account`
- hỗ trợ `multi-user`
- cấu hình nội dung riêng cho từng nhóm thông qua template + schedule
- gọi gửi bằng `cron endpoint URL`
- lưu đầy đủ `dispatch logs` và `message labels`
- chạy `database migration` qua một endpoint có `version`
- giao diện quản trị theo hướng `shadcn UI` với HTML/CSS thuần

## Kiến trúc

### 1. Telegram personal account

Ứng dụng không dùng Bot API. Thay vào đó, lớp `TelegramService` gọi `MadelineProto` để đăng nhập bằng số điện thoại, OTP và 2FA của tài khoản Telegram phụ.

Luồng:

1. User web tạo `telegram_account`
2. Bấm gửi OTP
3. Nhập mã OTP
4. Nếu Telegram yêu cầu, nhập mật khẩu 2FA
5. Session Telegram được lưu ở `storage/telegram/*.madeline`

### 2. Scheduler

- `schedule_jobs` lưu `cron_expression`, `timezone`, `next_run_at`
- cron ngoài hệ thống chỉ cần gọi endpoint:

```text
GET /cron/run?token=YOUR_CRON_TOKEN
```

- endpoint sẽ:
  - tìm các schedule tới hạn
  - khóa job tạm thời bằng `dispatch_locked_until`
  - gửi tin
  - ghi log vào `dispatch_logs`
  - tính lại `next_run_at`

### 3. Migration endpoint

Sau mỗi lần nâng cấp, chỉ cần gọi:

```text
GET /system/migrate?token=YOUR_MIGRATE_TOKEN&version=5
```

Hệ thống sẽ áp dụng tất cả migration chưa chạy với version nhỏ hơn hoặc bằng version truyền vào.

## Cấu trúc thư mục

```text
app/
  Controllers/
  Core/
  Models/
  Services/
config/
database/migrations/
public/
routes/
storage/
views/
```

## Cài đặt

### 1. Chuẩn bị

- PHP 8.3+
- MySQL 8+
- Composer
- `api_id` và `api_hash` từ Telegram app của bạn

### 2. Tạo file môi trường

```bash
cp .env.example .env
```

Điền các giá trị:

- `DB_*`
- `TELEGRAM_API_ID`
- `TELEGRAM_API_HASH`
- `CRON_TOKEN`
- `MIGRATE_TOKEN`
- `APP_URL`

### 3. Cài dependency

```bash
composer install
```

### 4. Tạo database và chạy migration

Tạo database MySQL trống, sau đó gọi:

```text
GET http://localhost:8000/system/migrate?token=YOUR_MIGRATE_TOKEN&version=5
```

Các migration hiện được đánh số tuần tự `1, 2, 3...` để dễ quản lý hơn.
Nếu môi trường cũ từng chạy version dạng timestamp như `202607020002`, hệ thống vẫn tự nhận diện là đã migrate rồi.

### 5. Chạy local server

```bash
php -S localhost:8000 -t public
```

### 6. Tạo user đầu tiên

Nếu `ALLOW_REGISTRATION=true`, truy cập:

```text
/register
```

## Quy trình sử dụng

1. Tạo user và đăng nhập
2. Thêm `Telegram account`
3. Gửi OTP và xác thực 2FA
4. Thêm `Telegram group`
5. Tạo `Message label`
6. Tạo `Message template`
7. Tạo `Schedule`
8. Dùng cron ngoài hệ thống bắn mỗi phút vào:

```text
/cron/run?token=YOUR_CRON_TOKEN
```

## Gợi ý cron ngoài hệ thống

```cron
* * * * * curl -fsS "https://your-domain.com/cron/run?token=YOUR_CRON_TOKEN" >/dev/null
```

## Bảo mật

- dùng token riêng cho `cron` và `migrate`
- dùng tài khoản Telegram phụ, không nên dùng tài khoản cá nhân chính
- giới hạn IP ở Nginx/Cloudflare nếu có thể
- bật HTTPS ở môi trường thật

## Ghi chú kỹ thuật

- Cron parser hiện hỗ trợ chuẩn 5 field: `minute hour day month weekday`
- Group hiện được khai báo thủ công bằng `peer_identifier`
- `dispatch_logs` giữ preview, payload phản hồi và thông báo lỗi
- Đây là nền tảng khởi tạo tốt để bạn mở rộng thêm: RBAC, queue worker, media attachments, sync dialog list, audit filter nâng cao, webhook monitoring
