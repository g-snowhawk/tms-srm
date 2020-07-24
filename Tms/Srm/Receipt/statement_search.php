<?php
$notes = <<<SQL
SELECT rc.*,nt.note
  FROM table::receipt rc
  LEFT JOIN (SELECT MIN(issue_date) AS issue_date,
               MIN(receipt_number) AS receipt_number,
               MIN(userkey) AS userkey,
               MIN(templatekey) AS templatekey,
               GROUP_CONCAT(content) AS note 
          FROM table::receipt_note
         GROUP BY issue_date,receipt_number,userkey,templatekey
       ) nt
    ON rc.issue_date = nt.issue_date
   AND rc.receipt_number = nt.receipt_number
   AND rc.userkey = nt.userkey
   AND rc.templatekey = nt.templatekey
SQL;

$receipt = <<<SQL
SELECT r.*,rt.content
  FROM ({$notes}) r
  JOIN (SELECT MIN(issue_date) AS issue_date,
               MIN(receipt_number) AS receipt_number,
               MIN(userkey) AS userkey,
               MIN(templatekey) AS templatekey,
               GROUP_CONCAT(content) AS content
          FROM table::receipt_detail
         GROUP BY issue_date,receipt_number,userkey,templatekey
       ) rt
    ON r.issue_date = rt.issue_date
   AND r.receipt_number = rt.receipt_number
   AND r.userkey = rt.userkey
   AND r.templatekey = rt.templatekey
SQL;

$statement = <<<SQL
SELECT issue_date,receipt_number,subject,draft,
       due_date,receipt,unavailable,
       company, collected
  FROM (SELECT r.issue_date,r.receipt_number,r.subject,r.draft,
               r.due_date,r.receipt,r.unavailable,
               c.company,
               CONCAT(r.subject,IFNULL(r.note,''),r.content,c.company) AS filter,
               {$collected} AS collected
          FROM ({$receipt}) AS r
          JOIN table::receipt_to AS c
            ON r.client_id = c.id
         WHERE r.userkey = ? AND r.templatekey = ?{$between}
       ) AS rc
 WHERE {$filter}
 ORDER BY draft DESC,collected ASC,issue_date DESC,receipt_number DESC
SQL;
