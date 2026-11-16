# Dashboard .53 — 16 พฤศจิกายน 2026

ฐาน Local e1a9974; migration/vm53 ใน dashboard_new จาก checkout dashboard1 ไม่ใช่ XAMPP dashboard
เจ้าของสั่ง push branchนี้แล้ว; ยังไม่ได้ deploy/เรียกexporter/เปลี่ยนcron/เขียนฐานจริง ไม่มีschema/grants executeจากagent

## เจ้าของยืนยัน
Portal path .53 /var/www/html/dental-dashboard; export_appointments.php เดิมรัน .52อ่าน .155 ให้ย้ายส่วนนี้ไป .53
export_dental.php/cronบน .155 ที่ส่งยอดรวมไปGitHubPagesคงเดิม ไม่deploybranch .53ลง .155 ไม่เปลี่ยนPagesmain/URL/domainเอง
DB user dashboard เจ้าของตั้งรหัส/รัน deployment/database-permissions.sql บน .246เท่านั้น account/password/grantsreplicateตามระบบ ต้องตรวจ .155

## Inventoryที่ต้องตรวจบนต้นทาง/ปลายทาง
| รายการ | path/key | การย้าย/สิทธิ์ |
|---|---|---|
| ENVใหม่ .53 | /etc/webapps/dashboard.env | root:apache0640 parent0750 httpd_config_t; ต้องกรอกค่าจริงจากtemplate |
| ENVWindows | D:/webapps-secrets/dashboard.env | templateเท่านั้น ACLตามservice |
| SQLiteเดิม | .52 /var/www/html/dental-dashboard/data/portal_users.sqlite และ -wal/-shm | pathจากโค้ด ยังรอlsยืนยันจริง; ห้ามcopyเฉพาะmainfileขณะมีwriter |
| SQLiteใหม่ | /var/lib/dashboard/portal_users.sqlite | apacheเขียนfileและdirectoryได้ ใช้snapshotSQLitebackup ไม่seed/reset |
| รายชื่อนัดเก่า | /var/lib/dental-dashboard/appointments.json | เจ้าของยืนยันจาก diff Server ของทั้ง exporter/API; ยังต้อง ls ยืนยันไฟล์จริงบน .52/.53 ข้อมูลผู้ป่วยห้ามGit/HTTP |
| รายชื่อนัดใหม่ | /var/lib/dashboard/appointments.json | exporterเขียนatomic workerapache mode0600 APIอ่านหลังloginเฉพาะdoctor |
| Sessionใหม่ | /var/lib/dashboard/sessions | apache0700, httpd_sys_rw_content_t; ไม่ต้องcopyPHPSESSIDเก่าหรือSSOsession ผู้ใช้loginใหม่ |
| SQLite/JSONdirectory | /var/lib/dashboard | apache0750 httpd_sys_rw_content_t; ไม่copyทั้ง /var/lib |
| cronรายชื่อนัด | เดิม .52 entryอ้าง export_appointments.php | inspectcommand/user/scheduleจริงก่อนคัดลอก ไม่เปิดwriter .53ระหว่างtest; setup_cron.shใหม่ไม่แก้crontabเอง |
| cronยอดรวม | .155 export_dental.php | คงเดิม main/paths/ENV/Gitdeploycredentials .155 ต้องinventoryแต่ยังไม่ย้าย |
| public JSON | Git repo data/dental_today.json | date/generated_at/total_countเท่านั้น ไม่exportรายคน public/index.htmlคงเดิม |
| Proxy/TLS/DNS | moph.laokhwanhos.com → upstreamจริง | ผู้ดูแลตรวจroutingกลับ .53ตอนcutover ไม่เปลี่ยนDNS/NATจากrepoเอง |

## จุดที่แก้
- secretมาตรฐานภายนอก ไม่มี .env fallback หรือDBcredentialdefault
- ย้ายprivateappointments/SQLite/sessionออกwebroot; หยุดautoCREATE/ALTER/seedในgetPortalDB ปฏิเสธเมื่อschema/stateไม่ครบ
- คงpasswordhash/doctor mapping/force-change/rate-limitเดิม ไม่copyusersจากconstแทนฐานจริง
- auth.phpมีsafe503handlerไม่แสดงstack/credentials; APIอ่านjsonเสียแจ้งerror ไม่ตีความว่าไม่มีนัด
- export_appointments CLI-onlyเขียนatomicJSONส่วนตัว; debug_exportเก่าปิด ไม่exportข้อมูลdebug
- รวม custom Server: JSON_INVALID_UTF8_SUBSTITUTE คงเดิม ไม่ให้ค่าHISที่ยังมีinvalidbyteทำให้exportทั้งไฟล์ล้ม; pathเดิม /var/lib/dental-dashboard/appointments.json ย้ายไปprivatepathใหม่ตามENV /var/lib/dashboard ไม่ใช้webrootfallback
- export_dentalในbranchนี้ใช้ENVมาตรฐาน/CLI-onlyและquoteGitargs แต่ไม่รันหรือdeployบน .155ในงานนี้ .155runtimeเดิมจึงไม่เปลี่ยน
- ไม่แตะpublic index.html/data/dental_today.json; publicgitrepoยังมีโค้ดแต่ไม่มีข้อมูลส่วนตัว/secretใหม่ .gitignoreบล็อกappointments/SQLite/WAL/SHM

