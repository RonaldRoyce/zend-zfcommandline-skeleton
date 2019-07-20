<?php

namespace RonaldRoyce\Zfcommandline\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Metadata\Metadata;

class DbQueryGenerateCommand extends Command {
	private $serviceManager;
	private $projectRootDir;
	private $appConfig;
	private $appNamespace;
	private $adapter;
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
			->setName(strtolower($appNamespace) . ':db:generatequery')
			->setDescription('Generate a database query');
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

	protected function promptUser($input, $output, $prompt, $default, $mustSpecify) {
		$helper = $this->getHelper('question');
		$question = new Question($prompt . ": ", $default);

		while (true) {
			$response = $helper->ask($input, $output, $question);

			if ($mustSpecify && trim($response) == "") {
				continue;
			}

			return $response;
		}
	}

	protected function promptChoiceUser($input, $output, $prompt, $choices) {
		$helper = $this->getHelper('question');
		$question = new ChoiceQuestion($prompt . ": ", $choices, 1);

		$response = $helper->ask($input, $output, $question);

		return $response;
	}

	protected function clearScreen($output) {
		$output->write(sprintf("\033\143"));
	}

	protected function getMainMenuSelection($tablesSelected, $input, $output) {
		$errmsg = "";

		while (true) {
			$this->clearScreen($output);

			$output->writeln("     Main Menu");
			$output->writeln("     =========");
			$output->writeln("");

			if ($errmsg != "") {
				$output->writeln("<error>$errmsg</error>\n");
			}

			if (count($tablesSelected) > 0) {
				$output->writeln("[1] Join new table");
				$output->writeln("[2] Define where clause");
				$output->writeln("[3] Define order by clause");
				$output->writeln("[4] Define group by clause");
				$output->writeln("[5] Generate query");
				$output->writeln("[6] Quit");

				$menuItemCount = 6;
			} else {
				$output->writeln("[1] New table");
				$output->writeln("[2] Quit");

				$menuItemCount = 2;
			}

			$selection = $this->promptUser($input, $output, "\nEnter 1 to $menuItemCount", "", false);

			if (intval($selection) < 1 || intval($selection) > $menuItemCount) {
				$errmsg = "Invalid selection";
				continue;
			}

			if (intval($selection) == $menuItemCount) {
				return null;
			}

			return $selection;
		}
	}

	protected function getClauseFromUser($input, $output, $clauseName) {
		$this->clearScreen($output);

		$title = ucwords($clauseName);

		$output->writeln("     $title Clause");
		$output->writeln("     " . str_pad("", strlen($title), '='));
		$output->writeln("");

		$clause = $this->promptUser($input, $output, "Enter " . strtolower($clauseName) . " clause (Leave empty to cancel)", "", false);

		if ($clause == "") {
			return null;
		}

		return $clause;
	}

	protected function getColumnsForTable($tableNameInfo, $input, $output) {
		$columns = $this->getDatabaseColumnNames($tableNameInfo);

		$errmsg = "";

		$columnsSelected = array();

		while (true) {
			$this->clearScreen($output);

			$output->writeln("     Column Selection");
			$output->writeln("     ================");
			$output->writeln("");

			if ($errmsg != "") {
				$output->writeln("<error>$errmsg</error>\n");
			}

			$output->writeln("Specify the columns to select for table " . $tableNameInfo["schema"] . "." . $tableNameInfo["table_name"] . ":\n");

			for ($i = 0; $i < count($columns); $i++) {
				$selectionChar = " ";

				if (in_array($columns[$i], $columnsSelected)) {
					$selectionChar = "<info>*</info>";
				}

				$output->writeln($selectionChar . str_pad("[" . ($i + 1) . "]", 5, ' ', STR_PAD_LEFT) . " " . $columns[$i]);
			}

			$output->writeln(" " . str_pad("[" . (count($columns) + 1) . "]", 5, ' ', STR_PAD_LEFT) . " All columns");
			$output->writeln(" " . str_pad("[" . (count($columns) + 2) . "]", 5, ' ', STR_PAD_LEFT) . " Done");
			$output->writeln(" " . str_pad("[" . (count($columns) + 3) . "]", 5, ' ', STR_PAD_LEFT) . " Abort table definition");

			$selection = $this->promptUser($input, $output, "\nEnter 1 to " . (count($columns) + 3), "", true);

			if ($selection == "") {
				if (count($columnsSelected) == 0) {
					return null;
				}

				return $columnsSelected;
			}

			if (intval($selection) < 1 || intval($selection) > count($columns) + 3) {
				$errmsg = "Invalid response";
				continue;
			}

			if (intval($selection) == count($columns) + 2 && count($columnsSelected) == 0) {
				$errmsg = "You haven't selected any columns";
				continue;
			}

			if (intval($selection) == count($columns) + 2) {
				return $columnsSelected;
			}

			if (intval($selection) == count($columns) + 1) {
				$columnsSelected = $columns;
				return $columnsSelected;
			}

			if (intval($selection) == count($columns) + 3) {
				return null;
			}

			if (!in_array($columns[intval($selection) - 1], $columnsSelected)) {
				$columnsSelected[] = $columns[intval($selection) - 1];
			}
		}

	}

