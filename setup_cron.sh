#!/bin/bash
# setup_cron.sh — ติดตั้ง cron jobs สำหรับ export ข้อมูลทันตกรรม
# รันครั้งเดียวบน Slave 2 (172.16.16.155)

SCRIPT_PATH="/opt/dental-export/export_dental.php"
LOG_PATH="/var/log/dental_export.log"
PHP_BIN=$(which php)

echo "=== ติดตั้ง Cron Jobs สำหรับ Dental Dashboard ==="
echo "PHP: $PHP_BIN"
echo "Script: $SCRIPT_PATH"

# สร้าง cron entry
# - ทุก 5 นาที ช่วง 07:00-08:00 → อัพเดทยอดรวม + push
CRON_ENTRY="*/5 7 * * * $PHP_BIN $SCRIPT_PATH >> $LOG_PATH 2>&1"
# - รันครั้งแรก 07:00 ตรง (อยู่ใน entry ข้างบนแล้ว)

echo ""
echo "Cron entry ที่จะเพิ่ม:"
echo "  $CRON_ENTRY"
echo ""

# ตรวจสอบว่ามีอยู่แล้วหรือยัง
if crontab -l 2>/dev/null | grep -q "dental_export"; then
    echo "⚠️  พบ cron เดิมอยู่แล้ว ลบก่อนแล้วเพิ่มใหม่..."
    crontab -l | grep -v "dental_export" | crontab -
fi

# เพิ่ม cron
(crontab -l 2>/dev/null; echo "$CRON_ENTRY") | crontab -

echo "✅ เพิ่ม Cron สำเร็จ"
echo ""
echo "=== Cron ปัจจุบัน ==="
crontab -l

echo ""
echo "=== ขั้นตอนถัดไป ==="
echo "1. คัดลอก .env.example → .env แล้วใส่ credential จริง"
echo "2. ตรวจสอบรหัสแผนกทันตกรรม (clinic/depcode) ด้วย:"
echo "   SELECT DISTINCT clinic, depcode FROM lkhos.oapp WHERE nextdate = CURDATE() LIMIT 20;"
echo "3. ตรวจสอบว่า git remote ตั้งค่าถูกต้อง:"
echo "   cd /opt/dental-dashboard && git remote -v"
echo "4. ทดสอบรัน script ด้วยมือ:"
echo "   php $SCRIPT_PATH"
echo "5. ดู log:"
echo "   tail -f $LOG_PATH"
