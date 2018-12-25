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
 * Template management request response class.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Response extends \Tms\Srm\Template
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
            ['title' => \P5\Lang::translate('HEADER_TITLE'), 'id' => 'template', 'class' => 'template']
        );
    }

    /**
     * Default view.
     */
    public function defaultView()
    {
        $this->checkPermission('srm.template.read');

        $templates = $this->db->select(
            'id ,title, line',
            'receipt_template',
            'WHERE userkey = ?',
            [$this->uid, 0]
        );
        $this->view->bind('templates', $templates);

        $this->setHtmlId('template-default');

        $globals = $this->view->param();
        $form = $globals['form'];
        $form['confirm'] = \P5\Lang::translate('CONFIRM_DELETE_DATA');
        $this->view->bind('form', $form);

        $this->view->render('srm/template/default.tpl');
    }

    /**
     * Show edit form.
     */
    public function edit()
    {
        $id = $this->request->param('id');
        $check = (empty($id)) ? 'create' : 'update';
        $this->checkPermission('srm.template.'.$check);

        if ($this->request->method === 'post') {
            $post = $this->request->POST();
        } else {
            $post = $this->db->get(
                'id, title, line, pdf_mapper, create_date, modify_date',
                'receipt_template',
                'id = ? AND userkey = ?',
                [$this->request->param('id'), $this->uid]
            );
        }
        $this->view->bind('post', $post);

        $globals = $this->view->param();
        $form = $globals['form'];
        $form['confirm'] = \P5\Lang::translate('CONFIRM_SAVE_DATA');
        $form['enctype'] = 'multipart/form-data';
        $this->view->bind('form', $form);

        $this->setHtmlId('template-edit');
        $this->view->render('srm/template/edit.tpl');
    }
}
