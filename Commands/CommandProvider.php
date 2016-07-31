<?php
/**
 * @author: Ulrich-Matthias Schäfer
 * @creation: 13.07.16 13:13
 * @package: ComposerRegisterBundlePlugin
 */

namespace Fuzzyma\Composer\RegisterBundlePlugin\Commands;

use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;

class CommandProvider implements CommandProviderCapability
{
    public function getCommands()
    {
        return [new RegisterCommand()];
    }
}