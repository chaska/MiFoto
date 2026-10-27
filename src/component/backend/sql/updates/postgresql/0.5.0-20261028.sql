-- 0.5.0: proof that the person accepted (owner of the photo + data protection) and reports about photos.
CREATE TABLE IF NOT EXISTS "#__mifoto_consents" (
  "id" serial NOT NULL,
  "user_id" integer NOT NULL,
  "version" smallint NOT NULL DEFAULT 1,
  "token" character(24) NOT NULL DEFAULT '',
  "accepted" timestamp without time zone NOT NULL,
  "withdrawn" timestamp without time zone NULL DEFAULT NULL,
  PRIMARY KEY ("id")
);
CREATE INDEX IF NOT EXISTS "#__mifoto_consents_idx_user" ON "#__mifoto_consents" ("user_id");

CREATE TABLE IF NOT EXISTS "#__mifoto_reports" (
  "id" serial NOT NULL,
  "owner_id" integer NOT NULL,
  "reporter_id" integer NOT NULL,
  "reason" character varying(16) NOT NULL,
  "comment" character varying(500) NOT NULL DEFAULT '',
  "created" timestamp without time zone NOT NULL,
  "status" smallint NOT NULL DEFAULT 0,
  "handled_by" integer NOT NULL DEFAULT 0,
  "handled" timestamp without time zone NULL DEFAULT NULL,
  PRIMARY KEY ("id")
);
CREATE INDEX IF NOT EXISTS "#__mifoto_reports_idx_status" ON "#__mifoto_reports" ("status", "created");
CREATE INDEX IF NOT EXISTS "#__mifoto_reports_idx_owner" ON "#__mifoto_reports" ("owner_id");
CREATE INDEX IF NOT EXISTS "#__mifoto_reports_idx_reporter" ON "#__mifoto_reports" ("reporter_id", "created");
