<?php
/* -----------------------------------------------------------------------------------------
   $Id: lang/german/modules/system/bx_billbee.php 1000 2023-03-20 13:00:00Z benax $
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

define('MODULE_BILLBEE_TEXT_TITLE', 'BX Billbee');
define('MODULE_BILLBEE_TEXT_DESCRIPTION', '<table><tr><td>'.xtc_image(DIR_WS_ICONS.'heading/bx_billbee.png', 'Billbee', '', '', 'style="max-height: 32px;"').'</td><td><strong>Die einfache Multichannel-Software in der Cloud</strong></td></tr></table>
<h4>Eigenschaftenändig anpassbar</p>'
.((defined('MODULE_BILLBEE_STATUS') && 'true' == MODULE_BILLBEE_STATUS ) ? '<a href="'.xtc_href_link(FILENAME_BILLBEE, '', 'SSL').'">
<h3 style="text-align: center;">&rAarr; Konfiguration &lAarr;</h3></a>' : '').' ');
define('MODULE_BILLBEE_STATUS_TITLE' , 'Status');
define('MODULE_BILLBEE_STATUS_DESC' , 'Modul aktivieren?');
define('MODULE_BILLBEE_CONFIG_ID_TITLE' , 'Konfigurations-ID');
define('MODULE_BILLBEE_CONFIG_ID_DESC' , 'Automatisch ermittelt.');
?>