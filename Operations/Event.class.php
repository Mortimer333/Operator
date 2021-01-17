<?php
  namespace Operations;

  /**
   * Stage events controller
   */
  class Event extends \Operations
  {
    public static function Fire(string $trigger, object $event)
    {
      $events = self::$_Arch->_events;
      // echo"EVENT - " . $trigger . PHP_EOL;
      if (!isset($events          )) return;
      if (!\Vali::Array($events   )) exit(json_encode(array("error" => '[Event] Podane Eventy nie są tablicą.')));
      if (!isset($events[$trigger])) return;

      foreach ($events[$trigger] as $eventItem) {
        if (!isset($eventItem['type'])) exit(json_encode(array("error" => '[Event] Akcja nie posiada typu.')));
        Event::Action((object) $eventItem, $event);
      }
    }

    public static function Action(object $action, object $event)
    {
      if (isset($action->table))
        if( !isset($event->table) || (isset($event->table) && $event->table != $action->table) ) return;
      // echo"ACTION - " . $action->type . PHP_EOL;

      switch ($action->type) {
        case 'replace':
          if (!isset($action->dir  )) exit(json_encode(array("error" => '[Event] Akcja nie posiada wskaźnika (drogi do zmiennej na którą ma oddziałać).')));
          if (!isset($action->value)) exit(json_encode(array("error" => '[Event] Akcja nie posiada zamiennej wartości.')));
          Event::Replace($action->dir, $action->value);
          break;
        case 'swapVars' :
          self::$_Arch->SwapValueVars();
          break;
        default:
          exit(json_encode(array("error" => '[Event] Nie rozpoznany typ Akcji `' . Tools::EscHTML($action->type) . '` w Event\'cie.')));
          break;
      }
    }

    public static function Replace(string $dir, $value)
    {
      if (strpos($value,"_fun->") !== false) {
        $commends = explode("->",$value);
        $commends = array_slice($commends,1); // here we delete _fun
        $value = Event::GetValue($commends);
        // echo"VALUES -> " . $value . PHP_EOL;
      }

      $dir = explode(".", $dir);
      self::$_Values = self::ReplaceByDir($dir, $value, self::$_Values);
    }

    private function ReplaceByDir(array $dir, $value, array $curDir) : array
    {
      if ($dir[0] === "_all") {
        if (sizeof($dir) !== 2) exit(json_encode(array("error" => '[Event] Przy użycia _all w Akcji Replace może się za nią znajdować tylko nazwa danej która wszędzie trzeba podmienić.')));
        $curDir = self::ReplaceAll($dir[1], $value, $curDir);
      } else if (sizeof($dir) === 1) {
        if (!isset($curDir[$dir[0]])) exit(json_encode(array("error" => '[Event] Nie istnieje taka dana jak ' .\Tools::EscHtml($dir[0]) . ' w podanym miejscu.')));
        $curDir[$dir[0]] = $value;
      } else {
        if (!isset($curDir[$dir[0]])) exit(json_encode(array("error" => '[Event] Nie istnieje taka ścieżka - ' . \Tools::EscHtml($dir[0]) . '.')));
        $curDir[$dir[0]] = self::ReplaceByDir(array_slice($dir,1), $value, $curDir[$dir[0]]);
      }

      return $curDir;
    }

    private function ReplaceAll($name, $value, $dir) : array
    {
      foreach ($dir as $key => $subDir) {
        // echo$key . PHP_EOL;
        if (\Vali::Array($subDir)) {
          $dir[$key] = self::ReplaceAll($name, $value, $subDir);
        } else if ($key === $name) {
          $dir[$key] = $value;
        }
      }

      return $dir;
    }

    public static function GetValue(array $commends, $var = false)
    {
      if (sizeof($commends) == 0) exit(json_encode(array("error" => '[Event] Podane komendy do GetValue są puste.')));
      $comDirs = explode(":",$commends[0]);
      if (sizeof($comDirs) !== 2) exit(json_encode(array("error" => '[Event] Jedno z poleceń w GetValue jest źle złożone i posiada więcej bądź mniej argumentów niż 2: ' . $commends[0] . '.')));
      if (sizeof($commends) > 1) {
        $var = self::GetVar($comDirs[0], $comDirs[1], $var);
        return self::GetValue(array_slice($commends,1), $var);
      } else return self::GetVar($comDirs[0], $comDirs[1], $var);
    }

    private function GetVar($com, $name, $var)
    {
      $args = [];
      switch ($com) {
        case 'class' :
          return new $name();

        case 'method' :
        case 'static' :
        case 'func'   :
          $args_pos = strpos( $name, '(' );
          if ( $args_pos !== false ) {
            $end  = strpos( $name, ')', $args_pos );

            $args = substr( $name, $args_pos + 1, $end - ( $args_pos + 1 ) );
            $args = str_replace( ', ', ',', $args );
            $args = trim( $args );
            $args = explode( ',', $args );

            foreach ($args as $key => $value) {
              $args[$key] = Validate::RepVar( $value );
            }

            $name = substr( $name, 0, $args_pos );
          }

          switch ( $com ) {
            case 'func' :
              if ( sizeof( $args ) > 0 ) return call_user_func_array( $name, $args );
              else return $name();

            case 'method' :
              if ( sizeof( $args ) > 0 ) return call_user_func_array( array($var, $name), $args );
              else return $var->$name();

            case 'static' :

              if ( sizeof( $args ) > 0 ) return call_user_func_array( array($var, $name), $args );
              else return $var::$name();
          }

          break;

        default:
          exit(json_encode(array("error" => '[Event] Nieznana akcja w GetVar.')));
          break;
      }
    }
  }
