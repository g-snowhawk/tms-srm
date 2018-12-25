<?php
/**
 * This file is part of Tak-Me System.
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 *
 * @see \Tms\PackageSetup
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 * @copyright 2016-2018 PlusFive (https://www.plus-5.com)
 */

namespace Tms\Srm;

/**
 * Application install class.
 */
final class setup extends \Tms\PackageSetup
{
    /**
     * Current version number.
     */
    const VERSION = '1.0.0';

    /**
     * Object constructor.
     *
     * @param Tms\App $app
     * @param string  $installed_version
     */
    public function __construct(\Tms\App $app, $installed_version)
    {
        $this->app = $app;
        $this->installed_version = $installed_version;
    }

    /**
     * Namespace of this package.
     *
     * @return string
     */
    public static function getNameSpace()
    {
        return __NAMESPACE__;
    }

    /**
     * Description of this package.
     *
     * @see P5\Lang::translate()
     *
     * @return string
     */
    public static function getDescription()
    {
        return \P5\Lang::translate('APP_DETAIL', __CLASS__);
    }

    /**
     * Execute install/update package.
     *
     * @param array &$configuration
     *
     * @return bool
     */
    public function update(&$configuration)
    {
        $result = false;
        if (false !== $this->updateDatabase(__DIR__)
         && false !== $this->updateConfiguration($configuration)
        ) {
            $result = true;
        }

        $key = ($result) ? 'SUCCESS_SETUP' : 'FAILED_SETUP';
        $this->message = \P5\Lang::translate($key);

        return $result;
    }

    /**
     * Update configuration.
     *
     * @param array &$configuration
     *
     * @return bool
     */
    private function updateConfiguration(&$configuration)
    {
        $class = __NAMESPACE__;
        $configuration['application'] = ['default_mode' => $class::DEFAULT_MODE];

        return true;
    }
}
