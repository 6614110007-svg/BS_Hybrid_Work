# BS Hybrid Work — ระบบจองโต๊ะทำงานออนไลน์

ระบบจองโต๊ะทำงานรูปแบบไฮบริด (Hybrid Work) สำหรับบริษัท จีเนียสซอฟ จำกัด
พนักงานเลือกจองโต๊ะตาม **โซนพื้นที่ (Zone)** และ **ช่วงเวลา (Time Slot)** เช็คอินด้วยรูปเซลฟี่
และให้ผู้ดูแลระบบดูสถิติแบบเรียลไทม์

เทคสแตก: Laravel 13 · Blade + Tailwind CSS · Alpine.js · Vite
ฐานข้อมูล: PostgreSQL 17 (Supabase) · รูปเซลฟี่: Supabase Storage

---

## 1. ติดตั้ง (ครั้งเดียว)

```bash
composer install
copy .env.example .env      # Windows · บน macOS/Linux ใช้ cp
php artisan key:generate
npm install
npm run build
```

สร้างตารางและข้อมูลตัวอย่าง:

```bash
php artisan migrate --seed
```

> **ระวังเรื่องฐานข้อมูล:** `php artisan migrate:fresh` จะ **ลบทุกตารางใน schema `public`**
> คำสั่งนี้ใช้กับ `DB_CONNECTION` ใน `.env` เสมอ ถ้าไม่แน่ใจให้ตรวจก่อนด้วย
> `php artisan about` หรือเติม `--env=testing` (ไฟล์ `.env.testing` บังคับเป็น SQLite `:memory:` ไว้แล้ว)

---

## 2. รันระบบ

```bash
# Terminal 1
npm run dev                 # ระหว่างพัฒนา (Vite HMR)

# Terminal 2
php artisan serve --port=8000
```

| หน้า | URL |
| --- | --- |
| หน้าแรก | `/` (redirect ไป `/login` หรือ dashboard ของบทบาทที่ล็อกอินอยู่) |
| พนักงาน — ผังที่นั่ง/จองโต๊ะ | `/dashboard` |
| พนักงาน — ประวัติการจองของฉัน | `/bookings` |
| ผู้ดูแลระบบ — แดชบอร์ด | `/admin/dashboard` |
| ผู้ดูแลระบบ — รายงาน + ส่งออก CSV | `/admin/reports` |

> หน้าเช็คอินใช้ `getUserMedia` ของเบราว์เซอร์ จึงต้องเปิดผ่าน `localhost` หรือ **HTTPS** เท่านั้น

---

## 3. บทบาทและการเข้าสู่ระบบ

ระบบมี 2 guard (`config/auth.php`)

| Guard | Model | ตาราง | เข้าโซน |
| --- | --- | --- | --- |
| `admin` | `App\Models\Admin` | `admin` | `/admin/*` |
| `web` | `App\Models\Employee` | `employee` | `/dashboard` |

- ผู้ดูแลระบบ = บัญชีในตาราง `admin` **หรือ** `employee.employee_role = 'Administrator'`
- พนักงานทั่วไป = `employee.employee_role = 'Employee'`
- บัญชีสถานะไม่ Active (`admin_status` / `employee_status`) ถูกบล็อกด้วย middleware `actor.active`
- หน้า login ครั้งเดียวรองรับทั้งสอง guard (เดาจากอีเมล)
- ไม่มีระบบลืมรหัสผ่าน/ยืนยันอีเมล มีเฉพาะเปลี่ยนรหัสผ่าน (`/password`)
- รหัสผ่านเก็บเป็น bcrypt hash (`admin_password`, `employee_password`)

### บัญชีตัวอย่าง (หลัง `php artisan db:seed`)

| บทบาท | อีเมล | รหัสผ่าน |
| --- | --- | --- |
| ผู้ดูแลระบบ (ตาราง `admin`) | `admin@bs-hybrid.test` | `password` |
| พนักงานทั่วไป | `employee@bs-hybrid.test` | `password` |
| พนักงานสิทธิ์ผู้ดูแล | `manager@bs-hybrid.test` | `password` |
| พนักงาน | `wichai@bs-hybrid.test` | `password` |
| พนักงาน | `ploy@bs-hybrid.test` | `password` |

> ข้อมูลตัวอย่างเหมาะกับเครื่อง dev เท่านั้น — ห้าม seed ลงฐานข้อมูล production

---

## 4. Database Schema (6 ตาราง)

