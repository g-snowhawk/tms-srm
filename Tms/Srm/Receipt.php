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

use DateTime;

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

    protected $clone_receipt_number;

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

        if (!empty($post['s1_submit'])) {
            $post['draft'] = '0';
        }
        $faircopy = $post['faircopy'] ?? '0';

        if (empty($post['receipt_number'])) {
            $post['receipt_number'] = $this->newReceiptNumber($post['issue_date'], $post['draft']);
        } elseif (!empty($post['s1_submit']) && $faircopy === '0') {
            $post['new_receipt_number'] = $this->newReceiptNumber($post['issue_date'], $post['draft']);
        }

        if (0 !== ($client_id = $this->saveClientData($post))
            && false !== $this->saveReceipt($client_id, $post)
            && false !== $this->saveReceiptDetails($post)
            && false !== $this->saveReceiptNote($post)
        ) {
            if (isset($post['new_receipt_number'])) {
                $post['receipt_number'] = $post['new_receipt_number'];
            }

            if (isset($post['s1_addpage'])) {
                $this->request->param('receipt_number', $post['receipt_number']);
            }

            $key_array = [];
            $key_array[] = date('Y-m-d', strtotime($post['issue_date']));
            $key_array[] = $post['receipt_number'];
            $key_array[] = $this->uid;
            $key_array[] = $this->session->param('receipt_id');

            // Output the receipt as a PDF
            $after_follow = true;
            if (!empty($post['s1_submit']) && $post['draft'] === '0') {
                if (false === $this->outputPdf($client_id, implode('-',array_merge($key_array,['0'])))
                    || false === $this->removeDraftFlag($key_array /*, $old_receipt_number */)
                ) {
                    $after_follow = false;
                }
            } elseif (!empty($post['receipt'])) {
                $pdf_mapper_source = $this->db->get('pdf_mapper', 'receipt_template', 'id = ? AND userkey = ?', [$this->session->param('receipt_id'), $this->uid]);
                if (!empty($pdf_mapper_source)) {
                    $pdf_mapper = simplexml_load_string($pdf_mapper_source);
                    $this->current_receipt_type = (string)$pdf_mapper->attributes()->typeof;
                }

                $key_array[] = '0';
                $this->total_price = $this->calcurateTotals(implode('-',$key_array));
            }

            if (false === $this->app->execPlugin('afterSaveReceipt', $post, $this->current_receipt_type, $this->total_price)) {
                $after_follow = false;
            }

            if (false !== $after_follow) {
                $this->clone_receipt_number = $post['receipt_number'];
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
        $templatekey = $this->session->param('receipt_id');
        $statement = 'userkey = ? AND templatekey = ? AND issue_date = ? AND receipt_number = ? AND draft = ?';
        $options = [$this->uid, $templatekey, $this->request->param('issue_date'), $this->request->param('receipt_number'), '1'];

        $this->db->begin();

        if (false === $this->db->delete('receipt', $statement, $options)) {
            $this->db->rollback();
            return false;
        }

        return $this->db->commit();
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
        if (preg_match("/^Duplicate entry '(.+)' for key '.*\.?PRIMARY'$/", $error_message, $match)) {
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
        $receipt_number = $post['new_receipt_number'] ?? $post['receipt_number'];

        $save = [
            'issue_date' => $post['issue_date'],
            'receipt_number' => $receipt_number,
            'userkey' => $this->uid,
            'templatekey' => $receipt_id,
            'page_number' => $page_number,
            'draft' => $post['draft'],
        ];

        for ($line_number = 1; $line_number <= $lines; $line_number++) {
            if (empty($post['content'][$line_number]) && empty($post['price'][$line_number])) {
                if (false === $this->db->delete(
                    $table_name,
                    'issue_date = ? AND receipt_number = ? AND userkey = ? AND templatekey = ? AND page_number = ? AND line_number = ? AND draft = ?',
                    [$save['issue_date'], $save['receipt_number'], $save['userkey'], $save['templatekey'], $save['page_number'], $line_number, $save['draft']]
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

            $statement = 'issue_date = ? AND receipt_number = ? AND userkey = ? AND templatekey = ? AND draft = ? AND page_number = ? AND line_number = ?';
            $replace = [
                $post['issue_date'],
                $save['receipt_number'],
                $this->uid,
                $save['templatekey'],
                $save['draft'],
                $save['page_number'],
                $save['line_number'],
            ];
            if ($this->db->exists($table_name, $statement, $replace)) {
                if (false === $this->db->update($table_name, $save, $statement, $replace)) {
                    return false;
                }
            } else {
                if (false === $this->db->insert($table_name, $save)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function saveReceiptNote($post) : bool
    {
        $receipt_id = $this->session->param('receipt_id');

        $page_number = $this->request->param('page_number');
        if (empty($page_number)) $page_number = 1;

        $table_name = 'receipt_note';
        $receipt_number = $post['new_receipt_number'] ?? $post['receipt_number'];

        $save = [
            'issue_date' => $post['issue_date'],
            'receipt_number' => $receipt_number,
            'userkey' => $this->uid,
            'templatekey' => $receipt_id,
            'draft' => $post['draft'],
            'page_number' => $page_number,
            'content' => $post['note'],
        ];

        $statement = 'issue_date = ? AND receipt_number = ? AND userkey = ? AND templatekey = ? AND draft = ? AND page_number = ?';
        $replace = [
            $post['issue_date'],
            $save['receipt_number'],
            $this->uid,
            $save['templatekey'],
            $save['draft'],
            $save['page_number'],
        ];
        if ($this->db->exists($table_name, $statement, $replace)) {
            if (empty($post['note'])) {
                return $this->db->delete($table_name, $statement, $replace);
            }

            if (false === $this->db->update($table_name, $save, $statement, $replace)) {
                return false;
            }
        } else {
            if (!empty($post['note']) && false === $this->db->insert($table_name, $save)) {
                return false;
            }
        }
        
        return true;
    }

    private function saveReceipt($client_id, $post) : bool
    {
        $table_name = 'receipt';
        $receipt_id = $this->session->param('receipt_id');
        $receipt_number = $post['new_receipt_number'] ?? $post['receipt_number'];

        $save = [
            'receipt_number' => $receipt_number,
            'userkey' => $this->uid,
            'templatekey' => $receipt_id,
            'client_id' => $client_id,
        ];

        $table_columns = $this->db->getFields($this->db->TABLE($table_name), true);
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

        $draft = $post['faircopy'] ?? '1';

        $statement = 'issue_date = ? AND receipt_number = ? AND userkey = ? AND templatekey = ? AND draft = ?';
        $replace = [$post['issue_date'],$post['receipt_number'],$this->uid,$save['templatekey'],$draft];

        if ($this->db->exists($table_name, $statement, $replace)) {
            if (false === $this->db->update($table_name, $save, $statement, $replace)) {
                return false;
            }
        } elseif (false === $this->db->insert($table_name, $save)) {
            return false;
        }

        return true;
    }

    private function newReceiptNumber($issue_date, $draft, $destination = null) : ?int
    {
        $receipt_id = (!empty($destination)) ? $destination : $this->session->param('receipt_id');
        if (empty($receipt_id)) {
            return null;
        }

        $timestamp = strtotime($issue_date);

        $statement = 'userkey = ? AND templatekey = ? AND draft = ?';
        $options = [$this->uid, $receipt_id, $draft];

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

    protected function outputPdf($client_id, $receiptkey, $preview = null) : bool
    {
        $encrypt_to = ['copy','modify'];
        if (is_null($preview)) {
            list($year, $month, $day, $receipt_number, $userkey, $templatekey, $draft) = explode('-', $receiptkey);
            $issue_date = implode('-', [$year, $month, $day]);
        } else {
            $issue_date = date('Y-m-d', strtotime($preview['issue_date']));
            $templatekey = $preview['templatekey'];
            $draft = '1';
            $encrypt_to[] = 'print';
        }

        $pdf_mapper_source = $this->db->get('pdf_mapper', 'receipt_template', 'id = ? AND userkey = ?', [$templatekey, $this->uid]);
        if (empty($pdf_mapper_source)) {
            // If PDF mapper is empty nothing to do.
            return true;
        }

        $pdf_mapper = simplexml_load_string($pdf_mapper_source);

        $this->current_receipt_type = (string)$pdf_mapper->attributes()->typeof;
        if ($this->request->param('create-pdf') === 'none') {
            $this->total_price = $this->calcurateTotals($receiptkey);
            return true;
        }

        $line_count = (int)$pdf_mapper->detail->attributes()->rows;
        $middlepage_line_count = (int)$pdf_mapper->detail->attributes()->mrows;
        $carry_forward = (isset($pdf_mapper->detail->attributes()->carryforward))
            ? (string)$pdf_mapper->detail->attributes()->carryforward : null;

        if (is_null($preview)) {
            $header = $this->db->get('*', 'receipt', "CONCAT(issue_date,'-',receipt_number,'-',userkey,'-',templatekey,'-',draft) = ?", [$receiptkey]);
            $header['note'] = $this->db->get('content', 'receipt_note', "CONCAT(issue_date,'-',receipt_number,'-',userkey,'-',templatekey,'-',draft) = ?", [$receiptkey]);
            $detail = $this->receiptDetailsForPdf($receiptkey, $line_count, $middlepage_line_count, $carry_forward, $header);
            $client = $this->db->get('*', 'receipt_to', "id = ? AND userkey = ?", [$client_id, $this->uid]);
        } else {
            $header = [];
            $detail = [];
            $client = [];
            $fields = $this->db->getFields('receipt');
            foreach($fields as $field) {
                $header[$field] = $preview[$field] ?? '';
            }
            $fields = $this->db->getFields('receipt_to');
            foreach($fields as $field) {
                $client[$field] = $preview[$field] ?? '';
            }

            $tax_rates = ['tax_rate' => 0, 'reduced_tax_rate' => 0];
            foreach ($tax_rates as $kind => $rate) {
                $tax_rates[$kind] = $this->getTaxRate($kind, $issue_date);
            }

            $page_number = $preview['page_number'] ?? 1;
            $subtotal = 0;
            $tax = 0;
            foreach($preview['content'] as $n => $value) {
                $price = $preview['price'][$n] ?? '';
                $quantity = $preview['quantity'][$n] ?? '';
                $kind = (($preview['reduced_tax_rate'][$n] ?? '') === '1')
                    ? 'reduced_tax_rate' : 'tax_rate';
                $tax_rate = $tax_rates[$kind];
                $sum = (float)$price * (float)$quantity;
                $subtotal += $sum;
                $tax += $sum * (float)$tax_rate;
                $detail[$page_number][] = [
                    'page_number' => $page_number,
                    'line_number' => $n,
                    'content' => $value,
                    'price' => $preview['price'][$n] ?? '',
                    'quantity' => $preview['quantity'][$n] ?? '',
                    'unit' => $preview['unit'][$n] ?? '',
                    'sum' => (($sum > 0) ? $sum : ''),
                ];
            }
            $header['subtotal'] = $subtotal;
            $header['tax'] = $tax;
            $header['total'] = $subtotal + $tax + (int)$header['additional_1_price'] + (int)$header['additional_2_price'];
            $header['note'] = $preview['note'] ?? '';
        }

        $signature = $this->signatureForPdf($this->userinfo);

        if (property_exists($pdf_mapper, 'bank')) {
            $bank = $this->db->get('*', 'bank', 'account_number = ? AND userkey = ?', [$header['bank_id'], $this->uid]);
            $bank['branch'] .= " (" . $bank['branch_code'] . ")";
            $bank['label'] = $pdf_mapper->bank->firstpage->account_holder->attributes()->label;
        }

        if (!empty($header['note'])) {
            $header['note'] = nl2br(preg_replace("/(\r\n|\r)/", "\n", $header['note']));
        }

        $timestamp = strtotime($header['issue_date']);
        $myname = [$signature['division'],$signature['fullname']];
        $footer = [
            'year' => date('Y', $timestamp),
            'month' => date('n', $timestamp),
            'day' => date('j', $timestamp),
            'zipcode' => $signature['zipcode'],
            'address1' => $signature['address1'],
            'address2' => $signature['address2'],
            'company' => $signature['company'],
            'fullname' => $signature['fullname'],
            'name' => implode('  ', $myname),
        ];


        $page_numbers = array_keys($detail);
        $page_count = max($page_numbers);

        // TODO: considered a better practice
        $this->honorificTitle($client, $pdf_mapper);
        $this->formatZipcode($client, $pdf_mapper);

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

            if (!is_null($preview)) {
                $watermark = __DIR__.'/templates/srm/receipt/watermark_draft.png';
                $size = getimagesize($watermark);
                $hd = $pdf->handler();
                $doc_width = $hd->getPageWidth();
                $doc_height = $hd->getPageHeight();
                $w = round($doc_width * 0.7);
                $h = round($w * $size[1] / $size[0]);
                $x = round(($doc_width - $w) * 0.5);
                $y = round(($doc_height - $h) * 0.5);
                $hd->Image($watermark, $x, $y, $w);
            }

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
                        if (isset($child_node->attributes()->mstart)) {
                            $y = (float)$child_node->attributes()->mstart;
                        } else {
                            $y -= ($middlepage_line_count - $line_count) * $child_node->attributes()->lineheight;
                        }
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

        if (!is_null($preview)) {

            // TODO: Fix error handling to TCPDF
            restore_error_handler();

            if (class_exists('Imagick')) {
                $tmpfile = tempnam(sys_get_temp_dir(), 'PDF');
                $image = "{$tmpfile}.png";
                $pdf->saveFileAs($tmpfile);
                $density = 144;
                $convert = new \Imagick();
                $convert->setResolution($density,$density);
                $convert->readImage($tmpfile);
                $convert->setIteratorIndex(0);
                $convert->writeImage($image);
                header('Content-Type: image/png');
                readfile($image);
                unlink($tmpfile);
                unlink($image);
            } else {
                $pdf->output('draft');
            }
            exit;
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

        $pdf->encrypt($encrypt_to, '', bin2hex(random_bytes(10)), 1);

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

            if ($child_node->attributes()->disableif) {
                $condition = (string)$child_node->attributes()->disableif;
                if (preg_match('/^(post|get)\.(.*)\s+(eq|ne)\s+(.*)$/', $condition, $match)) {
                    list ($all, $method, $item, $comparison_operator, $value) = $match;
                    switch ($comparison_operator) {
                        case 'eq':
                            if ($this->request->$method($item) === $value) {
                                continue 2;
                            }
                            break;
                        case 'ne':
                            if ($this->request->$method($item) !== $value) {
                                continue 2;
                            }
                            break;
                    }
                }
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

                $single_page_attributes = null;
                if (!empty($child_node->SinglePageAttributes)) {
                    $single_page_attributes = get_object_vars($child_node->SinglePageAttributes);
                }

                if ($page === $count) {
                    $single = [];
                    if ($page === 1 && !is_null($single_page_attributes)) {
                        foreach ($single_page_attributes as $n => $i_node) {
                            $single[$n] = [];
                            foreach ($i_node->attributes() as $key => $attr) {
                                switch ($key) {
                                case 'prefix':
                                case 'suffix':
                                case 'font':
                                case 'align':
                                case 'valign':
                                case 'style':
                                case 'size':
                                case 'type':
                                    $single[$n][$key] = (string)$attr;
                                    break;
                                case 'border':
                                    $single[$n][$key] = (int)$attr;
                                    break;
                                case 'pitch':
                                case 'x':
                                case 'y':
                                case 'width':
                                case 'height':
                                case 'maxh':
                                    $single[$n][$key] = (float)$attr;
                                    break;
                                case 'color':
                                case 'poly':
                                    $single[$n][$key] = \Tms\Pdf::mapAttrToArray((string)$attr);
                                    break;
                                case 'flg':
                                case 'ishtml':
                                    $single[$n][$key] = \Tms\Pdf::mapAttrToBoolean((string)$attr);
                                    break;
                                }
                            }
                        }
                    }
                    unset($child_node->SinglePageAttributes);
                    $map = self::pdfMapping(get_object_vars($child_node), $page, $count, $files);
                    foreach ($map as &$unit) {
                        if (isset($single[$unit['name']])) {
                            $unit = array_merge($unit, $single[$unit['name']]);
                        }
                    }
                    unset($unit);
                    $return_value = array_merge($return_value, $map);
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
                'poly' => \Tms\Pdf::mapAttrToArray((string)$child_node->attributes()->poly),
                'border' => (int)$child_node->attributes()->border
            ];

            if ($child_node->attributes()->ishtml) {
                $value['ishtml'] = \Tms\Pdf::mapAttrToBoolean(
                    (string)$child_node->attributes()->ishtml
                );
            }

            if ($child_node->attributes()->maxh) {
                $value['maxh'] = (float)$child_node->attributes()->maxh;
            }

            if ($child_node->attributes()->valign) {
                $value['valign'] = (string)$child_node->attributes()->valign;
            }

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

    private function receiptDetailsForPdf($receiptkey, $line_count, $middlepage_line_count, $carry_forward, &$header)
    {
        $statement = "CONCAT(issue_date,'-',receipt_number,'-',userkey,'-',templatekey,'-',draft) = ?";
        $detail = $this->db->select('*', 'receipt_detail', "WHERE CONCAT(issue_date,'-',receipt_number,'-',userkey,'-',templatekey,'-',draft) = ? ORDER BY `page_number`,`line_number`", [$receiptkey]);

        $return_value = [];

        // Culcuration totals
        $subtotal = 0;
        $tax = 0;
        $before_page_number = 1;
        foreach ($detail as $unit) {
            $page_number = (int)$unit['page_number'];
            $line_number = (int)$unit['line_number'];

            if (!is_null($carry_forward) && $page_number !== $before_page_number && $line_number > 1) {
                $return_value[$page_number][] = [
                    'page_number' => $page_number,
                    'line_number' => 1,
                    'content' => $carry_forward,
                    'price' => '',
                    'quantity' => '',
                    'unit' => '',
                    'sum' => $subtotal,
                ];
            }

            if ($line_number > $line_count && ($line_number - $line_count) % $middlepage_line_count === 1) {
                $unit['sum'] = $subtotal;
            } else {
                $sum = (float)$unit['price'] * (int)$unit['quantity'];
                $subtotal += $sum;
                $tax += $sum * (float)$unit['tax_rate'];
                $unit['sum'] = (is_null($unit['price']) && empty($sum)) ? NULL : $sum;
            }

            if (!isset($return_value[$page_number])) {
                $return_value[$page_number] = [];
            }
            $return_value[$page_number][] = $unit;
            $before_page_number = $page_number;
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

    public function honorificTitle(&$data, &$pdf_mapper)
    {
        $company = preg_replace('/\s+/', '', mb_convert_kana($data['company'], 's'));
        $fullname = preg_replace('/\s+/', '', mb_convert_kana($data['fullname'], 's'));
        if ($company === $fullname) {
            $data['fullname'] = null;
            $pdf_mapper->client->company->attributes()->suffix = $pdf_mapper->client->fullname->attributes()->suffix;
        }

        if (!empty($data['fullname'])) {
            $pdf_mapper->client->company->attributes()->suffix = '';
        }
    }

    public function formatZipcode(&$data, &$pdf_mapper)
    {
        if ($pdf_mapper->client->zipcode->attributes()->format
            && preg_match('/^(\d{3})(\d{4})$/', $data['zipcode'], $match)
        ) {
            $data['zipcode'] = [$match[1], $match[2]];
        }
    }

    protected function calcurateTotals($receiptkey)
    {
        $statement = "CONCAT(issue_date,'-',receipt_number,'-',userkey,'-',templatekey,'-',draft) = ?";
        $header = $this->db->get(
            'additional_1_price,additional_2_price',
            'receipt',
            $statement,
            [$receiptkey]
        );
        $detail = $this->db->select(
            '*',
            'receipt_detail',
            "WHERE {$statement} ORDER BY `page_number`,`line_number`",
            [$receiptkey]
        );

        // Culcuration totals
        $subtotal = 0;
        $tax = 0;
        foreach ($detail as $unit) {
            $sum = (float)$unit['price'] * (int)$unit['quantity'];
            $subtotal += $sum;
            $tax += $sum * (float)$unit['tax_rate'];
        }

        return $subtotal + $tax + (int)$header['additional_1_price'] + (int)$header['additional_2_price'];
    }

    private function removeDraftFlag($options)
    {
        $data = ['draft' => '0'];
        $statement = 'issue_date = ? AND receipt_number = ? AND userkey = ? AND templatekey = ? AND draft = ?';
        $options[] = '1';

        return false !== $this->db->delete('receipt', $statement, $options);
    }

    protected function receiptDetail($templatekey, $issue_date, $receipt_number, $page_number, $draft, $with_lines)
    {
        $statement = 'issue_date = ? AND receipt_number = ? AND userkey = ? AND templatekey = ? AND draft = ?';
        $replaces = [$issue_date, $receipt_number, $this->uid, $templatekey, $draft];
        $receipt_data = $this->db->get("*", 'receipt', $statement, $replaces);

        if (!empty($receipt_data['client_id'])) {
            $client_data = $this->clientData($receipt_data['client_id']);
            foreach ($client_data as $key => $value) {
                $receipt_data[$key] = $value;
            }
        }

        if ($with_lines) {
            if (!empty($page_number)) {
                $receipt_data['page_number'] = $page_number;
            } else {
                $page_number = 1;
            }
            $receipt_data = array_merge(
                $receipt_data,
                $this->receiptLines(
                    $templatekey,
                    $receipt_data['issue_date'],
                    $receipt_data['receipt_number'],
                    $page_number,
                    $draft
                )
            );
        }

        return $receipt_data;
    }

    protected function receiptLines($templatekey, $issue_date, $receipt_number, $page_number, $draft): array
    {
        $return_value = [];
        $lines = $this->db->select(
            'line_number,content,price,quantity,tax_rate,unit',
            'receipt_detail',
            'WHERE issue_date = ? AND receipt_number = ? AND userkey = ? AND templatekey = ? AND page_number = ? AND draft = ?',
            [$issue_date, $receipt_number, $this->uid, $templatekey, $page_number, $draft]
        );

        foreach ((array)$lines as $line) {
            $line_number = $line['line_number'];
            foreach ((array)$line as $key => $value) {
                if ($key === 'line_number') {
                    continue;
                }
                if ($key === 'tax_rate') {
                    $return_value[$key][$line_number] = 0;
                    continue;
                }
                $return_value[$key][$line_number] = $value;
            }
            if (!empty($sum)) {
                $return_value['sum'][$line_number] = $sum;
            }
        }

        return $return_value;
    }

    protected function clientData($client_id): array
    {
        return $this->db->get(
            'company,division,fullname,zipcode,address1,address2',
            'receipt_to',
            'id = ? ',
            [$client_id]
        );
    }

    protected function cloneReceipt($templatekey, $issue_date, $receipt_number, $page_number, &$draft, $destination = null)
    {
        $this->db->begin();

        $to = [];
        $pdf_mapper_source = $this->db->get('pdf_mapper', 'receipt_template', 'id = ? AND userkey = ?', [$destination, $this->uid]);
        if (!empty($pdf_mapper_source)) {
            $pdf_mapper = simplexml_load_string($pdf_mapper_source);
            foreach ($pdf_mapper->extendedfield->item as $obj) {
                $to[] = (string)$obj->attributes()->name;
            }
        }
        $from = [];
        $pdf_mapper_source = $this->db->get('pdf_mapper', 'receipt_template', 'id = ? AND userkey = ?', [$templatekey, $this->uid]);
        if (!empty($pdf_mapper_source)) {
            $pdf_mapper = simplexml_load_string($pdf_mapper_source);
            foreach ($pdf_mapper->extendedfield->item as $obj) {
                $from[] = (string)$obj->attributes()->name;
            }
        }
        $diff = array_diff($from, $to);

        $clone_issue_date = date('Y-m-d');
        $clone_receipt_number = $this->newReceiptNumber($issue_date, '1', $destination);
        foreach (['receipt', 'receipt_detail'] as $table_name) {
            $columns = [];
            $fields = $this->db->getFields($table_name);
            foreach ($fields as $field) {
                if ($field === 'issue_date' || $field === 'receipt_number') {
                    $columns[] = "? AS $field";
                } elseif ($field === 'subject') {
                    $columns[] = "CONCAT($field,' (Copy)') AS $field";
                } elseif ($field === 'draft') {
                    $columns[] = "'1' AS $field";
                } elseif ($field === 'templatekey' && !empty($destination) && $templatekey !== $destination) {
                    $columns[] = $this->db->quote($destination) . " AS $field";
                } elseif (in_array($field, $diff)) {
                    $columns[] = "NULL AS $field";
                } else {
                    $columns[] = "$field";
                }
            }
            $statement = "INSERT INTO table::{$table_name}
                                 SELECT " . implode(',', $columns) . " 
                                   FROM table::{$table_name}
                                  WHERE issue_date = ? AND receipt_number = ?
                                    AND userkey = ? AND templatekey = ? AND draft = '0'";
            $options = [
                $clone_issue_date, $clone_receipt_number,
                $issue_date, $receipt_number, $this->uid, $templatekey
            ];

            $this->db->prepare($statement);
            $this->db->execute($options);
        }

        $this->db->commit();

        $draft = '1';

        if (empty($this->request->param('receipt_number'))) {
            $this->request->param('receipt_number', $clone_receipt_number);
        }

        if (!empty($destination) && $templatekey !== $destination) {
            if (php_sapi_name() === 'cli') {
                $this->clone_receipt_number = $clone_receipt_number;
                return true;
            }

            $this->session->param('receipt_id', $destination);
            $this->session->clear('receipt_page');
            parent::redirect("srm.receipt.response:edit\&id\={$clone_issue_date}:{$clone_receipt_number}\&draft\=1");
        }

        return $this->receiptDetail($templatekey, $clone_issue_date, $clone_receipt_number, $page_number, '1', true);
    }

    public function getTaxRate($kind, $issue_date) : float
    {
        if (false === property_exists($this, $kind)) {
            throw new Exception($kind . ' is unknown proterty');
        }

        $date1 = new DateTime($issue_date);
        $tax_rates = $this->db->select(
            'effective_date,tax_rate,reduced_tax_rate',
            'tax_rates',
            'WHERE area_code = ? ORDER BY effective_date DESC',
            [$this->app->cnf('srm:tax_locale')]
        );

        foreach ($tax_rates as $tax_rate) {
            $date2 = new DateTime($tax_rate['effective_date']);
            if ($date1 >= $date2) {
                return $tax_rate[$kind];
            }
        }

        return $this->$kind;
    }

    protected function totalOfReceipt($issue_date, $receipt_number, $templatekey, $draft = '0', $without_tax = false): ?int
    {
        $statement = 'userkey = ? AND templatekey = ? AND issue_date = ? AND receipt_number = ? AND draft = ?';
        $replaces = [$this->uid, $templatekey, $issue_date, $receipt_number, $draft];
        $additionals = $this->db->get('additional_1_price,additional_2_price', 'receipt', $statement, $replaces);
        $details = $this->db->select('price,quantity,tax_rate', 'receipt_detail', "WHERE $statement", $replaces);

        if (false === $details) {
            return null;
        }

        $total = 0;
        foreach ($details as $unit) {
            $subtotal = $unit['price'] * $unit['quantity'];

            $tax_rate = ($without_tax) ? 0 : (float)$unit['tax_rate'];
            $total += $subtotal + $subtotal * $tax_rate;
        }
        $total += $additionals['additional_1_price'] ?? 0;
        $total += $additionals['additional_2_price'] ?? 0;

        return round($total);
    }

    protected function receiptIdFromType($type, $with_map = false)
    {
        if (false !== $records = $this->db->select(
            'id,pdf_mapper', 'receipt_template', 'WHERE userkey = ?', [$this->uid]
        )) {
            foreach($records as $record) {
                $pdf_mapper_source = $record['pdf_mapper'];
                if (!empty($pdf_mapper_source)) {
                    $pdf_mapper = simplexml_load_string($pdf_mapper_source);
                    if ((string)$pdf_mapper->attributes()->typeof === $type) {
                        if ($with_map !== false) {
                            return [(int)$record['id'], $pdf_mapper];
                        }
                        return (int)$record['id'];
                    }
                }
            }
        }

        return null;
    }

    protected function getPdfMapper($id)
    {
        if (false !== $pdf_mapper_source = $this->db->get(
            'pdf_mapper', 'receipt_template', 'WHERE id = ?', [$id]
        )) {
            return simplexml_load_string($pdf_mapper_source);
        }

        return null;
    }
}
