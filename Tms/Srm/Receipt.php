<?php
/**
 * This file is part of Tak-Me System.
 *
 * Copyright (c)2016-2017 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Tms\Srm;

/**
 * Category management class.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Receipt extends \Tms\Srm
{
    /**
     * Using common accessor methods
     */
    use \Tms\Accessor;

    private $current_receipt_type;
    private $total_price;

    /**
     * Object Constructer.
     */
    //public function __construct()
    //{
    //    call_user_func_array('parent::__construct', func_get_args());
    //}

    /**
     * Save the data.
     *
     * @return bool
     */
    protected function save() : bool
    {
        $post = $this->request->post();

        $privilege_type = (empty($post['id'])) ? 'create' : 'update';
        $this->checkPermission('srm.bank.'.$privilege_type);

        $verification_recipe = [
            ['vl_issue_date', 'issue_date', 'date'],
            ['vl_company', 'company', 'empty'],
        ];

        if (!$this->validate($verification_recipe)) {
            return false;
        }

        $this->db->begin();

        if (empty($post['receipt_number'])) {
            $post['receipt_number'] = $this->newReceiptNumber($post['issue_date']);
        }

        if (0 !== ($client_id = $this->saveClientData($post))
            && false !== $this->saveReceipt($client_id, $post)
            && false !== $this->saveReceiptDetails($post)
        ) {

            // Output the receipt as a PDF
            if ( empty($post['s1_draft']) ) {
                $key_array = [];
                $key_array[] = date('Y-m-d', strtotime($post['issue_date']));
                $key_array[] = $post['receipt_number'];
                $key_array[] = $this->uid;
                $key_array[] = $this->session->param('receipt_id');
                $created_pdf = $this->outputPdf($client_id, implode('-',$key_array));

                // Remove draft flag from current receipt
                if (false === $this->db->update
                    (
                        'receipt',
                        ['draft' => '0'],
                        "issue_date = ? AND receipt_number = ? AND userkey = ? AND templatekey = ?",
                        $key_array
                    )
                ) {
                    $created_pdf = false;
                }

                if (false !== $created_pdf && strtolower($this->app->cnf("srm:link_{$this->current_receipt_type}_to_transfer")) === "yes") {
                    if (false === $this->linkToTransfer($post)) {
                        $created_pdf = false;
                        // If failed link to transfer unlink PDF
                        // ...
                    }
                }
            }

            if (!isset($created_pdf) || false !== $created_pdf) {
                return $this->db->commit();
            }
        }

        trigger_error($this->db->error());
        $this->db->rollback();

        return false;
    }

    /**
     * Remove data.
     *
     * @return bool
     */
    protected function remove() : bool
    {
        return false;
    }

    private function saveClientData($post) : string
    {
        $table_name = 'receipt_to';
        $system_managed_columns = ['userkey','modified_at'];

        $client_id = md5($post['company'].$post['fullname'].$post['address1'].$post['address2']);

        $table_columns = $this->db->getFields($this->db->TABLE($table_name));
        $save = [
            'id' => $client_id,
            'userkey' => $this->uid
        ];
        $raw = [];
        foreach ($table_columns as $column) {
            if (in_array($column, $system_managed_columns)) {
                continue;
            }
            if (isset($post[$column])) {
                $save[$column] = $post[$column];
            }
        }

        if (false !== $result = $this->db->insert($table_name, $save, $raw)) {
            return $client_id;
        }

        $error_message = $this->db->error();
        if (preg_match("/^Duplicate entry '(.+)' for key 'PRIMARY'$/", $error_message, $match)) {
            return $client_id;
        }

        return 0;
    }

    private function saveReceiptDetails($post) : bool
    {
        $receipt_id = $this->session->param('receipt_id');
        $lines = $this->db->get('line', 'receipt_template', 'id = ? AND userkey = ?', [$receipt_id, $this->uid]);

        $page_number = $this->request->param('page_number');
        if (empty($page_number)) $page_number = 1;

        $table_name = 'receipt_detail';
        $save = [
            'issue_date' => $post['issue_date'],
            'receipt_number' => $post['receipt_number'],
            'userkey' => $this->uid,
            'templatekey' => $receipt_id,
            'page_number' => $page_number,
        ];

        for ($line_number = 1; $line_number <= $lines; $line_number++) {
            if (empty($post['content'][$line_number]) && empty($post['price'][$line_number])) {
                if (false === $this->db->delete(
                    $table_name,
                    'issue_date = ? AND receipt_number = ? AND userkey = ? AND templatekey = ? AND page_number = ? AND line_number = ?',
                    [$save['issue_date'], $save['receipt_number'], $save['userkey'], $save['templatekey'], $save['page_number'], $line_number]
                )) {
                    return false;
                }
                continue;
            }

            $tax_rate = $this->tax_rate;
            if (!empty($this->reduced_tax_rate)
                && isset($post['reduced_tax_rate'])
                && isset($post['reduced_tax_rate'][$line_number])
                && $post['reduced_tax_rate'][$line_number] === '1'
            ) {
                $tax_rate = $this->reduced_tax_rate;
            }
            $save['line_number'] = $line_number;
            $save['content'] = $post['content'][$line_number];
            $save['price'] = $post['price'][$line_number];
            $save['quantity'] = $post['quantity'][$line_number];
            $save['tax_rate'] = $tax_rate;
            $save['unit'] = $post['unit'][$line_number];

            // Save Zero as Null
            foreach (['content','price','quantity','unit'] as $key) {
                if (empty($save[$key])) {
                    $save[$key] = null;
                }
            }

            if (false !== $this->db->insert($table_name, $save)) {
                continue;
            }

            $error_message = $this->db->error();
            if (preg_match("/^Duplicate entry '(.+)' for key 'PRIMARY'$/", $error_message, $match)) {
                if (false === $this->db->update($table_name, $save, "CONCAT(issue_date,'-',receipt_number,'-',userkey,'-',templatekey,'-',page_number,'-',line_number) = ?", [$match[1]])) {
                    return false;
                }
            } else {
                return false;
            }
        }

        return true;
    }

    private function saveReceipt($client_id, $post) : bool
    {
        $table_name = 'receipt';
        $receipt_id = $this->session->param('receipt_id');

        $table_columns = $this->db->getFields($this->db->TABLE($table_name), true);
        $save = [
            'receipt_number' => $post['receipt_number'],
            'userkey' => $this->uid,
            'templatekey' => $receipt_id,
            'client_id' => $client_id,
        ];
        $system_managed_columns = array_keys($save);
        foreach ($table_columns as $column => $property) {
            if (in_array($column, $system_managed_columns)) {
                continue;
            }
            if (isset($post[$column])) {
                $save[$column] = $post[$column];
                if (empty($post[$column]) && strtoupper($property['Null']) === 'YES') {
                    $save[$column] = null;
                }
            }
        }

        if (false === $this->db->insert($table_name, $save)) {
            $error_message = $this->db->error();
            if (preg_match("/^Duplicate entry '(.+)' for key 'PRIMARY'$/", $error_message, $match)) {
                if (false === $this->db->update($table_name, $save, "CONCAT(issue_date,'-',receipt_number,'-',userkey,'-',templatekey) = ?", [$match[1]])) {
                    return false;
                }
            }
        }

        return true;
    }

    private function newReceiptNumber($issue_date) : ?int
    {
        $receipt_id = $this->session->param('receipt_id');
        if (empty($receipt_id)) {
            return null;
        }

        $timestamp = strtotime($issue_date);

        $statement = 'userkey = ? AND templatekey = ?';
        $options = [$this->uid, $receipt_id];

        if ($this->app->cnf('srm:allow_renumbering') === '1') {
            $statement .= ' AND issue_date <= ?';
            $options[] = date('Y-m-d', $timestamp);
        }

        $options_count = count($options);

        switch ($this->app->cnf('srm:reset_receipt_number_type')) {
            case 'fiscal_year' :
                $year = date('Y', $timestamp);
                $start_fiscal_year = $this->app->cnf('srm:start_fiscal_year');
                if (empty($start_fiscal_year)) {
                    $start_fiscal_year = '04-01';
                }

                // TODO: $start_fiscal_year should be checked with valid date formats
                // else {
                //     ...
                // }

                $issue_date = "$year-$start_fiscal_year";
                $options[] = date('Y-m-d', strtotime($issue_date));
                break;
            case 'year' :
                $options[] = date('Y-01-01', $timestamp);
                break;
            case 'month' :
                $options[] = date('Y-m-01', $timestamp);
                break;
        }
        if (count($options) > $options_count) {
            $statement .= ' AND issue_date >= ?';
        }

        $latest_number = $this->db->get(
            'receipt_number',
            'receipt',
            $statement . ' ORDER BY issue_date DESC LIMIT 1',
            $options
        );

        return (int)$latest_number + 1;
    }

    protected function outputPdf($client_id, $receiptkey) : bool
    {
        list($year, $month, $day, $receipt_number, $userkey, $templatekey) = explode('-', $receiptkey);
        $issue_date = implode('-', [$year, $month, $day]);
        $pdf_mapper_source = $this->db->get('pdf_mapper', 'receipt_template', 'id = ? AND userkey = ?', [$templatekey, $this->uid]);
        if (empty($pdf_mapper_source)) {
            // If PDF mapper is empty nothing to do.
            return true;
        }

        $pdf_mapper = simplexml_load_string($pdf_mapper_source);

        $this->current_receipt_type = (string)$pdf_mapper->attributes()->typeof;
        $line_count = (int)$pdf_mapper->detail->attributes()->rows;
        $middlepage_line_count = (int)$pdf_mapper->detail->attributes()->mrows;

        $header = $this->db->get('*', 'receipt', "CONCAT(issue_date,'-',receipt_number,'-',userkey,'-',templatekey) = ?", [$receiptkey]);
        $detail = $this->receiptDetailsForPdf($receiptkey, $line_count, $middlepage_line_count, $header);
        $client = $this->db->get('*', 'receipt_to', "id = ? AND userkey = ?", [$client_id, $this->uid]);
        $signature = $this->signatureForPdf($this->userinfo);

        if (property_exists($pdf_mapper, 'bank')) {
            $bank = $this->db->get('*', 'bank', 'account_number = ? AND userkey = ?', [$header['bank_id'], $this->uid]);
            $bank['branch'] .= " (" . $bank['branch_code'] . ")";
            $bank['label'] = $pdf_mapper->bank->firstpage->account_holder->attributes()->label;
        }

        $page_numbers = array_keys($detail);
        $page_count = max($page_numbers);

        $company = preg_replace('/\s+/', '', mb_convert_kana($client['company'], 's'));
        $fullname = preg_replace('/\s+/', '', mb_convert_kana($client['fullname'], 's'));
        if ($company === $fullname) {
            $client['fullname'] = null;
            $pdf_mapper->client->company->attributes()->suffix = $pdf_mapper->client->fullname->attributes()->suffix;
        }

        if ($pdf_mapper->client->zipcode->attributes()->format
            && preg_match('/^(\d{3})(\d{4})$/', $client['zipcode'], $match)
        ) {
            $client['zipcode'] = [$match[1], $match[2]];
        }

        $pdf = new \Tms\Pdf();

        $template_dir = $this->app->cnf('global:data_dir') . "/srm/$templatekey";
        $template_file = ($page_count > 1) ? 'multiple.pdf' : 'single.pdf';
        $pdf->loadTemplate("$template_dir/$template_file");

        for ($page_number = 1; $page_number <= $page_count; $page_number++) {
            if ($page_count > 1) {
                if ($pdf_mapper->header->midpage->next->attributes()->format) {
                    $header['next'] = [$page_number, $page_count];
                } else {
                    $header['next'] = sprintf("%d / %d", $page_number, $page_count);
                }
            }

            $import_page = 1;
            if ($page_number > 1) {
                $import_page = ($page_number === $page_count) ? 3 : 2;
            }

            $pdf->addPageFromTemplate($import_page);

            $node_list = get_object_vars($pdf_mapper);
            foreach ($node_list as $node_name => $child_node) {
                if (strpos($node_name, '@') === 0) {
                    continue;
                }
                $files = [];
                $map = self::pdfMapping($child_node, $page_number, $page_count, $files);
                if ($node_name === 'detail') {
                    $y = $child_node->attributes()->start;
                    if ($page_number > 1 && $middlepage_line_count !== $line_count) {
                        $y -= ($middlepage_line_count - $line_count) * $child_node->attributes()->lineheight;
                    }
                    $end = ($page_number == 1) ? $line_count : $middlepage_line_count;
                    $search_values = array_column($detail[$page_number], 'line_number');
                    for ($i = 1; $i <= $end; $i++) {
                        $y += $child_node->attributes()->lineheight;
                        if (false !== ($n = array_search($i, $search_values))) {
                            $pdf->draw($map, ${$node_name}[$page_number][$n], $y);
                        }
                    }
                    continue;
                } elseif ($node_name === 'signature') {
                    foreach ($map as $column) {
                        if ($column['type'] === "Image" || $column['type'] === "ImageEps") {
                            ${$node_name}[$column['name']] = "$template_dir/".$files[$column['name']];
                        }
                    }
                }

                if (isset(${$node_name})) {
                    $pdf->draw($map, ${$node_name});
                }
            }
        }

        $format = (string)$pdf_mapper->attributes()->savepath;
        $save_path = $this->pathToPdf($format, $issue_date, $receipt_number);

        $save_dir = dirname($save_path);
        if (!file_exists($save_dir)) {
            mkdir($save_dir, 0777, true);
        }

        if ($pdf_mapper->meta) {
            $format = (string)$pdf_mapper->meta->title;
            if (!empty($format)) {
                $title = sprintf($pdf_mapper->meta->title, $receipt_number);
                $title = date($title, strtotime($issue_date));
            }
            $meta = [
                'title' => $title,
                'subject' => (string)$pdf_mapper->meta->subject,
                'author' => (string)$pdf_mapper->meta->author,
            ];
            $pdf->setMetaData($meta);
        }

        $pdf->encrypt(['copy','modify'], '', bin2hex(random_bytes(10)), 1);

        return $pdf->saveFileAs($save_path);
    }

    protected static function pathToPdf($format, $issue_date, $receipt_number)
    {
        if (preg_match_all('/%([Ymd])/', $format, $match)) {
            $timestamp = strtotime($issue_date);
            foreach ($match[1] as $pattern) {
                $replace = date($pattern, $timestamp);
                $format = str_replace("%$pattern", $replace, $format);
            }
        }

        if (preg_match_all('/%(#+)/', $format, $match)) {
            foreach ($match[1] as $pattern) {
                $n = strlen($pattern);
                $regex = '/%#{'.$n.'}([^#]*)/';
                $zero_fill = ($n > 1) ? "%0{$n}d" : "%d";
                $format = preg_replace($regex, "$zero_fill$1", $format, 1);
                $format = sprintf($format, $receipt_number);
            }
        }

        return $format;
    }

    private function pdfMapping($node_list, $page, $count, &$files)
    {
        $return_value = [];
        foreach ($node_list as $node_name => $child_node) {
            if (!is_object($child_node)) {
                continue;
            }
            if ($node_name === 'firstpage') {
                if ($page === 1) {
                    $return_value = array_merge($return_value, self::pdfMapping(get_object_vars($child_node), $page, $count, $files));
                }
                continue;
            }
            if ($node_name === 'midpage') {
                if ($page > 1 && $page < $count) {
                    $return_value = array_merge($return_value, self::pdfMapping(get_object_vars($child_node), $page, $count, $files));
                }
                continue;
            }
            if ($node_name === 'lastpage') {
                if ($page === (int)$child_node->attributes()->more) {
                    continue;
                }
                if ($page === $count) {
                    $return_value = array_merge($return_value, self::pdfMapping(get_object_vars($child_node), $page, $count, $files));
                }
                continue;
            }
            $files[$node_name] = (string)$child_node->attributes()->file;
            $value = [
                'name' => (string)$node_name,
                'prefix' => (string)$child_node->attributes()->prefix,
                'suffix' => (string)$child_node->attributes()->suffix,
                'font' => (string)$child_node->attributes()->font,
                'align' => (string)$child_node->attributes()->align,
                'pitch' => (float)$child_node->attributes()->pitch,
                'style' => (string)$child_node->attributes()->style,
                'size' => (string)$child_node->attributes()->size,
                'color' => \Tms\Pdf::mapAttrToArray((string)$child_node->attributes()->color),
                'x' => (float)$child_node->attributes()->x,
                'y' => (float)$child_node->attributes()->y,
                'width' => (float)$child_node->attributes()->width,
                'height' => (float)$child_node->attributes()->height,
                'type' => (string)$child_node->attributes()->type,
                'flg' => \Tms\Pdf::mapAttrToBoolean((string)$child_node->attributes()->flg),
                'poly' => \Tms\Pdf::mapAttrToArray((string)$child_node->attributes()->poly)
            ];

            if ($child_node->attributes()->format) {
                $value['format'] = (string)$child_node->attributes()->format;
            }

            if ($child_node->attributes()->dateformat) {
                $value['dateformat'] = (string)$child_node->attributes()->dateformat;
            }

            $return_value[] = $value;
        }

        return $return_value;
    }

    private function receiptDetailsForPdf($receiptkey, $line_count, $middlepage_line_count, &$header)
    {
        $detail = $this->db->select('*', 'receipt_detail', "WHERE CONCAT(issue_date,'-',receipt_number,'-',userkey,'-',templatekey) = ? ORDER BY `page_number`,`line_number`", [$receiptkey]);

        $return_value = [];

        // Culcuration totals
        $subtotal = 0;
        $tax = 0;
        foreach ($detail as $unit) {
            $line_number = $unit['line_number'];
            if ($line_number > $line_count && ($line_number - $line_count) % $middlepage_line_count === 1) {
                $unit['sum'] = $subtotal;
            } else {
                $sum = (float)$unit['price'] * (int)$unit['quantity'];
                $subtotal += $sum;
                $tax += $sum * (float)$unit['tax_rate'];
                $unit['sum'] = (is_null($unit['price']) && empty($sum)) ? NULL : $sum;
            }

            if (!isset($return_value[$unit['page_number']])) {
                $return_value[$unit['page_number']] = [];
            }
            $return_value[$unit['page_number']][] = $unit;
        }

        $header['subtotal'] = $subtotal;
        $header['tax'] = $tax;
        $header['total'] = $subtotal + $tax + (int)$header['additional_1_price'] + (int)$header['additional_2_price'];

        $this->total_price = $header['total'];

        return $return_value;
    }

    private function signatureForPdf($userinfo)
    {
        return [
            'company' => $userinfo['company'],
            'division' => $userinfo['division'],
            'fullname' => $userinfo['fullname'],
            'zipcode' => $userinfo['zip'],
            'address1' => $userinfo['state'].$userinfo['city'].$userinfo['town'].$userinfo['address1'],
            'address2' => $userinfo['address2'],
            'tel' => $userinfo['tel'],
            'fax' => $userinfo['fax'],
            'email' => $userinfo['email'],
        ];
    }


    protected function savedMetaData($column_name)
    {
        $templatekey = $this->session->param('receipt_id');
        $fetch = $this->db->select(
            "$column_name AS opt", 'receipt',
            "WHERE userkey = ? AND templatekey = ? GROUP BY `$column_name` ORDER BY `$column_name`",
            [$this->uid, $templatekey]
        );

        foreach ($fetch as $unit) {
            if (empty($unit['opt'])) continue;
            $list[] = ['label' => $unit['opt'], 'value' => $unit['opt']];
        }

        return $list;
    }

    private function linkToTransfer($post)
    {
        switch ($this->current_receipt_type) {
            case 'bill':

                $note = 'bill:' . $post['receipt_number'];
                $category = 'T';

                $page_number = $this->db->get('page_number', \Tms\Oas\Transfer::TRANSFER_TABLE, 
                    'userkey = ? AND issue_date = ? AND category = ? AND note = ?',
                    [$this->uid, $post['issue_date'], $category, $note]
                );

                if (!empty($page_number)) {
                    $this->request->param('page_number', $page_number);
                }
                $this->request->param('category', $category);
                $this->request->param('amount_left', ['1' => $this->total_price, '2' => null]);
                $this->request->param('item_code_left', ['1' => '1131', '2' => null]);
                $this->request->param('summary', ['1' => $post['subject'], '2' => $post['company']]);
                $this->request->param('item_code_right', ['1' => '8111', '2' => null]);
                $this->request->param('amount_right', ['1' => $this->total_price, '2' => null]);
                $this->request->param('note', ['1' => $note, '2' => $note]);

                $transfer = new \Tms\Oas\Transfer\Relational($this, $this->app);
                $result = $transfer->save();

                if (!empty($page_number)) {
                    $this->request->param('page_number', null);
                }
                $this->request->param('category', null);
                $this->request->param('amount_left', null);
                $this->request->param('item_code_left', null);
                $this->request->param('summary', null);
                $this->request->param('item_code_right', null);
                $this->request->param('amount_right', null);
                $this->request->param('note', null);

                return $result;
        }
    }
}