| ตาราง | คอลัมน์ |
| --- | --- |
| `admin` | `admin_id` (CHAR 11, PK), `admin_fullname`, `admin_email` (unique), `admin_password`, `admin_status` |
| `department` | `department_id` (CHAR 11, PK), `department_name` |
| `employee` | `employee_id` (CHAR 11, PK), `employee_fullname`, `employee_tel`, `employee_email` (unique), `employee_password`, `employee_role`, `employee_status`, `department_id` (FK) |
| `zone` | `zone_id` (CHAR 11, PK), `zone_name`, `zone_total_desks` (default 0) |
| `desk` | `desk_id` (CHAR 11, PK), `desk_number`, `map_position`, `desk_status` (default `Available`), `zone_id` (FK) |
| `booking` | `booking_id` (CHAR 11, PK), `booking_date`, `time_slot`, `start_time`, `end_time`, `actual_checkin_time`, `actual_checkout_time`, `checkin_photo`, `booking_status`, `employee_id` (FK), `desk_id` (FK) |

- ไม่มี `created_at` / `updated_at` — ใช้ `$timestamps = false`
- Primary key ทุกตารางเป็น `CHAR(11)` ส่วน Foreign key เป็น `VARCHAR(11)` ตาม BRD (PostgreSQL รองรับ FK ข้าม `char`/`varchar`)
- รหัสรูปแบบ `<PREFIX><8 หลัก>` สร้างโดย `App\Models\Concerns\HasGeneratedId` + `App\Support\IdSequence` (`lockForUpdate` กัน ID ชนกัน)
- `booking.time_slot` เป็น VARCHAR อ้างอิงชื่อช่วงเวลาที่กำหนดใน `config/booking.php` (ไม่มีตาราง `time_slots`)
- กฎ "โต๊ะ 1 ตัว / คน 1 คน ต่อ 1 ช่วงเวลา" บังคับที่ระดับแอปด้วย `DB::transaction()` + `lockForUpdate()` (ไม่มี unique index ตาม schema)
- ส่วนที่เพิ่มจากตารางใน BRD: `UNIQUE` ที่ `admin_email` / `employee_email` (จำเป็นต่อการ login), `DEFAULT 0` ที่ `zone_total_desks`, `DEFAULT 'Available'` ที่ `desk_status` และ index เพื่อความเร็ว

---

## 5. กติกาการจองและเช็คอิน

ค่าตั้งทั้งหมดอยู่ใน `config/booking.php`

| คีย์ | ค่าเริ่มต้น | ความหมาย |
| --- | --- | --- |
| `slots` | Full Day 08:00–18:00, Morning 08:00–12:00, Afternoon 13:00–18:00 | ช่วงเวลาที่เปิดจอง |
| `lead_days` | 14 | จองล่วงหน้าได้กี่วัน |
| `early_checkin_minutes` | 60 | เช็คอินได้ก่อนเวลาเริ่มกี่นาที |
| `late_grace_minutes` | 60 | เกินเวลาเริ่มกี่นาทีแล้วยังไม่เช็คอิน → ยกเลิกอัตโนมัติ |
| `auto_checkout_minutes` | 60 | เช็คอินแล้วค้างเกินกี่นาที (สงวนไว้สำหรับนโยบายเช็คเอาต์อัตโนมัติ) |
| `checkin_photo_mimes` | jpeg, jpg, png, webp | ชนิดไฟล์รูปที่อนุญาต |
| `checkin_photo_max_kb` | 5120 | ขนาดรูปสูงสุด (KB) |

สถานะ: `booking_status` = `R` (จองแล้ว/รอเช็คอิน), `C` (เช็คอินแล้ว), `COMP` (เสร็จสิ้น), `X` (ยกเลิก/หมดอายุ)
สถานะ: `desk_status` = `Available`, `Reserved`, `Checked-In`, `Maintenance`

- `DeskStatusManager` คำนวณ `desk_status` อัตโนมัติจาก booking ของวันนี้ (เรียกผ่าน model event `Booking`)
- `Maintenance` ต้องตั้งเองโดยผู้ดูแล และปิดบำรุงโต๊ะที่มีการจอง active ไม่ได้
- `zone_total_desks` อัปเดตอัตโนมัติจาก model event `Desk`

---

## 6. Scheduler — ยกเลิกใบจองอัตโนมัติ

`routes/console.php` ตั้ง `bookings:auto-cancel` ทุก 5 นาที
ตัว Scheduler ต้องถูกกระตุ้นจากระบบปฏิบัติการ:

```bash
# ตรวจตารางงาน
php artisan schedule:list

# รันเอง (ทุกนาที)
php artisan schedule:run
```

- **Linux/macOS**: `* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1`
- **Windows**: Task Scheduler รัน `php artisan schedule:run` ทุก 1 นาที โดยตั้ง *Start in* เป็นโฟลเดอร์โปรเจกต์

---

## 7. ตัวแปรสภาพแวดล้อม

