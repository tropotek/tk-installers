<?php
namespace Tk\Installer;

use Composer\Package\PackageInterface;
use Composer\Installer\LibraryInstaller;

class Asset extends LibraryInstaller
{
    public function getInstallPath(PackageInterface $package)
    {
        $a = explode('/', $package->getPrettyName());
        if (count($a) != 2) {
            throw new \InvalidArgumentException(
                'Unable to install plugin package should be in the format ttek-asset/<name>'
            );
        }
        return 'assets/'.$a[1];
    }

    public function supports($packageType)
    {
        return ('uom-asset' === $packageType) || ('ttek-asset' === $packageType);
    }
}