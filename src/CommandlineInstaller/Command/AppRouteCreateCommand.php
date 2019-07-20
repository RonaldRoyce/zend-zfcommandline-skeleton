<?php

namespace RonaldRoyce\Zfcommandline\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use ZF\Configuration\ConfigResource;
use ZF\Configuration\ConfigWriter;

class AppRouteCreateCommand extends Command {
	private $serviceManager;
	private $projectRootDir;
	private $appConfig;
	private $appNamespace;
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
			->setName(strtolower($appNamespace) . ':route:create')
			->setDescription('Create a new application route')
			->addArgument('name', InputArgument::REQUIRED, 'Route name')
			->addArgument('controller', InputArgument::REQUIRED, 'Name of controller')
			->addArgument('action', InputArgument::OPTIONAL, 'Controller action (i.e. index).  Default is \'index\'');

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
		$this->driverName = $this->appConfig['zfcommandline']['driver'];

		$routeName = $input->getArgument('name');
		$routeControllerName = $input->getArgument('controller');
		$action = $input->getArgument('action');

		if (!isset($index)) {
			$action = 'index';
		}

		$fullControllerClassName = sprintf(
			'\%s\Controller\%s',
			$this->appNamespace,
			$routeControllerName
		);

		if (!class_exists($fullControllerClassName)) {
			$output->writeln("<error>Controller $fullControllerClassName does not exist</error>");
			return false;
		}

		$this->addToConfig($routeName, $routeControllerName, $action, $input, $output);
	}

	protected function addToConfig($routeName, $routeControllerName, $action, $input, $output) {
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

		$configResource = new ConfigResource($config, $this->projectRootDir . '/module/' . $this->appNamespace . '/config/module.config.php', $writer);

		$fullControllerName = sprintf(
			'\%s\Controller\%s',
			$this->appNamespace,
			$routeControllerName
		);

		$configResource->patch([
			'router' => [
				'routes' => [
					"$routeName" => [
						'type' => \Zend\Router\Http\Segment::class,
						'options' => [
							'route' => "/$routeName" . "[/:id]",
							'constraints' => [
								'id' => '[0-9]+',
							],
							'defaults' => [
								'controller' => $fullControllerName,
								'action' => "$action",
							],
						],
					],
				],
			],
		], true);

	}
}
