<?php
/**
 * This file is part of Tak-Me System.
 *
 * Copyright (c)2016-2017 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Tms\Srm\Bank;

/**
 * Category management response class.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Response extends \Tms\Srm\Bank
{
    /**
     * Object Constructor.
     */
    public function __construct()
    {
        $params = func_get_args();
        call_user_func_array('parent::__construct', $params);

        $this->view->bind(
            'header',
            ['title' => \P5\Lang::translate('HEADER_TITLE'), 'id' => 'srm-bank', 'class' => 'bank']
        );
    }

    /**
     * Default view.
     */
    public function defaultView() : void
    {
        $this->checkPermission('srm.bank.read');

        $banks = $this->db->select('*', 'bank', 'WHERE userkey = ?', [$this->uid]);
        foreach ($banks as &$bank) {
            $bank['id'] = implode('-', [$bank['bank_code'], $bank['branch_code'], $bank['account_number']]);
        }
        unset($bank);
        $this->view->bind('banks', $banks);

        $globals = $this->view->param();
        $form = $globals['form'];
        $form['confirm'] = \P5\Lang::translate('CONFIRM_DELETE_DATA');
        $this->view->bind('form', $form);

        $this->setHtmlId('srm-bank-default');
        $this->view->render('srm/bank/default.tpl');
    }

    /**
     * Edit view.
     */
    public function edit() : vold
    {
        $id = $this->request->param('id');
        $privilege_type = (empty($id)) ? 'create' : 'update';

        $this->checkPermission('srm.bank.'.$privilege_type);

        if ($this->request->method === 'post') {
            $post = $this->request->POST();
        } else {
            list($bank_code, $branch_code, $account_number) = explode('-', $this->request->param('id'));
            $fetch = $this->db->select(
                '*',
                'bank',
                'WHERE bank_code = ? AND branch_code = ? AND account_number = ?', 
                [$bank_code, $branch_code, $account_number]
            );
            if (count((array) $fetch) > 0) {
                $post = $fetch[0];
                $post['id'] = implode('-', [$post['bank_code'], $post['branch_code'], $post['account_number']]);
            }
        }
        $this->view->bind('post', $post);

        $globals = $this->view->param();
        $form = $globals['form'];
        $form['confirm'] = \P5\Lang::translate('CONFIRM_SAVE_DATA');
        $this->view->bind('form', $form);

        $header = $globals['header'];
        $header['id'] = 'srm-bank-edit';
        $this->view->bind('header', $header);

        $this->view->bind('err', $this->app->err);
        $this->view->render('srm/bank/edit.tpl');
    }
}
