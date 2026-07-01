# Schedule Builder Implementation Plan

## Mục tiêu

Thay thế trải nghiệm nhập `cron expression` thô bằng một giao diện tạo lịch trực quan, nhưng vẫn giữ `cron_expression` làm engine chạy thật ở backend để không phá vỡ luồng cron endpoint hiện có.

## Tiêu chí hoàn thiện

- Người dùng phổ thông có thể tạo và sửa lịch mà không cần biết cron.
- Vẫn hỗ trợ chế độ `Nâng cao` cho người muốn nhập cron thủ công.
- Các lịch cũ tiếp tục chạy bình thường.
- Các lịch mới lưu được cả:
  - `cron_expression` để chạy thật
  - cấu hình builder để mở lại form không bị mất ngữ nghĩa
- Có preview:
  - mô tả lịch bằng tiếng Việt
  - 5 lần chạy tiếp theo
- UI phải rõ ràng cho các mode phổ biến hơn hẳn preset cũ.

## Phạm vi triển khai

### Mode lịch cần hỗ trợ

1. `interval_minutes`
   - Mỗi X phút
   - Ví dụ: mỗi 15 phút

2. `interval_hours`
   - Mỗi X giờ
   - Cho chọn phút cố định
   - Ví dụ: mỗi 4 giờ, vào phút 00

3. `daily_times`
   - Mỗi ngày vào một hoặc nhiều mốc giờ
   - Ví dụ: 08:00, 12:00, 20:00

4. `weekly_times`
   - Mỗi tuần theo ngày + nhiều mốc giờ
   - Ví dụ: Thứ 2-6 lúc 08:30 và 14:00

5. `advanced`
   - Nhập cron trực tiếp

## Thay đổi dữ liệu

### Bảng `schedule_jobs`

Thêm các cột:

- `schedule_type` VARCHAR(40) NOT NULL DEFAULT `'advanced'`
- `schedule_config_json` LONGTEXT NULL

Ý nghĩa:

- `schedule_type` giúp biết lịch này thuộc mode nào khi mở form sửa.
- `schedule_config_json` lưu cấu hình builder dạng JSON.

Ví dụ:

```json
{
  "interval_hours": 4,
  "minute": 0
}
```

```json
{
  "times": ["08:00", "12:00", "20:00"]
}
```

## Tương thích ngược

- Lịch cũ:
  - nếu chưa có `schedule_type`, mặc định xem là `advanced`
  - dùng trực tiếp `cron_expression`
- Lịch mới:
  - luôn lưu cả `schedule_type`, `schedule_config_json`, `cron_expression`

## Thay đổi backend

### Bổ sung service builder

Tạo service riêng, dự kiến `app/Services/ScheduleBuilderService.php`, chịu trách nhiệm:

- validate dữ liệu builder
- generate `cron_expression`
- generate mô tả tiếng Việt
- trả về 5 lần chạy tiếp theo
- parse cấu hình edit từ:
  - `schedule_type` + `schedule_config_json`
  - hoặc fallback từ `cron_expression` nếu là `advanced`

### Thay đổi controller schedule

`ScheduleController` cần:

- nhận thêm `schedule_type`
- nhận dữ liệu builder theo từng mode
- dùng builder service để sinh `cron_expression`
- vẫn dùng luồng risk analysis hiện tại trên `cron_expression` cuối cùng

## Thay đổi UI

### Form tạo/sửa lịch

Thay phần nhập cron thô bằng:

- `Kiểu lịch`
- cụm input động theo mode
- block preview:
  - `Cron sẽ sinh ra`
  - `Mô tả lịch`
  - `5 lần chạy tiếp theo`

### Chi tiết UX

- `interval_minutes`
  - số phút
- `interval_hours`
  - số giờ
  - phút chạy
- `daily_times`
  - thêm/xóa nhiều mốc `HH:MM`
- `weekly_times`
  - chọn ngày trong tuần
  - thêm/xóa nhiều mốc `HH:MM`
- `advanced`
  - hiện ô cron raw

### Danh sách lịch

Hiển thị thêm:

- loại lịch
- mô tả lịch tiếng Việt

Nếu lịch là `advanced` thì vẫn hiện cron raw như hiện tại.

## Rule validate

- `interval_minutes`
  - 5-59 phút
- `interval_hours`
  - 1-23 giờ
  - phút 0-59
- `daily_times`
  - ít nhất 1 mốc
  - không trùng giờ
- `weekly_times`
  - ít nhất 1 ngày
  - ít nhất 1 mốc giờ
- `advanced`
  - validate cron như cũ

Ngoài ra vẫn giữ rule anti-spam hiện tại của scheduler.

## Rule sinh cron

- `interval_minutes`
  - `*/X * * * *`
- `interval_hours`
  - `M */X * * *`
- `daily_times`
  - `M H1,H2,H3 * * *`
- `weekly_times`
  - `M H1,H2 * * D1,D2,D3`

## Rule preview

Preview dùng `CronExpression` hiện có để lấy các lần chạy kế tiếp theo timezone đã chọn.

## Các điểm cần test

1. Tạo mới từng mode và lưu thành công
2. Edit lại từng mode không mất dữ liệu builder
3. Lịch cũ vẫn mở được ở mode `advanced`
4. Preview 5 lần chạy kế tiếp đúng timezone
5. Risk analysis vẫn hoạt động
6. Schedule queue/cooldown hiện tại không bị ảnh hưởng

## Trình tự triển khai

1. Migration cho `schedule_type`, `schedule_config_json`
2. Cập nhật model `ScheduleJob`
3. Tạo `ScheduleBuilderService`
4. Cập nhật `ScheduleController`
5. Nâng cấp UI `views/schedules/index.php`
6. Kiểm tra syntax + test local
