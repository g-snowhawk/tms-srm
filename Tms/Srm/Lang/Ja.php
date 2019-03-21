<?php
/**
 * This file is part of Tak-Me System.
 *
 * Copyright (c)2016-2017 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Tms\Srm\Lang;

/**
 * Japanese Languages for Tms.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Ja extends \P5\Lang
{
    const APP_NAME = 'SRM';
    const ALT_NAME = '帳票管理';

    protected $APPLICATION_NAME = self::APP_NAME;
    protected $APPLICATION_LABEL = '帳票発行';
    protected $APP_DETAIL    = self::ALT_NAME.'機能を提供します。';
    protected $SUCCESS_SETUP = self::ALT_NAME.'機能の追加に成功しました。';
    protected $FAILED_SETUP  = self::ALT_NAME.'機能の追加に失敗しました。';
}
