<?php
/**
 * @author: Ulrich-Matthias Schäfer
 * @creation: 13.07.16 23:21
 * @package: ComposerRegisterBundlePlugin
 */

namespace Fuzzyma\Composer\RegisterBundlePlugin\Commands;

use Composer\Command\BaseCommand;
use Composer\Installer\PackageEvent;
use Composer\Package\PackageInterface;
use Sensio\Bundle\GeneratorBundle\Manipulator\KernelManipulator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RegisterCommand extends BaseCommand
{
    protected function configure()
    {
        $this->setName('register')
            ->addArgument('package', InputArgument::IS_ARRAY, 'Packages / Namespaces to register in the AppKernel')
            ->addOption('--ns', null, InputOption::VALUE_NONE, 'Argument is namespace');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // include required files
        require_once 'vendor/autoload.php';

        // get command arguments
        $packages = $input->getArgument('package');

        // in case we got namespaces we just pass them directly to the registerWithNamespace method
        if ($input->getOption('ns')) {
            foreach ($packages as $ns) {
                self::registerWithNamespace($ns);
            }
            return;
        }

        // get composer instance and read local repository
        $composer = $this->getComposer(true);
        $localRepo = $composer->getRepositoryManager()->getLocalRepository();

        $cnt = 0;

        // search all packages for the specified one
        foreach ($localRepo->getPackages() as $package) {

            if (!in_array($package->getName(), $packages)) continue;

            ++$cnt;
            self::registerWithPackage($package, $output);

        }

        // warn if packages could not be found
        if (!$cnt) {
            $output->write('<error>Error: Could not find any of given packages</error>', true);
        }

    }

    /**
     * @param $namespace
     * @param null $output
     *
     * Register a Bundle with namespace given
     */
    public static function registerWithNamespace($namespace, $output = null)
    {
        try {
            if (self::writeKernel($namespace)) {
                $output && $output->write('<info>Added Bundle ' . $namespace . '</info>', true);
            } else {
                $output && $output->write('<error>Error: Could not add Bundle ' . $namespace . '</error>', true);
            }
        } catch (\RuntimeException $e) {
            $output && $output->write('<error>Error: Could not add Bundle ' . $namespace . '</error>', true);
            $output && $output->write('<error>Bundle is already registered</error>', true);
        }
    }

    /**
     * @param PackageInterface $package
     * @param $output
     *
     * Register a Bundle with package given
     */
    private static function registerWithPackage(PackageInterface $package, $output)
    {

        // get package directory
        $dir = 'vendor/' . $package->getName();

        // scan directory for Bundle file
        $needle = 'Bundle.php';
        $bundleFile = array_merge(array_filter(scandir($dir), function ($haystack) use ($needle) {
            return $needle === "" || (($temp = strlen($haystack) - strlen($needle)) >= 0 && strpos($haystack, $needle, $temp) !== false);
        }));

        if (count($bundleFile) == 0) {
            $output->write('<error>Error: Bundle not fount in folder ' . $dir . '</error>', true);
            return;
        }

        // create fully qualified name from bundle class
        $bundleClass = str_replace('.php', '', $bundleFile[0]);

        $autoload = $package->getAutoload();
        if (isset($autoload['psr-4'])) {
            $fullyQualifiedNamespace = array_keys($package->getAutoload()['psr-4'])[0] . $bundleClass;
        } elseif (isset($autoload['psr-0'])) {
            $fullyQualifiedNamespace = array_keys($package->getAutoload()['psr-0'])[0] . $bundleClass;
        } else {
            $output->write('<error>Error: Cannot read package namespace from autoloading section</error>', true);
            return;
        }

        self::registerWithNamespace($fullyQualifiedNamespace, $output);
    }

    /**
     * @param $fullyQualifiedNamespace
     * @return bool
     *
     * writes to the kernel
     */
    private static function writeKernel($fullyQualifiedNamespace)
    {

        $fullyQualifiedNamespace = str_replace('/', '\\', $fullyQualifiedNamespace);

        require_once 'app/AppKernel.php';

        // create kernelManipulator
        $kernelManipulator = new KernelManipulator(new \AppKernel('dev', true));

        // write to the AppKernel.php
        return $kernelManipulator->addBundle($fullyQualifiedNamespace);

    }

    /**
     * @param PackageEvent $event
     *
     * Can be added as post-install-cmd in composer.json
     */
    public static function registerBundle(PackageEvent $event)
    {
        $package = $event->getOperation()->getPackage();
        if ('symfony-bundle' === $package->getType()) {
            self::registerWithPackage($package, $output = $event->getIo());
        }
    }
}