<?php
namespace %APIMODULE%\Controller;

use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

use %APIMODULE%\Model\%APIMODEL%;

class %CONTROLLERNAME% extends AbstractRestfulController {
	/**
	 * @var %APIMODEL%
	 */

	protected $%APIMODELVARIABLE%;

	public function __construct(%APIMODEL% $%APIMODELVARIABLE%) {
		$this->%APIMODELVARIABLE% = $%APIMODELVARIABLE%;
	}

	public function getList() {
		$data = $this->%APIMODELVARIABLE%->findAll();

		return new JsonModel(['data' => $data]);
	}
	
	public function get($id) {
		$data = $this->%APIMODELVARIABLE%->find($id);

		return new JsonModel(['data' => $data]);
	}
}
