<?php
namespace Tk\Composer;

use Composer\Script\Event;
use Tk\Config;
use Tk\Db\Pdo;
use Tk\Util\SqlMigrate;

/**
 * Class InitProject
 *
 * Default initProject installer class for the Tk framework V2
 * 
 * For this to work be sure not to have the composer.lock file in your gitignore
 * The composer.lock file is generated after an update and should be published 
 * with the released source files. Otherwise the 'composer install' command has issues.
 *
 * @author Michael Mifsud <info@tropotek.com>
 * @link http://www.tropotek.com/
 * @license Copyright 2007 Michael Mifsud
 */
class SetupEvent
{

    /**
     * @param Event $event
     */
    static function postInstall(Event $event)
    {
        self::init($event, true);
    }

    /**
     * @param Event $event
     */
    static function postUpdate(Event $event)
    {
        self::init($event, false);
    }

    static function vd($obj)
    {
        echo print_r($obj, true) . "\n";
    }

    /**
     * @param Event $event
     * @param bool $isInstall
     */
    static function init(Event $event, $isInstall = false)
    {
        try {
            $sitePath = $_SERVER['PWD'];
            $io = $event->getIO();
            $composer = $event->getComposer();
            $pkg = $composer->getPackage();

            // Get the PHP user that will be executing the scripts
            if (function_exists('posix_getpwuid')) {
                $a = posix_getpwuid(fileowner(__FILE__));
                $phpUser = $a['dir'];
            } else {
                $phpUser = `whoami`;
            }

            $name = substr($pkg->getName(), strrpos($pkg->getName(), '/')+1);
            $version = $pkg->getVersion();
            $releaseDate = $pkg->getReleaseDate()->format('Y-m-d H:i:s');
            $year = $pkg->getReleaseDate()->format('Y');
            $desc = wordwrap($pkg->getDescription(), 45, "\n               ");
            $authors = array();
            foreach ($pkg->getAuthors() as $auth) {
                $authors[] = $auth['name'];
            }
            $authors = implode(', ', $authors);

            $head = <<<STR
-----------------------------------------------------------
       $name Installer - (c) tropotek.com $year
----------------------------------------------------------- 
  Project:     $name
  Version:     $version
  Released:    $releaseDate
  Author:      $authors
  Description: $desc
-----------------------------------------------------------
STR;
            $io->write(self::bold($head));
            $configInFile = $sitePath . '/src/config/config.php.in';
            $configFile = $sitePath . '/src/config/config.php';
            $htInFile = $sitePath . '/.htaccess.in';
            $htFile = $sitePath . '/.htaccess';


            // Check existing config file
            $overwrite = false; // Overwrite the existing Config if it exists
            if (@is_file($configInFile)) {
                if ($isInstall && is_file($configFile)) {
                    $overwrite = $io->askConfirmation(self::warning('Do you want to replace the existing site configuration [N]: '), false);
                }
                if (!is_file($configFile)) {
                    $overwrite = true;
                }
            }
            
            // TODO Add check for ability to write to config and .htaccess and data folders, throw a warning and exit if not....
            // othrwise all the user sees is an exception when they try to view the site....Not Good. 

            // Create new config.php
            if ($overwrite) {
                $configContents = file_get_contents($configInFile);
                $io->write(self::green('Please answer the following questions to setup your new site configuration.'));
                $configVars = self::userInput($io);

                // Set dev/debug mode
                if ($composer->getPackage()->isDev()) {
                    $configVars['debug'] = 'true';
                    $configVars['system.log.level'] = '\Psr\Log\LogLevel::DEBUG';
                    $logPath = '/home/user/log/error.log';
                    if (!empty($phpUser)) {
                        $logPath = $phpUser . '/log/error.log';
                    }
                    $configVars['system.log.path'] = $logPath;
                }

                // update the config contents string
                foreach ($configVars as $k => $v) {
                    $configContents = self::setConfigValue($k, $v, $configContents);
                }

                $io->write(self::green('Saving config.php'));
                file_put_contents($configFile, $configContents);
            }

            // Create .htaccess
            if (@is_file($htInFile)) {
                if ($overwrite || !@is_file($htFile)) {
                    $io->write(self::green('Creating .htaccess file'));
                    copy($htInFile, $htFile);
                    $path = '/';
                    if (preg_match('/(.+)\/public_html\/(.*)/', $sitePath, $regs)) {
                        $user = basename($regs[1]);
                        $path = '/~' . $user . '/' . $regs[2] . '/';
                    }
                    $path = trim($io->ask(self::bold('What is the base URL path [' . $path . ']: '), $path));
                    if (!$path) $path = '/';
                    $io->write(self::green('Saving .htaccess file'));
                    $buf = file_get_contents($htFile);
                    $buf = str_replace('RewriteBase /', 'RewriteBase ' . $path, $buf);
                    file_put_contents($htFile, $buf);
                }
            }

            // Create data dir. TODO: Path could be created by user???
            if (!is_dir($sitePath . '/data')) {
                $io->write(self::green('Creating data directory `./data`'));
                mkdir($sitePath . '/data', \Tk\Config::getInstance()->getDirMask(), true);
            }

            if (is_file($configFile)) {
                // Setup DB
                include $configFile;
                
                $config = Config::getInstance();
                $db = Pdo::getInstance('db', $config->getGroup('db', true));
                $config->setDb($db);
                
                $drop = false;
                $tables = $db->getTableList();
                if ($isInstall) {
                    if (count($tables)) {
                        $drop = $io->askConfirmation(self::warning('Replace the existing database. WARNING: Existing data tables will be deleted! [N]: '), false);
                    }
                    if ($drop) {
                        $db->dropAllTables(true);
                    }
                }

                // Update Database tables
                $tables = $db->getTableList();
                if (count($tables)) {
                    $io->write(self::green('Database Upgrade:'));
                } else {
                    $io->write(self::green('Database Install:'));
                }

                $migrate = new SqlMigrate($db);
                $migrate->setTempPath($config->getTempPath());
                $files1 = $migrate->migrate($config->getSitePath() . '/vendor/ttek/tk-site/config/sql');
                $files2 = $migrate->migrate($config->getSrcPath() . '/config/sql');
                $files = array_merge($files1, $files2);
                if (count($files)) {
                    foreach ($files as $f) {
                        $io->write(self::green('  .' . $f));
                    }
                }
                $io->write(self::green('Database Migration Complete'));

                if (!count($tables)) {
                    $io->write(self::warning('As this is a new DB install login into the site using User: `admin` and Password: `password` and configure your site as needed.'));
                }
            }
        } catch (\Exception $e) {
            $io->write(self::red($e->__toString()));
        }

    }