```dotenv
APP_TIMEZONE=Asia/Bangkok

DB_CONNECTION=pgsql
DB_HOST=<project-ref>.pooler.supabase.com
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres.<project-ref>
DB_PASSWORD=<รหัสผ่าน>
DB_SSLMODE=require

SUPABASE_URL=https://<project-ref>.supabase.co
SUPABASE_ANON_KEY=<anon-key>
SUPABASE_SERVICE_ROLE_KEY=<service-role-key>
SUPABASE_BUCKET=checkin-photos

BOOKING_LEAD_DAYS=14
BOOKING_EARLY_MINUTES=60
BOOKING_LATE_GRACE_MINUTES=60
BOOKING_AUTO_CHECKOUT_MINUTES=60
CHECKIN_PHOTO_MAX_KB=5120
```

ดูค่าทั้งหมดได้ใน `.env.example`

**ความปลอดภัย**

- ห้าม commit `.env` (อยู่ใน `.gitignore` แล้ว)
- `SUPABASE_SERVICE_ROLE_KEY` มีสิทธิ์สูงสุด ใช้ฝั่ง server เท่านั้น และควร rotate หลัง deploy
- รูปเซลฟี่เป็นข้อมูลส่วนบุคคล ควรตั้ง bucket เป็น private แล้วให้ระบบสร้าง signed URL (`SupabaseStorage::signedUrl()`)

---

## 8. โครงสร้างโค้ดที่ต้องรู้

| ไฟล์ | หน้าที่ |
| --- | --- |
| `app/Models/Actor.php` | Base class ของ `Admin`/`Employee`: `authGuard()`, `statusOptions()`, `homeRoute()` |
| `app/Models/Concerns/HasGeneratedId.php` | สร้าง primary key รูปแบบ `EMP00000001` |
| `app/Services/CurrentActor.php` | จุดรวม resolve ผู้ใช้งานปัจจุบันจาก 2 guard |
| `app/Services/DeskStatusManager.php` | คำนวณสถานะโต๊ะจาก booking ของวันนี้ |
| `app/Services/Analytics.php` | สถิติสำหรับ dashboard และรายงาน |
| `app/Services/SupabaseStorage.php` | อัปโหลด/อ่าน/ลบรูปเซลฟี่ผ่าน Supabase Storage API |
| `app/Support/TimeSlot.php` | ช่วงเวลาจาก `config/booking.php` + เวลาเปิด/ปิด/เช็คอิน |
| `app/Support/IdSequence.php` | ออกเลขรหัสแบบเรียงลำดับพร้อม row lock |
| `app/Http/Middleware/EnsureEmployee.php` | บังคับเฉพาะพนักงานทั่วไป |
| `app/Http/Middleware/EnsureAdministrator.php` | บังคับเฉพาะผู้ดูแลระบบ |
| `app/Http/Middleware/EnsureActorActive.php` | บล็อกบัญชีที่ไม่ Active |
| `app/Http/Controllers/Auth/LoginRequest.php` | ตรวจเข้าสู่ระบบ + rate limit |
| `app/Http/Controllers/Admin/ReportController.php` | รายงาน + ส่งออก CSV |
| `app/Console/Commands/AutoCancelExpiredBookings.php` | ยกเลิกใบจองที่เลยเวลาเช็คอิน |
| `resources/js/app.js` | Alpine.js + polling ผังที่นั่ง (30 วิ) และสถิติ dashboard (60 วิ) |

---

## 9. ทดสอบ

```bash
php artisan test          # 67 tests / 223 assertions
```

ชุดทดสอบครอบคลุม:

- `tests/Feature/Auth/` — login ทั้งสอง guard, rate limit, เปลี่ยนรหัสผ่าน
- `tests/Feature/Employee/` — ผังที่นั่ง, จอง/ยกเลิก, เช็คอิน/เช็คเอาต์, สิทธิ์ของใบจอง
- `tests/Feature/Admin/` — สิทธิ์ผู้ดูแล, CRUD ข้อมูลหลัก, รายงาน + CSV
- `tests/Feature/PageSmokeTest.php` — ทุกหน้า render ได้จริง
- `tests/Feature/AutoCancelExpiredBookingsTest.php` — กติกา auto-cancel

ก่อนส่งงานควรรัน:

```bash
php artisan migrate:fresh --env=testing --force   # เช็ก migration บนฐานว่าง (SQLite)
php artisan route:list
npm run build
```

> คำสั่งแรกต้องมี `--env=testing` เสมอ เพราะ `.env.testing` จะบังคับ SQLite
> ถ้าไม่ใส่ ระบบจะเชื่อม PostgreSQL ตัวจริงและลบข้อมูลทั้งหมด

---

## 10. หมายเหตุ

- ระบบไม่มีการส่งอีเมล (ยกเลิก forgot password / verify email ตาม schema)
- ตาราง `booking` มี index ตาม `booking_date`, `booking_status`, `employee_id`, `desk_id` เพิ่มจาก BRD เพื่อความเร็วในการ query รายงาน
- `.env.testing` ถูกตั้งค่าให้ใช้ SQLite `:memory:` เสมอ เพื่อกันไม่ให้คำสั่งทดสอบไปแตะฐานข้อมูลจริง
