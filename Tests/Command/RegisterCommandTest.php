<?php
/**
 * @author: Ulrich-Matthias Schäfer
 * @creation: 29.07.16 10:44
 * @package: vlipgo
 */

namespace Fuzzyma\Composer\RegisterBundlePlugin\Tests\Command;


use Composer\Composer;
use Composer\Package\Package;
use Composer\Repository\WritableArrayRepository;
use Fuzzyma\Composer\RegisterBundlePlugin\Commands\RegisterCommand;
use Composer\Repository\RepositoryManager;
use Mockery\Mock;
use Sensio\Bundle\GeneratorBundle\Manipulator\KernelManipulator;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Tester\CommandTester;

class RegisterCommandTest extends \PHPUnit_Framework_TestCase{

    public function testInstantiation()
    {
        $command = new RegisterCommand();
        $this->assertInstanceOf(RegisterCommand::class, $command);
    }


    /**
     * @param $options
     * @param $input
     * @param array $applicationMatcher
     * @param $kernelManipulatorMatcher
     * @dataProvider getDataForVariousInputs
     */
    public function testInputCombinations($options, $input, $applicationMatcher, $kernelManipulatorMatcher)
    {
        $command = $this->createRegisterCommand($applicationMatcher, $kernelManipulatorMatcher, $input);

        $tester = new CommandTester($command);
        $tester->execute($options);
    }

    public function testMissingPacketFails(){

        $command = $this->createRegisterCommand([0,0],$this->never());

        $tester = new CommandTester($command);
        $tester->execute(['packages' => ['fuzzyma/not-installed'], '--install' => 0]);

        $this->assertContains('Error: Could not find any of given packages', $tester->getDisplay());
    }

    public function testInstallationFails(){

        $this->expectException(RuntimeException::class);

        $command = $this->createRegisterCommand([1,0],$this->never(),"", 1);

        $tester = new CommandTester($command);
        $tester->execute(['packages' => ['fuzzyma/not-installed'], '--install' => 1]);

        $this->assertContains('An error occured while installation', $tester->getDisplay());
    }

    private function createRegisterCommand($applicationMatcher, $kernelManipulatorMatcher, $input = "", $installationReturn = 0){
        // set path so autoloader can be required
        RegisterCommand::setRootDir(__DIR__.'/../../');
        // mock the Kernelmanipulator
        RegisterCommand::setKernelManipulator($this->getKernelManipulatorMock($kernelManipulatorMatcher));

        $command = new RegisterCommand();
        $command->setComposer($this->getComposerMock());

        // set path to fixtures
        RegisterCommand::setRootDir(__DIR__.'/../Fixtures/');

        $application = $this->getApplicationMock($applicationMatcher[0], $applicationMatcher[1], $installationReturn);
        $application->setHelperSet($this->getHelperSet($input));

        $command->setApplication($application);
        //$command->setHelperSet($this->getHelperSet($input));

        return $command;
    }

    public function getDataForVariousInputs(){
        return [
            [
                ['packages' => ['fuzzyma/package-1']],
                "",
                [0,0],
                $this->exactly(1)
            ],
            [
                ['packages' => ['fuzzyma/package-1', 'fuzzyma/package-2']],
                "",
                [0,0],
                $this->exactly(2)
            ],
            [
                ['packages' => ['Awesome/Namespace', 'Another/One'], '--namespace' => true],
                "",
                [0,0],
                $this->exactly(2)
            ],
            [
                // supress installation with option --install=0
                ['packages' => ['fuzzyma/package-1', 'fuzzyma/package-2', 'fuzzyma/not-existent'], '--install' => 0],
                "",
                [0,0],
                $this->exactly(2)
            ],
            [
                // supress install with interactive input "no\n"
                ['packages' => ['fuzzyma/package-1', 'fuzzyma/package-2', 'fuzzyma/not-existent']],
                "no\n",
                [0,0],
                $this->exactly(2)
            ],
            [
                // install not-existent package with interactive input "yes\n"
                ['packages' => ['fuzzyma/package-1', 'fuzzyma/package-2', 'fuzzyma/not-existent']],
                "yes\n",
                [1,1],
                $this->exactly(3)
            ],
            [
                // install not-existent package with option --install=1
                ['packages' => ['fuzzyma/package-1', 'fuzzyma/package-2', 'fuzzyma/not-existent'], '--install' => 1],
                "",
                [1,1],
                $this->exactly(3)
            ],
            [
                // install not-existent package with option --install=1
                ['packages' => ['fuzzyma/package-1', 'fuzzyma/package-2', 'fuzzyma/not-existent'], '--install' => 1, '--no-scripts' => true],
                "",
                [1,0],
                $this->exactly(3)
            ],
            [
                ['packages' => ['Some/Odd/Namespace'], '--namespace' => true],
                "",
                [0,1],
                $this->exactly(1)
            ]
        ];
    }