	protected function getTableFromUser($input, $output) {
		$errmsg = "";

		while (true) {
			$this->clearScreen($output);

			$output->writeln("     Table Definition");
			$output->writeln("     ================");
			$output->writeln("");

			if ($errmsg != "") {
				$output->writeln("<error>$errmsg</error>\n");
			}

			$tableName = $this->promptUser($input, $output, "Enter table to query (Leave empty to return to previous menu)", "", false);

			if ($tableName == "") {
				return null;
			}

			$tableNameInfo = $this->validateTableName($tableName);
			if (!$tableNameInfo) {
				$errmsg = "Table $tableName does not exist";
				continue;
			}

			$tableAlias = $this->promptUser($input, $output, "\nEnter table alias (Leave empty to return to previous menu)", "", false);

			if (!$tableAlias) {
				return null;
			}

			$columns = $this->getColumnsForTable($tableNameInfo, $input, $output);

			if (!$columns) {
				continue;
			}

			return array("table" => $tableNameInfo, "table_alias" => $tableAlias, "columns" => $columns);
		}
	}

	protected function getJoinedTableFromUser($input, $output) {
		$errmsg = "";

		while (true) {
			$this->clearScreen($output);

			$output->writeln("     Table Definition");
			$output->writeln("     ================");
			$output->writeln("");

			if ($errmsg != "") {
				$output->writeln("     <error>$errmsg</error>\n");
			}

			$tableName = $this->promptUser($input, $output, "Enter table to query (Leave empty to return to previous menu)", "", false);

			if ($tableName == "") {
				return null;
			}

			$tableNameInfo = $this->validateTableName($tableName);
			if (!$tableNameInfo) {
				$errmsg = "Table $tableName does not exist";
				continue;
			}

			$tableAlias = $this->promptUser($input, $output, "Enter table alias (Leave empty to return to previous menu)", "", false);

			if (!$tableAlias) {
				return null;
			}

			$columns = $this->getColumnsForTable($tableNameInfo, $input, $output);

			if (!$columns) {
				continue;
			}

			$tableJoinClause = $this->promptUser($input, $output, "Enter table join condition (Leave empty to return to previous menu)", "", false);

			if (!$tableJoinClause) {
				return null;
			}

			$joinType = $this->promptChoiceUser($input, $output, "Specify join type (INNER, OUTER, LEFT, RIGHT)", ['INNER', 'OUTER', 'LEFT', 'RIGHT', 'Return to previous menu']);

			if ($joinType == "Return to previous menu") {
				return null;
			}

			return array("table" => $tableNameInfo, "table_alias" => $tableAlias, "columns" => $columns, "table_join" => $tableJoinClause, "join_type" => $joinType);
		}
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		if (!$this->validConsoleConfiguration($input)) {
			$output->writeln("<error>Missing/invalid console configuration</error>");
			print_r($this->appConfig);
			return false;
		}

		$this->appNamespace = $this->appConfig['zfcommandline']['namespaces']['app'];

		$tablesSelected = array();
		$whereClause = "";
		$orderByClause = "";
		$groupByClause = "";

		while (true) {
			$selection = $this->getMainMenuSelection($tablesSelected, $input, $output);
			if (!$selection) {
				break;
			}

			switch ($selection) {
			case "1":
				if (count($tablesSelected) == 0) {
					$tableInfo = $this->getTableFromUser($input, $output);
				} else {
					$tableInfo = $this->getJoinedTableFromUser($input, $output);
				}

				if (!$tableInfo) {
					continue;
				}

				$tablesSelected[] = $tableInfo;

				break;
			case "2":
				if (count($tablesSelected) == 0) {
					return;
				}

				$where = $this->getClauseFromUser($input, $output, "Where");

				if (!$where) {
					continue;
				}

				$whereClause = $where;

				break;
			case "3":
				$order = $this->getClauseFromUser($input, $output, "Order By");

				if (!$order) {
					continue;
				}

				$orderByClause = $order;

				break;
			case "4":
				$group = $this->getClauseFromUser($input, $output, "Group By");

				if (!$group) {
					continue;
				}

				$groupByClause = $group;

				break;
			case "5":
				$this->generateSelect($tablesSelected, $whereClause, $orderByClause, $groupByClause, $output);
				exit(1);
			case "6":
				return;
			}
		}
	}

