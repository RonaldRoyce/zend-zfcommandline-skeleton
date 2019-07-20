<?php

namespace RonaldRoyce\Zfcommandline\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class ViewCreateCommand extends Command {
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
			->setName(strtolower($appNamespace) . ':view:create')
			->setDescription('Create a new view')
			->addArgument('controller', InputArgument::REQUIRED, 'Name of controller')
			->addArgument('action', InputArgument::OPTIONAL, 'Name of action (i.e. index).  Default is \'index\'');
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

		$viewControllerName = $input->getArgument('controller');

		if (substr($viewControllerName, strlen($viewControllerName) - 10) != "Controller") {
			$output->writeln("<error>Controller $fullControllerClassName does not end in 'Controller'</error>");
			return false;
		}

		$action = $input->getArgument('action');

		if ($action == "") {
			$action = "index";
		}

		$fullControllerClassName = sprintf(
			'\%s\Controller\%s',
			$this->appNamespace,
			$viewControllerName
		);

		if (!class_exists($fullControllerClassName)) {
			$output->writeln("<error>Controller $fullControllerClassName does not exist</error>");
			return false;
		}

		$this->createView($viewControllerName, $action, $input, $output);
	}

	protected function hyphenateName($str) {
		$a = "";

		for ($i = 0; $i < strlen($str); $i++) {
			if ($i > 0 && substr($str, $i, 1) >= "A" && substr($str, $i, 1) <= 'Z') {
				$a .= "-";
			}

			$a .= strtolower(substr($str, $i, 1));
		}

		return $a;
	}

	protected function createView($viewControllerName, $action, $input, $output) {
		$templateFilename = $this->projectRootDir . "/console/Templates/View.tpl";

		$viewDir = $this->projectRootDir . "/module/" . $this->appNamespace . "/view/" . $this->hyphenateName($this->appNamespace) . "/" .
		$this->hyphenateName(substr($viewControllerName, 0, strlen($viewControllerName) - 10));

		if (!file_exists($viewDir)) {
			mkdir($viewDir, 0755);
		}

		$viewFilename = $viewDir . "/" . $action . ".phtml";

		if (file_exists($viewFilename)) {
			$output->writeln("<error>View file $viewFilename already exists</error>");
			$helper = $this->getHelper('question');
			$question = new ConfirmationQuestion('Overwrite file?', false);

			if (!$helper->ask($input, $output, $question)) {
				return false;
			}
		}

		$templateContents = file_get_contents($this->projectRootDir . "/console/Templates/View.tpl");

		file_put_contents($viewFilename, $templateContents);

		$output->writeln("<info>View file $viewFilename has been created succesfully</info>");

		return true;
	}
}