## เตรียมก่อนpull
1. ตรวจบริการworkerapacheและPHP pdo_sqlite/sqlite3, pathSQLite/JSON, cronที่ใช้งานจริงบน .52/.53 ห้ามส่งJSONหรือpasswordhashในchat
2. user dashboardบน .246และENV .53ตามtemplate; chownroot:apache0640, semanagehttpd_config_t/restorecon, เพิ่มREADME mapping ใช้ /etc/webappsเดิม
3. สร้าง /var/lib/dashboard และsessionsเฉพาะdirectoryที่ขาด apache0750/0700 SELinux httpd_sys_rw_content_t ให้parenttraverseได้
4. snapshotSQLiteผ่านSQLite3::backupหรือPDO VACUUM INTO (SQLite3.27+)ด้วย tools/snapshot-sqlite.php บน .52 ลง /root/dashboard-migration/ชื่อใหม่ที่ยังไม่มี (copyเฉพาะtoolsไปนอกเว็บไม่pullbranchบน .52); sourceเปิดREADONLY รวมcommittedWALในsnapshot แต่อย่าย้าย/ลบWALของต้นฉบับ
5. ส่งsnapshotแบบprivate .52→.53 ติดตั้งในprivatepath apache:apache0640 โดยไม่ทับDB .53ที่กำลังใช้งาน หลังsnapshotต้องไม่มีการresetpasswordบน .53ช่วงtestเพื่อเลี่ยงstateสองชุดขัดกัน
6. JSONนัดcopyจากต้นทางเมื่อexporterไม่กำลังเขียนแล้วvalidate หรือให้ownerอนุญาตรันexporterใหม่บน .53หลังDB/SQLพร้อม รันด้วยapacheเท่านั้น ไม่pushJSONนี้
7. status/fetch/switch/pullในrepo .53 (safe.directoryและ -C pathเดียวกัน) หยุดเมื่อconflict ห้ามreset/cleanหรือuploadทั้งLocalทับdata/vendor
8. tools/check-runtime.phpตรวจเฉพาะread/schema/SQLitequickcheck/privateJSON/sessions ไม่export/seed/cron/push; ทดสอบloginแพทย์เดิมทั้งถูก/ผิด force-changeflagเดิม rate-limitและdoctorisolation กับบัญชีที่อนุญาต
9. ทดสอบHTTP .env,.git/config,runtime.php,environment.php,portal/auth.php,data/appointments.json,data/portal_users.sqlite,tools/check-runtime.php ต้อง403/404; aggregate data/dental_today.jsonและpublicHTMLต้องยังอ่านได้
10. เปิด .53Portalผ่านIPโดยตรงทดสอบก่อนเปลี่ยนproxy; ข้อมูลจริงห้ามส่งในchat; คงold.env/privatefilesที่sourceไว้จนย้ายผ่าน อย่าลบไฟล์shared

## Cutoverยังไม่ดำเนินการ
- หยุดเฉพาะwriter/loginเปลี่ยนรหัสของPortal .52ตามแผนผู้ดูแล แล้วsnapshotสุดท้าย/JSONfinalsyncให้ .53 ก่อนสลับdomain และให้ผู้ใช้loginใหม่ ไม่หยุดPHP-FPMทั้งเครื่องที่มีSSO/แอปอื่นเพียงเพื่อsnapshot
- ปิดเฉพาะcron export_appointments .52ก่อนเปิดentryเทียบเท่าบน .53ใช้apache; ไม่แก้export_dental .155 ไม่ใช้setup_cronเก่าที่ลบ/เพิ่มentryเหมารวม
- export_appointmentsไม่ใช่Gitwriter ไม่มีpublishprivatedata; GitHubPagesยังรับaggregateจาก .155ตามเดิม
- rollbackcodeไม่rollbackpasswordstate/appointments ต้องคงbackupและตัดสินwriterเดียวก่อนกลับ .52
- ผ่านแล้วเจ้าของย้าย/ลบprivatefilesเก่าในwebroot .53เอง (SQLite/WAL/SHMต้องไม่มีprocessใช้อยู่) pullไม่ลบignoredfiles

## ผล Local
PHP lint, environment_test.php และruntime_test.phpผ่าน ใช้SQLite/JSONmockนอกwebroot ไม่เชื่อมDB/เรียกOpenData/รันexporter/Gitpush/cronจริง
ยืนยันmissingSQLiteไม่สร้างใหม่, openingคงhash/flag/bytes, wrongpasswordปฏิเสธ, JSONatomic, APIไม่คืนรายการแพทย์อื่น และcorruptJSONไม่กลายเป็นempty
consistentSQLitebackup WALผ่านfixtureแล้ว (PDO fallback), HTTP login/cookie/forced-password/401/403/doctorisolation/logout ผ่านloopbackfixtureแล้ว; ยังไม่ทดสอบSQLite3 extension branchเพราะLocalไม่มีextensionนี้ และยังต้องตรวจproxy/TLS/loginจริง/sourcecron/pathsและข้อมูลครบจากVM