    /**
     * @return \Composer\Composer
     */
    public function getComposerMock(){

        $packages = [
            $this->createPackage('fuzzyma/package-1', 'Some\\Cool\\Namespace'),
            $this->createPackage('fuzzyma/package-2', 'Some\\Other\\Namespace'),
            $this->createPackage('fuzzyma/package-3', 'Just\\Another\\Namespace'),
            $this->createPackage('fuzzyma/package-4', 'A\\Last\\Namespace')
        ];

        $extraPackage = $this->createPackage('fuzzyma/not-existent', 'Non\\Existent');

        /*$localRepo = \Mockery::mock(WritableArrayRepository::class.'[getPackages,reload]')
            ->shouldReceive('getPackages')
            ->andReturn($packages)
            ->shouldReceive('reload')
            ->andReturnUsing(function () use (&$packages, $extraPackage){
                $packages[] = $extraPackage;
            })
            ->getMock();


        $repoManager = \Mockery::mock(RepositoryManager::class.'[getLocalRepository]')
            ->shouldReceive('getLocalRepository')
            ->andReturn($localRepo)
            ->getMock();

        $composer = \Mockery::mock(Composer::class.'[getRepositoryManager]')->makePartial()
            ->shouldReceive('getRepositoryManager')
            ->andReturn($repoManager)
            ->getMock();*/


        $composer = $this->getMockBuilder(Composer::class)
            ->setMethods(['getRepositoryManager'])
            ->getMock();
        $repoManager = $this->getMockBuilder(RepositoryManager::class)
            ->disableOriginalConstructor()
            ->setMethods(['getLocalRepository'])
            ->getMock();
        $localRepo = $this->getMockBuilder(WritableArrayRepository::class)
            ->disableOriginalConstructor()
            ->setMethods(['getPackages', 'reload'])
            ->getMock();



        $localRepo->method('getPackages')->willReturnCallback(function() use (&$packages){
            return $packages;
        });

        //$localRepo->method('getPackages')->will($this->returnValue($packages));

        $localRepo->method('reload')->will($this->returnCallback(function() use (&$packages, $extraPackage, $localRepo){
            $packages[] = $extraPackage;
            return 0;
        }));

        $repoManager->method('getLocalRepository')->will($this->returnValue($localRepo));
        $composer->method('getRepositoryManager')->will($this->returnValue($repoManager));

        return $composer;
    }

    public function createPackage($name, $ns, $type = 'symfony-bundle'){
        $package = new Package($name, '1.0.0', '1.0.0');
        $package->setType($type);
        $package->setAutoload([
            'psr-4' => [$ns => '']
        ]);

        return $package;
    }

    public function getKernelManipulatorMock($matcher){
        $kernelManipulator = $this->getMockBuilder(KernelManipulator::class)
            ->disableOriginalConstructor()
            ->setMethods(['addBundle'])
            ->getMock();
        $kernelManipulator->expects($matcher)->method('addBundle');
        return $kernelManipulator;
    }

    /**
     * @param $requireCnt
     * @param $runScriptCnt
     * @return \Symfony\Component\Console\Application;
     */
    protected function getApplicationMock($requireCnt = 0, $runScriptCnt = 0, $commandReturns = 0){
        /*$command = \Mockery::mock(Command::class)
            ->makePartial()
            ->shouldReceive('run')
            ->andReturn(0)
            ->getMock();

        $application = \Mockery::mock(Application::class.'[find,run-script]')
            //->makePartial()
            ->shouldReceive('find')
            ->with('require')
            ->times($requireCnt)
            ->andReturn($command)
            ->shouldReceive('run-script')
            ->times($runScriptCnt)
            ->andReturn($command)
            ->getMock();*/

        $application = $this->getMockBuilder(Application::class)
            ->setMethods(['find'])
            ->getMock();

        $command = $this->getMockBuilder(Command::class)
            ->disableOriginalConstructor()
            ->setMethods(['run'])
            ->getMock();
        $command->method('run')->willReturn($commandReturns);

        if(!$requireCnt && !$runScriptCnt){
            $application->expects($this->never())->method('find')->with('require')->will($this->returnValue($command));
        }else if($requireCnt && !$runScriptCnt) {
            $application->expects($this->once())->method('find')->with('require')->will($this->returnValue($command));
        }else if($requireCnt && $runScriptCnt){
            $application->expects($this->at(0))->method('find')->with('require')->will($this->returnValue($command));
            $application->expects($this->at(1))->method('find')->with('run-script')->will($this->returnValue($command));
        }

        return $application;
    }

    protected function getHelperSet($input = "")
    {
        $question = new QuestionHelper();
        $question->setInputStream($this->getInputStream($input));

        return new HelperSet(array(new FormatterHelper(), $question));
    }

    protected function getInputStream($input)
    {
        $stream = fopen('php://memory', 'r+', false);
        fwrite($stream, $input . str_repeat("\n", 10));
        rewind($stream);

        return $stream;
    }

    public function tearDown() {
        \Mockery::close();
    }
}