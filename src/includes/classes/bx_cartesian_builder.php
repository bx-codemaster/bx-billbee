<?php
/* -----------------------------------------------------------------------------------------
   $Id: bx_cartesian_builder.php 14837 2023-06-15 00:00:00Z benax $
    _                           
   | |__   ___ _ __   __ ___  __
   | '_ \ / _ \ '_ \ / _ \ \/ /
   | |_) |  __/ | | | (_| |>  < 
   |_.__/ \___|_| |_|\__,_/_/\_\
   xxxxxxxxxxxxxxxxxxxxxxxxxxxxx

   modified eCommerce Shopsoftware
   http://www.modified-shop.org

   Copyright (c) 2009 - 2013 [www.modified-shop.org]
   -----------------------------------------------------------------------------------------
   Released under the GNU General Public License
   ---------------------------------------------------------------------------------------*/
  
   class bxCartesianBuilder {
    private int $id;
    private $attr = array(array());
  
    public function __construct($id) { 
      $this->id = $id;
      $this->getAtrributes();
    }
    
    private function getAtrributes() {
      $num = 0; $i = 0;
      $productsQuery = xtc_db_query( "SELECT options_id, 
          CONCAT_WS(\"-\", LPAD(options_id, 4, \"0\"), LPAD(options_values_id, 4, \"0\")) AS attributes 
          FROM products_attributes 
         WHERE products_id = '".$this->id ."' ORDER BY attributes" );
  
      while( $product = xtc_db_fetch_array( $productsQuery ) ) {
        if( $num === (int)$product['options_id'] || $num === $i ) {
          array_push($this->attr[$i], $product['attributes']);
        } else {
          $i++;
          $this->attr[$i][] = $product['attributes']; 
        }
        $num = (int)$product['options_id'];
      }
    }
    
    private function cartesian_helper($sofar, $arr, $pos, $max, &$collector) {
      $len = count($arr[$pos]);
      if($len > 0) {
        for($i = 0; $i < $len;$i++) {
          if($pos == $max) {
            $collector[] = $sofar.$arr[$pos][$i];
          } else {
            $this->cartesian_helper($sofar.$arr[$pos][$i].'x', $arr, $pos+1, $max, $collector);
          }
        }
      } else {
        $collector[] = substr($sofar, 0, -1);
      }
    }
    
    public function getCartesian( ) {
      $bucket = array();
      $currId = str_pad($this->id, 4, "0", STR_PAD_LEFT);
      $this->cartesian_helper($currId.'_', $this->attr, 0, count($this->attr)-1, $bucket);
      return $bucket;
    }
  }