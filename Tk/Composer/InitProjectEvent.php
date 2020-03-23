<?php
namespace Tk\Composer;

use Composer\Script\Event;

/**
 * Class InitProject
 *
 * Use this Composer event when you cannot rely on the lib files
 *
 * @author Michael Mifsud <info@tropotek.com>
 * @see http://www.tropotek.com/
 * @license Copyright 2007 Michael Mifsud
 *
 *
 *
 *
 * @note This is the basic init project version
 * @note Not really used much
 * @see SetupEvent
 * @deprecated
 */
class InitProjectEvent
{


    /**
     * @param Event $event
     */
    static function postInstall(Event $event)
    {
        self::init($event);

    }

    /**
     * @param Event $event
     */
    static function postUpdate(Event $event)
    {
        self::init($event);
    }

    /**
     * @param Event $event
     */
    static function init(Event $event)
    {
        $sitePath = $_SERVER['PWD'];
        if (!@is_file($sitePath.'/src/config/config.php') && @is_file($sitePath.'/src/config/config.php.in')) {
            echo " - Creating default `~/src/config/config.php`, edit this file to suit your server.\n";
            copy($sitePath.'/src/config/config.php.in', $sitePath.'/src/config/config.php');
        }

        if (!@is_file($sitePath.'/.htaccess') && @is_file($sitePath.'/.htaccess.in')) {
            echo " - Creating `.htaccess` for front controller.\n";
            copy($sitePath.'/.htaccess.in', $sitePath.'/.htaccess');
            if (preg_match('/(.+)\/public_html\/(.*)/', $sitePath, $regs)) {
                $user = basename($regs[1]);
                $path = 'RewriteBase /~' . $user . '/' . $regs[2] . '/';
                $buf = file_get_contents($sitePath.'/.htaccess');
                $buf = str_replace('RewriteBase /', $path, $buf);
                file_put_contents($sitePath.'/.htaccess', $buf);
            }
        }

        if (!file_exists($sitePath.'/data')) {
            echo " - Creating site writable data directory `/data`.\n";
            mkdir($sitePath.'/data', \Tk\Config::getInstance()->getDirMask(), true);
            // TODO: Test if dir writable by apache/user running the site ????
        }

        // Update DB....

    }

}