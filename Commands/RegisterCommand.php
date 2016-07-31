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
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;


// include autoloader
//require_once __DIR__.'/../vendor/autoload.php';


class RegisterCommand extends BaseCommand
{

    private $runScripts = false;
    private static $kernelManipulator;
    private static $rootDir = __DIR__.'/../../../../';

    public function __construct()
    {
        require_once self::getRootDir().'vendor/autoload.php';
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('register')
            ->setDefinition([
                new InputArgument('packages', InputArgument::IS_ARRAY, 'Packages / Namespaces to register in the AppKernel'),
                new InputOption('namespace', 's', InputOption::VALUE_NONE, 'Argument is namespace'),
                new InputOption('install', 'i', InputOption::VALUE_OPTIONAL, 'Set to 0 or 1 to not install / install packages without asking'),
                new InputOption('no-scripts', null, InputOption::VALUE_NONE, 'Will not run post-install scripts in case of any package being installed'),

                // mirroring Require command
                new InputOption('dev', null, InputOption::VALUE_NONE, 'Add requirement to require-dev.'),
                new InputOption('prefer-source', null, InputOption::VALUE_NONE, 'Forces installation from package sources when possible, including VCS information.'),
                new InputOption('prefer-dist', null, InputOption::VALUE_NONE, 'Forces installation from package dist even for dev versions.'),
                new InputOption('no-progress', null, InputOption::VALUE_NONE, 'Do not output download progress.'),
                new InputOption('no-update', null, InputOption::VALUE_NONE, 'Disables the automatic update of the dependencies.'),
                new InputOption('update-no-dev', null, InputOption::VALUE_NONE, 'Run the dependency update with the --no-dev option.'),
                new InputOption('update-with-dependencies', null, InputOption::VALUE_NONE, 'Allows inherited dependencies to be updated with explicit dependencies.'),
                new InputOption('ignore-platform-reqs', null, InputOption::VALUE_NONE, 'Ignore platform requirements (php & ext- packages).'),
                new InputOption('sort-packages', null, InputOption::VALUE_NONE, 'Sorts packages when adding/updating a new dependency'),
                new InputOption('optimize-autoloader', 'o', InputOption::VALUE_NONE, 'Optimize autoloader during autoloader dump'),
                new InputOption('classmap-authoritative', 'a', InputOption::VALUE_NONE, 'Autoload classes from the classmap only. Implicitly enables `--optimize-autoloader`.'),
            ]);
    }

    public function getQuestion($question, $default, $sep = ':')
    {
        return $default ? sprintf('<info>%s</info> [<comment>%s</comment>]%s ', $question, $default, $sep) : sprintf('<info>%s</info>%s ', $question, $sep);
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('namespace')) return;
        if ($input->getOption('install')  === 0 || $input->getOption('install')  === false) return; // when no value is given we treat it as a yes

        // get composer instance and read local repository
        $composer = $this->getComposer(true);
        $localRepo = $composer->getRepositoryManager()->getLocalRepository();

        $packages = $input->getArgument('packages');

        $installed = array_map(function (PackageInterface $package) {
            return $package->getName();
        }, $localRepo->getPackages());

        $toInstall = array_diff($packages, $installed);

        if (count($toInstall)) {

            if(!$input->getOption('install')){

                $questionHelper = $this->getHelper('question');

                $question = new Question($this->getQuestion('Some packages are not present. Do you want to install them first?', 'yes'), 'yes');

                if ('yes' != $questionHelper->ask($input, $output, $question)) return;

            }

            $this->install(
                $output,
                $toInstall,
                $input->getOption('dev'),
                $input->getOption('prefer-source'),
                $input->getOption('no-progress'),
                $input->getOption('no-update'),
                $input->getOption('update-no-dev'),
                $input->getOption('update-with-dependencies'),
                $input->getOption('ignore-platform-reqs'),
                $input->getOption('sort-packages'),
                $input->getOption('optimize-autoloader'),
                $input->getOption('classmap-authoritative')
            );

            // reload local repository to match the new dependencies
            $localRepo->reload();

            // activate scripts only when we installed something
            $this->runScripts = true;

        }

    }

    private function install($output, Array $toInstall, $dev, $preferSource, $noProgress, $noUpdate, $updateNoDev, $updateWithDependencies, $ignorePlatformRegs, $sortPackages, $optimizeAutoloader, $classmapAuthoritative)
    {
        $in = [];
        $in['packages'] = $toInstall;

        // we don't want to run install scripts at this point
        $in['--no-scripts'] = true;

        // clone options from input to install command
        if ($dev) $in['--dev'] = true;
        if ($preferSource) $in['--prefer-source'] = true;
        if ($noProgress) $in['--no-progress'] = true;
        if ($noUpdate) $in['--no-update'] = true;
        if ($updateNoDev) $in['--update-no-dev'] = true;
        if ($updateWithDependencies) $in['--update-width-dependencies'] = true;
        if ($ignorePlatformRegs) $in['--ignore-platform-reqs'] = true;
        if ($sortPackages) $in['--sort-packages'] = true;
        if ($optimizeAutoloader) $in['--optimize-autoloader'] = true;
        if ($classmapAuthoritative) $in['--classmap-authoritative'] = true;

        // try to install the packages
        try {
            if($this->getApplication()->find('require')->run(new ArrayInput($in), $output)){
                throw new RuntimeException('An error occured while installation');
            };
        }catch (Exception $e){
            throw $e;
        }


    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        // get command arguments
        $packages = $input->getArgument('packages');

        // in case we got namespaces we just pass them directly to the registerWithNamespace method
        if ($input->getOption('namespace')) {
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
        }else{
            if($this->runScripts && !$input->getOption('no-scripts')){
                // run scripts if needed and not disabled
                $this->getApplication()->find('run-script')->run(new ArrayInput(['script' => 'post-install-cmd']), $output);
            }
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

        if('symfony-bundle' !== $package->getType()){
            $output->write('<error>'. $package->getName() .' is not a symfony bundle</error>', true);
            return;
        }

        // get package directory
        $dir = self::getRootDir() . 'vendor/' . $package->getName();

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

        require_once self::getRootDir() . 'app/AppKernel.php';

        // create kernelManipulator
        $kernelManipulator = self::$kernelManipulator ?
            self::$kernelManipulator :
            new KernelManipulator(new \AppKernel('dev', true));

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

    // make kernelManipulator mockable (only for testing)
    public static function setKernelManipulator($kernelManipulator){
        self::$kernelManipulator = $kernelManipulator;
    }

    public static function getRootDir(){
        return self::$rootDir;
    }

    public static function setRootDir($dir){
        self::$rootDir = $dir;
    }
}