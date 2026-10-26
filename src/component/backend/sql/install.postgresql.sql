CREATE TABLE IF NOT EXISTS "#__mifoto_photos" (
  "user_id" integer NOT NULL,
  "pkey" character(16) NOT NULL,
  "token" character(24) NOT NULL,
  "width" smallint NOT NULL DEFAULT 0,
  "height" smallint NOT NULL DEFAULT 0,
  "bytes" integer NOT NULL DEFAULT 0,
  "state" smallint NOT NULL DEFAULT 1,
  "created" timestamp without time zone NOT NULL,
  "modified" timestamp without time zone NOT NULL,
  PRIMARY KEY ("user_id")
);
CREATE UNIQUE INDEX IF NOT EXISTS "#__mifoto_photos_idx_pkey" ON "#__mifoto_photos" ("pkey");

CREATE TABLE IF NOT EXISTS "#__mifoto_limits" (
  "user_id" integer NOT NULL,
  "window_start" timestamp without time zone NOT NULL,
  "uploads" smallint NOT NULL DEFAULT 0,
  PRIMARY KEY ("user_id")
);
