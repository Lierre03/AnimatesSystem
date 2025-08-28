-- One-time fix: assign unique ids to rows with id=0 and enforce PK+AUTO_INCREMENT

START TRANSACTION;

-- Create helper table to generate sequence
CREATE TEMPORARY TABLE tmp_new_ids AS
SELECT u.rownum, (@seed := @seed + 1) AS new_id
FROM (
  SELECT (@rownum := @rownum + 1) AS rownum
  FROM information_schema.COLUMNS, (SELECT @rownum := 0) r
  LIMIT 100000
) gen, (SELECT @seed := (SELECT IFNULL(MAX(id),0) FROM users)) s;

-- Update any rows with id=0 assigning new unique ids
UPDATE users u
JOIN (
  SELECT t.new_id
  FROM tmp_new_ids t
) seq ON 1=1
SET u.id = seq.new_id
WHERE u.id = 0
LIMIT 10000;

-- Add PK and AUTO_INCREMENT if missing
ALTER TABLE `users`
  MODIFY `id` INT(11) NOT NULL,
  ADD PRIMARY KEY (`id`);

ALTER TABLE `users`
  MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;

COMMIT;


