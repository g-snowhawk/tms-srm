<?php
/**
 * This file is part of Tak-Me System.
 *
 * Copyright (c)2016 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Tms\Srm\Template;

/**
 * Template management request receive class.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Receive extends Response
{
    /**
     * Save the data.
     */
    public function save()
    {
        if (parent::save()) {
            $this->session->param('messages', \P5\Lang::translate('SUCCESS_SAVED'));
            \P5\Http::redirect(
                $this->env->server('SCRIPT_NAME').'?mode=srm.template.response'
            );
        }
        $this->view->bind('err', $this->app->err);
        $this->edit();
    }

    /**
     * Remove data.
     */
    public function remove()
    {
        if (parent::remove()) {
            $this->session->param('messages', \P5\Lang::translate('SUCCESS_REMOVED'));
        }
        \P5\Http::redirect(
            $this->env->server('SCRIPT_NAME').'?mode=srm.template.response'
        );
    }
}
