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

use P5\Http;
use P5\Lang;
use P5\Mail;

/**
 * User management request receive class.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Receive extends Response
{
    const REDIRECT_MODE = 'srm.receipt.response';

    /**
     * Save the data receive interface.
     */
    public function save() : bool
    {
        if (!empty($this->request->param('s1_delete'))) {
            return $this->remove();
        }

        $redirect_type = 'redirect';
        $redirect_mode = (!empty($this->request->param('redirect_mode')))
            ? $this->request->param('redirect_mode')
            : self::REDIRECT_MODE;

        if ($referer = $this->request->param('script_referer')) {
            $redirect_mode = $referer;
            $redirect_type = 'referer';
        }

        $message_key = 'SUCCESS_SAVED';
        $status = 0;
        $options = [];
        $response = [[$this, 'redirect'], [$redirect_mode, $redirect_type]];

        if (!parent::save()) {
            $message_key = 'FAILED_SAVE';
            $status = 1;
            $options = [
                [[$this->view, 'bind'], ['err', $this->app->err]],
            ];
            $response = [[$this, 'edit'], null];
        }

        elseif ($this->request->param('s1_addpage')) {
            $response = [[$this, 'edit'], ['addpage']];
            $message_key = null;
        }

        elseif (!empty($this->request->param('move_page'))) {
            $redirect_mode .= sprintf(':edit&id=%s:%d:%d&draft=1',
                date('Y-m-d', strtotime($this->request->param('issue_date'))),
                $this->request->param('receipt_number'),
                $this->request->param('move_page')
            );
            $url = $this->app->systemURI()."?mode=$redirect_mode";
            Http::redirect($url);
        }

        $this->postReceived(Lang::translate($message_key), $status, $response, $options);
    }

    /**
     * Remove the data receive interface.
     */
    public function remove(): bool
    {
        $redirect_type = 'redirect';
        $redirect_mode = (!empty($this->request->param('redirect_mode')))
            ? $this->request->param('redirect_mode')
            : self::REDIRECT_MODE;

        if ($referer = $this->request->param('script_referer')) {
            $redirect_mode = $referer;
            $redirect_type = 'referer';
        }

        $message_key = 'SUCCESS_REMOVED';
        $status = 0;
        $options = [];
        $response = [[$this, 'redirect'], [$redirect_mode, $redirect_type]];

        if (!parent::remove()) {
            $message_key = 'FAILED_REMOVE';
            $status = 1;
        }

        $this->postReceived(Lang::translate($message_key), $status, $response, $options);
    }

    public function suggestClient()
    {
        $json_array = ['status' => 0];
        $ideographic_space = json_decode('"\u3000"');

        $keyword = str_replace([$ideographic_space,' '], '', $this->request->param('keyword'));

        $collate = 'utf8_unicode_ci';
        if (false !== $this->db->query("SHOW VARIABLES LIKE 'character_set_connection'")) {
            $variables = $this->db->fetch();
            if ($variables['Value'] === 'utf8mb4') {
                $collate = 'utf8mb4_unicode_ci';
            }
        }

        //$clients = $this->db->select(
        //    'company,fullname,zipcode,address1,address2,division',
        //    'receipt_to',
        //    "WHERE REPLACE(REPLACE(company,'$ideographic_space',' '),' ','') LIKE ? COLLATE $collate",
        //    ["%$keyword%"]
        //);

        $this->db->query("SELECT @@SESSION.sql_mode AS `mode`");
        $sql_session = $this->db->fetch();
        $sql_mode = str_replace('ONLY_FULL_GROUP_BY', '', $sql_session['mode']);
        $this->db->query("SET SESSION sql_mode = ?", [$sql_mode]);


        $templatekey = $this->session->param("receipt_id");
        $sql = "SELECT t.company,t.fullname,t.zipcode,t.address1,t.address2,t.division,
                       r.bank_id,r.term,r.valid,r.delivery,r.payment
                  FROM (SELECT id,company,fullname,zipcode,address1,address2,division
                          FROM table::receipt_to
                         WHERE userkey = ?
                           AND REPLACE(REPLACE(company,'{$ideographic_space}',' '),' ','') LIKE ? COLLATE {$collate}
                       ) t
                  LEFT JOIN (SELECT client_id,bank_id,term,valid,delivery,payment
                               FROM table::receipt
                              WHERE userkey = ? AND templatekey = ? AND draft = ?
                              ORDER BY issue_date DESC, receipt_number DESC
                            ) r
                    ON t.id = r.client_id
                 GROUP BY t.id";

        $where = [
            $this->uid,
            "%{$keyword}%",
            $this->uid,
            $templatekey,
            '0',
        ];

        if (false === $this->db->query($sql, $where)) {
            $json_array['status'] = 1;
            $json_array['message'] = 'Database Error: '.$this->db->error();
        } else {
            $clients = $this->db->fetchAll();
            // TODO: Use to Template Engine
            $this->view->bind('clients', $clients);
            $json_array['source'] = $this->view->render('srm/receipt/suggest_client.tpl', true);
        }

        header('Content-type: application/json');
        echo json_encode($json_array);
        exit;
    }

    public function unavailable($unavailable = "1")
    {
        if ($this->request->method !== 'post') {
            trigger_error('Invalid operation', E_USER_ERROR);
        }
        
        list($issue_date, $receipt_number) = explode(":", $this->request->param("id"));
        $reason = $this->request->param("reason");
        $templatekey = $this->session->param("receipt_id");

        $redirect_type = "redirect";
        $redirect_mode = (!empty($this->request->param("redirect_mode")))
            ? $this->request->param("redirect_mode")
            : self::REDIRECT_MODE;

        $message_key = 'SUCCESS_UNAVAILABLE';
        $status = 0;
        $options = [];
        $response = [[$this, 'redirect'], [$redirect_mode, $redirect_type]];

        if ($unavailable === "0") {
            $reason = null;
            $message_key = 'SUCCESS_AVAILABLE';
        }

        if (false === $this->db->update(
            "receipt", 
            ["unavailable" => $unavailable, "unavailable_reason" => $reason],
            "issue_date = ? AND receipt_number = ? AND userkey = ? AND templatekey = ?",
            [$issue_date, $receipt_number, $this->uid, $templatekey]
        )) {
            $message_key = ($unavailable === "0") ? 'FAILED_UNAVAILABLE' : 'FAILED_AVAILABLE';
            $status = 1;
        }

        $this->postReceived(Lang::translate($message_key), $status, $response, $options);
    }

    public function available()
    {
        self::unavailable("0");
    }

    public function saveSearchOptions()
    {
        if ($this->request->param('submitter') !== 's1_clear') {
            $andor = $this->request->param('andor');
            if ($andor !== 'AND' && $andor !== 'OR') {
                $andor = 'AND';
            }
            $search_options = [
                'issue_date_start' => $this->request->param('issue_date_start'),
                'issue_date_end' => $this->request->param('issue_date_end'),
                'andor' => $andor,
            ];
            $this->session->param(parent::SEARCH_OPTIONS_KEY, $search_options);
        } else {
            $this->session->clear(parent::SEARCH_OPTIONS_KEY);
        }

        $response = [[$this, 'didSetSearchOptions'], []];
        $this->postReceived('', 0, $response, []);
    }

    public function didSetSearchOptions()
    {
        return ['type' => 'close'];
    }

    public function sendmail(): void
    {
        $from = $this->request->param('from') ?? $this->userinfo['email'];
        $reply_to = $this->request->param('reply-to');
        $cc = $this->request->param('cc');
        $attachment = $this->request->param('pdf_path');
        $attachment_name = $this->request->param('attachment_name');

        $mail = new Mail();

        $mail->to($this->request->param('to'));
        $mail->from($from);
        if (defined('RETURN_PATH') && RETURN_PATH === 1) {
            $mail->envfrom($from);
        }
        $mail->subject($this->request->param('subject'));
        $mail->message($this->request->param('mail_body'));
 
        if (!empty($reply_to)) {
            $mail->setHeader('Reply-To', $reply_to);
        }
        if (!empty($cc)) {
            $mail->cc($cc);
        }

        // self check
        $mail->bcc($from);

        if (!empty($attachment) && file_exists($attachment)) {
            $mail->attachment($attachment, $attachment_name);
        }

        $json = ['status' => 0];
        if (false === $mail->send()) {
            $json['status'] = 1;
            $json['message'] = 'Server Error';
        }

        header('Content-type: application/json; charset=utf-8');
        echo json_encode($json);
        exit;
    }
}
