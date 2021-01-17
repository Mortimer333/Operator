<?php
  namespace Operations;

  /**
   * Class for rendering stages into admin view
   */

  class Render extends \Operations
  {

    static $_ID = 0;
    static $_ERRORS = [];


    public static function Arch($id = false, $arch = false)
    {
      if ($id   !== false) self::$_ID  = $id;
      if ($arch === false) $arch       = self::$_Arch->_arch;

      foreach ($arch as $conClass => $blocks):

        if (\Vali::String($conClass) && $conClass[0] === "_") {
          self::ResolverActions($conClass, $blocks);
          continue;
        }
      ?>
        <?php if (\Vali::String($conClass) && $conClass != ""): ?><div class="<?= $conClass; ?>"><?php endif; ?>
          <?php self::Arch(false, $blocks) ?>
        <?php if (\Vali::String($conClass) && $conClass != ""): ?></div><?php endif; ?>
      <?php endforeach;
    }

    public static function ResolveRender(array $blocks, $id, $data, $dataPool)
    {
      foreach ($blocks as $key => $block) {
        Render::ResolverActions($key, $block, $id, $data, $dataPool);
      }
    }

    public static function ResolverActions(string $key, $block)
    {
      switch ($key) {
        case '_wid' :
          if (\Vali::String($block)) {

            $name = $block;
            $dir  = explode('.',$block);

          } else if (\Vali::Array($block)) {
            $dir  = explode('.',$block['_dir']);
            $name = $block['_dir'];
            self::$_CUR_TABLE = $dir[0]; // setting current table
          } else {
            self::$_ERRORS[] = Validate::Error("Block widgetu był źle skontruowany, nie był ani tekstem ani tablicą - " . json_encode($block));
            break;
          }

          if (sizeof($dir) === 0) self::$_ERRORS[] = Validate::Error("Ścieżka do widgetu była za krótka, bądź źle skonstruowana - " . \Tools::EscHTML($block));
          $destination = self::Get($dir, self::$_Arch->_data);

          if (isset($destination['_multi'])) {
            $items = self::Get($dir, self::$_Values);
            self::$_CUR_SIZE = sizeof($items);

            self::$_CUR_ITER = 0;

            do {

              if (sizeof( $items ) > 0) $item = $items[ self::$_CUR_ITER ];
              else                      $item = [];

              isset($block['_preface' ]) ? Render::ResolverActions( "_preface" , $block['_preface' ] ) : null ;
              isset($block['_!preface']) ? Render::ResolverActions( "_!preface", $block['_!preface'] ) : null ;

              if (isset($block['_arch']) && sizeof( $item ) > 0 ) {
                Render::Arch(false, $block['_arch']);
              } else {
                foreach ($item as $c_name => $value) {
                  self::PrepWidget($destination[$c_name], $name . $c_name, array_merge($dir,[$i,$c_name]));
                }
              }
              isset($block['_addon' ]) ? Render::ResolverActions( "_addon" , $block['_addon' ] ) : null ;
              isset($block['_!addon']) ? Render::ResolverActions( "_!addon", $block['_!addon'] ) : null ;

              self::$_CUR_ITER++;

            } while ( self::$_CUR_ITER < sizeof( $items ) );

            self::$_CUR_ITER = -1;
            self::$_CUR_SIZE = -1;

          } else {

            if (self::$_CUR_ITER != -1) {
              $temp = array_slice($dir,0,sizeof($dir) - 1);
              $temp[] = self::$_CUR_ITER;
              $name_temp = array_slice($dir,-1);
              $dir = array_merge($temp, $name_temp);
            }

            if (self::$_CUR_ITER != -1) {
              isset($block['_preface' ]) ? Render::ResolverActions( "_preface" , $block['_preface' ] ) : null ;
              isset($block['_!preface']) ? Render::ResolverActions( "_!preface", $block['_!preface'] ) : null ;
            }

            self::PrepWidget($destination, $name, $dir);

            if (self::$_CUR_ITER != -1) {
              isset($block['_addon' ]) ? Render::ResolverActions( "_addon" , $block['_addon' ] ) : null ;
              isset($block['_!addon']) ? Render::ResolverActions( "_!addon", $block['_!addon'] ) : null ;
            }
          }

          if ( \Vali::Array( $block ) ) self::$_CUR_TABLE = '';
          break;

        case "_html" :
          $html = Validate::RepVar($block, self::$_CUR_TABLE); // replacing all left over variables

          if (isset($html['_error'])) {
            $_ERRORS[] = $html['_error'];
            echo "Wystapił bład i nie można pokazać widgetu";
            return;
          }

          echo str_replace(["_URL_","_ID_"],[\Path::$URL,self::$_ID],$html);
          break;

        case "_addon" :
          if ( self::$_CUR_ITER + 1 == self::$_CUR_SIZE || self::$_CUR_SIZE == 0 ) Render::Arch(false, $block);
          break;

        case "_!addon" :
          if ( self::$_CUR_ITER + 1 != self::$_CUR_SIZE || self::$_CUR_SIZE == 0  ) Render::Arch(false, $block);
          break;

        case "_preface" :
          if (self::$_CUR_ITER == 0 || self::$_CUR_SIZE == 0 ) Render::Arch(false, $block);
          break;

        case "_!preface" :
          if (self::$_CUR_ITER != 0 || self::$_CUR_SIZE == 0 ) Render::Arch(false, $block);
          break;

        default :
           self::$_ERRORS[] = Validate::Error("Nierozpoznany typ akcji - " . \Tools::EscHTML($key));
      }
    }

    private function Get(array $dir, $data)
    {
      if (sizeof($dir) > 1) return self::Get(array_slice($dir,1), $data[$dir[0]]);
      else                  return $data[$dir[0]];
    }

    private function PrepWidget(array $destination, string $des, array $dir)
    {
      $index = [];
      $index["dir"  ] = $des;
      $index["field"] = $destination['field'];
      $index["value"] = self::Get($dir, self::$_Values);
      $index['value'] = Validate::RepVar($index      ['value'], array_slice($dir,0,sizeof($dir) - 1));
      $index['name' ] = Validate::RepVar($destination['name' ], array_slice($dir,0,sizeof($dir) - 1));

      $add = [];
      if (isset($destination['add'])) $add = $destination['add'];

      self::Widget($index, $add);
    }

    public static function Widget(array $index, array $add = [])
    {
      $str_id         = $index['dir'] . "_" . self::$_ID;
      $name           = $index['dir'];
      if (self::$_CUR_ITER != -1) $str_id .= "_" . self::$_CUR_ITER;

      switch ($index['field']) {
        case 'text'    :
        case 'numeric' :
          echo \Widgets::InputText(array_merge([
            "id"          => $str_id,
            "name"        => $name,
            "placeholder" => $index['name'],
            "value"       => $index['value']
          ], $add));
          break;
        case 'date' :
          echo \Widgets::InputDate(array_merge([
            "id"          => $str_id,
            "name"        => $name,
            "placeholder" => $index['name'],
            "value"       => $index['value'] === "1970-01-01" ? date('Y-m-d') : date("Y-m-d",strtotime($index['value']))
          ], $add));
          break;
        case 'file' :
        case 'hidden' :
          echo "<input type='hidden' value='" . $index['value'] . "' name='" . $name . "'>";
          break;
        case "rich_editor" :
          echo \Widgets::WYSIWYG(array_merge([
            "id"          => $str_id,
            "placeholder" => $index['name'],
            "value"       => $index['value'],
            "name"        => $name,
            "incl" => [
              'comment' => false
            ]
          ], $add));
          break;
        case "check" :
          echo \Widgets::Checkbox(array_merge([
            "checkboxes" => [$str_id => [$name, $index['value'], $index['name']]]
          ], $add));
          break;
        case 'listItem' :
          if (!isset($add['oninput'])) $add['oninput'] = "";
          if (!isset($add['onfocus'])) $add['onfocus'] = "";

          echo "<div class=\"pointCon flexCenter\">
                  <div class=\"point\"></div>
                </div>
                <input type=\"text\" value=\"" . $index['value'] . "\" placeholder=\"" . $index['name'] . "\" name=\"" . $name . "\"
                oninput='" . $add['oninput'] . "'
                onfocusout=\"" . $add['onfocus'] . "\">";
          break;
        case "award_script" :
          $_Comp = new \Competition();
          $award = $_Comp->GetAward($index['value']);
          if (!isset($award->id)) {
            echo "Nie istnieje nagroda ze skryptem o id - " . $index['value'];
            return;
          }
          $stage_id = 0;
          if (isset(self::$_Values['stages'])) $stage_id = self::$_Values['stages']['id'];

          echo "<span class=\"awardBox\" award_id=\"" . $award->id . "\" stage_id=\"" . $stage_id . "\" name=\"" . $name . "\">
                  " . $award->name . "
                  <img class=\"del\" src=\"" . \Path::$URL . "media/img/icons/del.png\" alt=\"del\" onclick=\"deleteScriptAward(event.target)\">
                </span>";
          break;
        default:
          echo "Nieznany typ - " . $index['field'];
          break;
      }
    }
  }
