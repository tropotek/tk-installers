<?php
/**
 * Created by PhpStorm.
 * User: mifsudm
 * Date: 7/20/15
 * Time: 10:15 AM
 */
namespace Tk\Composer\Script\Event;

use Composer\Script\Event;

/**
 * Class InitProject
 *
 *
 *
 */
class InitProject
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
        if (!@is_file($sitePath.'/src/config/config.php')) {
            echo " - Creating default `~/src/config/config.php`, edit this file to suit your server.\n";
            copy($sitePath.'/src/config/config.php.in', $sitePath.'/src/config/config.php');
        }

        if (!@is_file($sitePath.'/.htaccess')) {
            echo " - Creating `.htaccess` for front controller.\n";
            copy($sitePath.'/.htaccess.in', $sitePath.'/.htaccess');
            if (preg_match('/(.+)\/public_html\/(.*)/', $sitePath, $regs)) {
                $user = basename($regs[1]);
                $path = '  RewriteBase /~' . $user . '/' . $regs[2] . '/';
                $buf = file_get_contents($sitePath.'/.htaccess');
                $buf = str_replace('  RewriteBase /', $path, $buf);
                file_put_contents($sitePath.'/.htaccess', $buf);
            }
        }

        if (!is_dir($sitePath.'/data')) {
            echo " - Creating site writable data directory `/data`.\n";
            mkdir($sitePath.'/data', 0755, true);
            // TODO: Test if dir writable by apache/user running the site ????
        }

        // Update DB....

    }

}