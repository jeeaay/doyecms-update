PRAGMA foreign_keys = off;
BEGIN TRANSACTION;

-- 表：ay_model
CREATE TABLE IF NOT EXISTS "ay_model" (
  "id"           INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  "mcode"        TEXT(20) NOT NULL,
  "name"         TEXT(50) NOT NULL,
  "type"         TEXT(1) NOT NULL,
  "urlname"      TEXT(100) NOT NULL,
  "listtpl"      TEXT(50) NOT NULL,
  "contenttpl"   TEXT(50) NOT NULL,
  "status"       TEXT(1) NOT NULL,
  "issystem"     TEXT(1) NOT NULL,
  "create_user"  TEXT(30) NOT NULL,
  "update_user"  TEXT(30) NOT NULL,
  "create_time"  TEXT NOT NULL,
  "update_time"  TEXT NOT NULL
);

-- 索引：ay_model_mcode
CREATE UNIQUE INDEX IF NOT EXISTS "ay_model_mcode"
ON "ay_model" ("mcode" ASC);

COMMIT TRANSACTION;
PRAGMA foreign_keys = on;
