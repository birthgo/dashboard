# dashboard
อ่าน C:/xampp/htdocs/dental-system-docs/AGENTS.md และเอกสารกลางที่อ้างถึงก่อนทำงาน

## Migration .53 / ทะเบียนสิ่งสำคัญ — 16 พฤศจิกายน 2026
- อ่าน MIGRATION_VM53.md ก่อนแก้deploy; .53 path /var/www/html/dental-dashboard เจ้าของยืนยัน export_appointmentsย้ายจาก .52อ่านDB .155 ส่วนexport_dentalบน .155คงเดิม ไม่เปิดcronซ้ำ
- ENV /etc/webapps/dashboard.env หรือWindows D:/webapps-secrets/dashboard.env root:apache0640 parent0750 httpd_config_t WindowsACLตามservice; DBuser dashboard SQLเจ้าของรัน .246เท่านั้น
- SQLiteต้นทางตามโค้ด data/portal_users.sqlite+WAL/SHM ต้องตรวจVMจริงและsnapshotผ่านSQLitebackup ห้ามcopyfileเดี่ยวขณะwriterทำงาน ห้ามseed/resetบัญชีแพทย์หรือhashเมื่อย้าย
- privateปลายทาง /var/lib/dashboard/portal_users.sqlite,appointments.json,sessions (DASHBOARD_PRIVATE_DIR) apacheเขียนdirectory/SQLiteได้ SELinux httpd_sys_rw_content_t; ไม่มีfallbackwebroot ไฟล์JSONมีข้อมูลผู้ป่วยห้ามGit/HTTP
- เจ้าของส่ง diff Server ยืนยัน JSON ต้นทางจริง /var/lib/dental-dashboard/appointments.json ทั้ง exporter/API ไม่ใช่ data/appointments.json และใช้ JSON_INVALID_UTF8_SUBSTITUTE; branchใหม่รักษาflagนี้พร้อมatomicwrite ต้องตรวจและย้ายJSONจากpathจริง ไม่สรุปว่าต้นทางไม่มีJSONเพราะไม่มีไฟล์ในเว็บ
- Portalยังใช้loginเดิมของแพทย์ในSQLiteเป็นระบบเดิมเฉพาะแอป ไม่เปลี่ยนเป็นSSOขณะmigration; sessionname DENTAL_DASHBOARDเก็บprivateต้องloginใหม่ ไม่reuseSSOsession
- publicPages index.html/data/dental_today.jsonคงmain/URLเดิมและมีaggregateเท่านั้น; ลิงก์ moph.laokhwanhos.comต้องตรวจproxy/NATupstreamจริงก่อนcutover ไม่replaceIPหรือเปลี่ยนDNSอัตโนมัติ
- .155 GIT_REPO_PATH/cron/log/Gitcredentialsอยู่นอกwebrootหลายpathตามREADMEเก่าเป็นค่าแนะนำ ยังไม่ได้ยืนยันVM; ห้ามอ่าน/แสดงtokenหรือแก้exporter .155เพียงเพราะpullPortal .53
- ไม่มีถาวรPDFแบบLMSจากโค้ดที่ตรวจ; CSV/printในbrowser รายชื่อนัดเก็บprivateJSON; รักษาpassword/accountmapping/rate-limit และsinglewriterโดยfinalsnapshotก่อนcutover
- ไม่รันsetup_cron/exporterเพื่อทดสอบreadiness ใช้tools/check-runtime.phpที่ไม่export/push/แก้รหัสแทน; source/targetที่ยังไม่ตรวจต้องระบุว่าไม่ยืนยัน

## ช่วงย้าย VM .52 / .53 — คำสั่งเจ้าของ 11 พฤศจิกายน 2026
- ก่อนแก้งานครั้งใหม่ทุกครั้ง ต้องถามเจ้าของว่าแก้สำหรับ .52 หรือ .53 และรอคำตอบก่อนแก้โค้ด ห้ามอนุมานจากชื่อโฟลเดอร์/branch หรือคำตอบในงานก่อนหน้า
- หากคำสั่งครั้งนั้นระบุ .52/.53 ชัดเจนแล้ว ถือว่าตอบแล้ว ไม่ถามซ้ำภายในงานเดียวกัน งาน migration ที่กำลังทำนี้อนุญาต .53 ในสำเนา _new
- .52 ใช้ checkout เดิม; .53 ใช้ checkout _new และ branch migration/vm53 ของ repo เดิม ตรวจ branch/status ก่อนแก้
- ห้าม merge/push/deploy โดยอาศัยคำอนุญาตเก่า ต้องมีคำสั่งครอบคลุมงานปัจจุบัน
- ใช้กฎนี้จนเจ้าของสั่ง merge branch และยืนยันย้ายไป .53 ถาวร แล้วจึงปรับ AGENTS ให้เลิกถามสอง VM
- ฐานข้อมูลร่วมยังเป็นของจริง Git branch ไม่แยกข้อมูล; ห้ามเปลี่ยน app_url กลางระหว่างทดสอบโดยไม่ตรวจผลต่อ .52
