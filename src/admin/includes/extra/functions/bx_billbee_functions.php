<?php
/* ----------------------------------------------------------------------------------------------
   $Id: admin/includes/extra/functions/bx_billbee_functions.php 1000 2023-03-20 13:00:00Z benax $
    _                           
   | |__   ___ _ __   __ ___  __
   | '_ \ / _ \ '_ \ / _ \ \/ /
   | |_) |  __/ | | | (_| |>  < 
   |_.__/ \___|_| |_|\__,_/_/\_\
   xxxxxxxxxxxxxxxxxxxxxxxxxxxxx

   modified eCommerce Shopsoftware
   http://www.modified-shop.org

   Copyright (c) 2009 - 2013 [www.modified-shop.org]
   ----------------------------------------------------------------------------------------------
   Released under the GNU General Public License
   ----------------------------------------------------------------------------------------------*/


  function bx_billbee_get_group_id() {
    $result = array();	
    $result_query_raw = xtc_db_query("SELECT configuration_value AS value 
                                        FROM ".TABLE_CONFIGURATION."
                                      WHERE configuration_key = 'MODULE_BILLBEE_CONFIG_ID'");
    if( 0 < xtc_db_num_rows($result_query_raw)) {
      $result_query= xtc_db_fetch_array($result_query_raw);
    }
    $result = $result_query['value'];
    return $result;
  }

  function bx_get_language_ids() {
    $languages_array = array();
    $languages_query = xtc_db_query("SELECT languages_id,
                                            name
                                      FROM ".TABLE_LANGUAGES."
                                      WHERE status = '1'
                                  ORDER BY sort_order");

    while ($languages = xtc_db_fetch_array($languages_query)) {
      $languages_array[] = array (
        'id' => $languages['languages_id'],
        'text' => $languages['name'],
      );
    }
    return $languages_array;
  }

  function bx_searchArray($key, $st, $array) {
    foreach ($array as $k => $v) {
      if (isset($v[$key]) && $v[$key] === $st) {
        //return $v[$key];
        return $v["id"];
      }
    }
    return "default";
  }

  function bx_get_modified_status_id($billbee_status_id) {
    $modified_status_query = xtc_db_query("SELECT modified_status_id AS id 
                                            FROM ".TABLE_BB_ORDER_STATUS." 
                                            WHERE billbee_status_id = '".$billbee_status_id."';");
    
    $modified_status = xtc_db_fetch_array($modified_status_query);

    if( NULL !== $modified_status ) {
      return $modified_status["id"];
    } else {
      return '0';
    }
  }
