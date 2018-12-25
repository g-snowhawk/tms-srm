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
}
