<?php

namespace Operations;

/**
 * Class for validating stage data
 */
class Validate extends \Operations
{
  public static function Arch($values = [], $arch = [])
  {
    if ($arch   === []) $arch   = self::$_Arch->_data;
    if ($values === []) $values = self::$_Values;
    foreach ($arch as $table => $columns) {
      if (!isset($values[$table])) return Validate::Error("Etap nie posiada tabeli `" . $table . "`.");
      $s_table = $values[$table];
      if (isset($columns["_multi"]) && $columns["_multi"] == true) {

        foreach ($s_table as $s_items) {
          foreach ($columns as $column_name => $column) {

            if ($column_name === "_conditions") {
              foreach ($s_table as $item) {
                $res = Validate::Condition($column,array($table => $item),$table,$values);
                if ($res !== true) return $res;
              }
            }

            if ($column_name[0] == "_"                                                            ) continue; // here we check if it's argument or real column (if it starts from _ it's argument)
            if (!isset($s_items[$column_name]) && isset($column['req']) && $column['req'] == false) continue;

            if (!isset($s_items[$column_name])                                        ) return Validate::Error("Etap nie posiada kolumny `" . $table . "." . $column_name . "`.");
            if (!\Vali::date($s_items[$column_name],$column["typeof"],$column["name"])) return \Vali::$_LAST_ERROR;
          }
        }

      } else {

        foreach ($columns as $column_name => $column) {

          if ($column_name === "_conditions") {
            $res = Validate::Condition($column,$values,$table);
            if ($res !== true) return $res;
          }

          if ($column_name[0] == "_") continue; // here we check if it's argument or real column (if it starts from _ it's argument)

          if (!isset($column['value'])) return self::Arch($s_table,$column);

          if (!isset($s_items[$column_name]) && isset($column['req']) && $column['req'] == false) continue;

          if (!isset($s_table[$column_name])) return Validate::Error("Etap nie posiada kolumny `" . $table . "." . $column_name . "`.");

          $s_column = $s_table[$column_name];

          $val = \Vali::date($s_column,$column["typeof"],$column["name"]);
          // echo $column["name"] . " - " . strval($val) . PHP_EOL;
          if (!$val) return \Vali::$_LAST_ERROR;
        }

      }
    }

    return true;
  }

  public static function Conditions(array $values, $data = false)
  {
    if (!$data) $data = self::$_Arch->_data;
    foreach ($data as $table => $columns) {
      if (!isset($values[$table])) return Validate::Error("Dana nie posiada tabeli `" . $table . "`.");
      $s_table = $values[$table];
      if (isset($columns["_multi"]) && $columns["_multi"] == true) {

        foreach ($s_table as $s_items) {
          foreach ($columns as $column_name => $column) {

            if ($column_name === "_conditions") {
              foreach ($s_table as $item) {
                $res = Validate::Condition($column,array($table => $item),$table,$values);
                if ($res !== true) return $res;
              }
            }

            if ($column_name[0] == "_"                                                            ) continue; // here we check if it's argument or real column (if it starts from _ it's argument)
            if (!isset($s_items[$column_name]) && isset($column['req']) && $column['req'] == false) continue;

            if (!isset($s_items[$column_name])                                        ) return Validate::Error("Dana nie posiada kolumny `" . $table . "." . $column_name . "`.");
            if (!\Vali::date($s_items[$column_name],$column["typeof"],$column["name"])) return \Vali::$_LAST_ERROR;
          }
        }

      } else {

        foreach ($columns as $column_name => $column) {
          if ($column_name === "_conditions") {
            $res = Validate::Condition($column,$values,$table);
            if ($res !== true) return $res;
          }
          if ($column_name[0] == "_"        ) continue; // here we check if it's argument or real column (if it starts from _ it's argument)

          if (!isset($s_items[$column_name]) && isset($column['req']) && $column['req'] == false) continue;

          if (!isset($s_table[$column_name])) return Validate::Error("Dana nie posiada kolumny `" . $table . "." . $column_name . "`.");

          $s_column = $s_table[$column_name];
          if (!\Vali::date($s_column,$column["typeof"],$column["name"])) return \Vali::$_LAST_ERROR;
        }

      }
    }

    return true;
  }

  /**
  * Function for sorting Stages by order they are setted, it also checks if there is not holes in the order like 1,2,4
  *
  * @param stages array with stages
  *
  * @return array of sorted stages by order
  */

