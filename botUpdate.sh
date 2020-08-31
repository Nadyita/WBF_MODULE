#!/bin/bash
DB="$HOME/Games/aoia/drive_c/users/mark/Local Settings/Application Data/Hallucina Software/AOIAPlus/aoitems.db"
{
  sqlite3 "$DB" "
    PRAGMA case_sensitive_like=ON;
    select aoid from tblAOItems where name like '% of the Xan';
    select aoid from tblAOItems where name like 'Lord of %' or name like 'Lady of %';
    select distinct r1.aoid from tblAOItemRequirements r1 LEFT JOIN tblAOItemRequirements r2 ON (r1.aoid=r2.aoid AND r2.type=8 AND r2.attribute=60 AND r2.value < 14) WHERE r1.type=8 AND r1.attribute=60 AND r1.value >= 14 AND r2.aoid IS NULL;
    select distinct r1.aoid from tblAOItemRequirements r1 LEFT JOIN tblAOItemRequirements r2 ON (r1.aoid=r2.aoid AND r2.type=6 AND r2.attribute=60 AND r2.value < 14) WHERE r1.type=6 AND r1.attribute=60 AND r1.value >= 14 AND r2.aoid IS NULL;

  ;" \
  | \
  sort -V | uniq | \
  sed -e 's/\(.*\)/INSERT INTO item_paid_only (item_id) VALUES (\1);/g'
}
