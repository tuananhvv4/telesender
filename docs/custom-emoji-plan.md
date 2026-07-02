# Ke hoach trien khai custom emoji Telegram Premium

## Muc tieu

Xay dung luong su dung custom emoji Telegram Premium theo cach than thien voi user:

- User khong can nho `emoji-id`
- User chi can quan ly emoji mot lan trong thu vien rieng
- Trong template, user chi can chen token de doc/de sua de hon
- Luc gui tin, he thong tu compile token thanh the HTML `tg-emoji`
- Co preview, tim kiem, recent/favorite de thao tac nhanh

## Pham vi chuc nang

### 1. Thu vien custom emoji

Them man hinh quan ly custom emoji:

- Ten goi nho
- Slug
- Emoji ID
- Fallback emoji
- Ghi chu / tu khoa
- Trang thai active

Muc dich:

- Tao kho emoji dung chung cho tung user
- Khong phai chen tay `emoji-id` vao noi dung

### 2. Luu tru template than thien voi user

Template se luu theo dang token noi bo:

```text
Khuyen mai hom nay {{ce:fire_cat}}
Ho tro nhanh {{ce:question_blue}}
```

Khong bat buoc user phai nhin thay:

```html
<tg-emoji emoji-id="5318779098686826724">🔥</tg-emoji>
```

### 3. Compile truoc khi gui

Truoc khi goi Telegram:

- Tim tat ca token `{{ce:slug}}`
- Validate token co ton tai trong thu vien custom emoji cua user
- Thay token bang:

```html
<tg-emoji emoji-id="...">fallback</tg-emoji>
```

Neu template co custom emoji token thi:

- Parse mode phai la `HTML`
- Neu khong phai `HTML`, he thong chan luu hoac canh bao ro rang

### 4. Picker nang cao trong form template

Them bo cong cu custom emoji vao man hinh template:

- Search theo ten / slug / tu khoa
- Grid chon nhanh
- Recent da dung gan day
- Favorite luu local trong browser
- Chen token vao dung vi tri con tro trong textarea
- Hien helper token dang duoc chen

### 5. Preview

Preview gom 2 lop:

- Preview noi bo de user de doc noi dung va thay custom emoji dang duoc gan
- Preview compiled HTML de user biet he thong se gui gi len Telegram

Luu y:

- Browser khong render duoc custom emoji Telegram giong client Telegram
- Preview se uu tien tinh de doc + minh bach hon la "giong 100%"

## Thay doi ky thuat du kien

### Database

Them migration moi:

- `0006_add_custom_emojis_table.php`

Bang `custom_emojis`:

- `id`
- `user_id`
- `name`
- `slug`
- `emoji_identifier`
- `fallback_emoji`
- `keywords`
- `is_active`
- `created_at`
- `updated_at`

### Backend

Them moi:

- `app/Models/CustomEmoji.php`
- `app/Controllers/CustomEmojiController.php`
- `app/Services/CustomEmojiService.php`

Cap nhat:

- `routes/web.php`
- `views/layouts/app.php`
- `app/Controllers/MessageTemplateController.php`
- `app/Services/SchedulerService.php`

### Frontend

Cap nhat:

- `views/templates/index.php`
- `public/assets/app.css`

Co the them endpoint preview:

- `/templates/preview`

## Nguyen tac UX

- Khong de user nho id dai
- Khong de noi dung template bi "ban" boi HTML kho doc
- Co tim kiem, favorite, recent
- Token phai ngan, de nhin, de sua
- Loi validate phai noi ro token nao sai

## Kiem tra truoc khi xong

- Tao / sua / xoa custom emoji
- Chen token vao template bang picker
- Validate token sai / trung / emoji inactive
- Gui thu bang send-now
- Cron gui thu tu schedule
- Log van ghi binh thuong
- Kiem tra syntax PHP
- Kiem tra CSS/JS khong vo layout trang template

## Ghi chu trien khai

- Se uu tien huong compile token -> `tg-emoji`
- Khong bat user chen tay HTML raw
- Van giu kha nang cho power-user tu viet HTML neu can, nhung UX chinh se di qua picker
