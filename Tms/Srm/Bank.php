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
class Bank extends \Tms\Srm
{
    /**
     * Using common accessor methods
     */
    use \Tms\Accessor;

    /**
     * Object Constructer.
     */
    //public function __construct()
    //{
    //    $params = func_get_args();
    //    call_user_func_array('parent::__construct', $params);
    //}

    /**
     * Save the data.
     *
     * @return bool
     */
    protected function save()
    {
        $table_name = 'bank';
        $skip = ['id','userkey','item_code','create_date','modify_date'];

        $post = $this->request->post();

        $privilege_type = (empty($post['id'])) ? 'create' : 'update';
        $this->checkPermission('srm.bank.'.$privilege_type);

        $verification_recipe = [
            ['vl_bank_code', 'bank_code', 'empty'],
            ['vl_branch_code', 'branch_code', 'empty'],
            ['vl_bank', 'bank', 'empty'],
            ['vl_branch', 'branch', 'empty'],
            ['vl_account_type', 'account_type', 'empty'],
            ['vl_account_number', 'account_number', 'empty'],
            ['vl_account_holder', 'account_holder', 'empty'],
            ['vl_bank_code', 'bank_code', 'digit', 2],
            ['vl_branch_code', 'branch_code', 'digit', 2],
            ['vl_account_number', 'account_number', 'digit', 2],
        ];

        if (!$this->validate($verification_recipe)) {
            return false;
        }

        $this->db->begin();

        $table_columns = $this->db->getFields($this->db->TABLE($table_name));
        $save = [];
        $raw = [];
        foreach ($table_columns as $column) {
            if (in_array($column, $skip)) {
                continue;
            }
            if (isset($post[$column])) {
                $save[$column] = $post[$column];
            }
        }

        $statement = 'bank_code = ? AND branch_code = ? AND account_number = ?';

        if (empty($post['id'])) {
            $raw['create_date'] = 'CURRENT_TIMESTAMP';
            $save['userkey'] = $this->uid;
            $result = $this->db->insert($table_name, $save, $raw);
        } else {
            list($bank_code, $branch_code, $account_number) = explode('-', $this->request->POST('id'));
            $params = [$bank_code, $branch_code, $account_number];
            $result = $this->db->update($table_name, $save, $statement, $params, $raw);
        }

        if ($result !== false) {
            $params = [$save['bank_code'], $save['branch_code'], $save['account_number']];
            $modified = ($result > 0) ? $this->db->modified($table_name, $statement, $params) : true;
            if ($modified) {
                // If there is a need to do something after saving
                // ^ write here.
            } else {
                $result = false;
            }
            if ($result !== false) {
                $this->app->logger->log("Save the bank `{$post['bank_code']}-{$post['branch_code']} {$post['account_number']}'", 201);

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
    protected function remove()
    {
        $this->checkPermission('srm.bank.delete');

        $id = $this->request->POST('delete');
        list($bank_code, $branch_code, $account_number) = explode('-', $id);

        $this->db->begin();

        if (false !== $this->db->delete('bank', 'bank_code = ? AND branch_code = ? AND account_number = ?', [$bank_code, $branch_code, $account_number])) {
            $this->app->logger->log("Remove the bank `{$id}'", 201);

            return $this->db->commit();
        } else {
            trigger_error($this->db->error());
        }
        $this->db->rollback();

        return false;
    }
}
