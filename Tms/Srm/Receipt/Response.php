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
                'WHERE userkey = ?',
                [$this->uid]
            );
            $this->view->bind('receipts', $receipts);
        } else {
            $type_of_receipt = null;
            if (!empty($receipt_id)) {
                $receipt = $this->db->get('title,pdf_mapper', 'receipt_template', 'id = ?', [$receipt_id]);
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
            }

            $this->view->bind('receiptName', $receipt_name);
            $this->view->bind('typeOf', $type_of_receipt);

            $collected = 'NULL';
            if ($type_of_receipt === 'bill') {
                $collected = "CASE WHEN r.due_date IS NULL AND r.receipt IS NULL THEN 3
                                   WHEN r.receipt IS NOT NULL THEN 3
                                   WHEN r.receipt IS NULL AND r.due_date >= NOW() THEN 2
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

                $line_count = (int)$pdf_mapper->detail->attributes()->rows;
                $middlepage_line_count = (int)$pdf_mapper->detail->attributes()->mrows;

                if ($line_count > 0) {
                    $receipt_detail_lines = $line_count;
                }
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
                        $this->request->GET('cp')
                    );
            }

            if (empty($post['issue_date'])) {
                $post['issue_date'] = date('Y-m-d');
            }
        }

        // Set the TaxRate by issue_date
        foreach (['tax_rate','reduced_tax_rate'] as $kind) {
            $this->view->bind($kind, $this->getTaxRate($kind, $post['issue_date']));
        }

        $page_count = $this->db->max(
            'page_number',
            'receipt_detail',
            'issue_date = ? AND receipt_number = ? AND userkey = ? AND templatekey = ?',
            [$post['issue_date'], $post['receipt_number'], $this->uid, $receipt_id]
        );

        if ($add_page === 'addpage') {
            $post['page_number'] = (int)$page_count + 1;
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

        if ($post["unavailable"] === "1") {
            $this->appendHtmlClass('unavailable-receipt');
        }

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
}