  public static function Order(array $stages) : array
  {
    $sortedStages = [];
    // I'm adding spaces in $sortedStages equal to the length of the $stages
    // so we will put them where their order tells to (-1 cuz it will be from 1)
    // and if we got order which is higher then length of newStages then we will throw error
    // "Incorrect order. The Stages have holes between them.";

    // Why not just check the length of $stages? We can but I wanna put $stages immediately in $sortedStages in right position
    // so I have to replace already existing instances in array. Ex: first element of $stages has order = 4 so you want to
    // put it in 3rd position (cuz it starts from 0) but you have empty array so $sortedStages[3] will throw error.
    // But if you have array with empty values like [] or "" of length 4 you can replace $sortedStages[3] with your stage
    // I think it's better solution for this then creating reseting loops when you have order writen inside of object
    // you sort.
    for ($i=0; $i < sizeof($stages); $i++) {
      $sortedStages[] = '';
    }

    foreach ($stages as $stage) {
      if (!isset($stage['stages']['order_pos'])) return Validate::Error("Etap nie posiada pozycji, nie można go ułożyć.");
      $order = $stage['stages']['order_pos'];
      if (!\Vali::Int($order,"Pozycja Etapu"  )) return \Vali::$_LAST_ERROR;
      if ($order - 1 > sizeof($sortedStages   )) return Validate::Error('Nieprawidłowa Kolejność. Etapy mają dziury między sobą.');
      $sortedStages[$order - 1] = $stage;
    }

    return $sortedStages;
  }

  public static function Overlap (array $stages, string $comp_end, string $ass_end)
  {
    foreach ($stages as $i => $stage) {
      if (!isset($stage["stages"]['end'])) return Validate::Error("Etap nie posiada daty zakończnia.");
      $end = $stage["stages"]['end'];
      if ($i === 0) {
        if (strtotime($ass_end) >= strtotime($end)) return Validate::Error("Pierwszy Etap nie może się kończyć wcześniej, bądź tego samego dnia niż data zakończenia zapisywania się użytkowników na konkurs.");
      } else {
        if (strtotime($stages[$i - 1]["stages"]['end']) >= strtotime($end)) return Validate::Error("Etap nie może się kończyć wcześniej, bądź tego samego dnia co poprzedni Etap.");
      }
    }

    if (strtotime($stages[sizeof($stages) - 1]["stages"]['end']) >= strtotime($comp_end))
      return Validate::Error("Konkursu nie może się kończyć wcześniej, bądź tego samego dnia co ostatni Etap.");

    return true;
  }

  /**
  * This function checks if stages can by one after each other. It check sis one stage does depend on other and if they are correctly set in time.
  *
  * @param sortedStages array of sorted stage by their order
  *
  * @return bool
  */

  public static function Dependencies(array $sortedStages)
  {
    foreach ($sortedStages as $index => $stage) {
      if (!isset($stage['stages']['type']))                  return Validate::Error("Etap nie posiada typu, nie można go zwalidować.");
      if (!\Vali::Int($stage['stages']['type'],"Typ Etapu")) return \Vali::$_LAST_ERROR;

      $_Comp = new \Competition();
      $type = $_Comp->GetType($stage['stages']["type"]);

      if (!isset($type->type))
        return Validate::Error('Etap ma nieznany typ.');

      if (!$type->can_start && $index === 0)
        return Validate::Error('Ten Etap nie może zacząć konkursu.');

      if (!isset($type->depends_on))
        continue;

      if ($type->depends_on && $index === 0)
        return Validate::Error('Ten Etap nie może zacząć konkursu ponieważ potrzebuje innego Etapu do działania.');

      for ($i = $index; $i <= 0; $i--) {
        if ($sortedStages[$i]['stages']['type'] === $type->depends_on)  break;
        if ($i === 0) return Validate::Error('Nie znaleziono Etapu potrzebnego do działania konkursu. Typ - ' . $type->name);
      }
    }

    return true;
  }

  public static function Error(string $error, array $data = [], array $abs_data = [], string $cur_table = '')
  {
    if (sizeof($data) > 0 && sizeof($abs_data) > 0  && $cur_table != '') $error = Validate::RepVar($error, $cur_table, $data);
    return array("_error" => $error);
  }

