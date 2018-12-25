<?php
/**
 * This file is part of Tak-Me System.
 *
 * Copyright (c)2016-2017 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Tms\Srm\Client;

/**
 * Category management response class.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Response extends \Tms\Srm\Client
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
            ['title' => \P5\Lang::translate('HEADER_TITLE'), 'id' => 'srm-client', 'class' => 'client']
        );
    }

    /**
     * Default view.
     */
    public function defaultView()
    {
        $this->checkPermission('srm.client.read');

        $ret = $this->db->nsmGetDecendants(
            'children.id, children.company, children.fullname',
            '(SELECT * FROM table::user WHERE id = ?)',
            '(SELECT * FROM table::user WHERE restriction = ?)',
            [$this->uid, $this->packageName()]
        );
        $this->view->bind('users', $ret);

        $globals = $this->view->param();
        $form = $globals['form'];
        $form['confirm'] = \P5\Lang::translate('CONFIRM_DELETE_DATA');
        $this->view->bind('form', $form);

        $this->setHtmlId('srm-client-default');
        $this->view->render('srm/client/default.tpl');
    }

    /**
     * Edit view.
     */
    public function edit()
    {
        $id = $this->request->param('id');
        $check = (empty($id)) ? 'create' : 'update';

        $this->checkPermission('srm.client.'.$check);

        if ($this->request->method === 'post') {
            $post = $this->request->post();
        } else {
            $post = $this->db->get(
                'id, company, division, fullname, zip, state, city, town,
                 address1, address2, tel, fax, create_date, modify_date',
                'user', 'id = ?', [$this->request->param('id')]
            );
            $stat = $this->db->select(
                '*', 'permission',
                'WHERE userkey = ? AND application IN (?,?)',
                [$this->request->param('id'), '', $this->currentApp()]
            );

            $perm = [];
            foreach ($stat as $unit) {
                $tmp = [];
                $tmp[] = ($unit['filter1'] !== '0') ? $unit['filter1'] : '';
                $tmp[] = ($unit['filter2'] !== '0') ? $unit['filter2'] : '';
                $tmp[] = $unit['application'];
                $tmp[] = $unit['class'];
                $tmp[] = $unit['type'];
                $key = preg_replace('/^\.+/', '', implode('.', $tmp));
                $perm[$key] = $unit['priv'];
            }
            $post['perm'] = $perm;
        }
        $this->view->bind('post', $post);

        $globals = $this->view->param();
        $form = $globals['form'];
        $form['confirm'] = \P5\Lang::translate('CONFIRM_SAVE_DATA');
        $this->view->bind('form', $form);

        $globals = $this->view->param();
        $header = $globals['header'];
        $header['id'] = 'srm-bank-edit';
        $this->view->bind('header', $header);

        $this->view->bind('err', $this->app->err);
        $this->view->render('srm/client/edit.tpl');
    }
}
