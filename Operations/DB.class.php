<?php

namespace Operations;

/**
 *
 */
class DB extends \Operations
{

  private $_FORCE_ACT = "";
  static $_ACT;
  static $_REL_ACT;
  static $_GET;
  static $_MULTI_TO_DEL = [];
  static $_CUR_VALUES;
  static $_FORCE_CLEAN = false;
  static $_FILES = [];

  public function __construct($values = false)
  {
    parent::__construct($values);
  }

  public function Action(string $action)
  {
    self::$_ACT = $action;
    if (!self::$_Arch instanceof Arch) return Validate::Error('[DB] Architektura nie została ustawiona, nie ma jak zebrać danych.');
    $event = new \stdClass();
    $resAll = [];
    foreach (self::$_Arch->_data as $table => $columns) {
      $event->table = $table;
      $event->data  = $columns;
      self::$_CUR_TABLE = $table;
      switch (self::$_ACT) {

        case 'put':
          $res = self::ResolverSet($table, $columns);
          if (isset($res['_error'])) return $res;
          $resAll[$table] = $res;
          break;

        case 'force_insert' :
          $this->_FORCE_ACT = "insert";
          Event::Fire("before_set", $event);
          $res = self::ResolverSet($table, $columns);
          Event::Fire("after_set", $event);
          if (isset($res['_error'])) return $res;
          $resAll[$table] = $res;
          break;

        case 'force_update' :
          $this->_FORCE_ACT = "update";
          Event::Fire("before_update", $event);
          $res = self::ResolverSet($table, $columns);
          Event::Fire("after_update", $event);
          if (isset($res['_error'])) return $res;
          break;

        case 'get' :
          Event::Fire("before_get", $event);
          $res = self::ResolverGet($table, $columns, self::$_Values[$table]);
          Event::Fire("after_get", $event);
          if (isset($res['_error'])) return $res;
          $resAll[$table] = $res;
          break;

        default:
          return Validate::Error('Nie rozpoznana akcja - ' . \Tools::EscHTML(self::$_ACT) );
          break;
      }

    }

    $this->_FORCE_ACT = "";
    return $resAll;
  }

  private function GetColumn(string $type, array $columns)
  {
    foreach ($columns as $c_name => $column) {
      if (\Vali::String($c_name) && $c_name[0] === "_") continue;
      if (isset($column['type']) && $column['type'] === $type) return $index = $c_name;
    }

    return false;
  }

  private function EntityExists(array $columns, array $values, string $index = '')
  {
    if ($index === '') $index = self::GetColumn('index', $columns);

    if ($index === false || !isset($values[$index])) return Validate::Error('[DB] Podane dane nie posiadają wartości klucza podstawowego.');
    $sql = "SELECT " . $index . " FROM " . self::$_CUR_TABLE . " WHERE " . $index . " = " . $values[$index] . ";";
    $handle = $this->link->prepare($sql);
    $handle->execute();
    $res = $handle->fetch();
    $res = (array) $res;

    return isset($res[$index]);
  }

