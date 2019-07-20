<?php

namespace RonaldRoyce\Zfcommandline\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use ZF\Configuration\ConfigResource;
use ZF\Configuration\ConfigWriter;

class ControllerCreateCommand extends Command {
	private $serviceManager;
	private $projectRootDir;
	private $appConfig;
	private $appNamespace;
	private $apiNamespace;
	private $driverName;

	public function __construct($serviceManager, $projectRootDir, $appConfig) {
		$this->serviceManager = $serviceManager;
		$this->projectRootDir = $projectRootDir;
		$this->appConfig = $appConfig;

		$this->appNamespace = $appConfig['zfcommandline']['namespaces']['app'];
		$this->apiNamespace = $appConfig['zfcommandline']['namespaces']['api'];

		parent::__construct();
	}

	protected function configure() {
		$this
			->setName(strtolower($this->appNamespace) . ':controller:create')
			->setDescription('Create a new controller')
			->addArgument('name', InputArgument::REQUIRED, 'Name of controller')
			->addArgument('model', InputArgument::REQUIRED, 'Name of API model');
	}

	protected function validConsoleConfiguration($input) {
		if (!isset($this->consoleConfig['console'])
			|| !isset($this->consoleConfig['console']['namespaces'])
			|| !isset($this->consoleConfig['console']['namespaces']['app'])
			|| !isset($this->consoleConfig['console']['namespaces']['api'])
			|| !isset($this->consoleConfig['console']['driver'])
		) {
			return false;
		}

		return true;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		if (!$this->validConsoleConfiguration($input)) {
			$output->writeln("<error>Missing/invalid console configuration</error>");
			print_r($this->consoleConfig);
			return false;
		}

		$this->appNamespace = $this->consoleConfig['console']['namespaces']['app'];
		$this->apiNamespace = $this->consoleConfig['console']['namespaces']['api'];
		$this->driverName = $this->consoleConfig['console']['driver'];

		$controllerName = $input->getArgument('name') . "Controller";
		$modelName = $input->getArgument('model') . "Model";

		$fullModelClassName = sprintf(
			'\%s\Model\%s',
			$this->apiNamespace,
			$modelName
		);
		if (!class_exists($fullModelClassName)) {
			$output->writeln("<error>Model $fullModelClassName does not exist</error>");
			return false;
		}

		if (!$this->createController($controllerName, $modelName, $input, $output)) {
			return;
		}

		if (!$this->createControllerFactory($controllerName, $modelName, $input, $output)) {
			return;
		}

		$this->addToConfig($controllerName, $input, $output);
	}

	protected function addToConfig($controllerName, $input, $output) {
		$config = $this->serviceManager->get('config');
		$writer = $this->serviceManager->get(ConfigWriter::class);

		$writer->setUseClassNameScalars(true);
		$writer->setUseBracketArraySyntax(true);

		$configResource = new ConfigResource($config, $this->projectRootDir . '/module/' . $this->appNamespace . '/config/module.config.php', $writer);

		$fullClassName = sprintf(
			'%s\\Controller\\%s',
			$this->appNamespace,
			$controllerName
		);

		$factoryClassName = sprintf(
			'%s\\Factory\\%sFactory',
			$this->appNamespace,
			$controllerName
		);

		$configResource->patch([
			'controllers' => [
				'factories' => [
					$fullClassName => $factoryClassName,
				],
			],
		], true);

		$configResource->patch([
			'service_manager' => [
				'factories' => [
					$fullClassName => $factoryClassName,
				],
			],
		], true);
	}

	protected function createController($controllerName, $model, $input, $output) {
		$templateFilename = $this->projectRootDir . "/console/Templates/Controller.tpl";

		$templateContents = file_get_contents($templateFilename);

		$templateContents = str_replace("%APPMODULE%", $this->appNamespace, $templateContents);
		$templateContents = str_replace("%APIMODULE%", $this->apiNamespace, $templateContents);
		$templateContents = str_replace("%APIMODELVARIABLE%", lcfirst(ucwords($model)), $templateContents);
		$templateContents = str_replace("%APIMODEL%", $model, $templateContents);
		$templateContents = str_replace("%CONTROLLERNAME%", $controllerName, $templateContents);

		$outputFilename = $this->projectRootDir . "/module/" . $this->appNamespace . "/src/Controller/$controllerName.php";

		if (file_exists($outputFilename)) {
			$output->writeln("<error>Application controller $controllerName already exists</error>");
			$helper = $this->getHelper('question');
			$question = new ConfirmationQuestion('Overwrite file?', false);

			if (!$helper->ask($input, $output, $question)) {
				return false;
			}
		}

		file_put_contents($outputFilename, $templateContents);

		$output->writeln("<info>Application controller $controllerName has been created succesfully</info>");

		return true;
	}

	protected function createControllerFactory($controllerName, $model, $input, $output) {
		$controllerFactoryName = $controllerName . "Factory";

		$templateFilename = $this->projectRootDir . "/console/Templates/ControllerFactory.tpl";

		$templateContents = file_get_contents($templateFilename);

		$templateContents = str_replace("%APPMODULE%", $this->appNamespace, $templateContents);
		$templateContents = str_replace("%APIMODULE%", $this->apiNamespace, $templateContents);
		$templateContents = str_replace("%APIMODELVARIABLE%", lcfirst(ucwords($model)), $templateContents);
		$templateContents = str_replace("%APIMODEL%", $model, $templateContents);
		$templateContents = str_replace("%CONTROLLERNAME%", $controllerName, $templateContents);
		$templateContents = str_replace("%DRIVERNAME%", $this->driverName, $templateContents);

		$outputFilename = $this->projectRootDir . "/module/" . $this->appNamespace . "/src/Factory/$controllerFactoryName.php";

		if (file_exists($outputFilename)) {
			$output->writeln("<error>Application controller factory $controllerFactoryName" . " already exists</error>");
			$helper = $this->getHelper('question');
			$question = new ConfirmationQuestion('Overwrite file?', false);

			if (!$helper->ask($input, $output, $question)) {
				return false;
			}
		}

		file_put_contents($outputFilename, $templateContents);

		$output->writeln("<info>Application controller $controllerFactoryName has been created succesfully</info>");

		return true;
	}
}
