<?php
namespace Tk\Installer;

use Composer\Package\PackageInterface;
use Composer\Installer\LibraryInstaller;

class Theme extends LibraryInstaller
{
    public function getInstallPath(PackageInterface $package)
    {
        $a = explode('/', $package->getPrettyName());
        if (count($a) != 2) {
            throw new \InvalidArgumentException(
                'Unable to install plugin package should be in the format ttek-theme/<name>'
            );
        }
        return 'html/'.$a[1];
    }

    public function supports($packageType)
    {
        return ('uom-theme' === $packageType) || ('ttek-theme' === $packageType);
    }
}