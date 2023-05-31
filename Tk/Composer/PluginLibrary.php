<?php
namespace Tk\Composer;

use Composer\Package\PackageInterface;
use Composer\Installer\LibraryInstaller;

class PluginLibrary extends LibraryInstaller
{
    public function getInstallPath(PackageInterface $package)
    {
        $a = explode('/', $package->getPrettyName());
        if (count($a) != 2) {
            throw new \InvalidArgumentException(
                'Unable to install plugin package should be in the format ttek-plg/<name>'
            );
        }
        return 'plugin/'.$a[1];
    }

    public function supports($packageType)
    {
        return preg_match('/(oum|ttek)-plugin$/', $packageType);
        //return ('uom-plugin' === $packageType) || ('ttek-plugin' === $packageType);
    }
}