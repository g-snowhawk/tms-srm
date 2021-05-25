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
 * Template management class.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Template extends \Tms\Srm
{
    /*
     * Using common accessor methods
     */
    use \Tms\Accessor;

    /**
     * Save the data.
     */
    protected function save()
    {
        $id = $this->request->param('id');
        $check = (empty($id)) ? 'create' : 'update';
        $this->checkPermission('srm.template.'.$check);

        $table = 'receipt_template';
        $skip = ['id', 'userkey', 'create_date', 'modify_date'];

        $post = $this->request->POST();

        $valid = [];
        $valid[] = ['vl_title', 'title', 'empty'];
        // TODO: validate upload PDF

        if (!$this->validate($valid)) {
            return false;
        }
        $this->db->begin();

        $fields = $this->db->getFields($this->db->TABLE($table));
        $save = [];
        $raw = [];
        foreach ($fields as $field) {
            if (in_array($field, $skip)) {
                continue;
            }
            if (isset($post[$field])) {
                $save[$field] = $post[$field];
            }
        }

        if (!empty($save['priority'])) {
            if (!empty($post['id'])) {
                $priority = $this->db->get('priority', 'receipt_template', 'id = ?', [$post['id']]);
                if (false === $this->db->update(
                    'receipt_template',
                    [],
                    'userkey = ? and priority > ?',
                    [$this->uid, $priority],
                    ['priority' => 'priority - 1']
                )) {
                    trigger_error($this->db->error());
                    $this->db->rollback();

                    return false;
                }
            }
            if (false === $this->db->update(
                'receipt_template',
                [],
                'userkey = ? and priority >= ?',
                [$this->uid, $save['priority']],
                ['priority' => 'priority + 1']
            )) {
                trigger_error($this->db->error());
                $this->db->rollback();

                return false;
            }
        }

        if (empty($post['id'])) {
            $raw = ['create_date' => 'CURRENT_TIMESTAMP'];
            $save['userkey'] = $this->uid;

            if (empty($save['priority']) && $save['priority'] !== '0') {
                $max = $this->db->max('priority', 'receipt_template', 'userkey = ?', [$this->uid]);
                $save['priority'] = (int)$max + 1;
            }

            if (false !== $result = $this->db->insert($table, $save, $raw)) {
                $post['id'] = $this->db->lastInsertId(null, 'id');
            }
        } else {
            if (empty($save['priority']) && $save['priority'] !== '0') {
                unset($save['priority']);
            }

            $result = $this->db->update($table, $save, 'id = ?', [$post['id']], $raw);
        }

        if ($result !== false) {
            $modified = ($result > 0) ? $this->db->modified($table, 'id = ?', [$post['id']]) : true;
            if ($modified) {
                if (false === $file_count = $this->saveFiles($post['id'])) {
                    $result = false;
                } else {
                    $result += $file_count;
                }
                if ($result === 0) {
                    $this->app->err['vl_nochange'] = 1;
                    $result = false;
                }
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
     * Remove template data and files.
     *
     * @return bool
     */
    protected function remove()
    {
        $this->checkPermission('srm.template.delete');

        $this->db->begin();
        $id = $this->request->param('delete');
        if (false !== $this->db->delete('receipt_template', 'id = ?', [$id])) {
            if (false !== $this->removeFiles($id)) {
                return $this->db->commit();
            }
        }
        trigger_error($this->db->error());
        $this->db->rollback();

        return false;
    }

    protected function saveFiles($id)
    {
        $count = 0;
        foreach ($_FILES as $key => $unit) {
            if ($unit['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($unit['error'] !== UPLOAD_ERR_OK) {
                return false;
            }
            if (preg_match("/^base_pdf_(single|multiple)$/", $key, $match)) {
                $save_path = $this->app->cnf('global:data_dir') . "/srm/$id";
                if (!file_exists($save_path)) {
                    try {
                        mkdir($save_path, 0777, true);
                    } catch(\ErrorException $e) {
                        return false;
                    }
                }
                $file_name = $match[1];
                if (false === move_uploaded_file($unit['tmp_name'], "$save_path/$file_name.pdf")) {
                    return false;
                }
            }
            ++$count;
        }
        return $count;
    }

    protected function removeFiles($id)
    {
        $save_path = $this->app->cnf('global:data_dir') . "/srm/$id";

        if (!file_exists($save_path)) {
            return true;
        }

        return \P5\File::rmdir($save_path, true);
    }
}
