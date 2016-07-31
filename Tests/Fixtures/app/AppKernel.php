<?php

/**
 * @author: Ulrich-Matthias Schäfer
 * @creation: 29.07.2016 15:47
 * @package: RegisterBundlePlugin
 */

use \Symfony\Component\HttpKernel\Kernel;
use \Symfony\Component\Config\Loader\LoaderInterface;

class AppKernel extends Kernel
{
    public function registerBundles()
    {
        // just a Fixture
        return [];
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        // just a Fixture
    }
}