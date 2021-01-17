<?php
namespace Operations;
/**
 * Class for manipulating arch of stages
 */
class Arch extends \Operations
{

  private $_TYPE;

  function __construct($type, $arch = false)
  {
    if (!$arch) {
      $this->_TYPE = $type;
      if (!is_numeric($this->_TYPE)) strtolower($this->_TYPE);
      switch ($this->_TYPE) {
        case 'writing': // Writing
        case '1':
        $this->_TYPE = 1;
        $path = \Path::Configs('stages_arch/writing.stage.json');
        break;
        case 'judging': // Judging
        case '2':
        $this->_TYPE = 2;
        $path = \Path::Configs('stages_arch/judging.stage.json');
        break;
        case 'breaks': // Breaks
        case '3':
        $this->_TYPE = 3;
        $path = \Path::Configs('stages_arch/breaks.stage.json');
        break;
        default:
        $path = \Path::Configs('blocks_arch/' . \Tools::EscHTML($this->_TYPE) . '.json');
        if (!is_file($path)) exit(json_encode(array("error" => "[Arch] Nieobsługiwany Typ Architektury.")));
        break;
      }


      if (is_file($path)) $arch = json_decode(file_get_contents($path), true);
      else                exit(json_encode(array("error" => "[Arch] Nie znaleziono architektury.")));
    }

    if (!isset($arch['_arch'])) exit(json_encode(array("error" => "[Arch] Wykaz architektury (_arch) nie został znaleziony.")));

    $arch = self::GetExpand($arch);

    if (isset($arch['_data']['stages']["type"])) $arch['_data']['stages']["type"]['value'] = $this->_TYPE;

    foreach ($arch as $key => $value) {
      $this->$key = $value;
    }

    self::$_Arch = $this;
    $this->Restore();
  }

  // SwapVars doesn't work with this too well so I'm gonna leave it for later

  // public function SwapVars()
  // {
  //   foreach ($this->_data as $t_name => $table) {
  //     $this->_data[$t_name] = self::IterateOverAllColumnAndSwapVars($table, $t_name, $this->_data[$t_name]);
  //   }
  //
  //   return $this->_data;
  // }
  //
  // private function IterateOverAllColumnAndSwapVars(array $table, string $t_name, array $data) : array
  // {
  //   foreach ($table as $c_name => $column) {
  //     if (\Vali::Array($column)) $table[$c_name] = self::IterateOverAllColumnAndSwapVars($column, $c_name, $data[$c_name]);
  //     else {
  //       if (\Vali::String($column) && strpos($column,"_{") !== false){
  //         $table[$c_name] = Validate::RepVar($column, $data, $t_name, $this->_data);
  //       }
  //     }
  //   }
  //
  //   return $table;
  // }

  public function SwapValueVars()
  {
    foreach (self::$_Values as $t_name => $table) {
      if ( $t_name == "_error" ) return Validate::Error( $table );
      self::$_Values[$t_name] = self::IterateAndSwapValueVars($table, $t_name, self::$_Arch->_data[$t_name]);
    }

    return self::$_Values;
  }

  private function IterateAndSwapValueVars(array $table, string $t_name, array $data) : array
  {
    foreach ($table as $c_name => $column) {

      if (\Vali::String($c_name)) {
        if (!isset($data[$c_name])) continue;
        $tempData = $data[$c_name];
      } else $tempData = $data;

          if (\Vali::Array($column )) $table[$c_name] = self::IterateAndSwapValueVars($column        , $c_name, $tempData);
      elseif (\Vali::Object($column)) $table[$c_name] = self::IterateAndSwapValueVars((array) $column, $c_name, $tempData);
      else {
        if (isset($data[$c_name]['value'])) {
          // all vars have to be defined inside of value attribute so if it doesn't have it then we skip
          $tempValue = Validate::RepVar($data[$c_name]['value'], $t_name);
          // if value was changed after replacement then it means we have to swap proper value
          // thanks to that check we wont be setting all values to default ones
          if ($tempValue != $data[$c_name]['value']) $table[$c_name] = $tempValue;
        }
      }

    }

    return $table;
  }

