<?php
/**
 * @author: Ulrich-Matthias Schäfer
 * @creation: 13.07.16 10:16
 * @package: ComposerRegisterBundlePlugin
 */

namespace Fuzzyma\Composer\RegisterBundlePlugin;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;

class ComposerRegisterBundlePlugin implements PluginInterface, Capable
{
    public function activate(Composer $composer, IOInterface $io)
    {
    }

    public function getCapabilities()
    {
        return array(
            'Composer\Plugin\Capability\CommandProvider' => 'Fuzzyma\Composer\RegisterBundlePlugin\Commands\CommandProvider'
        );
    }
}