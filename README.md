# Dashboard .53 — private portal + existing GitHub Pages

อ่าน MIGRATION_VM53.md และ AGENTS.md ก่อนติดตั้ง ห้ามรัน exporter/setup cron บน .53 ระหว่างทดสอบ

- GitHub Pages https://birthgo.github.io/dashboard/ และ index.html/data/dental_today.json ยังคงmain/URLเดิม ไม่mergebranchนี้เพื่อย้ายVM
- export_dental.php ที่ทำงานบน .155 และ cron/ENV/Git credentials เดิมบน .155 ยังไม่deployจากbranchนี้ ไม่เปลี่ยนเพียงเพราะย้ายPortal
- Portalอยู่ /var/www/html/dental-dashboard บน .53 และ exporterรายชื่อนัดย้ายจาก .52 อ่านDB .155ตามเจ้าของ
- บัญชีแพทย์/hash/force-change/lockout ต้องคัดลอกจากSQLiteเดิมอย่างสอดคล้องรวมWAL ไม่มีการseed/resetบัญชีเมื่อไฟล์หาย
- ENV /etc/webapps/dashboard.env; private state /var/lib/dashboard; Windows template D:/webapps-secrets/dashboard.env
- Portalคงระบบloginเดิมของแพทย์ ไม่เปลี่ยนเป็นSSOในงานย้ายVMนี้ และไม่ใช่MySQL user dashboard
- .155อาจมีreplicationlag; user dashboardอ่านเฉพาะตารางที่ใช้ SQLอยู่deployment/database-permissions.sql รันบน .246โดยเจ้าของเท่านั้น
- appointments.jsonมีข้อมูลรายคน ห้ามcommit/uploadGitHub; เฉพาะ dental_today.jsonที่มี date/generated_at/total_count เป็นpublic
- หน้า publicให้ใช้GitHubPagesURLเดิม local index.htmlในclone .53ไม่ใช่หลักฐานว่าได้รับยอดอัปเดตจากexporter .155แล้ว

PHPCLI diagnostics: tools/check-runtime.php ไม่queryข้อมูลผู้ป่วยออกจอ ไม่export ไม่push ไม่สร้างuser/schemaและไม่เปลี่ยนรหัส
ทดสอบ Local: environment_test.php และ runtime_test.php ใช้ข้อมูลสมมติและSQLiteชั่วคราว ตรวจhash/flagเดิมไม่เปลี่ยน,ไม่สร้างDBเมื่อหาย,atomicJSON,wrongpassword,doctorisolation; ยังไม่ทดสอบVMจริง
