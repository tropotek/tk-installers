<?php
namespace Tk\Installer;

use Composer\Package\PackageInterface;
use Composer\Installer\LibraryInstaller;

/**
 * Class Asset
 *
 * @author Michael Mifsud <info@tropotek.com>
 * @see http://www.tropotek.com/
 * @license Copyright 2016 Michael Mifsud
 */
class Asset extends LibraryInstaller
{
    /**
     * {@inheritDoc}
     */
    public function getInstallPath(PackageInterface $package)
    {
        /*
        $prefix = substr($package->getPrettyName(), 0, 23);
        if ('phpdocumentor/template-' !== $prefix) {
            throw new \InvalidArgumentException(
                'Unable to install template, phpdocumentor templates '
                .'should always start their package name with '
                .'"phpdocumentor/template-"'
            );
        }
        */
        // tropotek/jquery
        $a = explode('/', $package->getPrettyName());
        return 'assets/'.$a[1];
    }

    /**
     * {@inheritDoc}
     */
    public function supports($packageType)
    {
        return ('uom-asset' === $packageType) || ('ttek-asset' === $packageType);
    }
}