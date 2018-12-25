<?php
/**
 * This file is part of Tak-Me System.
 *
 * Copyright (c)2016 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Tms\Srm\Bank;

/**
 * User management request receive class.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Receive extends Response
{
    const REDIRECT_MODE = 'srm.bank.response';

    /**
     * Save the data receive interface.
     */
    public function save()
    {
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

        $this->postReceived(\P5\Lang::translate($message_key), $status, $response, $options);
    }

    /**
     * Remove the data receive interface.
     */
    public function remove()
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

        $this->postReceived(\P5\Lang::translate($message_key), $status, $response, $options);
    }
}
