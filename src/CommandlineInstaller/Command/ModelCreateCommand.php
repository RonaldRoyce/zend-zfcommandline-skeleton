<?php

namespace RonaldRoyce\Zfcommandline\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Zend\View\Model\ViewModel;
use ZF\Configuration\ConfigResource;
use ZF\Configuration\ConfigWriter;

class ModelCreateCommand extends Command {
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
		$appNamespace = $this->appConfig['zfcommandline']['namespaces']['app'];

		$this
			->setName(strtolower($appNamespace) . ':model:create')
			->setDescription('Create a new model')
			->addArgument('name', InputArgument::REQUIRED, 'Name of model')
			->addArgument('table', InputArgument::REQUIRED, 'Name of table');
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

		$modelName = $input->getArgument('name') . "Model";
		$tableName = $input->getArgument('table');
		$factoryName = $modelName . "ControllerFactory";
		$controllerName = $modelName . "Controller";

		if (!$this->createModel($modelName, $tableName, $input, $output)) {
			return;
		}

		if (!$this->createModelController($modelName, $controllerName, $input, $output)) {
			return;
		}

		if (!$this->createModelControllerFactory($modelName, $controllerName, $factoryName, $input, $output)) {
			return;
		}

		$this->addToConfig($controllerName, $factoryName, $input, $output);
	}

	protected function addToConfig($controllerName, $factoryName, $input, $output) {
		$config = $this->serviceManager->get('config');
		$writer = $this->serviceManager->get(ConfigWriter::class);

		$writer->setUseClassNameScalars(true);
		$writer->setUseBracketArraySyntax(true);

		$configResource = new ConfigResource($config, $this->projectRootDir . '/module/' . $this->apiNamespace . '/config/module.config.php', $writer);

		$fullClassName = sprintf(
			'%s\\Controller\\%s',
			$this->apiNamespace,
			$controllerName
		);

		$factoryClassName = sprintf(
			'%s\\Factory\\%s',
			$this->apiNamespace,
			$factoryName
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

	protected function getSourcePath($module, $serviceName) {
		$sourcePath = $this->projectRootDir . "/modules/$module";

		if (!file_exists($sourcePath)) {
			mkdir($sourcePath, 0775, true);
		}

		return $sourcePath;
	}

	public function createFactoryClass($module, $serviceName) {
		$srcPath = $this->getSourcePath($serviceName);

		$classResource = sprintf('%sResource', $serviceName);
		$className = sprintf('%sResourceFactory', $serviceName);
		$classPath = sprintf('%s/%s.php', $srcPath, $className);

		if (file_exists($classPath)) {
			throw new Exception\RuntimeException(sprintf(
				'The resource factory "%s" already exists',
				$className
			));
		}

		$view = new ViewModel([
			'module' => $module,
			'resource' => $serviceName,
			'classfactory' => $className,
			'classresource' => $classResource,
			'version' => '1.0',
		]);
		if (!$this->createClassFile($view, 'factory', $classPath)) {
			throw new Exception\RuntimeException(sprintf(
				'Unable to create resource factory "%s"; unable to write file',
				$className
			));
		}

		$fullClassName = sprintf(
			'%s\\V%s\\Rest\\%s\\%s',
			$module,
			$this->moduleEntity->getLatestVersion(),
			$serviceName,
			$className
		);

		return $fullClassName;
	}

	protected function createClassFile(ViewModel $model, $type, $classPath) {
		$renderer = $this->getRenderer();
		$template = $this->injectResolver($renderer, $type);
		$model->setTemplate($template);

		if (file_put_contents(
			$classPath,
			'<' . "?php\n" . $renderer->render($model)
		)) {
			return true;
		}

		return false;
	}

	protected function createAppController($controllerName, $model, $input, $output) {
		$templateFilename = $this->projectRootDir . "/console/Templates/AppController.tpl";

		$templateContents = file_get_contents($templateFilename);

		$templateContents = str_replace("%APPMODULE%", $this->appNamespace, $templateContents);
		$templateContents = str_replace("%APIMODULE%", $this->apiNamespace, $templateContents);
		$templateContents = str_replace("%APIMODELVARIABLE%", lcfirst(ucwords($model)), $templateContents);
		$templateContents = str_replace("%APIMODEL%", $model, $templateContents);
		$templateContents = str_replace("%CONTROLLERNAME%", $controllerName, $templateContents);

		$outputFilename = $this->projectRootDir . "module/" . $this->appNamespace . "/src/Controller/$controllerName.php";

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

	protected function createModel($modelName, $tableName, $input, $output) {
		$templateFilename = $this->projectRootDir . "/console/Templates/Model.tpl";

		$templateContents = file_get_contents($templateFilename);

		$templateContents = str_replace("%APIMODULE%", $this->apiNamespace, $templateContents);
		$templateContents = str_replace("%APIMODEL%", $modelName, $templateContents);
		$templateContents = str_replace("%TABLENAME%", $tableName, $templateContents);

		$outputFilename = $this->projectRootDir . "/module/" . $this->apiNamespace . "/src/Model/$modelName.php";

		if (file_exists($outputFilename)) {
			$output->writeln("<error>Model $modelName already exists</error>");
			$helper = $this->getHelper('question');
			$question = new ConfirmationQuestion('Overwrite file?', false);

			if (!$helper->ask($input, $output, $question)) {
				return false;
			}
		}

		file_put_contents($outputFilename, $templateContents);

		$output->writeln("<info>Model $modelName has been created succesfully</info>");

		return true;
	}

	protected function createModelController($modelName, $controllerName, $input, $output) {
		$templateFilename = $this->projectRootDir . "/console/Templates/ModelController.tpl";

		$templateContents = file_get_contents($templateFilename);

		$templateContents = str_replace("%APIMODULE%", $this->apiNamespace, $templateContents);
		$templateContents = str_replace("%APIMODELVARIABLE%", lcfirst(ucwords($modelName)), $templateContents);
		$templateContents = str_replace("%APIMODEL%", $modelName, $templateContents);
		$templateContents = str_replace("%CONTROLLERNAME%", $controllerName, $templateContents);

		$outputFilename = $this->projectRootDir . "/module/" . $this->apiNamespace . "/src/Controller/$controllerName.php";

		if (file_exists($outputFilename)) {
			$output->writeln("<error>Model controller $controllerName" . " already exists</error>");
			$helper = $this->getHelper('question');
			$question = new ConfirmationQuestion('Overwrite file?', false);

			if (!$helper->ask($input, $output, $question)) {
				return false;
			}
		}

		file_put_contents($outputFilename, $templateContents);

		$output->writeln("<info>Model controller $controllerName has been created succesfully</info>");

		return true;
	}

	protected function createModelControllerFactory($modelName, $controllerName, $factoryName, $input, $output) {
		$templateFilename = $this->projectRootDir . "/console/Templates/ModelControllerFactory.tpl";

		$templateContents = file_get_contents($templateFilename);

		$templateContents = str_replace("%APIMODULE%", $this->apiNamespace, $templateContents);
		$templateContents = str_replace("%APIMODELVARIABLE%", lcfirst(ucwords($modelName)), $templateContents);
		$templateContents = str_replace("%APIMODEL%", $modelName, $templateContents);
		$templateContents = str_replace("%CONTROLLERNAME%", $controllerName, $templateContents);
		$templateContents = str_replace("%DRIVERNAME%", $this->driverName, $templateContents);

		$outputFilename = $this->projectRootDir . "/module/" . $this->apiNamespace . "/src/Factory/$factoryName.php";

		if (file_exists($outputFilename)) {
			$output->writeln("<error>API controller factory $factoryName" . " already exists</error>");
			$helper = $this->getHelper('question');
			$question = new ConfirmationQuestion('Overwrite file?', false);

			if (!$helper->ask($input, $output, $question)) {
				return false;
			}
		}

		file_put_contents($outputFilename, $templateContents);

		$output->writeln("<info>API controller factory $controllerFactoryName has been created succesfully</info>");

		return true;
	}

}
