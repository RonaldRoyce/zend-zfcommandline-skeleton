<?php

namespace %APPMODULE%\Factory;

use Interop\Container\ContainerInterface;
use %APIMODULE%\Model\%APIMODEL%;
use %APPMODULE%\Controller\%CONTROLLERNAME%;
use Zend\ServiceManager\Factory\FactoryInterface;

class %CONTROLLERNAME%Factory implements FactoryInterface {
	public function __invoke(ContainerInterface $container, $requestedName, array $options = null) {
		$adapter = $container->get('%DRIVERNAME%');

		return new %CONTROLLERNAME%(new %APIMODEL%($adapter));
	}	
}
