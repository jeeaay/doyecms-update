PRAGMA foreign_keys = off;
BEGIN TRANSACTION;

-- 表：ay_spec_language
CREATE TABLE IF NOT EXISTS "ay_spec_language" (
  "id"          INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  "name"        TEXT(50) NOT NULL,
  "code"        TEXT(20) NOT NULL,
  "create_time" TEXT NOT NULL,
  "update_time" TEXT NOT NULL
);

-- 索引：ay_spec_language_code_unique
CREATE UNIQUE INDEX IF NOT EXISTS "ay_spec_language_code_unique"
ON "ay_spec_language" ("code" ASC);

-- 初始化数据（如已存在则跳过）
INSERT OR IGNORE INTO "ay_spec_language" ("id","name","code","create_time","update_time") VALUES
(1,'俄语','ru',datetime('now','localtime'),datetime('now','localtime')),
(2,'繁体中文','zh-TW',datetime('now','localtime'),datetime('now','localtime')),
(3,'英语','en',datetime('now','localtime'),datetime('now','localtime')),
(4,'西班牙语','es',datetime('now','localtime'),datetime('now','localtime')),
(5,'法语','fr',datetime('now','localtime'),datetime('now','localtime')),
(6,'阿拉伯语','ar',datetime('now','localtime'),datetime('now','localtime')),
(7,'葡萄牙语','pt',datetime('now','localtime'),datetime('now','localtime')),
(8,'日语','ja',datetime('now','localtime'),datetime('now','localtime')),
(9,'韩语','ko',datetime('now','localtime'),datetime('now','localtime')),
(10,'德语','de',datetime('now','localtime'),datetime('now','localtime'));

-- 菜单动作与权限（AJAX接口）
INSERT OR IGNORE INTO "ay_menu_action" ("mcode","action") VALUES ('M133','getLanguageList');
INSERT OR IGNORE INTO "ay_menu_action" ("mcode","action") VALUES ('M133','addLanguage');
INSERT OR IGNORE INTO "ay_menu_action" ("mcode","action") VALUES ('M133','delLanguage');

INSERT OR IGNORE INTO "ay_role_level" ("rcode","level") VALUES ('R101','/admin/SpecPage/getLanguageList');
INSERT OR IGNORE INTO "ay_role_level" ("rcode","level") VALUES ('R101','/admin/SpecPage/addLanguage');
INSERT OR IGNORE INTO "ay_role_level" ("rcode","level") VALUES ('R101','/admin/SpecPage/delLanguage');

COMMIT;
