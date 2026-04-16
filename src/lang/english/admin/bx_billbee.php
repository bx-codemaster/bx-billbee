<?php
  /* ----------------------------------------------------------------------
   $Id: lang/english/admin/bx_billbee.php 1000 2023-03-20 13:00:00Z benax $
    _                           
   | |__   ___ _ __   __ ___  __
   | '_ \ / _ \ '_ \ / _ \ \/ /
   | |_) |  __/ | | | (_| |>  < 
   |_.__/ \___|_| |_|\__,_/_/\_\
   xxxxxxxxxxxxxxxxxxxxxxxxxxxxx

   modified eCommerce Shopsoftware
   http://www.modified-shop.org

   Copyright (c) 2009 - 2013 [www.modified-shop.org]
   ----------------------------------------------------------------------
   Released under the GNU General Public License
   ----------------------------------------------------------------------*/

  define('HEADING_BILLBEE_TITLE', 'BILLBEE');
  define('HEADING_BILLBEE_SUB_TITLE', 'The simple multichannel software in the cloud');
  define('AUTHENTICATOR_HEADING', 'Authenticator');
  define('AUTHENTICATOR_TEXT', 'The password you enter here must also be stored on the <em><span style="color:#00C069;">Billbee</span></em> configuration page for your shop.');
  define('MODULE_BILLBEE_DEBUG_HEADING', 'Logging');
  define('MODULE_BILLBEE_DEBUG_TEXT', 'If you activate logging, you will find the calls from Billbee to your shop in the <em>log</em> folder.');
  define('PRAEFIX_HEADING', 'Prefix invoice number');
  define('PRAEFIX_TEXT', 'A character string that precedes the invoice number.');
  define('POSTFIX_HEADING', 'Postfix invoice number');
  define('POSTFIX_TEXT', 'A character string that is appended to the invoice number.');
  define('LANGUAGE_HEADING', 'Language');
  define('LANGUAGE_TEXT', 'Select the language in which you operate <em><span style="color:#00C069;">Billbee</span></em>.');
  define('STATUSE_PAYMENT_HEADING', 'Payment modules');
  define('STATUSE_PAYMENT_TEXT', 'When <em><span style="color:#00C069;">Billbee</span></em> retrieves new orders, the identification number of the payment method used is also transmitted.<br/><br/>The payment methods defined by <em><span style="color:#00C069;">Billbee</span></em> can be found on this page (scroll down a bit):<br/><a href="https://hilfe.billbee.io/article/483-billbee-api-zur-anbindung-von-eines-eigenen-webshops" target="_blank">https://hilfe.billbee.io/article/483-billbee-api-zur-anbindung-von-eines-eigenen-webshops</a><br/><br/>The identification number of <em><span style="color:#B0347E;">mod</span>ified eCommerce</em> and <em><span style="color:#00C069;">Billbee</span></em> do not match.<br/>If <em><span style="color:#00C069;">Billbee</span></em> does not know the reported identification number, the payment method will be set to "Other".<br/>It is also possible that the identification number reported by the shop exists at <em><span style="color:#00C069;">Billbee</span></em>, but refers to a different payment method.<br/><br/>To avoid these problems, you can assign a <em><span style="color:#00C069;">Billbee</span></em> payment method to your installed payment methods or payment modules. Anything you do not assign will be set to "Other" as described. ');
  define('STATUSE_ORDER_HEADING', 'Order status');
  define('STATUSE_ORDER_TEXT', 'If the order status in <em><span style="color:#00C069;">Billbee</span></em> changes, <em><span style="color:#00C069;">Billbee</span></em> reports this to the Shop by sending a status identification number.<br/><br/>The status values defined by <em><span style="color:#00C069;">Billbee</span></em> can be found on this page (scroll down a bit more):<br/><a href="https://hilfe.billbee.io/article/483-billbee-api-zur-anbindung-von-eines-eigenen-webshops" target="_blank">https://hilfe.billbee.io/article/483-billbee-api-zur-anbindung-von-eines-eigenen-webshops</a><br/><br/>The identification number of <em><span style="color:#B0347E;">mod</span>ified eCommerce</em> and <em><span style="color:#00C069;">Billbee</span></em> do not match.<br/><br/>If <em><span style="color:#B0347E;">mod</span>ified eCommerce</em> does not know the reported identification number or there is no assignment for it, the order status will be entered by <em><span style="color:#00C069;">Billbee</span></em> in the order history.<br/>It is also possible that the identification number reported by the shop exists in <em><span style="color:#00C069;">Billbee</span></em>, but refers to a different order status.<br/><br/>To avoid these problems, you can assign the order statuses from <em><span style="color:#B0347E;">mod</span>ified eCommerce</em> to the <em><span style="color:#00C069;">Billbee</span></em> order statuses here.');
  define('PAYMENT_MODULE_INSTALLED', 'Installed payment modules');
  define('PAYMENT_MODULE_BILLBEE', 'Billbee payment modules');
  define('ORDER_STATUSE_INSTALLED', 'Modified Order Status');
  define('ORDER_STATUSE_BILLBEE', 'Billbee Order Status');
  define('TEXT_PLEASE_CHOOSE', 'Please select');
  define('TEXT_SAVE_SUCCESS', 'Data successfully saved!');
  define('TEXT_SAVE', 'Save');
  define('TEXT_VISIT_DEVELOPER', 'Visit the developer');
  define('TEXT_VISIT_DEVELOPER_CLAIM', 'Decorative articles and handicrafts made of metal');
?>