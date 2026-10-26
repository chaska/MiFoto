-- 0.2.0: hourly upload limit per person.
CREATE TABLE IF NOT EXISTS "#__mifoto_limits" (
  "user_id" integer NOT NULL,
  "window_start" timestamp without time zone NOT NULL,
  "uploads" smallint NOT NULL DEFAULT 0,
  PRIMARY KEY ("user_id")
);
