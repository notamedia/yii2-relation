/* Replace this file with actual dump of your database */

BEGIN TRANSACTION;

CREATE TABLE news (
  id      INTEGER PRIMARY KEY ASC,
  name    VARCHAR(255),
  file_id INTEGER
);

CREATE TABLE files (
  id  INTEGER PRIMARY KEY ASC,
  src VARCHAR(500),
  entity_id INTEGER,
  entity_type VARCHAR(10)
);

CREATE TABLE news_files (
  news_id INTEGER,
  file_id INTEGER,
  entity_type VARCHAR(10),
  PRIMARY KEY (news_id, file_id)
);

CREATE TABLE news_files_via_table (
  news_id INTEGER,
  file_id INTEGER,
  PRIMARY KEY (news_id, file_id)
);

COMMIT;