# BS Hybrid Work — ระบบจองโต๊ะทำงานออนไลน์

แอปพลิเคชัน **จองโต๊ะทำงานระยะผสม (Hybrid Work)** สำหรับ บริษัท จีเนียสซอฟ จำกัด
พนักงาน 35 คน เลือกโต๊ะได้ 15–18 โต๊ะ โดยสารด้วยกันตามโซนพื้นที่ (Zone) และช่วงเวลา (Time Slot)
บนแนวคิด **BS_HYBRID_WORK**

เทคสแตก: Laravel 13 + Breeze (Blade) · Tailwind CSS · Alpine.js · PostgreSQL 17 (Supabase) · Supabase Storage (รูป selfie)

---

## 1. ขั้นตอนติดตั้ง (ทำครั้งแรกเท่านั้น)

```bash
composer install
copy .env.example .env        # จากนั้นไปแก้ .env ด้วยของจริง (ดูข้อ 4)
php artisan key:generate
npm install
npm run build
```

สร้างข้อมูลเริ่มต้น (role/แผนก/โซน/โต๊ะ/ช่วงเวลา + บัญชี demo):

```bash
php artisan migrate:fresh --seed
```

---

## 2. รันแอปพลิเคชัน

```bash
# Terminal 1 — build assets (php artisan serve จำเป็นต้องรันทุกครั้งหลังแก้ไฟล์ JS)
npm run build

# Terminal 2 — web server (ต้องเปิดผ่าน localhost เสมอ กล้องของหน้าเช็คอินจำเป็นต้องใช้ HTTPS/Secure context)
php artisan serve --port=8000
```

เข้าใช้งาน:
- **หน้าแรก**: http://localhost:8000
- **แผนผังที่นั่ง/การจอง (พนักงาน)**: http://localhost:8000/dashboard
- **Admin**: http://localhost:8000/admin — แดชบอร์ด + จัดการพนักงาน/แผนก/โซน/โต๊ะ + รายงาน CSV

> หมายเหตุ: ในการแจกจ่ายจริงต้องเปิดผ่าน **HTTPS** (เช่น `php artisan serve --host=0.0.0.0 --port=443` หลัง Set TLS certificate) เพราะ `getUserMedia` ของเบราว์เซอร์ (หน้ากล้องเช็คอิน) ทำงานได้เฉพาะใน Secure Content (localhost หรือ HTTPS) เท่านั้น

---

## 3. ขั้นตอนของ Scheduler (จองเกินเวลา → auto-cancel)

Command `bookings:auto-cancel` ถูกเช็ตไว้ให้รัน **ทุก 5 นาที** ใน `routes/console.php`
แต่ตัว Laravel Scheduler เองยังต้องถูกกระตุ้นจากระบบปฏิบัติการ:

- **Linux/macOS (crontab)**:
  ```bash
  crontab -e
  # เพิ่มบรรทัด:
  * * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
  ```
- **Windows (Task Scheduler)**:
  สร้าง Task ใหม่ให้รันทุก 1 นาทีด้วยคำสั่ง:
  ```
  php artisan schedule:run
  ```
  (ให้ workdir ชี้ไปที่โฟลเดอร์โปรเจกต์ แล้วรีบบันทึก — Laravel จะรับรองว่าคำสั่ง `bookings:auto-cancel` ทำงานแม่นยำทุก ๆ 5 นาทีผ่าน `withoutOverlapping`)

ทดสอบ:
```bash
php artisan schedule:list
php artisan bookings:auto-cancel   # รันทันทีแบบ manual (ควรใช้ตัวจริง)
```

---

## 4. ตัวแปรสภาพแวดล้อม (.env) ที่สำคัญ

```dotenv
APP_NAME="BS_Hybrid Work"
APP_URL=http://localhost:8000
APP_TIMEZONE=Asia/Bangkok

# ฐานข้อมูล (Supabase อีกรูปแบบ: ผ่าน Hosting Pooler)
DB_CONNECTION=pgsql
DB_HOST=<hostname>.pooler.supabase.com
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres.<project-ref>
DB_PASSWORD=<รหัสผ่านจริง>

# Supabase REST + Storage (ใช้ SERVICE_ROLE_KEY — ควร Rotate หลัง Deploy เพื่อความปลอดภัย!)
SUPABASE_URL=https://<project-ref>.supabase.co
SUPABASE_ANON_KEY=<anon-key>
SUPABASE_SERVICE_ROLE_KEY=<service-role-key>
SUPABASE_STORAGE_BUCKET=checkin-photos
SUPABASE_STORAGE_KEY_FOLDER=checkins

# นโยบายการจอง
BOOKING_LEAD_DAYS=14
BOOKING_EARLY_MINUTES=60
BOOKING_LATE_GRACE_MINUTES=60
BOOKING_AUTO_CHECKOUT_MINUTES=60
CHECKIN_PHOTO_MAX_KB=5120
```

**สำคัญที่สุดเรื่องความปลอดภัย:**
- ห้าม commit `.env` (มันถูก ignore อยู่แล้ว)
- `SERVICE_ROLE_KEY` เป็น key ที่มีสิทธิ์สูงสุด — อย่าใช้ในโค้ดฝั่ง Client; ใช้เฉพาะ backend + **ควร Rotate หลัง Deploy**
- SMTP ยังไม่ได้ตั้ง (Forgot Password ตอนนี้ส่งผ่าน log เท่านั้น) — ใส่ค่า Mail เข็มขัดจริงก่อนขึ้น Production
- กล้องเช็คอิน: บังคับ HTTPS หรือ localhost ตามข้อ 2

---

## 5. ทดสอบ

```bash
npm run build          # ถ้าแก้ JS/Alpine
php artisan test       # 49 tests / 124 assertions (ตระกูล Feature + Unit ทั้งหมด)
```

หรือแบบสคริปต์ E2E จริงกับเซิร์ฟเวอร์ (PowerShell โฟลเดอร์ `scripts/`):

```powershell
.\scripts\admin-smoke-test.ps1
.\scripts\employee-smoke-test.ps1
```

---

## 6. โครงสร้างหลัก (Dev ต้องการ)

| ไฟล์ | หน้าที่ |
| --- | --- |
| `app/Services/Analytics.php` | สถิติ real-time (liveCounts, zoneOccupancy, dailyStats, monthlyStats, recentActivity) ใช้กับ Dashboard + Reports |
| `app/Http/Controllers/Admin/DashboardController.php` | Dashboard + endpoint `/admin/dashboard/realtime` (poll JSON) |
| `app/Http/Controllers/Admin/ReportController.php` | Report: ฟิลเตอร์ + Stats รายวัน/รายเดือน + Export CSV |
| `app/Http/Controllers/Admin/ReportController` (view `admin/reports`) | หน้า Reports (ตาราง + แท็บ chart) |
| `app/Console/Commands/AutoCancelBooking.php` | auto-cancel ที่เลยเวลาตามนโยบาย |
| `resources/js/app.js` | Alpine components: `seatMap`, `checkin`, `adminRealtime` |

---

## 7. บัญชี Demo

| บทบาท | อีเมล | รหัสผ่าน |
| --- | --- | --- |
| Admin | `admin@deskbooking.local` | `Password123!` |
| Employee | `somchai@deskbooking.local` | `TempPass123!` (บังคับเปลี่ยนรหัสผ่านครั้งแรก) |

> หมายเหตุ: หลัง `php artisan migrate:fresh --seed` รหัสพนักงานจะถูก reset เป็น `TempPass123!` + ต้องเปลี่ยนรหัสผ่านก่อนใช้งานอีกครั้ง
