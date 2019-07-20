<?php
namespace  %APIMODULE%\Model;

use Zend\Db\Sql\Select;
use Zend\Db\Sql\Sql;
use \Zend\Db\Sql\Expression;

class %APIMODEL% {
	private $adapter;

	public function __construct($adapter) {
		if (!$adapter) {
			throw new \Exception("Adapter not found in model constructor");
		}

		$this->adapter = $adapter;
	}

	public function find($id) {
		$sql = new Sql($this->adapter);

		$select = new Select();

		$select->from(
			array(
				't' => '%TABLENAME%',
			),
			array(
			)
		);

		$statement = $sql->prepareStatementForSqlObject($select);

		$results = $statement->execute();
		$data = array();
		while ($row = $results->next()) {
			$data[] = $row;
		}

		return $data;
	}

	public function findAll() {
		$sql = new Sql($this->adapter);

		$select = new Select();

		$select->from(
			array(
				't' => '%TABLENAME%',
			),
			array(
			)
		);

		$statement = $sql->prepareStatementForSqlObject($select);
		$results = $statement->execute();
		$data = array();
		while ($row = $results->next()) {
			$data[] = $row;
		}

		return $data;
	}
}