  private function ResolverSet(string $table, array $_data, array $sValues = [])
  {
    if (sizeof($sValues) === 0 ) $sValues = self::$_Values;
    $res = true;
    if (isset($_data['_multi'])) {                              // case where we have multi identical entries for the same table
      $link_name = self::GetColumn ('link' , $_data);
      $index     = self::GetColumn ('index', $_data);
      $files     = self::GetColumns('file' , $_data);

      if ( $link_name ) {
        $lValue = Validate::RepVar($_data[$link_name]['value'], self::$_CUR_TABLE);
        $multi_items = (array) self::GetData($_data, $link_name, $lValue, true);
      }

      foreach ($sValues[$table] as $c_name => $value) {
        $value = (array) $value;
        $EE = self::EntityExists($_data, $value, $index);

        if (isset($EE['error'])) return $EE;

        if (!$EE)  self::$_REL_ACT = "insert";
        else       self::$_REL_ACT = "update";

        if ($this->_FORCE_ACT != "") self::$_REL_ACT = $this->_FORCE_ACT;


        if (self::$_REL_ACT === "update") {
          $inValue = $value[$index];  // index value
          // here we unset all existing multi items to have only non existing which we will delete later
          if (isset($multi_items)) {
            foreach ($multi_items as $i => $item) {
              $item = (array) $item;
              // if index value exists in table then unset item from being deleted
              if ($item[$index] === $inValue) {
                unset($multi_items[$i]);
                break;
              }
            }
          }
        }

        if (sizeof($value) > 0) $res = self::PrepareAndSet($_data, $value);
        else                    $res = null;

        if (isset($res['_error'])) return $res;
      }

      if (isset($multi_items) && self::$_REL_ACT === "update") {

        $event = new \stdClass();
        $event->table = self::$_CUR_TABLE;
        $event->data  = $_data;

        Event::Fire("before_del_table", $event);

        foreach ($multi_items as $item) {
          $item = (array) $item;
          $event->row   = $item;
          $event->index = $index;

          Event::Fire("before_del_row", $event);

          foreach ($files as $c_name) {
            if ( is_file( $item[$c_name] ) ) unlink($item[$c_name]);
          }

          self::DelData($index, $item[$index]);
          Event::Fire("after_del_row", $event);

          unset($event->index);
          unset($event->row  );
        }

        Event::Fire("after_del_table", $event);

      }

      return $res;
    } else {                                                   // case when we have only one entry for one table
      $i = 0;
      foreach ($_data as $c_name => $column) {
        $i++;
        if (isset($column['value'])) break;
        if ($i === sizeof($_data)) $i = -1;
      }


      if ($i === -1) {
        foreach ($_data as $c_name => $column) {

          if ( isset($sValues[$table][$c_name]) ) $res = self::ResolverSet($c_name, $_data[$c_name], $sValues[$table]);
          else $res = null;

          if (isset($res['_error'])) return $res;
        }

        return $res;
      } else {

        $EE = self::EntityExists($_data, (array) $sValues[$table]);
        if (isset($EE['error'])) return $EE;

        if (!$EE) self::$_REL_ACT = "insert";
        else      self::$_REL_ACT = "update";

        if ($this->_FORCE_ACT != "") self::$_REL_ACT = $this->_FORCE_ACT;


        if (sizeof($sValues[$table]) > 0) return self::PrepareAndSet($_data, $sValues[$table]);
        else return;
      }

    }
  }

  private function GetColumns(string $type, array $columns) : array
  {
    $cols = [];

    foreach ($columns as $c_name => $column) {
      if (\Vali::String($c_name) && $c_name[0] == "_") continue;

      if (isset($column['type']) && $column['type'] === $type) $cols[] = $c_name;
    }

    return $cols;
  }

  private function PrepareAndSet(array $_data, array $values)
  {
    $event = new \stdClass();
    $event->table = self::$_CUR_TABLE;
    $event->data  = $_data;

        if (self::$_REL_ACT === "insert") Event::Fire("before_set_table"   , $event);
    elseif (self::$_REL_ACT === "update") Event::Fire("before_update_table", $event);

        if (self::$_REL_ACT === "insert") {
          $res = self::InsertData($_data, $values);
          Event::Fire("after_set_table"   ,$event);
        }
    elseif (self::$_REL_ACT === "update") {
          $res = self::UpdateData($_data, $values);
          Event::Fire("after_update_table",$event);
        }

    return $res;
  }

