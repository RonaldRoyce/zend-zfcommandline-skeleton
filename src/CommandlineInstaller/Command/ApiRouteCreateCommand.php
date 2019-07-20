<?php

namespace RonaldRoyce\Zfcommandline\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use ZF\Configuration\ConfigResource;
use ZF\Configuration\ConfigWriter;

class ApiRouteCreateCommand extends Command {
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
		$apiNamespace = $this->appConfig['zfcommandline']['namespaces']['api'];

		$this
			->setName(strtolower($apiNamespace) . ':route:create')
			->setDescription('Create a new API route')
			->addArgument('name', InputArgument::REQUIRED, 'Route name')
			->addArgument('controller', InputArgument::REQUIRED, 'Name of controller');
	}

	protected function validConsoleConfiguration($input) {
		if (!isset($this->appConfig['zfcommandline'])
			|| !isset($this->appConfig['zfcommandline']['namespaces'])
			|| !isset($this->appConfig['zfcommandline']['namespaces']['app'])
			|| !isset($this->appConfig['zfcommandline']['namespaces']['api'])
			|| !isset($this->appConfig['zfcommandline']['driver'])
		) {
			return false;
		}

		return true;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		if (!$this->validConsoleConfiguration($input)) {
			$output->writeln("<error>Missing/invalid console configuration</error>");
			print_r($this->appConfig);
			return false;
		}

		$this->appNamespace = $this->appConfig['zfcommandline']['namespaces']['app'];
		$this->apiNamespace = $this->appConfig['zfcommandline']['namespaces']['api'];
		$this->driverName = $this->appConfig['zfcommandline']['driver'];

		$routeName = $input->getArgument('name');
		$routeControllerName = $input->getArgument('controller');

		$fullControllerClassName = sprintf(
			'\%s\Controller\%s',
			$this->apiNamespace,
			$routeControllerName
		);

		if (!class_exists($fullControllerClassName)) {
			$output->writeln("<error>Controller $fullControllerClassName does not exist</error>");
			return false;
		}

		$this->addToConfig($routeName, $routeControllerName, $input, $output);
	}

	protected function addToConfig($routeName, $routeControllerName, $input, $output) {
		$config = $this->serviceManager->get('config');

		if (array_key_exists('router', $config) && array_key_exists('routes', $config['router']) &&
			array_key_exists($routeName, $config['router']['routes'])) {
			$output->writeln("<error>Route '$routeName' already exists</error>");
			$helper = $this->getHelper('question');
			$question = new ConfirmationQuestion('Overwrite route?', false);

			if (!$helper->ask($input, $output, $question)) {
				return false;
			}
		}

		$writer = $this->serviceManager->get(ConfigWriter::class);

		$writer->setUseClassNameScalars(true);
		$writer->setUseBracketArraySyntax(true);

		$configResource = new ConfigResource($config, $this->projectRootDir . '/module/' . $this->apiNamespace . '/config/module.config.php', $writer);

		$fullControllerName = sprintf(
			'\%s\Controller\%s',
			$this->apiNamespace,
			$routeControllerName
		);

		$configResource->patch([
			'router' => [
				'routes' => [
					"$routeName" => [
						'type' => \Zend\Router\Http\Segment::class,
						'options' => [
							'route' => "/api/$routeName" . "[/:id]",
							'constraints' => [
								'id' => '[0-9]+',
							],
							'defaults' => [
								'controller' => $fullControllerName,
							],
						],
					],
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