	protected function arrayToString($a) {
		$str = "[";

		$firstTime = true;

		foreach ($a as $col) {
			if (!$firstTime) {
				$str .= ", ";
			} else {
				$firstTime = false;
			}

			$str .= "'$col'";
		}

		$str .= "]";

		return $str;
	}

	protected function generateSelect($tablesSelected, $whereClause, $orderByClause, $groupByClause, $output) {
		$str = "
		\$adapter = \$container->get('dbconnection');

                \$sql = new Sql(\$adapter);

                \$select = new Select();
		";

		$str .= "
                \$select->from(['" . $tablesSelected[0]["table_alias"] . "' => new TableIdentifier('" . $tablesSelected[0]['table']['table_name'] . "', '" . $tablesSelected[0]['table']['schema'] . "')])";

		$str .= "
                        ->columns(" . $this->arrayToString($tablesSelected[0]['columns']) . ")";

		for ($i = 1; $i < count($tablesSelected); $i++) {
			$str .= "
                        ->join(['" . $tablesSelected[$i]["table_alias"] . "' =>  new TableIdentifier('" . $tablesSelected[$i]['table']['table_name'] . "', '" . $tablesSelected[0]['table']['schema'] . "')],
                                '" . $tablesSelected[$i]["table_join"] . "',
				" . $this->arrayToString($tablesSelected[$i]["columns"]) . ",
                                \$select::JOIN_" . $tablesSelected[$i]["join_type"] . "
                              )";
		}

		if ($whereClause != "") {
			$str .= "
                        ->where('" . $whereClause . "')";
		}

		if ($groupByClause != "") {
			$str .= "
                        ->group('" . $groupByClause . "')";
		}

		if ($orderByClause != "") {
			$str .= "
                        ->order('" . $orderByClause . "')";
		}

		$str .= ";\n
                \$statement = \$sql->prepareStatementForSqlObject(\$select);

                \$results = \$statement->execute();
                \$data = array();
                while (\$row = \$results->next()) {
                        \$data[] = \$row;
                }

		";

		$output->writeln("\nThe following is the PHP code to execute the query you defined:\n\n");

		$output->writeln("<info>$str\n</info>");
	}

	protected function parseTableName($tableName) {
		$schema = "tsop";
		$tblName = $tableName;

		$pos = stripos($tableName, ".");

		if ($pos !== FALSE) {
			$schema = substr($tableName, 0, $pos);
			$tblName = substr($tableName, $pos + 1);
		}

		return array("schema" => $schema, "table_name" => $tblName);
	}

	protected function validateTableName($tableName) {
		$tableInfo = $this->parseTableName($tableName);

		$metadata = new Metadata($this->adapter);

		try
		{
			$table = $metadata->getTable($tableInfo["table_name"]);
		} catch (\Exception $ex) {
			return null;
		}

		return $tableInfo;
	}

	protected function getDatabaseColumnNames($tableNameInfo) {
		$metadata = new Metadata($this->adapter);

		$table = $metadata->getTable($tableNameInfo["table_name"]);

		$columns = array();

		foreach ($table->getColumns() as $column) {
			$columns[] = $column->getName();
		}

		return $columns;
	}
}