  private function InsertData(array $_data, array $values)
  {
    if (sizeof($_data) === 0) return Validate::Error('Podano puste kolumny przy próbie włożenia danych do tabeli - `' . $table . '`.');

    $sql        = "INSERT INTO " . self::$_CUR_TABLE . " ";
    $keys       = "";
    $ques_marks = "";
    $i          = 0 ;
    $index      = 0 ;

    foreach ($_data as $key => $value) {
      if ($key[0] === "_") continue;
      if (isset($value['type']) && $value['type'] === "index") {
        $index = $values[$key];
        continue;
      }

      if (isset($value['type']) && $value['type'] === "file" ) {

        if ( strpos( $values[$key], ';base64' ) === false && is_file( $values[$key] ) ) {
          $path = $values[$key];
          $type = pathinfo($path, PATHINFO_EXTENSION);
          $data = file_get_contents($path);
          $values[$key] = 'data:image/' . $type . ';base64,' . base64_encode($data);
        }

        $dir  = \Path::$DOCUMENT_ROOT_PUBLIC . "media/files/"; // default directory

        if ( isset( $value['path'] ) ) {
          $path = Validate::RepVar( $value['path'], self::$_CUR_TABLE );
          if ( isset( $path['_error'] ) ) return $path;
          $dir  = \Path::$DOCUMENT_ROOT_PUBLIC . $path;
        }

        $dir = trim( $dir, '/' ) . '/';

        $name = \Tools::GenerateToken(6);
        $exists = true;

        $ext = \Tools::GetImgTypeBase64( $values[$key] );

        if ( $ext === false ) return Validate::Error("Nie udało się zuploadować zdjęcia do bazy posiada złe rozszerzenie (dozwolone na tą chwile są tylko png oraz jpg).");

        if ( !is_dir( $dir ) ) mkdir( $dir, "0755", true );

        // making sure there wont be the same file in directory
        while ($exists) {
          if ( !is_file( $dir . $name . '.' . $ext ) ) $exists = false;
          else $name = \Tools::GenerateToken(6);
        }

        $res  = \Tools::UploadImg( $values[$key], $dir, $name, $ext);
        if ( $res === false ) return Validate::Error("Nie udało się zuploadować zdjęcia do bazy (wyślany plik musi być zdjęciem).");

        $values[$key] = $dir . $name . '.' . $ext;

      }

      if ($i === 0) {
        $keys       = "("  . \Tools::EscHTML($key);
        $ques_marks = "(:" . \Tools::EscHTML($key);
      } else {
        $keys       .= ","  . \Tools::EscHTML($key);
        $ques_marks .= ",:" . \Tools::EscHTML($key);
      }
      $i++;
    }

    if ($keys === "" || $ques_marks === "") return;

    $keys       .= ")";
    $ques_marks .= ")";

    $sql .= $keys . " VALUES " . $ques_marks . ";";
    // echo $sql . PHP_EOL;
    $handle = $this->link->prepare($sql);
    $handle = self::BindAssigner($handle,$values,$_data);
    if (\Vali::Array($handle) && isset($handle['_error'])) {
      return $handle;
    }
    $handle->execute();
    return true;
  }

  private function UpdateData(array $_data, array $values)
  {
    if (sizeof($_data) === 0) return Validate::Error('Podano puste kolumny przy próbie włożenia danych do tabeli - `' . $table . '`.');

    $sql        = "UPDATE " . self::$_CUR_TABLE . " SET ";
    $keys       = "";
    $index      = "";
    $i          = 0;

    foreach ($_data as $key => $value) {
      if ($key[0] === "_") continue;
      if (isset($value['type']) && $value['type'] === "index") {
        $index .= " WHERE " . \Tools::EscHTML($key) . " = " . ":" . \Tools::EscHTML($key);
        continue;
      }

      if ($i === 0) $keys  = \Tools::EscHTML($key) . " = " . ":" . \Tools::EscHTML($key);
      else          $keys .= ", "  . \Tools::EscHTML($key) . " = " . ":" . \Tools::EscHTML($key);

      $i++;
    }

    if ($keys === "" || $index === "") return;

    $sql .= $keys . $index . ";";
     // echo $sql . PHP_EOL;
    $handle = $this->link->prepare($sql);
    $handle = self::BindAssigner($handle,$values,$_data);
    $handle->execute();
    return true;
  }

  private function BindAssigner(\PDOStatement $handle, array $values, array $_data, int $start_from = 0)
  {
    // echo self::$_CUR_TABLE . PHP_EOL;
    foreach ($_data as $c_name => $column) {
      if (\Vali::String($c_name) && $c_name[0] === '_') continue;
      if (isset($column['type']) && $column['type'] === "index" && self::$_REL_ACT === "insert") continue;

      if (!isset($values[$c_name])) {
        if (!isset($column['value'])) return Validate::Error('Nie istnieje wartość ' . $c_name . " nie można dodać danych do tabeli " . self::$_CUR_TABLE);
        // echo $column['value'] . PHP_EOL;
        $value = Validate::RepVar($column['value'],self::$_CUR_TABLE);
        //  echo $value . PHP_EOL;
      } else {
        $value = Validate::RepVar($values[$c_name],self::$_CUR_TABLE);
      }
      // echo ": $c_name - " . $value . PHP_EOL;

      $handle->bindValue(":" . $c_name,$value);

    }

    return $handle;
  }

