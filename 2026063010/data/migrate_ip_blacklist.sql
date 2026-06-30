PRAGMA foreign_keys = off;
BEGIN TRANSACTION;

CREATE TABLE IF NOT EXISTS "ay_ip_blacklist" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  "ip" TEXT(64) NOT NULL,
  "source_type" TEXT(30) NOT NULL,
  "reason" TEXT(255) NOT NULL,
  "status" TEXT(1) NOT NULL DEFAULT '1',
  "expire_time" TEXT(30) NOT NULL,
  "hit_count" INTEGER NOT NULL DEFAULT 0,
  "last_uri" TEXT(255) NOT NULL,
  "remark" TEXT(255) NOT NULL,
  "create_user" TEXT(30) NOT NULL,
  "update_user" TEXT(30) NOT NULL,
  "create_time" TEXT NOT NULL,
  "update_time" TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS "ay_ip_blacklist_ip"
ON "ay_ip_blacklist" ("ip" ASC);

CREATE INDEX IF NOT EXISTS "ay_ip_blacklist_status"
ON "ay_ip_blacklist" ("status" ASC);

CREATE TABLE IF NOT EXISTS "ay_ip_blacklist_rule" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  "name" TEXT(100) NOT NULL,
  "match_type" TEXT(20) NOT NULL,
  "pattern" TEXT(255) NOT NULL,
  "risk_level" TEXT(20) NOT NULL,
  "status" TEXT(1) NOT NULL DEFAULT '1',
  "description" TEXT(255) NOT NULL,
  "create_user" TEXT(30) NOT NULL,
  "update_user" TEXT(30) NOT NULL,
  "create_time" TEXT NOT NULL,
  "update_time" TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS "ay_ip_blacklist_rule_status"
ON "ay_ip_blacklist_rule" ("status" ASC);

INSERT OR IGNORE INTO ay_menu (mcode, pcode, name, url, sorting, status, shortcut, ico, create_user, update_user, create_time, update_time)
VALUES ('M134', 'M110', 'IP黑名单', '/admin/IpBlacklist/index', 404, '1', '0', 'fa-ban', 'admin', 'admin', datetime('now'), datetime('now'));

INSERT OR IGNORE INTO ay_menu_action (mcode, action) VALUES ('M134', 'index');
INSERT OR IGNORE INTO ay_menu_action (mcode, action) VALUES ('M134', 'add');
INSERT OR IGNORE INTO ay_menu_action (mcode, action) VALUES ('M134', 'mod');
INSERT OR IGNORE INTO ay_menu_action (mcode, action) VALUES ('M134', 'del');
INSERT OR IGNORE INTO ay_menu_action (mcode, action) VALUES ('M134', 'addRule');
INSERT OR IGNORE INTO ay_menu_action (mcode, action) VALUES ('M134', 'modRule');
INSERT OR IGNORE INTO ay_menu_action (mcode, action) VALUES ('M134', 'delRule');
INSERT OR IGNORE INTO ay_menu_action (mcode, action) VALUES ('M134', 'saveSettings');

INSERT OR IGNORE INTO ay_role_level (rcode, level) VALUES ('R101', '/admin/IpBlacklist/index');
INSERT OR IGNORE INTO ay_role_level (rcode, level) VALUES ('R101', '/admin/IpBlacklist/add');
INSERT OR IGNORE INTO ay_role_level (rcode, level) VALUES ('R101', '/admin/IpBlacklist/mod');
INSERT OR IGNORE INTO ay_role_level (rcode, level) VALUES ('R101', '/admin/IpBlacklist/del');
INSERT OR IGNORE INTO ay_role_level (rcode, level) VALUES ('R101', '/admin/IpBlacklist/addRule');
INSERT OR IGNORE INTO ay_role_level (rcode, level) VALUES ('R101', '/admin/IpBlacklist/modRule');
INSERT OR IGNORE INTO ay_role_level (rcode, level) VALUES ('R101', '/admin/IpBlacklist/delRule');
INSERT OR IGNORE INTO ay_role_level (rcode, level) VALUES ('R101', '/admin/IpBlacklist/saveSettings');

COMMIT TRANSACTION;
PRAGMA foreign_keys = on;

