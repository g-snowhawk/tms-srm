<?php
/**
 * This file is part of Tak-Me System.
 *
 * Copyright (c)2016 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Tms\Srm;

/**
 * User management class.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Client extends \Tms\Srm
{
    /*
     * Using common accessor methods
     */
    use \Tms\Accessor;

    /**
     * Object constructor.
     */
    public function __construct()
    {
        $params = func_get_args();
        call_user_func_array('parent::__construct', $params);
    }

    /**
     * Save the data.
     *
     * @return bool
     */
    protected function save()
    {
        $id = $this->request->post('id');
        $check = (empty($id)) ? 'create' : 'update';
        $this->checkPermission("srm.client.{$check}");

        $post = $this->request->post();

        $table = 'user';
        $skip = ['id', 'admin', 'create_date', 'modify_date'];

        $valid = [];
        $valid[] = ['vl_company', 'company', 'empty'];

        if (!$this->validate($valid)) {
            return false;
        }
        $this->db->begin();

        $fields = $this->db->getFields($this->db->TABLE($table));
        $permissions = [];
        $save = [];
        $raw = [];
        foreach ($fields as $field) {
            if (in_array($field, $skip)) {
                continue;
            }
            if (isset($post[$field])) {
                //if ($field === 'upass') {
                //    if (!empty($post[$field])) {
                //        $save[$field] = \P5\Security::encrypt($post[$field], '', $this->app->cnf('global:password_encrypt_algorithm'));
                //    }
                //    continue;
                //}
                $save[$field] = $post[$field];
            }
        }

        $save['restriction'] = $this->packageName();

        if (empty($post['id'])) {
            $parent = '(SELECT * FROM table::user WHERE id = ?)';
            $range = $this->db->nsmGetPosition($parent, 'table::user', [$this->uid]);
            $parent_lft = (float)$range['lft'];
            $parent_rgt = (float)$range['rgt'];

            $save['lft'] = $parent_rgt;
            $save['rgt'] = $parent_rgt + 1;

            // TODO: 
            $save['uname'] = uniqid();

            $raw = ['create_date' => 'CURRENT_TIMESTAMP'];

            $update_parent = $this->db->prepare(
                $this->db->nsmBeforeInsertChildSQL('user')
            );

            if (   false !== $update_parent->execute(['parent_rgt' => $parent_rgt, 'offset' => 2])
                && false !== $result = $this->db->insert($table, $save, $raw)
            ) {
                $post['id'] = $this->db->lastInsertId(null, 'id');
            }
        } else {
            $result = $this->db->update($table, $save, 'id = ?', [$post['id']], $raw);
        }
        if ($result !== false) {
            $modified = ($result > 0) ? $this->db->modified($table, 'id = ?', [$post['id']]) : true;
            if ($modified) {
                //if ($this->request->param('profile') !== '1' && false === $this->updatePermission($post)) {
                //    $result = false;
                //}
            } else {
                $result = false;
            }
            if ($result !== false) {
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
        $this->checkPermission('srm.client.remove');

        $result = 0;
        $this->db->begin();
        if (false !== $result = $this->db->delete('user', 'id = ?', [$this->request->param('delete')])) {
            if ($result === 0 || false !== $this->db->nsmCleanup('user')) {
                return $this->db->commit();
            }
        }
        trigger_error($this->db->error());
        $this->db->rollback();

        return false;
    }
}
