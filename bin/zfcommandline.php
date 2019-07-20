#!/usr/bin/env php
<?php
namespace RonaldRoyce\Zfcommandline;

require __DIR__ . '/../../autoload.php';

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Zend\Stdlib\ArrayUtils;
use Zend\Mvc\Application as ZendApplication;
use Zend\ConfigAggregator\ConfigAggregator;
use Zend\ConfigAggregator\PhpFileProvider;
use Zend\Code\Generator\ValueGenerator;

// use rroyce\zfcommandline\Controller\Plugin\ConsoleParams;

use RonaldRoyce\Zfcommandline\Command\ControllerCreateCommand;
use RonaldRoyce\Zfcommandline\Command\ModelCreateCommand;
use RonaldRoyce\Zfcommandline\Command\AppRouteCreateCommand;
use RonaldRoyce\Zfcommandline\Command\ApiRouteCreateCommand;
use RonaldRoyce\Zfcommandline\Command\ViewCreateCommand;
use RonaldRoyce\Zfcommandline\Command\DbQueryGenerateCommand;
use RonaldRoyce\Zfcommandline\Command\ConfigCommand;

$appConfig = require __DIR__ . '/../../../config/application.config.php';
if (file_exists(__DIR__ . '/../../../config/development.config.php')) {
    $appConfig = ArrayUtils::merge(
    $appConfig, require __DIR__ . '/../../../config/development.config.php'
    );
}

if (file_exists(__DIR__ . '/../../../config/autoload/global.php')) {
    $appConfig = ArrayUtils::merge(
    $appConfig, require __DIR__ . '/../../../config/autoload/global.php' 
    );
}

if (file_exists(__DIR__ . '/../../../config/autoload/local.php')) {
    $appConfig = ArrayUtils::merge(
    $appConfig, require __DIR__ . '/../../../config/autoload/local.php'
    );
}


if (isset($appConfig['zfcommandline'])
    && isset($appConfig['zfcommandline']['namespaces'])
    && isset($appConfig['zfcommandline']['namespaces']['app'])
    && isset($appConfig['zfcommandline']['namespaces']['api'])
    && isset($appConfig['zfcommandline']['driver'])
   ) 
{
	if (file_exists(__DIR__ . '/../../../module/' . $appConfig['zfcommandline']['namespaces']['app'] . '/config/module.config.php')) {
	    $appConfig = ArrayUtils::merge(
	    $appConfig, require __DIR__ . '/../../../module/' . $appConfig['zfcommandline']['namespaces']['app'] . '/config/module.config.php'
	    );
	}


	if (file_exists(__DIR__ . '/../../../module/' . $appConfig['zfcommandline']['namespaces']['api'] . '/config/module.config.php')) {
	    $appConfig = ArrayUtils::merge(
	    $appConfig, require __DIR__ . '/../../../module/' . $appConfig['zfcommandline']['namespaces']['api'] . '/config/module.config.php'
	    );
	}
}

$zendApplication = ZendApplication::init($appConfig);
$serviceManager = $zendApplication->getServiceManager();

$projectRootDir = realpath(dirname(__FILE__) . "/../../../");

$application = new Application();

if (isset($appConfig['zfcommandline'])
    && isset($appConfig['zfcommandline']['namespaces'])
    && isset($appConfig['zfcommandline']['namespaces']['app'])
    && isset($appConfig['zfcommandline']['namespaces']['api'])
    && isset($appConfig['zfcommandline']['driver'])
   ) 
{
	$controllerCreateCommand = new ControllerCreateCommand($serviceManager, $projectRootDir, $appConfig);
	$modelCreateCommand = new ModelCreateCommand($serviceManager, $projectRootDir, $appConfig);
	$appRouteCreateCommand = new AppRouteCreateCommand($serviceManager, $projectRootDir, $appConfig);
	$apiRouteCreateCommand = new ApiRouteCreateCommand($serviceManager, $projectRootDir, $appConfig);
	$viewCreateCommand = new ViewCreateCommand($serviceManager, $projectRootDir, $appConfig); 
	$dbQueryGenerateCommand = new DbQueryGenerateCommand($serviceManager, $projectRootDir, $appConfig);
	$application->add($controllerCreateCommand);
	$application->add($modelCreateCommand);
	$application->add($appRouteCreateCommand);
	$application->add($apiRouteCreateCommand);
	$application->add($viewCreateCommand);
	$application->add($dbQueryGenerateCommand);
}

$configCommand = new ConfigCommand($serviceManager, $projectRootDir, $appConfig);

$application->add($configCommand);

$application->run();