  public function replaceData(array $data)
  {
    $values = [];

    foreach ($data as $name => $value) {
      if (\Vali::Array($value)) {
        $values[$name] = self::replaceData($value);
      }

      $values[$name] = $value;
    }

    return $values;
  }

  private function IterateAndReplaceValue(array $table, string $t_name, array $data) : array
  {
    foreach ($table as $c_name => $column) {
      if (isset($data[$c_name])) $data[$c_name] = $column;
      else if (\Vali::Array($column)) $data[$c_name] = self::IterateAndReplaceValue($column, $c_name, $data[$c_name]);
    }
  }

  /**
  * Gets all files to expand this stage arch and the expands of the files which will expand stage
  *
  * @param arch - array of the architecture of stage
  *
  * @return array - returns expanded arch
  */

  private function GetExpand(array $arch) : array
  {
    if (isset($arch['_expand']['before'])) {
      foreach ($arch['_expand']['before'] as $name) {
        $path = \Path::Configs('stages_arch/' . $name . '.json');
        if (is_file($path)) {

          $exp = json_decode(file_get_contents($path), true);

          if (isset($exp['_expand'])) $exp = self::GetExpand($exp);

          foreach ($exp as $key => $value) {
            if (isset($arch[$key])) $arch[$key] = array_merge($value,$arch[$key]);
            else                    $arch[$key] = $value;
          }

        }
      }
    }

    if (isset($arch['_expand']['after'])) {
      foreach ($arch['_expand']['after'] as $name) {

        $path = \Path::Configs('stages_arch/' . $name . '.json');
        if (is_file($path)) {

          $exp = json_decode(file_get_contents($path), true);

          if (isset($exp['_expand'])) $exp = self::GetExpand($exp);

          foreach ($exp as $key => $value) {
            if (isset($arch[$key])) $arch[$key] = array_merge($arch[$key],$value);
            else                    $arch[$key] = $value;
          }

        }
      }
    }

    return $arch;
  }

  public function Restore()
  {
    self::$_Values = [];
    $data = self::$_Arch->_data;
    foreach ($data as $table => $columns) {
      self::$_Values[$table] = self::RestoreResolve($columns);
    }
  }

  private function RestoreResolve(array $columns)
  {
    $tableAr = [];
    if (isset($columns['_multi'])) {
      $tableAr = $columns['_items'];
    } else {
      foreach ($columns as $c_name => $column) {

        if (\Vali::String($c_name) && $c_name[0] === "_") continue;

        if (!isset($column['value']) && \Vali::Array($column)) $tableAr[$c_name] = self::RestoreResolve($column);
        else $tableAr[$c_name] = Validate::RepVar($column['value'], $c_name);


      }
    }

    return $tableAr;
  }

  public function Update(array $values, string $table, string $_sep, bool $multi)
  {
    if ($multi) {
      $items = [];
      $values = array_reverse($values);
      if ($_sep != "") $_data = self::$_Arch->_data[$table]['_separator'][$_sep];
      else             $_data = self::$_Arch->_data[$table];
      if (isset($_data['_get']['_it_state'])) {
        switch ($_data['_get']['_it_state']) {
          case 'clean':
            break;
          default :
            array_merge($items,$_data['_items']);
        }
      }
      foreach ($values as $value) {
        array_unshift($items,self::Update((array)$value, $table, $_sep, false));
      }

      if ($_sep != "") self::$_Values[$table]['_separator'][$_sep] = $_data;
      else             self::$_Values[$table] = $_data;

    } else {
      if ($_sep != "") $_data = self::$_Arch->_data[$table]['_separator'][$_sep];
      else             $_data = self::$_Arch->_data[$table];

      if (isset($_data['_multi'])) {
        return self::CreateItemFromTemplate($_data['_template'], $values);
      } else {
        foreach ($values as $key => $value) {
          if (\Vali::String($key) && $key[0] === "_") continue;
          if ($_sep != "") self::$_Values[$table]['_separator'][$key] = $value;
          else             self::$_Values[$table][$key] = $value;
        }
      }
    }
  }

  private function CreateItemFromTemplate(array $template, array $values) : array
  {
    $item = [];
    foreach ($template as $key => $value) {
      $item[$key] = $values[$key];
    }
    return $item;
  }

}
