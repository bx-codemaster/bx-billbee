<?php
/**
 * Billbee Integration - Admin Configuration Interface
 *
 * Admin backend configuration interface for Billbee integration module.
 * Manages configuration settings, payment method mappings, and order status mappings
 * between the modified eCommerce shop system and Billbee.
 *
 * Features:
 * - Authentication configuration
 * - Invoice number prefix/postfix settings
 * - Payment method mapping (Billbee <-> Shop)
 * - Order status mapping (Billbee <-> Shop)
 * - Language selection
 * - Debug mode configuration
 *
 * @package    Billbee Integration
 * @subpackage Admin
 * @file       admin/bx_billbee.php
 * @version    1.4.0
 * @date       2026-02-08
 * @author     benax
 * @copyright  Copyright (c) 2009 - 2013 www.modified-shop.org
 * @license    GNU General Public License
 * @link       http://www.modified-shop.org
 *
 * @see        modified eCommerce Shopsoftware
 * @see        https://www.billbee.io/
 *
 * $Id: admin/bx_billbee.php 1000 2023-03-20 13:00:00Z benax $
 *
 * This file is part of the Billbee integration module for modified eCommerce.
 * Released under the GNU General Public License.
 */

  require('includes/application_top.php');

  $action = (isset($_GET['action']) ? $_GET['action'] : '');

  switch ($action) {
    case 'save':
      if( isset($_POST["BILLBEE_AUTHENTICATOR"]) && xtc_not_null($_POST["BILLBEE_AUTHENTICATOR"]) ) {
        $sql_data_array = array('configuration_value' => filter_input(INPUT_POST, 'BILLBEE_AUTHENTICATOR', FILTER_SANITIZE_SPECIAL_CHARS) );
        xtc_db_perform(TABLE_CONFIGURATION, $sql_data_array, 'update', "configuration_key = 'BILLBEE_AUTHENTICATOR'");
      }

      if( isset($_POST["configuration"]["MODULE_BILLBEE_DEBUG"]) && xtc_not_null($_POST["configuration"]["MODULE_BILLBEE_DEBUG"]) ) {
        $sql_data_array = array('configuration_value' => $_POST["configuration"]["MODULE_BILLBEE_DEBUG"]);
        xtc_db_perform(TABLE_CONFIGURATION, $sql_data_array, 'update', "configuration_key = 'MODULE_BILLBEE_DEBUG'");
      }
      if( isset($_POST["BILLBEE_INVOICE_NUMBER_PREFIX"]) ) {
        $sql_data_array = array('configuration_value' => filter_input(INPUT_POST, 'BILLBEE_INVOICE_NUMBER_PREFIX', FILTER_SANITIZE_SPECIAL_CHARS) );
        xtc_db_perform(TABLE_CONFIGURATION, $sql_data_array, 'update', "configuration_key = 'BILLBEE_INVOICE_NUMBER_PREFIX'");
      }

      if( isset($_POST["BILLBEE_INVOICE_NUMBER_POSTFIX"]) ) {
        $sql_data_array = array('configuration_value' => filter_input(INPUT_POST, 'BILLBEE_INVOICE_NUMBER_POSTFIX', FILTER_SANITIZE_SPECIAL_CHARS) );
        xtc_db_perform(TABLE_CONFIGURATION, $sql_data_array, 'update', "configuration_key = 'BILLBEE_INVOICE_NUMBER_POSTFIX'");
      }

      if( isset($_POST["BILLBEE_LANGUAGE_ID"]) && xtc_not_null($_POST["BILLBEE_LANGUAGE_ID"]) ) {
        $sql_data_array = array('configuration_value' => filter_input(INPUT_POST, 'BILLBEE_LANGUAGE_ID', FILTER_SANITIZE_SPECIAL_CHARS) );
        xtc_db_perform(TABLE_CONFIGURATION, $sql_data_array, 'update', "configuration_key = 'BILLBEE_LANGUAGE_ID'");
      }

      if( isset($_POST["BILLBEE_PAYMENT"]) && is_array($_POST["BILLBEE_PAYMENT"]) ) {
        $sql_data_array = array('modified_payment_code' => 'default');
        xtc_db_perform(TABLE_BB_PAYMENT_METHOD, $sql_data_array, 'update');

        if(is_array($_POST["BILLBEE_PAYMENT"])) {
          foreach($_POST["BILLBEE_PAYMENT"] as $key => $value) {
            $sql_data_array = array('modified_payment_code' => xtc_db_input(strtolower($key)));
            xtc_db_perform(TABLE_BB_PAYMENT_METHOD, $sql_data_array, 'update', "billbee_payment_id = '".xtc_db_input($value)."'");
          }
        }
      }

      if( isset($_POST["BILLBEE_STATUS"]) && is_array($_POST["BILLBEE_STATUS"]) ) {
        $sql_data_array = array('modified_status_id' => '0');
        xtc_db_perform(TABLE_BB_ORDER_STATUS, $sql_data_array, 'update');

        foreach($_POST["BILLBEE_STATUS"] as $key => $value) {
          $sql_data_array = array('modified_status_id' => xtc_db_input($value));
          xtc_db_perform(TABLE_BB_ORDER_STATUS, $sql_data_array, 'update', "billbee_status_id = '".xtc_db_input($key)."'");
        }
      }
      $messageStack->add_session(TEXT_SAVE_SUCCESS, 'success');
      xtc_redirect(xtc_href_link(FILENAME_BILLBEE, '', 'SSL'));
      break;
  }

  $config_query = xtc_db_query("SELECT configuration_key, 
                                       configuration_value 
                                  FROM ".TABLE_CONFIGURATION." 
                                  WHERE configuration_group_id = ".MODULE_BILLBEE_CONFIG_ID);
  
  /* $billbee_authenticator, $billbee_invoice_number_prefix, $billbee_invoice_number_postfix, $billbee_language_id, $module_billbee_debug */
  while ($config = xtc_db_fetch_array($config_query)) {
    ${strtolower($config["configuration_key"])} = $config["configuration_value"];
  }

  $billbeePaymentsQuery = xtc_db_query("SELECT billbee_payment_id, 
                                               billbee_payment_name, 
                                               modified_payment_code 
                                          FROM ".TABLE_BB_PAYMENT_METHOD." 
                                         WHERE language_id = '".(int)$_SESSION["languages_id"]."' 
                                         AND billbee_payment_name IS NOT NULL;");
 
  while ($billbeePayment = xtc_db_fetch_array($billbeePaymentsQuery)) {
    $billbeePayments[] = array('id'       => $billbeePayment['billbee_payment_id'],
                               'text'     => htmlspecialchars($billbeePayment['billbee_payment_name'], ENT_QUOTES, 'UTF-8'),
                               'modified' => $billbeePayment['modified_payment_code']
                              );
  }

  $doNotList = array('novalnet_config.php');
  $paymentsInstalledRaw = explode(";", MODULE_PAYMENT_INSTALLED);

  $i = 1;
  foreach($paymentsInstalledRaw as $payment) {
    if(!in_array($payment, $doNotList)) {
    $class = basename($payment, ".php");
 
      if (file_exists('../'.DIR_WS_MODULES.'payment/'.$class.'.php')) {
        include_once('../'.DIR_WS_MODULES.'payment/'.$class.'.php');
        $safe_language = basename($_SESSION["language"]); // Nur Dateiname, keine Pfade
        include_once('../lang/'.$safe_language.'/modules/payment/'.$class.'.php');
  
        if (class_exists($class)) {
          $module = new $class();
          $paymentInstalled[] = array ( 'id' => $module->code, 'text' => $module->title );
        } else {
          $messageStack->add_session(htmlspecialchars($class).' existiert nicht!', 'error');
        }
      }
      $i++;
    }
  }

  $billbeeOrderStatuseQuery = xtc_db_query("SELECT billbee_status_id, 
                                                   billbee_status_name, 
                                                   modified_status_id 
                                              FROM ".TABLE_BB_ORDER_STATUS." 
                                             WHERE language_id = '".(int)$_SESSION["languages_id"]."' 
                                          ORDER BY billbee_status_id ASC;");
 
  while ($billbeeOrderStatus = xtc_db_fetch_array($billbeeOrderStatuseQuery)) {
    $billbeeOrderStatuse[] = array('id'       => $billbeeOrderStatus['billbee_status_id'],
                                   'text'     => '('.$billbeeOrderStatus['billbee_status_id'].') '.htmlspecialchars($billbeeOrderStatus['billbee_status_name'], ENT_QUOTES, 'UTF-8'),
                                   'modified' => (NULL !== $billbeeOrderStatus['modified_status_id'] ? $billbeeOrderStatus['modified_status_id'] : "0")
                                );
  }

  $modifiedOrderStatusQuery = xtc_db_query("SELECT orders_status_id, 
                                                   orders_status_name 
                                              FROM ".TABLE_ORDERS_STATUS." 
                                             WHERE language_id = '".(int)$_SESSION['languages_id']."' 
                                          ORDER BY orders_status_id; ");

  while ($modifiedOrderStatus = xtc_db_fetch_array($modifiedOrderStatusQuery)) {
    $modifiedOrderStatuse[] = array('id'   => $modifiedOrderStatus['orders_status_id'],
                                    'text' => htmlspecialchars($modifiedOrderStatus['orders_status_name'], ENT_QUOTES, 'UTF-8')
                                 );
  }

  require (DIR_WS_INCLUDES.'head.php');
?>
   </head>
<body>
  <!-- header //-->
<?php require(DIR_WS_INCLUDES.'header.php'); ?>
  <!-- header_eof //-->
  <!-- body //-->
  <table class="tableBody">
    <tr>
<?php //left_navigation
  if (USE_ADMIN_TOP_MENU == 'false') {
   echo '<td class="columnLeft2">'.PHP_EOL;
	echo '<!-- left_navigation //-->'.PHP_EOL;       
	require_once(DIR_WS_INCLUDES.'column_left.php');
	echo '<!-- left_navigation eof //-->'.PHP_EOL; 
	echo '</td>'.PHP_EOL;
  }

  $messageStack->output();
?>
      <!-- body_text //-->
      <td class="boxCenter">
         <div class="pageHeadingImage"><?php echo xtc_image(DIR_WS_ICONS.'heading/bx_billbee.png', HEADING_BILLBEE_TITLE, '', '', 'style="max-height: 32px;"'); ?></div>
         <div class="pageHeading"><?php echo HEADING_BILLBEE_TITLE; ?></div>
         <div class="main pdg2 flt-l"><?php echo HEADING_BILLBEE_SUB_TITLE; ?></div>
         <div style="clear:both;"></div>
      </td>
    </tr>
    <tr>
      <td class="boxCenter">
      <table class="tableCenter">
        <tr>
          <td class="boxCenterLeft">
          <?php echo xtc_draw_form('configuration', FILENAME_BILLBEE, 'action=save', 'post', 'style="margin-top:0;"').PHP_EOL; ?>
            <table class="clear tableConfig">
              <tr>
                <td class="dataTableConfig col-left">1. <?php echo AUTHENTICATOR_HEADING; ?></td>
                <td class="dataTableConfig col-middle" style="width: 29%;">
                  <?php echo xtc_draw_input_field("BILLBEE_AUTHENTICATOR", $billbee_authenticator, 'id="BILLBEE_AUTHENTICATOR" style="width:100%;"', false, 'password'); ?>
                </td>
                <td class="dataTableConfig col-middle" style="width: 1%; padding: 8px 0;">
                  <button class="button" id="toggleViz" style="padding: 0 2px;" title="Toggle visibility">
                    <?php echo xtc_image('images/icons/icon_show.png', 'Toggle visibility', '', '', 'style="max-width: 24px;"'); ?>
                  </button>
                </td>
                <td class="dataTableConfig col-right"><?php echo AUTHENTICATOR_TEXT; ?></td>
              </tr>
              <tr>
                <td class="dataTableConfig col-left">2. <?php echo MODULE_BILLBEE_DEBUG_HEADING; ?></td>
                <td class="dataTableConfig col-middle" colspan="2"><?php echo xtc_cfg_select_option(array('true', 'false'), $module_billbee_debug, 'MODULE_BILLBEE_DEBUG'); ?></td>
                <td class="dataTableConfig col-right"><?php echo MODULE_BILLBEE_DEBUG_TEXT; ?></td>
              </tr>
              <tr>
                <td class="dataTableConfig col-left">3. <?php echo PRAEFIX_HEADING; ?></td>
                <td class="dataTableConfig col-middle" colspan="2"><input type="text" name="BILLBEE_INVOICE_NUMBER_PREFIX" value="<?php echo $billbee_invoice_number_prefix; ?>" style="width:100%;"></td>
                <td class="dataTableConfig col-right"><?php echo PRAEFIX_TEXT; ?></td>
              </tr>
              <tr>
                <td class="dataTableConfig col-left">4. <?php echo POSTFIX_HEADING; ?></td>
                <td class="dataTableConfig col-middle" colspan="2"><input type="text" name="BILLBEE_INVOICE_NUMBER_POSTFIX" value="<?php echo $billbee_invoice_number_postfix; ?>" style="width:100%;"></td>
                <td class="dataTableConfig col-right"><?php echo POSTFIX_TEXT; ?></td>
              </tr>
              <tr>
                <td class="dataTableConfig col-left">5. <?php echo LANGUAGE_HEADING; ?></td>
                <td class="dataTableConfig col-middle" colspan="2">
                  <?php echo xtc_draw_pull_down_menu( 'BILLBEE_LANGUAGE_ID', bx_get_language_ids(), $billbee_language_id, '', false, true); ?>
                </td>
                <td class="dataTableConfig col-right"><?php echo LANGUAGE_TEXT; ?></td>
              </tr>
              <tr>
                <td class="dataTableConfig col-left" style="vertical-align: top;">6. <?php echo STATUSE_PAYMENT_HEADING; ?></td>
                <td class="dataTableConfig col-middle" colspan="2">
                  <table class="tableConfirm" style="width:auto; margin-top:0;">
                    <tr>
                      <td><strong><small><?php echo PAYMENT_MODULE_INSTALLED; ?></small></strong></td>
                      <td><strong><small><?php echo PAYMENT_MODULE_BILLBEE; ?></small></strong></td>
                    </tr>
<?php
$sortText     = array_column($billbeePayments, 'text');
array_multisort($sortText, SORT_ASC, $billbeePayments);

array_unshift($billbeePayments, array('id' => 'default',
                                        'text' => TEXT_PLEASE_CHOOSE,
                                        'modified' => ''));
  
  foreach($paymentInstalled as $payment) {
    $default = bx_searchArray('modified', $payment["id"], $billbeePayments);
?>
                    <tr>
                      <td><em><?php echo $payment["text"]; ?></em></td>
                      <td><?php echo xtc_draw_pull_down_menu("BILLBEE_PAYMENT[".strtoupper($payment["id"])."]", $billbeePayments, $default, 'autocomplete="off"'); ?></td>
                    </tr>
<?php
  }
?>  
                  </table>
                </td>
                <td class="dataTableConfig col-right" style="vertical-align: top;"><?php echo STATUSE_PAYMENT_TEXT; ?></td>
              </tr>
              <tr>
                <td class="dataTableConfig col-left" style="vertical-align: top;">7. <?php echo STATUSE_ORDER_HEADING; ?></td>
                <td class="dataTableConfig col-middle" colspan="2">
                  <table class="tableConfirm" style="width:auto; margin-top:0;">
                    <tr>
                      <td><strong><small><?php echo ORDER_STATUSE_BILLBEE; ?></small></strong></td>
                      <td><strong><small><?php echo ORDER_STATUSE_INSTALLED; ?></small></strong></td>
                    </tr>
<?php
/*
$billbeeOrderStatuse
$modifiedOrderStatuse
*/
  array_unshift($modifiedOrderStatuse, array('id'   => '0',
                                            'text' => TEXT_PLEASE_CHOOSE));
  sort($billbeeOrderStatuse);
  sort($modifiedOrderStatuse);
  foreach($billbeeOrderStatuse as $status) {
    $default = bx_get_modified_status_id($status["id"]);
?>
                    <tr>
                      <td><em><?php echo $status["text"]; ?></em></td>
                      <td><?php echo xtc_draw_pull_down_menu("BILLBEE_STATUS[".$status["id"]."]", $modifiedOrderStatuse, $default, 'autocomplete="off"'); ?></td>
                    </tr>
<?php
  }
?>  
                  </table>
                </td>
                <td class="dataTableConfig col-right" style="vertical-align: top;"><?php echo STATUSE_ORDER_TEXT; ?></td>
              </tr>
            </table>
            <div class="main pdg2 txta-r mrg5"><input type="submit" class="button" onclick="this.blur();" value="<?php echo TEXT_SAVE; ?>"></div>
          </form>
          </td>
          <?php
            $heading    = array();
            $contents   = array();
            $heading[]  = array('text' => '<strong>'.HEADING_BILLBEE_TITLE.'</strong>');
            $contents[] = array('text' => HEADING_BILLBEE_SUB_TITLE);
            $contents[] = array('text' =>  '<a href="https://www.billbee.io?via=eisenhans2020" target="_blank" style="display: inline-block; padding: 0 0.75rem; background-color:transparent;"><img src="https://dka575ofm4ao0.cloudfront.net/pages-transactional_logos/retina/169787/Billbee-Logo_part-of-comrce_01-primary-0248396f-bf5d-4f8c-a4ac-dc62d677cc19.png" alt="Billbee logo" style="max-width: 220px; margin: 0;"></a>', 'params' => 'style="text-align: center;"');
              
            echo '            <td class="boxRight" style="padding-top: 0.5rem;">' . "\n";
            $box = new box;
            if(true === MODULE_BILLBEE_DEBUG) {
              ob_start();
              var_dump($_POST);
              $post = ob_get_clean();

              ob_start();
              var_dump($billbee_language_id);
              $language = ob_get_clean();
                
              ob_start();
              var_dump($billbeePayments);
              $payments = ob_get_clean();

              ob_start();
              var_dump($billbeeOrderStatuse);
              $billbeeStatuse = ob_get_clean();

              ob_start();
              var_dump($modifiedOrderStatuse);
              $modifiedStatuse = ob_get_clean();
                
              $contents[] = array('text' => '<strong>$_POST:</strong><pre>'.$post.'</pre>');
              $contents[] = array('text' => '<strong>$billbee_language_id:</strong><pre>'.$language.'</pre>');
              $contents[] = array('text' => '<strong>$billbeePayments:</strong><pre>'.$payments.'</pre>');
              $contents[] = array('text' => '<strong>$billbeeStatuse:</strong><pre>'.$billbeeStatuse.'</pre>');
              $contents[] = array('text' => '<strong>$modifiedStatuse:</strong><pre>'.$modifiedStatuse.'</pre>');
            }
            echo $box->infoBox($heading, $contents);
              
            $heading    = array();
            $contents   = array();
            $heading[]  = array('text' => '<strong>'.TEXT_VISIT_DEVELOPER.'</strong>');
            $contents[] = array('text' =>  '<a href="https://www.der-eisenhans.de" target="_blank" style="display: inline-block; padding: 0.25rem 0.75rem; background-color: #fff; border-radius: 0.75rem;">'.xtc_image(DIR_WS_IMAGES.'logo_eisenhans.png', TEXT_VISIT_DEVELOPER_CLAIM, '', '','style="max-width: 260px; margin: 0;"').'</a>', 'params' => 'style="text-align: center;"');
            $contents[] = array('text' =>  '<form action="https://www.paypal.com/donate" method="post" target="_blank">
              <input type="hidden" name="hosted_button_id" value="EUPEVMDDXJ48U" />
              <input type="image" src="https://www.paypalobjects.com/de_DE/DE/i/btn/btn_donate_LG.gif" name="submit" title="PayPal - The safer, easier way to pay online!" alt="Spenden mit dem PayPal-Button" style="border: none;" />
              <img alt="" border="0" src="https://www.paypal.com/de_DE/i/scr/pixel.gif" width="1" height="1" />
              </form>', 'params' => 'style="text-align: center;"');

            echo $box->infoBox($heading, $contents);
            echo '            </td>' . "\n";
          ?>
        </tr>                
      </table>
      </td>
      <!-- body_text_eof //-->
    </tr>
  </table>
  <!-- body_eof //-->
  <!-- footer //-->
  <?php require(DIR_WS_INCLUDES.'footer.php'); ?>
  <!-- footer_eof //-->
  <script>
    $(document).ready(function() {

      $( "button#toggleViz").on("click", function(e){
        e.preventDefault();
        let nextImage = $(this).find("img");
        let pInput    = $("input#BILLBEE_AUTHENTICATOR");

        if("password" === pInput.prop("type")) {
          pInput.prop("type", "text");
          nextImage.attr("src", "images/icons/icon_hide.png");
        } else {
          pInput.prop("type", "password");
          nextImage.attr("src", "images/icons/icon_show.png");
        }
      });

      $( "select[name^='BILLBEE_PAYMENT'] option:selected" ).each( function() {
        let selected = $(this).val();
        if ('default' !== selected) {
          $( this ).parent().next('.CaptionCont.SlectBox').css('backgroundColor', "#d0ffd0");
        }
      });

      $( "select[name^='BILLBEE_STATUS'] option:selected" ).each( function() {
        let selected = $(this).val();
        if ('0' !== selected) {
          $( this ).parent().next('.CaptionCont.SlectBox').css('backgroundColor', "#d0ffd0");
        }
      });

      setTimeout(function(){ $(".fixed_messageStack").hide("slow"); }, 3000);

    });
  </script>

</body>
</html>
<?php require(DIR_WS_INCLUDES.'application_bottom.php'); ?>
