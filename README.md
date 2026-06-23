# Dental Dashboard — Setup Guide

## โครงสร้างระบบ

```
[MySQL Slave 2]        [Slave 2: PHP Script]       [GitHub Pages]
172.16.16.155    →→→   export_dental.php      →→→  birthgo.github.io/dashboard
(lkhos.oapp)           cron ทุก 5 นาที              index.html
                        07:00–08:00                  ดึง data/dental_today.json
```

---

## 1. ติดตั้งบน Slave 2 (172.16.16.155)

```bash
# Clone dashboard repo
git clone https://github.com/birthgo/dashboard.git /opt/dental-dashboard

# สร้างโฟลเดอร์ script
mkdir -p /opt/dental-export
cp export_dental.php /opt/dental-export/
cp .env.example /opt/dental-export/.env

# แก้ไข .env
nano /opt/dental-export/.env
```

## 2. ตั้งค่า Git Credentials บน Server

```bash
cd /opt/dental-dashboard
git config user.name "Dental Bot"
git config user.email "bot@hospital.local"

# ใช้ Personal Access Token แทน password
# สร้างที่ GitHub → Settings → Developer settings → Personal access tokens
git remote set-url origin https://<TOKEN>@github.com/birthgo/dashboard.git
```

## 3. ติดตั้ง Cron

```bash
chmod +x /opt/dental-export/setup_cron.sh
/opt/dental-export/setup_cron.sh
```

Cron ที่ถูกเพิ่ม:
```
*/5 7 * * * php /opt/dental-export/export_dental.php >> /var/log/dental_export.log 2>&1
```

## 4. ตรวจสอบรหัสแผนกทันตกรรม

```sql
-- รันใน HeidiSQL หรือ MySQL CLI
SELECT DISTINCT clinic, depcode, COUNT(*) as cnt
FROM lkhos.oapp
WHERE nextdate = CURDATE()
GROUP BY clinic, depcode
ORDER BY cnt DESC
LIMIT 20;
```

แก้ไขรหัสในไฟล์ `export_dental.php` บรรทัด:
```php
AND (o.clinic = '1D' OR o.depcode = '1D')
```

## 5. ทดสอบ

```bash
# ทดสอบ script
php /opt/dental-export/export_dental.php

# ดู log
tail -f /var/log/dental_export.log

# ตรวจสอบ JSON ที่สร้าง
cat /opt/dental-dashboard/data/dental_today.json | head -30
```

## 6. เปิด GitHub Pages

GitHub repo → Settings → Pages → Source: **Deploy from branch** → branch: `main` → folder: `/ (root)`

Dashboard URL: `https://birthgo.github.io/dashboard/`

---

## โครงสร้างไฟล์ใน Repo

```
dashboard/
├── index.html               ← Dashboard หลัก (GitHub Pages serve นี้)
└── data/
    └── dental_today.json    ← Script เขียนไฟล์นี้ทุก 5 นาที
```

## Format ของ JSON

```json
{
  "date": "2026-06-23",
  "generated_at": "2026-06-23 07:00:01",
  "total_count": 15,
  "patients": [
    {
      "hn": "123456",
      "fullname": "นาย ชื่อ นามสกุล",
      "nexttime": "08:00:00",
      "doctor": "D001",
      "doctor_name": "ทพ.ชื่อแพทย์"
    }
  ]
}
```
