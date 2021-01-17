<?php

/**
 * Mother class for Operations
 */

class Operations extends Connection
{
  static array            $_Values;
  static Operations\Arch  $_Arch ;
  static $_CUR_ITER   = -1;
  static $_CUR_SIZE   = -1;
  static $_CUR_TABLE  = '';

  public function __construct($stage = false){
    parent::__construct();
    if ($stage !== false) self::$_Values = $stage;
  }
}
?>
