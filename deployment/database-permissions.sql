-- Owner executes on .246 ONLY; confirm .53 source .201; replace password locally.
-- Account/password replicate to .155 per owner; do not CREATE USER there.
-- Existing account/1396: STOP and inspect, never DROP blindly.
CREATE USER 'dashboard'@'172.16.16.201' IDENTIFIED BY 'CHANGE_ME_DASHBOARD_PASSWORD';
GRANT SELECT ON lkhos.oapp TO 'dashboard'@'172.16.16.201';
GRANT SELECT ON lkhos.patient TO 'dashboard'@'172.16.16.201';
GRANT SELECT ON lkhos.doctor TO 'dashboard'@'172.16.16.201';
GRANT SELECT ON lkhos.ovst TO 'dashboard'@'172.16.16.201';
GRANT SELECT ON lkhos.kskdepartment TO 'dashboard'@'172.16.16.201';
GRANT SELECT ON lkhos.spclty TO 'dashboard'@'172.16.16.201';
GRANT SELECT ON lkhos.dtmain TO 'dashboard'@'172.16.16.201';
SHOW GRANTS FOR 'dashboard'@'172.16.16.201';
-- Portal users/password hashes stay in the copied SQLite DB; not MySQL users.
-- No new .155 cron account needed in this .53 scope; .155 exporter remains unchanged.
