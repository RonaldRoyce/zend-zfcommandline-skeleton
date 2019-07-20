<?php

namespace RonaldRoyce\Zfcommandline\Command;

use Symfony\Component\Console\Command\Command;

class ConfigCommand extends Command {
	private $serviceManager;
	private $projectRootDir;
	private $appConfig;

	public function __construct($serviceManager, $projectRootDir, $appConfig) {
		$this->serviceManager = $serviceManager;
		$this->projectRootDir = $projectRootDir;
		$this->appConfig = $appConfig;

		parent::__construct();
	}

	protected function configure() {
		$this
			->setName('zfcommandline:config')
			->setDescription('Configure zfcommandline');
	}
}