  public static function RepVar(?string $var, $cur_table = false, array $_data = [])
  {
    if ( $cur_table === false ) $cur_table = self::$_CUR_TABLE;
    if (!isset($var)) return "";

    if (sizeof($_data) === 0) $_data = self::$_Arch->_data;

    $strpos = strpos($var,'${');
    if ($strpos !== false) {
      // after : ypu can define how script will interpret what you wrote
      if (substr( $var, $strpos + 2, 1 ) == ":") {

        $endType = strpos( $var, " ", $strpos );
        $type    = substr( $var, $strpos + 3, $endType - ($strpos + 3) );
        $end     = strpos( $var, "}:" . $type, $endType );
        $data    = substr( $var, $strpos + 3 + strlen($type), $end - ($strpos + 3 + strlen($type) ) );
        $data    = str_replace( " ", "", $data );

        switch ( $type ) {
          case 'f':
            $data = explode( '->', $data );
            $res = Event::GetValue( $data );
            if (isset($res['_error'])) return $res;
            $str = substr( $var, $strpos, ( $end + 2 + strlen($type) ) - ( $strpos ) );
            $var = str_replace($str, $res, $var);
            return $var;

          default:
            return "Nieznany typ akcji - " . $type;
            break;
        }
      }

      $strposend =  strpos($var,"}",$strpos);
      if ($strposend !== false) {

        $strDir     = substr($var,$strpos + 2, $strposend - ($strpos + 2));
        $directions = explode(".",$strDir);
        if (isset(self::$_Values)) {
          if ($directions[0] === "this") {

            $directions[0] = $cur_table;
            if (self::$_CUR_ITER != -1) array_splice( $directions, 1, 0, self::$_CUR_ITER ); // if script is working on multi element then add to direction index of current value

            $value = Validate::FindValue(self::$_Values, $directions);

            if (isset($value['_error'])) {
              // this is our fallback if the value wasn't found in Values.
              // We check if it exists in Arch and if so we deliver default value for the type

              $column = Validate::FindValue(self::$_Arch->_data, $directions);
              if (isset($column['_error'])) $value = $column; // it doesn't exist
              else $value = self::GetDefault($column['typeof']);
            }

          } else {
            $value = Validate::FindValue(self::$_Values, $directions);

            if (isset($value['_error'])) {
              // debug_print_backtrace();
              // this is our fallback if the value wasn't found in Values.
              // We check if it exists in Arch and if so we deliver default value for the type

              $column = Validate::FindValue(self::$_Arch->_data, $directions);
              if (isset($column['_error'])) $value = $column; // it doesn't exist
              else $value = self::GetDefault($column['typeof']);

            }
          }
        } else {
          $value = "";
        }

        if (isset($value['_error'])) return $value;
        $var = str_replace('${' . $strDir . "}", $value, $var);
        return $var;

      } else return Validate::Error('Wartość pomimo rozpoczęcia zmiennej nie posiada jej zakończenia.');

    } else return $var;

  }

  public function GetDefault(string $type)
  {
    switch ($type) {
      case 'int':
        return 0;
      case 'decimal':
        return 0.0;
      case 'string':
        return "Default";
      case 'empty_string':
        return "";
      case 'bool':
        return false;
      case 'date':
        return date('Y-m-d');
      default:
        return null;
    }
  }


  public static function Condition(array $condition, array $data, string $cur_table, array $abs_data = [])
  {
    if (sizeof($abs_data) === 0) $abs_data = $data;

    foreach ($condition as $conItem) {
      if (!isset($conItem['error'])) return Validate::Error('Warunek nie posiada opisu błędu.');
      if (!isset($conItem['con']) || (isset($conItem['con']) && !\Vali::Array($conItem['con']))) return Validate::Error('Warunek nie posiada wskazówek.');
      foreach ($conItem['con'] as $j => $con) {
        if (\Vali::Array($con) && !\Vali::Assoc($con)) {
          foreach ($con as $i => $sub_con) {
            $res = Validate::CheckCond($sub_con['var1'], $sub_con['oper'], $sub_con['var2'], $data, $cur_table);
            if ($res) break;
            if ($i + 1 === sizeof($con)) return Validate::Error($conItem['error'], $data, $abs_data, $cur_table);
          }
        } else {
          $res = Validate::CheckCond($con['var1'], $con['oper'], $con['var2'], $data, $cur_table);
          if ($res) break;
          if ($j + 1 === sizeof($conItem['con'])) return Validate::Error($conItem['error'], $data, $abs_data, $cur_table);
        }
      }
    }

    return true;
  }

