<?php defined('SYSPATH') OR die('No direct access allowed.');

class Controller_Scaffold extends Controller {

	protected $column = '';

	protected $auto_modeller = TRUE;

	protected $items_per_page = 15;

	protected $db_first = "";

	protected $header = Array();

	protected function _get_schema($force = FALSE) {
		$column = ImplodeUppercase::decode($this->column);
		if (empty($this->header) || $force) {
			$db = Database::instance()->list_columns($column);
			$this->header = Array();
			foreach ($db as $collum) {
				array_push($this->header, $collum["column_name"]);
				if (isset($collum["key"]) && $collum["key"] === "PRI") {
					$this->db_first = $collum["column_name"];
				}
			}
		}
	}

	public function before() {
	}

	public function flash($msg, $type = "success") {
		$last = Session::instance()->get("flash.message");
		$new = array(
			"msg" => $msg,
			"type" => $type
		);
		if ($last !== NULL) {
			$new = array_merge(array($new), $last);
		} else {
			$new = array($new);
		}
		Session::instance()->set("flash.message", $new);
	}

	protected function _auto_model($model = NULL) {
		$success = FALSE;
		if ($this->auto_modeller) {
			if ($model !== NULL) {
				$model_tmp = $this->column = $model;
			}
			$class_name = $this->generateClassName($this->column);
			if (strpos($class_name, '_')) {
				// We can't handle subdirectories yet
				$class_name = str_replace('_', '', $class_name);
			}
			$directory_name = "model" . DIRECTORY_SEPARATOR . "scaffold" . DIRECTORY_SEPARATOR;
			$path = APPPATH . 'classes' . DIRECTORY_SEPARATOR . $directory_name;
			$file = $path . $class_name . EXT;

			if (!file_exists($file)) {
				$db = Database::instance()->list_columns($this->column);
				$_primary_key = "";
				$_primary_val = "";
				$properties_phpdoc = array();
				$belongs_to = array();

				foreach ($db as $column) {
					if (($_primary_key !== "") && ($_primary_val === "") && $column["type"] === "string") {
						$_primary_val = $column["column_name"];
					}
					if ($column["key"] === "PRI") {
						$_primary_key = $column["column_name"];
					}
					if ($column['key'] === 'MUL') {
						if (substr($column['column_name'], -3) == '_id') {
							$referenced_class = substr($column['column_name'], 0, -3);
							$referenced_model = 'Model_Scaffold_' . ucfirst($referenced_class);
							$belongs_to[] = '\'' . $referenced_class . '\' => array(\'model\' => \'scaffold_'.$referenced_class.'\')';
							$properties_phpdoc[] = '@property ' . $referenced_model . ' $' . $referenced_class;
						}
					}
					$properties_phpdoc[] = '@property ' . $column['type'] . ' $' . $column["column_name"];
				}
				$properties_phpdoc_implode = implode("\n * ", $properties_phpdoc);
				$factory = "\tpublic static function factory(\$model=NULL,\$id=NULL) { return new Model_Scaffold_$class_name(\$id); }";
				$model_container = "<?php defined('SYSPATH') or die('No direct access allowed.');

/**
 * $properties_phpdoc_implode
 */
class Model_Scaffold_" . $class_name . " extends ORM
{
	protected \$_db = 'default';
	protected \$_table_name  = '" . str_replace('scaffold_', "", $this->column) . "';
	protected \$_primary_key = '$_primary_key';
	protected \$_primary_val = '$_primary_val';
";
				if (count($belongs_to)) {
					$model_container .= "\tprotected \$_belongs_to = array(" . implode($belongs_to, ', ') . ');' . PHP_EOL;
				}
				$model_container .= "
	protected \$_table_columns = array(\n";
				foreach ($db as $column) {
					$model_container .= "\t\t'" . $column["column_name"] . "' => array('data_type' => '" . $column["type"] . "', 'is_nullable' => " . (($column["is_nullable"])
							? "TRUE" : "FALSE") . "),\n";
				}
				// TODO: BaRoN!: Add a few static factory methods :)
				$model_container .= "\t);\n\n$factory\n}";

				if (!is_dir($path)) {
					mkdir($path, 0777, TRUE);
				}
				file_put_contents($file, $model_container);
				$success = TRUE;
			}
			if (isset($model_tmp)) {
				$this->column = $model_tmp;
			}
		}
		return $success;
	}

	protected function generateClassName($column) {
		// TODO: BaRoN: make this configurable
		$class_name = Inflector::singular($column);
		$class_name = str_replace(" ", "", ucwords($class_name));
		return $class_name;
	}

	protected function auto_modeller() {
		$i = 0;
		$items = array();
		foreach (Database::instance()->list_tables() as $item) {
			$className = $this->generateClassName($item);
			$simpleName = 'Model_' . $className;
			if (class_exists($simpleName)) continue;
			// We can't deal with subdirectories yet, so…
			if ($this->_auto_model($item)) {
				$i++;
			}
			$items[] = $className;
		}

		$path = APPPATH . 'classes' . DIRECTORY_SEPARATOR . "model" . DIRECTORY_SEPARATOR . "scaffold" . DIRECTORY_SEPARATOR;
		$files = glob($path . "*.php");

		foreach ($files as $fname) {
			$what = str_replace(array($path, ".php"), "", $fname);
			if (!in_array($what, $items)) {
				unlink($fname);
			}
		}

		if ($i == 1) {
			$this->flash(__("One new model"));
		}
		elseif ($i > 0) {
			$this->flash(__(":num new models", array(':num' => $i)));
		}
		else {
			$this->flash(__("No new models found"), "notice");
		}
		$this->request->redirect("scaffold");
	}

	protected function remove_models() {
		$path = APPPATH . 'classes' . DIRECTORY_SEPARATOR . "model" . DIRECTORY_SEPARATOR . "scaffold" . DIRECTORY_SEPARATOR;
		$count = 0;
		foreach (glob($path . "*") as $fname) {
			unlink($fname);
			$count++;
		}
		if ($count === 0) {
			$this->flash(__("No models removed"), "notice");
		} else {
			$this->flash(__(":count models removed", array(':count'=>$count)), "notice");
		}
		$this->request->redirect("scaffold");
	}

	public function action_index() {
		$content = array();

		if (isset($_GET["auto_modeller"])) {
			if (empty($_GET["auto_modeller"])) {
				$this->auto_modeller();
			} else {
				$this->auto_modeller($_GET["auto_modeller"]);
			}
		}

		if (isset($_GET["remove_models"])) {
			if (empty($_GET["remove_models"])) {
				$this->remove_models();
			} else {
				$this->remove_models($_GET["remove_models"]);
			}
		}

		$subPath = (isset($_GET["dir"])) ? $_GET["dir"] : "";
		$path = APPPATH . 'classes' . DIRECTORY_SEPARATOR . "model" . DIRECTORY_SEPARATOR . "scaffold" . DIRECTORY_SEPARATOR . $subPath;

		if (!is_dir($path)) {
			mkdir($path, 0777, TRUE);
		}

		if ($handle = opendir($path)) {
			$files = Array();
			$directores = Array();
			while (FALSE !== ($file = readdir($handle))) {
				if (preg_match("/" . EXT . "/i", $file)) {
					array_push($files, str_replace(EXT, "", $file));
				} else if (!preg_match("/\./i", $file)) {
					array_push($directores, str_replace(EXT, "", $file));
				}
			}
			closedir($handle);

			foreach ($directores as $item) {
				$item_name = str_replace(Array($path, EXT), "", $item);
				// array_push( $content, HTML::anchor('scaffold?dir='.$item_name, "[+] " . ucfirst($item_name)) );
				// array_push( $content, "[+] " . ucfirst($item_name) );
			}

			foreach ($files as $item) {
				$item_name = str_replace(Array($path, EXT), "", $item);
				array_push($content, HTML::anchor('scaffold/list/' . $subPath . $item_name, ImplodeUppercase::ucwords_text($item_name)));
			}
		}

		if (empty($content)) {
			$content = __("No models to list");
		}

		$data = Array(
			"content" => $content,
			"msg" => (isset($_GET["msg"]) ? $_GET["msg"] : ""),
			"msgtype" => (isset($_GET["msgtype"]) ? $_GET["msgtype"] : "success")
		);
		echo View::factory("scaffold/index", $data)->render();
	}

	public function action_list($column = NULL) {
		$column = $this->request->param('column');
		if (empty($column)) {
			$this->request->redirect('scaffold');
		}
		$orm = ORM::factory(class_exists('Model_' . $column) ? $column : 'scaffold_' . $column);
		$this->column = $orm->table_name();
		$this->_get_schema();

		if ($this->column === "") {
			echo "<p>" . __("Please, select a column") . "</p>";
			exit;
		}

		$controller = url::site($this->request->controller());

		$this->items_per_page = (isset($_GET["items_per_page"])) ? $_GET["items_per_page"] : $this->items_per_page;

		$pagination = Pagination::factory(array(
		                                       'total_items' => $orm->count_all(),
		                                       'items_per_page' => $this->items_per_page
		                                  ));

		$query = $orm
				->limit($this->items_per_page)
				->offset($pagination->offset)
				->find_all();

		$result = Array();
		foreach ($query as $key) {
			$key = $key->as_array();
			$item = Array();
			foreach ($key as $value) {
				array_push($item, $value);
			}

			$id = $key[$this->db_first];
			array_push($item, "<a href=\"$controller/edit/" . $column . "/$id\">" . __("Edit") . "</a> | <a href=\"$controller/delete/" . $column . "/$id\"  class=\"delete\">" . __("Delete") . "</a>");
			array_push($result, $item);
		}

		$data = Array(
			"column" => $column,
			"db_first" => $this->db_first,
			"header" => $this->header,
			"pagination" => $pagination->render(),
			"content" => $result,
			"msg" => (isset($_GET["msg"]) ? $_GET["msg"] : NULL),
			"msgtype" => (isset($_GET["msgtype"]) ? $_GET["msgtype"] : "success")
		);

		echo View::factory("scaffold/list", $data)->render();
	}

	public function action_insert($request = NULL) {
		if (is_null($request)) {
			$request = $this->request->param('column');
		}
		if ($request === "save") {
			$column = $_POST["column"];
			unset($_POST["column"]);

			$orm = ORM::factory(class_exists('Model_' . $column) ? $column : 'scaffold_' . $column)->values($_POST);

			if ($orm->check()) {
				$orm->save();
				$this->flash(__('Record added successfully!'));
			} else {
				$errors = $orm->validate()->errors();
				$this->flash($errors, "error");
			}
			$this->request->redirect("scaffold/list/" . $this->column . "/");
		} else {
			$column = $this->generateClassName($request);
			$orm = ORM::factory((class_exists('Model_' . $column) ? $column : 'scaffold_' . $column));
			$this->column = $orm->table_name();
			$this->_get_schema();
			$data = Array(
				"column" => $column,
				"header" => $this->header,
				"first" => $this->db_first
			);
			echo View::factory("scaffold/insert", $data)->render();
		}
	}

	public function action_edit() {
		$id = $this->request->param('id');
		$column = $this->request->param('column');
		$orm = ORM::factory((class_exists('Model_' . $column) ? $column : 'scaffold_' . $column), $id);
		$this->column = $orm->table_name();
		$this->_get_schema(TRUE);

		$data = Array(
			"column" => ucfirst($column),
			"request" => $id,
			"first" => $this->db_first,
			"content" => $orm->as_array()
		);

		echo View::factory("scaffold/edit", $data)->render();
	}

	public function action_save() {
		$column = $_POST["column"];
		unset($_POST["column"]);
		$id = array_shift($_POST);

		$orm = ORM::factory((class_exists('Model_' . $column) ? $column : 'scaffold_' . $column), $id)->values($_POST);

		if ($orm->check()) {
			$orm->save();
			$this->request->redirect('scaffold/list/' . $column . '/?msg=' . __('Record updated successfully') . '!');
		} else {
			$errors = $orm->validate()->errors();
			$this->request->redirect("scaffold/list/" . $column . "/?msg=$errors&msgtype=error");
		}
	}

	public function action_delete() {
		$column = $this->request->param('column');
		$id = $this->request->param('id');
		$orm = ORM::factory((class_exists('Model_' . $column) ? $column : 'scaffold_' . $column), $id)->delete();
		$this->column = $orm->table_name();
		$this->flash(__("Model :model with id :id successfully deleted", array(':model' => $column, ':id' => $id)), "error");
		$this->request->redirect("scaffold/list/" . $column);
	}

}

// end controller