    /**
     * @param Composer\IO\IOInterface $io
     * @return array
     */
    static function userInput($io)
    {
        $config = array();
        // Prompt for the database access
        $dbTypes = array('mysql', 'pgsql', 'sqlite');
        $dbTypes = array('mysql', 'pgsql');
        $io->write('<options=bold>');
        $i = $io->select('Select the DB type [mysql]: ', $dbTypes, 0);
        $io->write('</>');
        $config['db.type'] = $dbTypes[$i];

        $config['db.host'] = $io->ask(self::bold('Set the DB hostname [localhost]: '), 'localhost');
        $config['db.name'] = $io->askAndValidate(self::bold('Set the DB name: '), function ($data) { if (!$data) throw new \Exception('Please enter the DB name to use.');  return $data; });
        $config['db.user'] = $io->askAndValidate(self::bold('Set the DB user: '), function ($data) { if (!$data) throw new \Exception('Please enter the DB username.'); return $data; });
        $config['db.pass'] = $io->askAndValidate(self::bold('Set the DB password: '), function ($data) { if (!$data) throw new \Exception('Please enter the DB password.'); return $data; });

        return $config;
    }


    /**
     * updateConfig
     *
     * @param string $k
     * @param string $v
     * @param string $configContents
     * @return mixed
     */
    static function setConfigValue($k, $v, $configContents)
    {
        // filter out non quotable values
        if (is_string($v) && !preg_match('/^(true|false|null|new|array|function|\[|\\\\)/', $v)) {
            $v = self::quote($v);
        }
        $reg = '/\$config\[[\'"]('.preg_quote($k, '/').')[\'"]\]\s=\s[\'"]?(.+)[\'"]?;/';
        return preg_replace($reg, '\$config[\'$1\'] = ' . $v . ';', $configContents);
    }

    static function bold($str) { return '<options=bold>'.$str.'</>'; }

    static function green($str) { return '<fg=green>'.$str.'</>'; }

    static function warning($str) { return '<fg=yellow;options=bold>'.$str.'</>'; }

    static function red($str) { return '<fg=white;bg=red>'.$str.'</>'; }

    static function quote($str) { return '\''.$str.'\''; }

// IO Examples
//$output->writeln('<fg=green>foo</>');
//$output->writeln('<fg=black;bg=cyan>foo</>');
//$output->writeln('<bg=yellow;options=bold>foo</>');

}