  private function GetAll(array $varAr, array $data)
  {
    $var = 0;
    foreach ($data[$varAr[0]] as $item) {
      if (is_numeric($item[$varAr[2]])) $var += $item[$varAr[2]];
      else return Validate::Error("Słowo klucz _all działa tylko na liczbach");
    }
    return $var;
  }

  private function GetAny(array $varAr, array $data) : array
  {
    $var = [];
    foreach ($data[$varAr[0]] as $item) {
      $var[] = $item[$varAr[2]];
    }
    return $var;
  }

  public static function CheckCond(string $var1, string $oper, string $var2, array $data, string $cur_table)
  {
    $varAr1 = explode(".",$var1);
    $varAr2 = explode(".",$var2);

    if ($varAr1[0] === "this") $varAr1[0] = $cur_table;
    if ($varAr2[0] === "this") $varAr2[0] = $cur_table;

    if (sizeof($varAr1) > 2) {
      if($varAr1[1] === "_all" && is_array($data[$varAr1[0]])) {
        $var1   = self::GetAll($varAr1, $data);
        if (\Vali::Array($var1)) return $var1;
        $varAr1 = [];
      } elseif ($varAr1[1] === "_any" && is_array($data[$varAr1[0]])) $var1 = self::GetAny($varAr1,$data);
    }

    if (sizeof($varAr2) > 2) {
      if($varAr2[1] === "_all" && is_array($data[$varAr2[0]])) {
        $var2   = self::GetAll($varAr2, $data);
        if (\Vali::Array($var2)) return $var2;
        $varAr2 = [];
      } elseif ($varAr2[1] === "_any" && is_array($data[$varAr2[0]])) $var2 = self::GetAny($varAr2,$data);
    }

    if (is_array($var1) && is_array($var2)) {
      foreach ($var1 as $i => $value1) {
        foreach ($var2 as $value2) {
          $res = Validate::Operator($value1, $oper, $value2);
          if (\Vali::Array($res) || $res === true) return $res;
          if ($i + 1 === sizeof($var1))                    return false;
        }
      }
    } elseif(is_array($var1)) {
      foreach ($var1 as $i => $value) {
        $res = Validate::Operator($value, $oper, $var2);
        if (\Vali::Array($res) || $res === true) return $res;
        if ($i + 1 === sizeof($var1))                 return false;
      }
    } elseif (is_array($var2)) {
      foreach ($var2 as $i => $value) {
        $res = Validate::Operator($var1, $oper, $value);
        if (\Vali::Array($res) || $res === true) return $res;
        if ($i + 1 === sizeof($var2))            return false;
      }
    }

    if (sizeof($varAr1) > 0 && is_string($varAr1[0]) && isset($data[$varAr1[0]])) $var1 = Validate::FindValue($data,$varAr1);
    if (\Vali::Array($var1)) return $var1;
    if (sizeof($varAr2) > 0 && is_string($varAr2[0]) && isset($data[$varAr2[0]])) $var2 = Validate::FindValue($data,$varAr2);
    if (\Vali::Array($var2)) return $var2;

    return Validate::Operator($var1, $oper, $var2);
  }

  public static function Operator(string $var1, string $oper, string $var2) : bool
  {
    switch ($oper) {
      case '>':
        $result = $var1 > $var2;
        break;
      case '<':
        $result = $var1 < $var2;
        break;
      case '>=':
        $result = $var1 >= $var2;
        break;
      case '<=':
        $result = $var1 <= $var2;
        break;
      case '==':
        $result = $var1 == $var2;
        break;
      case '===':
        $result = $var1 === $var2;
        break;
      case '!=':
        $result = $var1 != $var2;
        break;
      case '!==':
        $result = $var1 !== $var2;
        break;
      default:
        return Validate::Error("Nie rozpoznany operator");
        break;
    }
    // if any of this is true then the error occured (becuase you write error sequences)
    if ($result) return false;
    return true;
  }

  public static function FindValue(array $data, array $directions)
  {
    if (sizeof($directions) > 1) {
      $dir = $directions[0];
      if (!isset($data[$dir])) {
        return Validate::Error("[FV] Nie istnieje taka dana - " . $dir);
      }
      $directions = array_slice($directions,1);
      return Validate::FindValue($data[$dir], $directions);
    } else {
      if (isset($data[$directions[0]]))
        return $data[$directions[0]];
      else {
        return Validate::Error("[FV] Nie istnieje taka dana - " . $directions[0]);
      }
    }
  }

}
