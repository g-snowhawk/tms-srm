<?php
$statement = <<<SQL
SELECT r.issue_date,r.receipt_number,r.subject,r.draft,
       r.due_date,r.receipt,r.unavailable,
       c.company,
       {$collected} AS collected
  FROM {$receipt} AS r
  JOIN table::receipt_to AS c
    ON r.client_id = c.id
 WHERE r.userkey = ? AND r.templatekey = ?{$between}
 ORDER BY r.draft DESC,collected ASC,r.issue_date DESC,r.receipt_number DESC
SQL;
