<?php
/* -----------------------------------------------------------------------------------------
  $Id: /bx_billbee_test.php 1000 2023-03-20 13:00:00Z benax $
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
if( isset($_SERVER['REQUEST_METHOD']) ) {
  $request = date("d.m.Y H:i:s")."\n";
  switch (strtoupper($_SERVER['REQUEST_METHOD'])) {
    case "GET":
      $request .= "GET: ".$_SERVER['QUERY_STRING']."\n";
      $request .= "___________________________________________________________________ \n";
      break;
    case "POST":
      $request .= "POST: ".$_SERVER['QUERY_STRING']."\n";
      foreach($_POST as $key => $value) {
        $request .=  $key.": ".$value."\n";
      }
      $request .= "___________________________________________________________________ \n";
      break;
    case "DELETE":
        $request .= "DELETE: ".$_SERVER['QUERY_STRING']."\n";
        $request .= "___________________________________________________________________ \n";
        break;
    case "PUT":
      $request .= "PUT: ".$_SERVER['QUERY_STRING']."\n";
      $request .= "___________________________________________________________________ \n";
      break;
    case "TRACE":
      $request .= "TRACE: ".$_SERVER['QUERY_STRING']."\n";
      $request .= "___________________________________________________________________ \n";
      break;
    case "OPTIONS":
      $request .= "OPTIONS: ".$_SERVER['QUERY_STRING']."\n";
      $request .= "___________________________________________________________________ \n";
      break;
    case "HEAD":
      $request .= "HEAD: ".$_SERVER['QUERY_STRING']."\n";
      $request .= "___________________________________________________________________ \n";
      break;

  }
  $logfile = fopen("log/bx_billbee.log", "a");
  fwrite($logfile, $request);
  fclose($logfile);
}
?>