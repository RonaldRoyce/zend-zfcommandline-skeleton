<?php
namespace %APPMODULE%\Controller;

use %APIMODULE%\Model\%APIMODEL%;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class %CONTROLLERNAME% extends AbstractActionController {
	/**
	 * @var %APIMODEL%
	 */

	protected $%APIMODELVARIABLE%;

	public function __construct(%APIMODEL% $%APIMODELVARIABLE%) {
		$this->%APIMODELVARIABLE% = $%APIMODELVARIABLE%;
	}

	public function indexAction() {
		try {
			$data = $this->%APIMODELVARIABLE%->findAll();

			return new ViewModel('', [ 'data' => $data ]);

		} catch (Exception $e) {
			Zend_Registry::get('logger')->log('Exception : ' . $e->getMessage() . $e->getTraceAsString(), LOG_ERR);
		}
	}
}
