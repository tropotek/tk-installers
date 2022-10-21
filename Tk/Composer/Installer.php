<?php
namespace Tk\Composer;

use Composer\IO\IOInterface;
use Composer\Script\Event;
use Tk\Db\Pdo;
use Tk\Db\Util\SqlMigrate;

/**
 * Default initProject installer class for the Tk framework V2
 *
 * For this to work be sure not to have the composer.lock file in your gitignore
 * The composer.lock file is generated after an update and should be published
 * with the released source files. Otherwise, the 'composer install' command has issues.
 *
 * @author Tropotek <http://www.tropotek.com/>
 * @see https://getcomposer.org/doc/articles/plugins.md
 */
class Installer
{

    /**
     * @param Event $event
     */
    static function postInstall(Event $event): void
    {
        self::init($event, true);
    }

    /**
     * @param Event $event
     */
    static function postUpdate(Event $event): void
    {
        self::init($event, false);
    }

    static function init(Event $event, bool $isInstall = false): void
    {
        try {
            $sitePath = $_SERVER['PWD'];
            $io = $event->getIO();
            $composer = $event->getComposer();
            $pkg = $composer->getPackage();

            $pkgPaths = ['/vendor/ttek', '/plugin', '/http/theme'];
            // TODO: search these paths for packages and load files => [config.php, routes.php, ...]
            //       Or maybe we need to load an object that can init a lib as needed (simple type of bundle???)
            //         eg: \Tk\Framework. \Dom\Domtemplate


            // Get the PHP user that will be executing the scripts
            if (function_exists('posix_getpwuid')) {
                $a = posix_getpwuid(fileowner(__FILE__));
                $phpUser = $a['dir'];
            } else {
                $phpUser = `whoami`;
            }

            $name = substr($pkg->getName(), strrpos($pkg->getName(), '/')+1);
            $version = $pkg-> getFullPrettyVersion();
            $releaseDate = $pkg->getReleaseDate()->format('Y-m-d H:i:s');
            $year = $pkg->getReleaseDate()->format('Y');
            $desc = wordwrap($pkg->getDescription(), 45, "\n               ");
            $authors = [];
            foreach ($pkg->getAuthors() as $auth) {
                $authors[] = $auth['name'];
            }
            $authors = implode(', ', $authors);


            $head = <<<STR
-----------------------------------------------------------
       $name Plugin - (c) tropotek.com $year
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
                if (!is_file($configFile)) {
                    $overwrite = true;
                } else if ($isInstall) {
                    $overwrite = $io->askConfirmation(self::warning('Do you want to replace the existing site configuration [N]: '), false);
                }
            }

            // Create new config.php
            if ($overwrite) {
                $configContents = file_get_contents($configInFile);
                $io->write(self::green('Please answer the following questions to setup your new site configuration.'));
                $configVars = self::userDbInput($io);

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

            // Do any site install setup, with new Config object
            if (is_file($configFile) && is_file($sitePath.'/_prepend.php')) {
                include $sitePath.'/_prepend.php';
                $config = \Tk\Config::instance();

                $mask = 0777;
                // Create Data path and clear any existing Cache path
                if (!is_dir($config->getDataPath())) {
                    $io->write(self::green('Creating data directory: ' . $config->getDataPath()));
                    mkdir($config->getDataPath(), $mask, true);
                } else {    // Clear existing Caches
                    if (is_dir($config->getCachePath())) {
                        $io->write(self::green('Clearing cache: ' . $config->getCachePath()));
                        \Tk\FileUtil::rmdir($config->getCachePath());
                    }
                }

                // -----------------  DM Migration START  -----------------
                $db = Pdo::instance('default', $config->getGroup('db.default', true));

                $drop = false;
                $tables = $db->getTableList();
                if ($isInstall) {
                    if (count($tables))
                        $drop = $io->askConfirmation(self::warning('Replace the existing database. WARNING: Existing data tables will be deleted! [N]: '), false);
                    if ($drop) {
                        $exclude = [];
                        if ($config->isDebug()) {
                            $exclude = [$config->get('session.db_table')];
                        }
                        $db->dropAllTables(true, $exclude);
                    }
                }

                // Update Database tables
                $tables = $db->getTableList();
                if (count($tables)) {
                    $io->write(self::green('Database Upgrade:'));
                } else {
                    $io->write(self::green('Database Install:'));
                }

                // Migrate new SQL files
                $migrate = new SqlMigrate($db);
                $migrate->setTempPath($config->getTempPath());


                // TODO: find a better solution than passing paths from the config.
                $migrateList = ['App Sql' => $config->getBasePath() . '/config'];
                if ($config->get('sql.migrate.list')) {
                    $migrateList = $config->get('sql.migrate.list');
                }
                // we should refactor the migration process
                $migrate->migrateList($migrateList, function (string $str, SqlMigrate $m) use ($io) {
                    $io->write(self::green($str));
                });


                $io->write(self::green('Database Migration Complete'));
                if ($isInstall) {
                    $io->write('Open the site in a browser to complete the site setup: ' . \Tk\Uri::create('/')->toString());
                }
            }
        } catch (\Exception $e) {
            $io->write(self::red($e->__toString()));
        }
    }

    /**
     * @throws \Exception
     */
    static function userDbInput(IOInterface $io): array
    {
        $config = [];
        // Prompt for the database access
//        $dbTypes = ['mysql', 'pgsql', 'sqlite'];
//        $dbTypes = ['mysql', 'pgsql'];
        $dbTypes = ['mysql'];

        $i = 0;
        if (count($dbTypes) > 1) {
            $io->write('<options=bold>');
            $i = $io->select('Select the DB type [mysql]: ', $dbTypes, 0);
        }
        $io->write('</>');
        $config['db.default.type'] = $dbTypes[$i];
        $config['db.default.host'] = $io->ask(self::bold('Set the DB hostname [localhost]: '), 'localhost');
        $config['db.default.name'] = $io->askAndValidate(self::bold('Set the DB name: '), function ($data) { if (!$data) throw new \Exception('Please enter the DB name to use.');  return $data; });
        $config['db.default.user'] = $io->askAndValidate(self::bold('Set the DB user: '), function ($data) { if (!$data) throw new \Exception('Please enter the DB username.'); return $data; });
        $config['db.default.pass'] = $io->askAndValidate(self::bold('Set the DB password: '), function ($data) { if (!$data) throw new \Exception('Please enter the DB password.'); return $data; });

        return $config;
    }

    static function setConfigValue(string $k, string $v, string $configContents): array|string|null
    {
        // filter out non quotable values
        if (!preg_match('/^(true|false|null|new|array|function|\[|\\\\)/', $v)) {
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

    static function vd($obj)
    {
        echo print_r($obj, true) . "\n";
    }
}
