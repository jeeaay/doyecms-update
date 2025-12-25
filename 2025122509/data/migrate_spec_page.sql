PRAGMA foreign_keys = off;
BEGIN TRANSACTION;

-- 表：ay_spec_page
CREATE TABLE IF NOT EXISTS "ay_spec_page" (
  "id"             INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  "name"           TEXT(100) NOT NULL,
  "template_path"  TEXT(200) NOT NULL,
  "output_dir"     TEXT(200) NOT NULL,
  "base_language"  TEXT(20) NOT NULL,
  "status"         TEXT(1) NOT NULL,
  "create_user"    TEXT(30) NOT NULL,
  "update_user"    TEXT(30) NOT NULL,
  "create_time"    TEXT NOT NULL,
  "update_time"    TEXT NOT NULL
);

-- 表：ay_spec_translation
CREATE TABLE IF NOT EXISTS "ay_spec_translation" (
  "id"              INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  "spec_id"         INTEGER(10) NOT NULL,
  "target_language" TEXT(20) NOT NULL,
  "output_filename" TEXT(100) NOT NULL,
  "status"          TEXT(1) NOT NULL,
  "last_error"      TEXT(500) NOT NULL,
  "create_time"     TEXT NOT NULL,
  "update_time"     TEXT NOT NULL
);

-- 索引：ay_spec_page_name
CREATE INDEX IF NOT EXISTS "ay_spec_page_name"
ON "ay_spec_page" ("name" ASC);

-- 索引：ay_spec_page_base_language
CREATE INDEX IF NOT EXISTS "ay_spec_page_base_language"
ON "ay_spec_page" ("base_language" ASC);

-- 索引：ay_spec_translation_spec_id
CREATE INDEX IF NOT EXISTS "ay_spec_translation_spec_id"
ON "ay_spec_translation" ("spec_id" ASC);

-- 索引：ay_spec_translation_target_language
CREATE INDEX IF NOT EXISTS "ay_spec_translation_target_language"
ON "ay_spec_translation" ("target_language" ASC);

-- 索引：ay_spec_translation_unique
CREATE UNIQUE INDEX IF NOT EXISTS "ay_spec_translation_unique"
ON "ay_spec_translation" ("spec_id" ASC, "target_language" ASC);

-- 菜单与权限配置
INSERT INTO ay_menu (mcode, pcode, name, url, sorting, status, shortcut, ico, create_user, update_user, create_time, update_time) 
VALUES ('M133', 'M110', '专题管理', '/admin/SpecPage/index', 403, '1', '0', 'fa-file-code-o', 'admin', 'admin', datetime('now'), datetime('now'));

INSERT INTO ay_menu_action (mcode, action) VALUES ('M133', 'index');
INSERT INTO ay_menu_action (mcode, action) VALUES ('M133', 'add');
INSERT INTO ay_menu_action (mcode, action) VALUES ('M133', 'mod');
INSERT INTO ay_menu_action (mcode, action) VALUES ('M133', 'del');
INSERT INTO ay_menu_action (mcode, action) VALUES ('M133', 'generate');
INSERT INTO ay_menu_action (mcode, action) VALUES ('M133', 'translate');

INSERT INTO ay_role_level (rcode, level) VALUES ('R101', '/admin/SpecPage/index');
INSERT INTO ay_role_level (rcode, level) VALUES ('R101', '/admin/SpecPage/add');
INSERT INTO ay_role_level (rcode, level) VALUES ('R101', '/admin/SpecPage/mod');
INSERT INTO ay_role_level (rcode, level) VALUES ('R101', '/admin/SpecPage/del');
INSERT INTO ay_role_level (rcode, level) VALUES ('R101', '/admin/SpecPage/generate');
INSERT INTO ay_role_level (rcode, level) VALUES ('R101', '/admin/SpecPage/translate');

COMMIT TRANSACTION;
PRAGMA foreign_keys = on;