  private function ResolverGet(string $table, array $_data, array $_values)
  {
    // echo self::$_CUR_TABLE . "/" . $table . "<br>";
    $values = [];
    if (isset($_data['_multi'])) {                              // case where we have multi identical entries for the same table

      $i = 0;
      foreach ($_data as $c_name => $column) {
        $i++;
        if ($c_name[0] === "_") continue;
        if (isset($column['type']) && $column['type'] == "link") {
          $link = $c_name;
          break;
        }
        if ($i === sizeof($_data)) return Validate::Error('[DB] Nie znaleziono kolumny `link` w tabeli typu multi - ' . \Tools::EscHTML($table) );
      }
      $res = self::PrepareAndGet($_data, $link, $_values, true);
      if (isset($res['_error'])) return $res;
      $values = array_merge($values,$res);

    } else {                                                   // case when we have only one entry for one table
      $i = 0;
      foreach ($_data as $c_name => $column) {
        $i++;
        if (isset($column['value']) && !\Vali::Array($column['value'])) break;
        elseif ($i === sizeof($_data)) {
          $i = -1;
          foreach ($_data as $c_name => $column) {
            $values[$c_name] = self::ResolverGet($c_name, $column, $_values[$c_name]);
          }
        }
      }
      if ($i != -1) {
        $i = 0;
        foreach ($_data as $c_name => $column) {
          $i++;
          if (\Vali::String($c_name) && $c_name[0] === "_") continue;
          if (!isset($link) && isset($column['type']) && $column['type'] == "index") $link = $c_name;
          else if (isset($column['type']) && $column['type'] == "link")              $link = $c_name;

          if (isset($link) && $i === sizeof($_data)) break;
          else if ($i === sizeof($_data)) return Validate::Error('[DB] Nie znaleziono kolumny `index` ani `link` w tabeli - ' . \Tools::EscHTML($table) );
        }

        $res = self::PrepareAndGet($_data, $link, $_values);
        if (isset($res['_error'])) return $res;
        $values = array_merge($values,$res);
      }
    }

    return $values;
  }

  private function PrepareAndGet(array $_data, string $link, array $_values, bool $multi = false)
  {
    $event = new \stdClass();
    $event->table = self::$_CUR_TABLE;
    $event->data  = $_data;

    Event::Fire("before_get_table", $event);

    $lValue = Validate::RepVar($_data[$link]['value'], self::$_CUR_TABLE);
    $data = self::GetData($_data, $link, $lValue, $multi);

    Event::Fire("after_get_table", $event);

    return $data;
  }

  private function GetData(array $_data, string $link, $lValue, bool $multi)
  {
    if (sizeof($_data) === 0) return Validate::Error('Podano puste kolumny przy próbie zabrania danych z tabeli - `' . self::$_CUR_TABLE . '`.');
    if (isset($value['error'])) return $value;
    $sql  = "SELECT * FROM " . self::$_CUR_TABLE . " WHERE " . $link . " = :" . $link;
    if (isset($_data['_get']['sql_end'])) $sql .= " " . $_data['_get']['sql_end'];
    $sql .= ";";
    // echo $sql . "<br>";
    $handle = $this->link->prepare($sql);
    $handle->bindValue(":" . $link, $lValue);
    $handle->execute();

    if ($multi) $results = $handle->fetchAll();
    else        $results = $handle->fetch();

    $results = (array) $results;
    $results = self::SolveGet($results, $_data, $multi);

    return $results;
  }

  private function SolveGet(array $res, array $table, bool $multi) : array
  {
    if (isset($table['_get'])) {
      $get = $table['_get'];
      if (self::$_FORCE_CLEAN) $get['_it_state'] = 'clean';
      if (!isset($get['_it_state'])) $res = array_merge($res, $table['_items']);
      else {
        switch ($get['_it_state']) {
          case 'clean':
          // do nothing
          break;
        }
      }
    } else {
      // default actions
      if(!self::$_FORCE_CLEAN && isset($table['_items'])) $res = array_merge($res, $table['_items']);
    }

    return $res;
  }

  private function DelData(string $index, $inVal)
  {
    $sql        = "DELETE FROM " . self::$_CUR_TABLE . " WHERE " . $index . " = :" . $index . ";";
    $handle = $this->link->prepare($sql);
    $handle->bindValue(':' . $index, $inVal);
    $handle->execute();

    return true;
  }
}
?>
