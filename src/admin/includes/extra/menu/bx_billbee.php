<?php
/* -----------------------------------------------------------------------------------------
   $Id: admin/includes/extra/menu/bx_billbee.php 2023-01-29 12:00:00Z benax $
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

defined( '_VALID_XTC' ) or die( 'Direct Access to this location is not allowed.' );

if( defined("MODULE_BILLBEE_STATUS") && 'true' === MODULE_BILLBEE_STATUS)
{
switch ($_SESSION['language_code']) {
  case 'de':
	if(!defined('MENU_NAME_BILLBEE')) define('MENU_NAME_BILLBEE','Billbee Interface');
	break;
  default:
	if(!defined('MENU_NAME_BILLBEE')) define('MENU_NAME_BILLBEE','Billbee Interface');
	break;
}

// BOX_HEADING_PARTNER_MODULES = Name der box in der der neue Menueeintrag erscheinen soll
$add_contents[BOX_HEADING_PARTNER_MODULES][] = array( 
  'admin_access_name' => 'bx_billbee',
  'filename' => 'bx_billbee.php',
  'boxname' => MENU_NAME_BILLBEE,
  'parameters' => '',
  'ssl' => ''
  );
}