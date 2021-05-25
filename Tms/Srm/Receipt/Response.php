<?php
/**
 * This file is part of Tak-Me System.
 *
 * Copyright (c)2016 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Tms\Srm\Receipt;

use P5\Lang;
use P5\Mail;

/**
 * Template management request response class.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Response extends \Tms\Srm\Receipt
{
    const QUERY_STRING_KEY = 'receipt_search_condition';
    const SEARCH_OPTIONS_KEY = 'receipt_search_options';
    const RECEIPT_PAGE_KEY = 'receipt_page';

    private $rows_per_page = 10;

    /**
     * Object Constructor.
     */
    public function __construct()
    {
        $params = func_get_args();
        call_user_func_array('parent::__construct', $params);

        $this->view->bind(
            'header',
            ['title' => \P5\Lang::translate('HEADER_TITLE'), 'id' => 'template', 'class' => 'template']
        );
    }

    /**
     * Default view.
     */
    public function defaultView()
    {
        $this->checkPermission('srm.receipt.read');

        $template_path = 'srm/receipt/default.tpl';

        $receipt_name = null;
        if (!empty($this->request->param('t'))) {
            $receipt = $this->db->get('id,title', 'receipt_template', 'id = ?', [$this->request->param('t')]);
            if (!empty($receipt)) {
                $this->session->param('receipt_id', $receipt['id']);
                $receipt_name = $receipt['title'];

                // Reset current page number
                $this->session->param(self::RECEIPT_PAGE_KEY, 1);
            }
        }

        if (!empty($this->request->param('p'))) {
            $this->session->param(self::RECEIPT_PAGE_KEY, $this->request->param('p'));
        }

        $receipt_id = $this->session->param('receipt_id');
        if (empty($receipt_id)) {
            $template_path = 'srm/receipt/receipt_list.tpl';
            $receipts = $this->db->select(
                'id,title',
                'receipt_template',
                'WHERE userkey = ? ORDER by priority',
                [$this->uid]
            );
            $this->view->bind('receipts', $receipts);
        } else {
            $type_of_receipt = null;
            if (!empty($receipt_id)) {
                $receipt = $this->db->get('title,pdf_mapper,mail_template', 'receipt_template', 'id = ?', [$receipt_id]);
                $receipt_name = $receipt['title'];

                if (!empty($receipt['pdf_mapper'])) {
                    $pdf_mapper = simplexml_load_string($receipt['pdf_mapper']);
                    $type_of_receipt = (string)$pdf_mapper->attributes()->typeof;

                    $duplicate_to = $pdf_mapper->duplicateto;
                    $items = [];
                    foreach ($duplicate_to->item as $item) {
                        $items[] = [
                            'id' => (string)$item->attributes()->id,
                            'label' => (string)$item,
                        ];
                    }
                    if (!empty($items)) {
                        $this->view->bind('duplicateTo', $items);
                    }
                }

                if (!empty($receipt['mail_template'])) {
                    $this->view->bind('mail', 'enable');
                }
            }

            $this->view->bind('receiptName', $receipt_name);
            $this->view->bind('typeOf', $type_of_receipt);

            $collected = 'NULL';
            if ($type_of_receipt === 'bill') {
                $collected = "CASE WHEN r.unavailable = '1' THEN 3
                                   WHEN r.due_date IS NULL AND r.receipt IS NULL THEN 3
                                   WHEN r.receipt IS NOT NULL THEN 3
                                   WHEN r.receipt IS NULL AND DATE_FORMAT(r.due_date,'%Y-%m-%d 23:59:59') > NOW() THEN 2
                                   ELSE 1
                               END";
            }

            $options = [$this->uid, $receipt_id];

            $search_options = $this->session->param(self::SEARCH_OPTIONS_KEY) ?: ['andor' => 'AND'];
            $between = '';
            if (!empty($search_options['issue_date_start'])) {
                $between .= ' AND r.issue_date >= ?';
                $options[] = date('Y-m-d', strtotime($search_options['issue_date_start']));
            }
            if (!empty($search_options['issue_date_end'])) {
                $between .= ' AND r.issue_date <= ?';
                $options[] = date('Y-m-d', strtotime($search_options['issue_date_end']));
            }
            $andor = (!empty($search_options['andor'])) ? $search_options['andor'] : 'AND';

            $query_string = $this->getSearchCondition();
            $receipt = 'table::receipt';
            $filter = '';
            include(__DIR__ . '/statement.php');
            if (!empty($query_string)) {
                $keywords = explode(' ', $query_string);
                $filters = [];
                foreach ($keywords as $keyword) {
                    $filters[] = "%{$keyword}%";
                }
                $filter = implode(" {$andor} ", array_fill(0, count($filters), 'filter LIKE ?'));
                $options = array_merge($options, $filters);
                include(__DIR__ . '/statement_search.php');

                $this->view->bind('queryString', $query_string);
                $this->session->param(self::QUERY_STRING_KEY, $query_string);
            }

            // Pagenation
            $current_page = (int)$this->session->param(self::RECEIPT_PAGE_KEY) ?: 1;
            $rows_per_page = (empty($this->session->param('rows_per_page_receipt_list')))
                ? $this->rows_per_page
                : (int)$this->session->param('rows_per_page_receipt_list');
            $total_count = $this->db->recordCount($statement, $options);
            $offset_list = $rows_per_page * ($current_page - 1);
            $pager = clone $this->pager;
            $pager->init($total_count, $rows_per_page);
            $pager->setCurrentPage($current_page);
            $pager->setLinkFormat($this->app->systemURI().'?mode='.parent::DEFAULT_MODE.'&p=%d');
            $this->view->bind('pager', $pager);
            $statement .= " LIMIT $offset_list,$rows_per_page";

            $this->db->prepare($statement);
            $this->db->execute($options);

            $receipts = $this->db->fetchAll();
            $this->view->bind('receipts', $receipts);

            $this->setHtmlId('srm-receipt-default');

            $globals = $this->view->param();
            $form = $globals['form'];
            $form['confirm'] = \P5\Lang::translate('CONFIRM_DELETE_DATA');
            $this->view->bind('form', $form);
        }

        $this->view->render($template_path);
    }

    /**
     * Show edit form.
     */
    public function edit($add_page = null)
    {
        $id = $this->request->param('id');
        $check = (empty($id)) ? 'create' : 'update';
        $this->checkPermission('srm.receipt.'.$check);

        $receipt_name = null;
        $receipt_detail_lines = 0;
        $receipt_id = $this->session->param('receipt_id');
        if (!empty($receipt_id)) {
            $receipt = $this->db->get('title,line,pdf_mapper', 'receipt_template', 'id = ?', [$receipt_id]);
            $receipt_name = $receipt['title'];
            $receipt_detail_lines = (int)$receipt['line'];

            if (!empty($receipt['pdf_mapper'])) {
                $pdf_mapper = simplexml_load_string($receipt['pdf_mapper']);
                if ($pdf_mapper->extendedfield) {
                    $extended_fields = [];
                    foreach ($pdf_mapper->extendedfield->item as $item) {
                        $attrs = [];
                        foreach ($item->attributes() as $key => $value) {
                            $attrs[$key] = (string)$value;
                        }
                        $extended_fields[] = $attrs;
                    }
                    $this->view->bind('extendedFields', $extended_fields);
                }

                if ($pdf_mapper->detail) {
                    $line_count = (int)$pdf_mapper->detail->attributes()->rows;
                    if ($line_count > 0) {
                        $receipt_detail_lines = $line_count;
                    }

                    $middlepage_line_count = (int)$pdf_mapper->detail->attributes()->mrows;
                }

                if (isset($pdf_mapper->detail->attributes()->carryforward)) {
                    $this->view->bind(
                        'carryForwardTitle',
                        (string)$pdf_mapper->detail->attributes()->carryforward
                    );
                }

                $current_receipt_type = (string)$pdf_mapper->attributes()->typeof;
            }
        }
        if (empty($receipt_name)) {
            $this->defaultView();
        }

        $this->view->bind('receiptName', $receipt_name);

        $page_count = 0;

        if ($this->request->method === 'post') {
            $post = $this->request->POST();
            if ($add_page === 'addpage') {
                if (!isset($post['receipt_number'])) {
                    $post['receipt_number'] = $this->request->param('receipt_number');
                }
                unset(
                    $post['content'],
                    $post['price'],
                    $post['reduced_tax_rate'],
                    $post['quantity'],
                    $post['unit']
                );
            }
        } else {
            $post = [];
            if (preg_match("/^(\d{4}-\d{2}-\d{2}):(\d+)(:(\d+))?$/", $this->request->GET('id'), $match)) {
                $draft = empty($this->request->GET('draft')) ? '0' : $this->request->GET('draft');
                $page_number = (count($match) > 3) ? $match[4] : null;
                $post = (empty($this->request->GET('cp'))) 
                    ? $this->receiptDetail(
                        $receipt_id,
                        $match[1],
                        $match[2],
                        $page_number,
                        $draft,
                        ($add_page !== 'addpage')
                    )
                    : $this->cloneReceipt(
                        $receipt_id,
                        $match[1],
                        $match[2],
                        $page_number,
                        $draft,
                        $this->request->GET('cp')
                    );
            }

            if (empty($post['issue_date'])) {
                $post['issue_date'] = date('Y-m-d');
            }
        }

        if (isset($post['receipt_number'])) {
            if (!isset($draft)) {
                $draft = '1';
            }
            $statement = 'issue_date = ? AND receipt_number = ? AND userkey = ? AND templatekey = ? AND draft = ?';
            $replace = [$post['issue_date'], $post['receipt_number'], $this->uid, $receipt_id, $draft];
            $page_count = $this->db->max(
                'page_number',
                'receipt_detail',
                $statement,
                $replace
            );

            $post['note'] = $this->db->get(
                'content',
                'receipt_note',
                $statement,
                $replace
            );
        }

        // Set the TaxRate by issue_date
        foreach (['tax_rate','reduced_tax_rate'] as $kind) {
            $this->view->bind($kind, $this->getTaxRate($kind, $post['issue_date']));
        }

        $draft = $this->request->param('draft');
        if (empty($draft)) {
            $draft = '0';
        }


        if ($add_page === 'addpage') {
            $page_count = (int)$page_count + 1;
            $post['page_number'] = $page_count;
        }

        if (isset($post['page_number']) && $post['page_number'] > 1) {
            if (!empty($middlepage_line_count)) {
                $receipt_detail_lines = $middlepage_line_count;
            }

            $details = $this->db->select(
                'price,quantity,tax_rate',
                'receipt_detail',
                'WHERE issue_date = ? AND receipt_number = ? AND userkey = ? AND templatekey = ? AND page_number < ?',
                [$post['issue_date'], $post['receipt_number'], $this->uid, $receipt_id, $post['page_number']]
            );

            $carry_forward = 0;
            $carry_forward_tax = 0;
            foreach ((array)$details as $detail) {
                $carry_forward += $detail['price'] * $detail['quantity'];
                $carry_forward_tax = $carry_forward * $detail['tax_rate'];
            }
            $this->view->bind('carryForward', $carry_forward);
            $this->view->bind('carryForwardTax', $carry_forward_tax);
        }

        $this->view->bind('lineCount', $receipt_detail_lines);
        $this->view->bind('pageCount', $page_count);
        $this->view->bind('post', $post);

        $clients = $this->db->nsmGetDecendants(
            'children.id, children.company, children.representative',
            '(SELECT * FROM table::user WHERE id = ?)',
            '(SELECT * FROM table::user WHERE restriction = ?)',
            [$this->uid, $this->packageName()]
        );
        $this->view->bind('clients', $clients);

        $globals = $this->view->param();
        $form = $globals['form'];
        $form['confirm'] = \P5\Lang::translate('CONFIRM_SAVE_DATA');
        $this->view->bind('form', $form);

        if (isset($post["unavailable"]) && $post["unavailable"] === "1") {
            $this->appendHtmlClass('unavailable-receipt');
        }

        $this->view->bind('enable_preview', class_exists('Imagick'));

        $this->app->execPlugin('beforeRendering');

        $this->setHtmlId('receipt-edit');
        $this->view->render('srm/receipt/edit.tpl');
    }

    public function downloadPdf()
    {
        if (preg_match("/^(\d{4}-\d{2}-\d{2}):(\d+)(:(\d+))?$/", $this->request->GET('id'), $match)) {
            $issue_date = $match[1];
            $receipt_number = $match[2];
            $templatekey = $this->session->param('receipt_id');

            $pdf_mapper_source = $this->db->get('pdf_mapper', 'receipt_template', 'id = ? AND userkey = ?', [$templatekey, $this->uid]);
            if (empty($pdf_mapper_source)) {
                trigger_error('System Error', E_USER_ERROR);
            }

            $pdf_mapper = simplexml_load_string($pdf_mapper_source);

            $format = (string)$pdf_mapper->attributes()->savepath;
            $pdf_path = $this->pathToPdf($format, $issue_date, $receipt_number);

            if (!file_exists($pdf_path)) {
                trigger_error('PDF is not found', E_USER_ERROR);
            }

            $format = (string)$pdf_mapper->attributes()->download_name;
            $file_name = $this->pathToPdf($format, $issue_date, $receipt_number);

            header('Content-type: application/pdf');
            header("Content-Disposition: inline; filename=$file_name");
            readfile($pdf_path);
            exit;
        }
    }

    public function searchOptions(): void
    {
        $search_options = $this->session->param(self::SEARCH_OPTIONS_KEY) ?: ['andor' => 'AND'];
        $this->view->bind('post', $search_options);
        $response = $this->view->render('srm/receipt/search_options.tpl', true);
        $json = [
            'status' => 200,
            'response' => $response,
        ];
        header('Content-type: application/json; charset=utf-8');
        echo json_encode($json);
        exit;
    }

    private function getSearchCondition(): ?string
    {
        $query_string = mb_convert_kana($this->request->param('q'), 's');
        if (!$this->request->isset('q')) {
            $query_string = $this->session->param(self::QUERY_STRING_KEY);
        } elseif (empty($query_string)) {
            $this->session->clear(self::QUERY_STRING_KEY);
        }

        return $query_string;
    }

    public function mailer(): void
    {
        $json = ['status' => 0, 'headers' => []];
        if (preg_match("/^(\d{4}-\d{2}-\d{2}):(\d+)(:(\d+))?$/", $this->request->GET('id'), $match)) {
            $issue_date = $match[1];
            $receipt_number = $match[2];
            $templatekey = $this->session->param('receipt_id');

            $tmp = $this->db->get(
                'mail_template,pdf_mapper',
                'receipt_template',
                'id = ? AND userkey = ?',
                [$templatekey, $this->uid]
            );

            $mail_template = $tmp['mail_template'] ?? null;

            if (!empty($mail_template)) {
                $unit = $this->db->get(
                    'issue_date,receipt_number,client_id,subject,due_date',
                    'receipt',
                    'userkey = ? AND templatekey = ? AND issue_date = ? AND receipt_number = ?',
                    [$this->uid, $templatekey, $issue_date, $receipt_number]
                );

                $unit['total'] = $this->totalOfReceipt($issue_date, $receipt_number, $templatekey);

                if (!empty($tmp['pdf_mapper'])) {
                    $pdf_mapper = simplexml_load_string($tmp['pdf_mapper']);
                    $format = (string)$pdf_mapper->attributes()->savepath;
                    $pdf_path = $this->pathToPdf($format, $issue_date, $receipt_number);

                    if (file_exists($pdf_path)) {
                        $format = (string)$pdf_mapper->attributes()->download_name;
                        $file_name = $this->pathToPdf($format, $issue_date, $receipt_number);
                        $json['pdf'] = [
                            'path' => $pdf_path,
                            'basename' => basename($pdf_path),
                            'size' => filesize($pdf_path),
                            'attachment_name' => $file_name,
                        ];
                    }
                }

                $client = $this->db->get('company,fullname', 'receipt_to', 'id = ?', [$unit['client_id']]);
                $unit['company'] = $client['company'];
                $unit['fullname'] = $client['fullname'];

                $this->view->bind('unit', $unit);

                $template = $this->view->render($mail_template, true, true);

                if (preg_match('/^(((cc|from|reply-to|subject):.+?(\r\n|\r|\n)){1,})?(.+)$/is', $template, $match)) {
                    $template = trim($match[5]);
                    if (preg_match_all('/(cc|from|reply-to|subject):\s*(.+)/i', $match[1], $metas)) {
                        foreach ($metas[1] as $n => $value) {
                            $json['headers'][$value] = trim($metas[2][$n]);
                        }
                    }
                }

                $json['template'] = $template;
                $json['token'] = $this->session->param('ticket');
            }
        }

        header('Content-type: application/json; charset=utf-8');
        echo json_encode($json);
        exit;
    }

    /**
     * TODO:
     *  - separate by tax rate
     *  - support to multiple pages      
     */
    public function remindBilling(): void
    {
        if (php_sapi_name() !== 'cli') {
            trigger_error('Bad Requiest!', E_USER_ERROR);
        }

        $today = date('Y-m-d');
        $template = 'srm/receipt/remind_billing.tpl';
        $records = $this->db->select(
            '*',
            'receipt',
            'WHERE billing_date = ?'.
            'ORDER BY userkey, issue_date, receipt_number',
            [$today]
        );

        $data = [];
        $bill = [];
        foreach ($records as $record) {
            $client_fields = ['company','division','fullname','zipcode','address1','address2'];
            $client = $record['client_id'];
            $uname = $this->db->get('uname', 'user', 'id = ?', [$record['userkey']]);
            $this->resetUserByCli($uname);

            list($id, $mapper) = $this->receiptIdFromType('bill', true);
            $subject = $mapper->autoissue->subject ?? Lang::translate('AUTOISSUE_BILL_SUBJECT');
            $format = $mapper->autoissue->contentform ?? '%s (%tNo.%n)';
            $bill[$uname] = ['id' => $id, 'subject' => $subject];

            if (!isset($data[$uname])) {
                $data[$uname] = [];
            }
            if (!isset($data[$uname][$client])) {
                $data[$uname][$client] = [];
            }

            $total = $this->totalOfReceipt(
                $record['issue_date'],
                $record['receipt_number'],
                $record['templatekey'],
                '0',
                true
            );

            $record['title'] = $this->db->get('title', 'receipt_template', 'id = ?', [$record['templatekey']]);

            $data[$uname][$client][] = [
                'content' => $this->sprintf($format, $record),
                'price' => $total,
                'quantity' => 1,
                'issue_date' => $record['issue_date'],
                'receipt_number' => $record['receipt_number'],
                'templatekey' => $record['templatekey'],
            ];
        }

        $this->request->param('draft', '1');
        $this->request->param('note', '');

        $this->view->bind('billing_date', time());

        foreach($data as $uname => $clients) {
            $this->resetUserByCli($uname);
            $this->session->param('receipt_id', $bill[$uname]['id']);

            $draft_bills = [];
            foreach ($clients as $client => $list) {

                $to = $this->db->get('*', 'receipt_to', 'id = ?', [$client]);
                if (empty($to)) {
                    continue;
                }

                $client_name = $to['company'] ?? '';

                // Clear cached receipt_number
                $this->request->param('receipt_number', null, true);

                if (count($list) === 1) {

                    $page_number = 1;

                    if (false === $this->cloneReceipt(
                        $list[0]['templatekey'],
                        $list[0]['issue_date'],
                        $list[0]['receipt_number'],
                        $page_number,
                        $draft,
                        $bill[$uname]['id']
                    )) {
                        trigger_error('Database Error.');
                    } else {
                        $draft_bills[] = [
                            'number' => $this->clone_receipt_number,
                            'company' => $client_name,
                        ];
                    }
                    continue;
                }

                foreach($client_fields as $field) {
                    $this->request->param($field, ($to[$field] ?? ''));
                }

                $this->request->param('issue_date', $today);
                $this->request->param('subject', date($bill[$uname]['subject']));

                $line = 0;
                $content = [];
                $price = [];
                $quantity = [];

                foreach ($list as $attr) {
                    ++$line;
                    $content[$line] = $attr['content'];
                    $price[$line] = $attr['price'];
                    $quantity[$line] = $attr['quantity'];
                    $unit[$line] = '';
                }

                $this->request->param('content', $content);
                $this->request->param('price', $price);
                $this->request->param('quantity', $quantity);
                $this->request->param('unit', $unit);

                if (false === $this->save()) {
                    trigger_error('Database Error');
                } else {
                    $draft_bills[] = [
                        'number' => $this->clone_receipt_number,
                        'company' => $client_name,
                    ];
                }
            }

            $email = $this->userinfo['email'];
            if (!empty($email)) {
                $this->view->bind('draft_bills', $draft_bills);
                $eml = $this->view->render($template, true);

                list($header, $body) = preg_split('/(\r\n|\r|\n){2}/', $eml, 2);

                $mail = new Mail();
                $mail->from(Mail::noreplyAt());
                if (preg_match_all('/^([^:]+):\s*([^\r\n]+)/', $header, $match)) {
                    foreach($match[1] as $i => $key) {
                        $func = strtolower($key);
                        if (method_exists($mail, $func)) {
                            $mail->$func($match[2][$i]);
                        } else {
                            $mail->setHeader($key, $match[2][$i]);
                        }
                    }
                }
                $mail->to();
                $mail->to($email);
                $mail->message($body);

                $mail->send();
            }
        }

        exit;
    }

    private function sprintf($format, $data): string
    {
        return str_replace(
            ['%s','%t','%n'],
            [$data['subject'],$data['title'],$data['receipt_number']],
            $format
        );
    }
}
