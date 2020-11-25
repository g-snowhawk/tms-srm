<?php
/**
 * This file is part of Tak-Me System.
 *
 * Copyright (c)2016 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Tms\Srm\Client;

/**
 * User management request receive class.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Receive extends Response
{
    /**
     * Save the data receive interface.
     */
    public function save()
    {
        if (parent::save()) {
            $this->session->param('messages', \P5\Lang::translate('SUCCESS_SAVED'));
            $url = $this->env->server('SCRIPT_NAME').'?mode=srm.client.response';
            \P5\Http::redirect($url);
        }
        $this->edit();
    }

    /**
     * Remove the data receive interface.
     */
    public function remove()
    {
        if (parent::remove()) {
            $this->session->param('messages', \P5\Lang::translate('SUCCESS_REMOVED'));
        }
        $url = $this->env->server('SCRIPT_NAME').'?mode=srm.client.response';
        \P5\Http::redirect($url);
    }

    public function update(): void
    {
        $response = [ 'status' => 0 ];

        $client_id = $this->request->param('client_id');
        $no_suggestion = $this->request->param('no_suggestion');

        if (!empty($client_id)) {
            $value = $no_suggestion[$client_id] ?? 'no';
            $ret = $this->db->update(
                'receipt_to',
                ['no_suggestion' => $value],
                'userkey = ? AND id = ?',
                [$this->uid, $client_id]
            );
            if (false === $ret) {
                $response['status'] = 1;
                $response['message'] = 'Server Error';
            }
        } else {
            $response['status'] = 1;
            $response['message'] = 'Server Error';
        }

        $this->responseJson($response);
    }
}
