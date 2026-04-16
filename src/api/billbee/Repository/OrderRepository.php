<?php
/**
 * OrderRepository - Verwaltung von Bestellungen für die Billbee API Integration
 *
 * Diese Klasse implementiert die OrdersRepositoryInterface und stellt Methoden
 * zum Abrufen, Aktualisieren und Verarbeiten von Bestellungen aus dem Modified Shop
 * für die Übertragung an Billbee bereit.
 *
 * @package   Billbee\ModifiedShopApi\Repository
 * @author    Axel Benkert - BX Coding
 * @copyright 2024-2026 BX Coding
 * @license   Proprietary
 * @version   2.0.0
 * @link      https://www.bx-coding.de/
 * @link      https://www.billbee.de/
 */
namespace Billbee\ModifiedShopApi\Repository;

use Billbee\CustomShopApi\Repository\OrdersRepositoryInterface;
use Billbee\CustomShopApi\Exception\OrderNotFoundException;
use Billbee\CustomShopApi\Model\PagedData;
use Billbee\CustomShopApi\Model\Address;
use Billbee\CustomShopApi\Model\Order;
use Billbee\CustomShopApi\Model\OrderProduct;
use Billbee\CustomShopApi\Model\OrderProductOption;
use Billbee\CustomShopApi\Model\OrderComment;
use Billbee\CustomShopApi\Model\ProductImage;
use splitPageResults;
use DateTime;
use StdClass;
use Exception;

class OrderRepository implements OrdersRepositoryInterface {

  /**
   * Ruft eine einzelne Bestellung anhand der ID ab
   *
   * Lädt alle Bestelldaten inklusive Adressen, Positionen, Status und Zahlungsinformationen
   * aus der Datenbank und konvertiert sie in ein Billbee Order-Objekt.
   *
   * @param string|int $orderId Die Modified Shop Bestell-ID
   * 
   * @return Order Das vollständig befüllte Order-Objekt
   * 
   * @throws OrderNotFoundException Wenn die Bestellung nicht gefunden wurde
   */
  public function getOrder( $orderId ): Order {
    $orderId = xtc_db_input($orderId);
    $conditional_fields = '';

    if ( defined("MODULE_PDF_BILL_STATUS") && MODULE_PDF_BILL_STATUS == 'True' ) {
      $conditional_fields = ', o.ibn_billnr ';
    }

    $order_query_raw = "SELECT o.*, 
                               ot.value AS shipCost
                               ".$conditional_fields." 
                          FROM ".TABLE_ORDERS." o
                          LEFT JOIN ".TABLE_ORDERS_TOTAL." ot 
                            ON (o.orders_id = ot.orders_id AND ot.class = 'ot_shipping')
                          WHERE o.orders_id = '".$orderId."'";

    $order_query = xtc_db_query($order_query_raw);

    if( 0 < xtc_db_num_rows($order_query) ) {
			$order = xtc_db_fetch_array($order_query);
      
      // Verwende die neue Helper-Methode zum Erstellen des Order-Objekts
      $myOrder = $this->buildOrderFromData($order);

      if(defined("MODULE_BILLBEE_DEBUG") && (MODULE_BILLBEE_DEBUG === 'true' || MODULE_BILLBEE_DEBUG === true)) {
        ob_start();
        $testresult = print_r($myOrder, true);
        $protocol = PHP_EOL.PHP_EOL.date("Y-m-d H:i:s").' getOrder'
        .PHP_EOL.'----------------------------------------------------------------------------------------------------------'.PHP_EOL
        .$testresult;
        $logFile = defined('DIR_FS_CATALOG') ? DIR_FS_CATALOG . 'log/bx_billbee.log' : __DIR__ . '/../../../log/bx_billbee.log';
        @file_put_contents($logFile, $protocol, FILE_APPEND);
        ob_end_flush();
      }

      return $myOrder;
		} else {

      if(defined("MODULE_BILLBEE_DEBUG") && (MODULE_BILLBEE_DEBUG === 'true' || MODULE_BILLBEE_DEBUG === true)) {
        ob_start();
        $testresult = print_r($order_query, true);
        $protocol = PHP_EOL.PHP_EOL.date("Y-m-d H:i:s").' getOrder'
        .PHP_EOL.'----------------------------------------------------------------------------------------------------------'.PHP_EOL
        .$testresult;
        $logFile = defined('DIR_FS_CATALOG') ? DIR_FS_CATALOG . 'log/bx_billbee.log' : __DIR__ . '/../../../log/bx_billbee.log';
        @file_put_contents($logFile, $protocol, FILE_APPEND);
        ob_end_flush();
      }
			
      throw new OrderNotFoundException(404);
		}
	}

  /**
   * Ruft eine paginierte Liste von Bestellungen ab
   *
   * Lädt alle nicht exportierten Bestellungen seit einem bestimmten Datum.
   * Berücksichtigt sowohl das Bestelldatum als auch das Änderungsdatum.
   *
   * Ankommender Aufruf von BILLBEE:
   * bx_billbee.php?Action=GetOrders&Key=XXXXXXX&Page=1&PageSize=50&StartDate=xxxx-xx-xx
   *
   * @param int      $page          Die Seitennummer (1-basiert)
   * @param int      $pageSize      Anzahl der Bestellungen pro Seite
   * @param DateTime $modifiedSince Datum ab dem Bestellungen geladen werden sollen
   * 
   * @return PagedData Objekt mit Bestellungs-Array und Gesamtanzahl
   */
  public function getOrders( $page, $pageSize, DateTime $modifiedSince ) : PagedData {
    $StartDate = $modifiedSince->format( 'Y-m-d H:i:s' );

    $myOrders = [];

    /**
     * Wenn das Modul PDFBill installiert ist holen wir die Rechnungsnummer von dort.
     */
    $conditional_fields = '';
    if ( defined("MODULE_PDF_BILL_STATUS") && MODULE_PDF_BILL_STATUS == 'True' ) {
      $conditional_fields .= ', o.ibn_billnr ';
    }

    $source_country_query = xtc_db_query("SELECT countries_iso_code_2 FROM countries WHERE countries_id = '".STORE_COUNTRY."';");
    $source_country       = xtc_db_fetch_array($source_country_query);

    // Hole alle konfigurierten Modified-Status-IDs die exportiert werden sollen
    // Nur Bestellungen mit Status die in der Billbee-Konfiguration gemappt sind (modified_status_id != 0) werden exportiert
    $mapped_status_query = xtc_db_query("SELECT DISTINCT modified_status_id 
                                           FROM ".TABLE_BB_ORDER_STATUS." 
                                          WHERE modified_status_id != '0' 
                                            AND language_id = '".$_SESSION["languages_id"]."'");
    
    $status_ids = array();
    while ($status_row = xtc_db_fetch_array($mapped_status_query)) {
      $status_ids[] = (int)$status_row['modified_status_id'];
    }
    
    // Wenn keine Status konfiguriert sind, leeres Ergebnis zurückgeben
    if (empty($status_ids)) {
      return new PagedData([], 0);
    }
    
    $status_condition = "o.orders_status IN (" . implode(',', $status_ids) . ")";

    $orders_query_raw = "SELECT o.*, 
                               ot.value AS shipCost 
                               ".$conditional_fields." 
                        FROM ".TABLE_ORDERS." o
                        LEFT JOIN ".TABLE_ORDERS_TOTAL." ot
                          ON (o.orders_id = ot.orders_id AND ot.class = 'ot_shipping')
                        WHERE (o.date_purchased >= '".$StartDate."' OR o.last_modified >= '".$StartDate."') 
                          AND o.bx_exported = 'n'
                          AND ".$status_condition."
                        GROUP BY o.orders_id";

    // Effiziente Zählung der Datensätze
    $count_sql = "SELECT COUNT(DISTINCT o.orders_id) AS total
                    FROM ".TABLE_ORDERS." o
                   WHERE (o.date_purchased >= '".$StartDate."' OR o.last_modified >= '".$StartDate."') 
                     AND o.bx_exported = 'n'
                     AND ".$status_condition;

    $count_query  = xtc_db_query($count_sql);
    $count_data   = xtc_db_fetch_array($count_query);
    $actual_count = (int)($count_data['total'] ?? 0);

    $orders_split = new splitPageResults( $orders_query_raw, $page, $pageSize );
    /*
    $orders_split->number_of_rows          string
    $orders_split->current_page_number     int
    $orders_split->number_of_pages         float
    $orders_split->number_of_rows_per_page int
    */

    $orders_query = xtc_db_query( $orders_split->sql_query );

    $i = 0;
    while ( $orders = xtc_db_fetch_array( $orders_query ) ) {
      // Verwende Helper-Methode zum Erstellen des Basis-Order-Objekts
      $myOrders[ $i ] = $this->buildOrderFromData($orders);
      
      // Setze zusätzliche Informationen die nur in getOrders() relevant sind
      $myOrders[ $i ]->setDeliverySourceCountryCode( empty( $source_country["countries_iso_code_2"] ) ? NULL : $source_country["countries_iso_code_2"] );

      $i++;
    }
    
    if(!empty($myOrders)) {
      $return = new PagedData( $myOrders, $actual_count );

      if(defined("MODULE_BILLBEE_DEBUG") && (MODULE_BILLBEE_DEBUG === 'true' || MODULE_BILLBEE_DEBUG === true)) {
        // Serialisiere das myOrders Array direkt zu JSON
        $jsonOrders = json_encode($myOrders, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        // Fallback falls json_encode fehlschlägt
        if ($jsonOrders === false) {
          $jsonOrders = 'JSON Encoding Error: ' . json_last_error_msg();
        }
        
        $protocol = PHP_EOL.PHP_EOL.date("Y-m-d H:i:s").' getOrders'
        .PHP_EOL.'----------------------------------------------------------------------------------------------------------'.PHP_EOL
        .'JSON Data:'.PHP_EOL
        .$jsonOrders;
        
        $logFile = defined('DIR_FS_CATALOG') ? DIR_FS_CATALOG . 'log/bx_billbee.log' : __DIR__ . '/../../../log/bx_billbee.log';
        @file_put_contents($logFile, $protocol, FILE_APPEND);

      }

      return $return;
    } else {
      return new PagedData( $myOrders, 0 );
      //throw new OrderNotFoundException(0);
	  }
  }

  /**
   * Markiert eine Bestellung als an Billbee exportiert
   *
   * Setzt das bx_exported Flag auf 'y' um zu verhindern, dass die Bestellung
   * erneut an Billbee übertragen wird.
   *
   * @param string|int $orderId Die Modified Shop Bestell-ID
   * 
   * @return bool True bei Erfolg, False bei Fehler
   * 
   * @throws OrderNotFoundException Wenn die Bestellung nicht gefunden wurde oder bereits exportiert ist
   */
  public function acknowledgeOrder( $orderId ): bool {
    $orderId = xtc_db_input($orderId);
    $orderIdQuery = xtc_db_query("SELECT orders_id, bx_exported FROM ".TABLE_ORDERS." WHERE orders_id = '".$orderId."' AND bx_exported = 'n'");

    if(defined("MODULE_BILLBEE_DEBUG") && (MODULE_BILLBEE_DEBUG === 'true' || MODULE_BILLBEE_DEBUG === true)) {
      ob_start();
      $testresult = print_r($orderIdQuery, true);
      $protocol = PHP_EOL.PHP_EOL.date("Y-m-d H:i:s").' acknowledgeOrder'
      .PHP_EOL.'----------------------------------------------------------------------------------------------------------'.PHP_EOL
      .$testresult;
      $logFile = defined('DIR_FS_CATALOG') ? DIR_FS_CATALOG . 'log/bx_billbee.log' : __DIR__ . '/../../../log/bx_billbee.log';
      @file_put_contents($logFile, $protocol, FILE_APPEND);
      ob_end_flush();
    }

		if(0 < xtc_db_num_rows($orderIdQuery) ) {
      $exported_array = array('bx_exported' => 'y');
      $res = xtc_db_perform(TABLE_ORDERS, $exported_array, 'update', "orders_id = '".$orderId."'");
			if( false === $res) {
				return false;
			} else {
				return true;
			}
		} else {
			throw new OrderNotFoundException(404);
		}
  }

  /**
   * Setzt einen neuen Bestellstatus
   *
   * Mappt den Billbee-Status auf einen Modified Shop Status und aktualisiert
   * die Bestellung. Falls kein Mapping existiert, wird der Billbee-Status
   * automatisch als neuer Status in Modified Shop angelegt.
   *
   * @param string|int $orderId        Die Modified Shop Bestell-ID
   * @param string|int $NewStateTypeId Die Billbee Status-ID
   * @param string     $comment        Optional: Kommentar zum Statuswechsel
   * 
   * @return void
   */
  public function setOrderState( $orderId, $NewStateTypeId, $comment ): bool {
    // Eingaben sanitieren
    $orderId        = xtc_db_input($orderId);
    $NewStateTypeId = xtc_db_input($NewStateTypeId);
    $comment        = xtc_db_input($comment);
    
    // Die dem Billbee-Status zugeordnete Modified-Status-Id auslesen und falls vorhanden in $orderStatus speichern.
    $billbeeStatuseQuery = xtc_db_query("SELECT billbee_status_name, 
                                                       modified_status_id 
                                                  FROM ".TABLE_BB_ORDER_STATUS."
                                                 WHERE billbee_status_id = '".$NewStateTypeId."' 
                                                   AND language_id = '".$_SESSION["languages_id"]."';");
  
    if( 0 < xtc_db_num_rows($billbeeStatuseQuery)) {
      $orderStatus = xtc_db_fetch_array($billbeeStatuseQuery);
      // Falls es eine zugeordnete Modified-Status-Id gibt wird diese verwendet.
      if( "0" !== $orderStatus["modified_status_id"]) {      
        $NewStateTypeId = $orderStatus["modified_status_id"];
  
        $update_sql_data = array('orders_status' => $NewStateTypeId);
        xtc_db_perform(TABLE_ORDERS, $update_sql_data, 'update', "orders_id = '".$orderId."'");

        $insert_sql_data = array('orders_id'         => $orderId,
                                 'orders_status_id'  => $NewStateTypeId,
                                 'date_added'        => 'now()',
                                 'customer_notified' => '0',
                                 'comments'          => $comment,
                                 'comments_sent'     => '0');
        xtc_db_perform(TABLE_ORDERS_STATUS_HISTORY, $insert_sql_data);
      } else {
        // Falls es keine zugeordnete Modified-Status-Id gibt, tragen wir den aktuelle Billbee-Status in die Tabelle ordes_status ein
        $next_id_query    = xtc_db_query("SELECT max(orders_status_id)+1 AS orders_status_id FROM ".TABLE_ORDERS_STATUS);
        $next_id          = xtc_db_fetch_array($next_id_query);
        $orders_status_id = $next_id['orders_status_id'];

        $languages_array = array();
        $languages_query = xtc_db_query("SELECT *, languages_id AS id
                                           FROM ".TABLE_LANGUAGES."
                                          WHERE status_admin = '1'
                                       ORDER BY sort_order");
    
        while ($languages = xtc_db_fetch_array($languages_query)) {
          $languages_array[] = $languages;
        }
        $languages =  $languages_array;
  
        foreach($languages as $language) {
          $billbeeStatuseQuery = xtc_db_query("SELECT billbee_status_name
                                                 FROM bx_billbee_order_status 
                                                WHERE billbee_status_id = '".$NewStateTypeId."' AND language_id = '".$language["languages_id"]."';");
  
          $billbeeStatus = xtc_db_fetch_array($billbeeStatuseQuery);                     
  
          $insert_sql_data = array('orders_status_id'   => $orders_status_id,
                                   'orders_status_name' => xtc_db_prepare_input( $billbeeStatus["billbee_status_name"]),
                                   'language_id'        => $language["languages_id"]);
          xtc_db_perform(TABLE_ORDERS_STATUS, $insert_sql_data);
          
          // Der Billbee-Status-ID wird der Modified-Status-Id zugewiesen
          $update_sql_data = array('modified_status_id' => $orders_status_id);
          xtc_db_perform('bx_billbee_order_status', $update_sql_data, 'update', "billbee_status_id = '".$NewStateTypeId."'");
        }
         
        $update_sql_data = array('orders_status' => $orders_status_id);
        xtc_db_perform(TABLE_ORDERS, $update_sql_data, 'update', "orders_id = '".$orderId."'");

        $insert_sql_data = array('orders_id'         => $orderId,
                                 'orders_status_id'  => $orders_status_id,
                                 'date_added'        => 'now()',
                                 'customer_notified' => '0',
                                 'comments'          => $comment,
                                 'comments_sent'     => '0');
        xtc_db_perform(TABLE_ORDERS_STATUS_HISTORY, $insert_sql_data);
      }
      return true;
    }
    return false;
  }

  /**
   * Parst eine Adresszeile und trennt Straße von Hausnummer
   *
   * Verwendet einen regulären Ausdruck um die Hausnummer vom Straßennamen zu trennen.
   * Funktioniert mit deutschen Adressen im Format "Straßenname Hausnummer".
   *
   * @param string $address Die vollständige Adresszeile (z.B. "Hauptstraße 123")
   * 
   * @return \StdClass Objekt mit Properties 'street' und 'street_number'
   */
  public function parseAddress( $address ): StdClass {
    $match = array();
    preg_match( '/^([^\d]*[^\d\s]) *(\d.*)$/', $address, $match );
    $street = new StdClass();
    if ( count( $match ) == 0 ) {
      $street->street = $address;
      $street->street_number = NULL;
    } else {
      $street->street = $match[ 1 ];
      $street->street_number = $match[ 2 ];
    }
    return $street;
  }

  /**
   * Setzt Basis-Bestellinformationen für ein Order-Objekt
   * @param Order $myOrder Das Order-Objekt
   * @param array $order Die Bestelldaten aus der Datenbank
   * @return Order Das befüllte Order-Objekt
   */
  private function setOrderBasicInfo(Order $myOrder, array $order): Order {
    $myOrder->setInvoiceNumberPrefix( ( defined("BILLBEE_INVOICE_NUMBER_PREFIX") && empty( BILLBEE_INVOICE_NUMBER_PREFIX ) ) ? NULL : BILLBEE_INVOICE_NUMBER_PREFIX );
    $myOrder->setInvoiceNumberPostfix( ( defined("BILLBEE_INVOICE_NUMBER_POSTFIX") && empty( BILLBEE_INVOICE_NUMBER_POSTFIX ) ) ? NULL : BILLBEE_INVOICE_NUMBER_POSTFIX );
    $myOrderNumber = BILLBEE_INVOICE_NUMBER_PREFIX . str_pad($order[ 'orders_id' ], 6, '0', STR_PAD_LEFT) . BILLBEE_INVOICE_NUMBER_POSTFIX;
    $myOrder->setOrderId( empty( $order[ 'orders_id' ] ) ? NULL : ( int )$order[ 'orders_id' ] );
    $myOrder->setOrderNumber( empty( $order[ 'ibn_billnr' ] ) ? $myOrderNumber : $order[ 'ibn_billnr' ] );
    $myOrder->setCurrencyCode( empty( $order[ 'currency' ] ) ? NULL : $order[ 'currency' ] );
    $myOrder->setNickName( empty( $order[ 'customers_name' ] ) ? NULL : $order[ 'customers_name' ] );
    $myOrder->setShipCost( empty( $order[ 'shipCost' ] ) ? NULL : (float) $order[ 'shipCost' ] );
    $myOrder->setOrderDate( empty( $order[ 'date_purchased' ] ) ? new DateTime() : new DateTime( $order[ 'date_purchased' ] ) );
    $myOrder->setEmail( empty( $order[ 'customers_email_address' ] ) ? NULL : $order[ 'customers_email_address' ] );
    $myOrder->setPhone1( empty( $order[ 'customers_telephone' ] ) ? NULL : $order[ 'customers_telephone' ] );

    return $myOrder;
  }

  /**
   * Setzt die Rechnungsadresse für eine Bestellung
   * @param Order $myOrder Das Order-Objekt
   * @param array $order Die Bestelldaten aus der Datenbank
   * @return Order Das Order-Objekt mit Rechnungsadresse
   */
  private function setInvoiceAddress(Order $myOrder, array $order): Order {
    $myOrder->invoiceAddress = new Address;
    $myOrder->invoiceAddress->setSalutation( ( 'f' === $order[ 'billing_gender' ] ? 'Frau' : 'Herr' ) );
    $myOrder->invoiceAddress->setFirstName( empty( $order[ 'billing_firstname' ] ) ? NULL : $order[ 'billing_firstname' ] );
    $myOrder->invoiceAddress->setLastName( empty( $order[ 'billing_lastname' ] ) ? NULL : $order[ 'billing_lastname' ] );
    
    $street = $this->parseAddress( $order[ 'billing_street_address' ] );
    $myOrder->invoiceAddress->setStreet( $street->street );
    $myOrder->invoiceAddress->setHouseNumber( $street->street_number );
    
    $myOrder->invoiceAddress->setAddress2( empty( $order[ 'billing_suburb' ] ) ? NULL : $order[ 'billing_suburb' ] );
    $myOrder->invoiceAddress->setPostcode( empty( $order[ 'billing_postcode' ] ) ? NULL : $order[ 'billing_postcode' ] );
    $myOrder->invoiceAddress->setCity( empty( $order[ 'billing_city' ] ) ? NULL : $order[ 'billing_city' ] );
    $myOrder->invoiceAddress->setCountry( empty( $order[ 'billing_country' ] ) ? NULL : $order[ 'billing_country' ] );
    $myOrder->invoiceAddress->setCountryCode( empty( $order[ 'billing_country_iso_code_2' ] ) ? NULL : $order[ 'billing_country_iso_code_2' ] );
    $myOrder->invoiceAddress->setCompany( empty( $order[ 'billing_company' ] ) ? NULL : $order[ 'billing_company' ] );
    $myOrder->invoiceAddress->setState( empty( $order[ 'billing_state' ] ) ? NULL : $order[ 'billing_state' ] );

    return $myOrder;
  }

  /**
   * Setzt die Lieferadresse für eine Bestellung
   * @param Order $myOrder Das Order-Objekt
   * @param array $order Die Bestelldaten aus der Datenbank
   * @return Order Das Order-Objekt mit Lieferadresse
   */
  private function setDeliveryAddress(Order $myOrder, array $order): Order {
    $myOrder->deliveryAddress = new Address;
    $myOrder->deliveryAddress->setSalutation( ( 'f' === $order[ 'delivery_gender' ] ? 'Frau' : 'Herr' ) );
    $myOrder->deliveryAddress->setFirstName( empty( $order[ 'delivery_firstname' ] ) ? NULL : $order[ 'delivery_firstname' ] );
    $myOrder->deliveryAddress->setLastName( empty( $order[ 'delivery_lastname' ] ) ? NULL : $order[ 'delivery_lastname' ] );
    
    $street = $this->parseAddress( $order[ 'delivery_street_address' ] );
    $myOrder->deliveryAddress->setStreet( $street->street );
    $myOrder->deliveryAddress->setHouseNumber( $street->street_number );
    
    $myOrder->deliveryAddress->setAddress2( empty( $order[ 'delivery_suburb' ] ) ? NULL : $order[ 'delivery_suburb' ] );
    $myOrder->deliveryAddress->setPostcode( empty( $order[ 'delivery_postcode' ] ) ? NULL : $order[ 'delivery_postcode' ] );
    $myOrder->deliveryAddress->setCity( empty( $order[ 'delivery_city' ] ) ? NULL : $order[ 'delivery_city' ] );
    $myOrder->deliveryAddress->setCountry( empty( $order[ 'delivery_country' ] ) ? NULL : $order[ 'delivery_country' ] );
    $myOrder->deliveryAddress->setCountryCode( empty( $order[ 'delivery_country_iso_code_2' ] ) ? NULL : $order[ 'delivery_country_iso_code_2' ] );
    $myOrder->deliveryAddress->setCompany( empty( $order[ 'delivery_company' ] ) ? NULL : $order[ 'delivery_company' ] );
    $myOrder->deliveryAddress->setState( empty( $order[ 'delivery_state' ] ) ? NULL : $order[ 'delivery_state' ] );

    return $myOrder;
  }

  /**
   * Ermittelt und setzt die Zahlungsmethode
   * @param Order $myOrder Das Order-Objekt
   * @param array $order Die Bestelldaten aus der Datenbank
   * @return Order Das Order-Objekt mit Zahlungsmethode
   */
  private function setPaymentMethod(Order $myOrder, array $order): Order {
    $paymentMethodQuery = xtc_db_query("SELECT billbee_payment_id 
                                          FROM ".TABLE_BB_PAYMENT_METHOD." 
                                         WHERE modified_payment_code = '".$order['payment_class']."'");

    if( 0 < xtc_db_num_rows($paymentMethodQuery) ) {
      $paymentMethod = xtc_db_fetch_array( $paymentMethodQuery );
      $myOrder->setPaymentMethod( intval($paymentMethod['billbee_payment_id']) );
    } else {
      $myOrder->setPaymentMethod( intval(22) ); // PaymentMethod "Andere"
    }

    return $myOrder;
  }

  /**
   * Prüft ob die Bestellung bereits bezahlt wurde und setzt ggf. das Bezahldatum
   * @param Order $myOrder Das Order-Objekt
   * @param array $order Die Bestelldaten aus der Datenbank
   * @return Order Das Order-Objekt mit ggf. Bezahldatum
   */
  private function setPayDate(Order $myOrder, array $order): Order {
    $alreadyPaidWith = array('klarna_', 
                             'mcp_', 
                             'novalnet_', 
                             'paypal', 
                             'cash', 
                             'billpay', 
                             'moneybookers',
                             'sofort_ideal',
                             'worldpay_junior');
                             
    $paymentClass = strtolower($order['payment_class'] ?? '');
    $isPaid = false;
    
    foreach ($alreadyPaidWith as $value) {
      if (strpos($paymentClass, $value) !== false) { 
         $isPaid = true;
         break;
      }
    }

    if ($isPaid) {
      $payDateQuery = xtc_db_query("SELECT date_added 
                                     FROM ".TABLE_ORDERS_STATUS_HISTORY." 
                                    WHERE orders_id = '".(int)$myOrder->getOrderId()."' 
                                 ORDER BY date_added ASC LIMIT 1");
      $payDate = xtc_db_fetch_array($payDateQuery);
      
      if (!empty($payDate['date_added'])) {
        $myOrder->setPayDate(new DateTime($payDate['date_added']));
      } elseif (!empty($order['date_purchased']) && $order['date_purchased'] !== '0000-00-00 00:00:00') {
        $myOrder->setPayDate(new DateTime($order['date_purchased']));
      }
    }

    return $myOrder;
  }

  /**
   * Ermittelt und setzt den Bestellstatus (Modified <-> Billbee Mapping)
   * @param Order $myOrder Das Order-Objekt
   * @param array $order Die Bestelldaten aus der Datenbank
   * @return Order Das Order-Objekt mit Status
   */
  private function setOrderStatus(Order $myOrder, array $order): Order {
    $statusIdModifiedQuery = xtc_db_query( "SELECT orders_status_id 
                                              FROM ".TABLE_ORDERS_STATUS_HISTORY." 
                                             WHERE orders_id = '".$order['orders_id']."' 
                                          ORDER BY date_added DESC LIMIT 1" );
    $statusIdModified = xtc_db_fetch_array( $statusIdModifiedQuery );

    $statusIdBillbeeQuery = xtc_db_query("SELECT billbee_status_id 
                                            FROM bx_billbee_order_status 
                                           WHERE modified_status_id = '".$statusIdModified["orders_status_id"]."' 
                                             AND language_id = '".$_SESSION["languages_id"]."';");
    $statusIdBillbee = xtc_db_fetch_array( $statusIdBillbeeQuery );

    if(NULL !== $statusIdBillbee["billbee_status_id"]) {
      $myOrder->setStatusId($statusIdBillbee["billbee_status_id"]);
    } else {
      $myOrder->setStatusId('1');
    }

    return $myOrder;
  }

  /**
   * Lädt und setzt die Bestellpositionen mit Optionen
   * @param Order $myOrder Das Order-Objekt
   * @return Order Das Order-Objekt mit Items
   */
  private function setOrderItems(Order $myOrder): Order {
    $orderProductsQuery = xtc_db_query( "
      SELECT op.orders_products_id, 
             op.orders_id, 
             op.products_id, 
             op.products_model, 
             op.products_ean,
             (SELECT opa_sub.attributes_model 
                FROM ".TABLE_ORDERS_PRODUCTS_ATTRIBUTES." opa_sub
               WHERE opa_sub.orders_products_id = op.orders_products_id
                 AND opa_sub.attributes_model != ''
               LIMIT 1) AS attributes_model,
             (SELECT opa_sub.attributes_ean 
                FROM ".TABLE_ORDERS_PRODUCTS_ATTRIBUTES." opa_sub
               WHERE opa_sub.orders_products_id = op.orders_products_id
                 AND opa_sub.attributes_ean != ''
               LIMIT 1) AS attributes_ean,
             op.products_name, 
             op.products_price, 
             op.products_price_origin, 
             op.products_discount_made, 
             op.products_shipping_time, 
             op.final_price, 
             op.products_tax, 
             op.products_quantity, 
             op.allow_tax, 
             op.products_order_description, 
             op.products_weight,
             p.products_image  
        FROM ".TABLE_ORDERS_PRODUCTS." op 
        LEFT JOIN ".TABLE_PRODUCTS." p 
          ON op.products_id = p.products_id
       WHERE op.orders_id = '".$myOrder->getOrderId()."' 
    ORDER BY op.orders_products_id ASC" );

    $p = 0;
    $uniqueId = '';
    $orderProducts = array();
    
    while ( $orderProduct = xtc_db_fetch_array( $orderProductsQuery ) ) {
      $orderProducts[ $p ] = new OrderProduct;
      $orderProducts[ $p ]->setDiscountPercent( $orderProduct[ 'products_discount_made' ] );
      $orderProducts[ $p ]->setQuantity( $orderProduct[ 'products_quantity' ] );
      $orderProducts[ $p ]->setUnitPrice( $orderProduct[ 'products_price' ] );

      if(isset($orderProduct[ 'attributes_model' ]) && !empty($orderProduct[ 'attributes_model' ])) {
        $orderProducts[ $p ]->setSku( $orderProduct[ 'attributes_model' ] );
      } else {
        $orderProducts[ $p ]->setSku( $orderProduct[ 'products_model' ] );
      }

      if(isset($orderProduct[ 'attributes_ean' ]) && !empty($orderProduct[ 'attributes_ean' ])) {
        $orderProducts[ $p ]->setEan( $orderProduct[ 'attributes_ean' ] );
      } else {
        $orderProducts[ $p ]->setEan( $orderProduct[ 'products_ean' ] );
      }

      $orderProducts[ $p ]->setOrdersProductId( $orderProduct[ 'orders_products_id' ] );
      $orderProducts[ $p ]->setTaxRate( $orderProduct[ 'products_tax' ] );

      $uniqueId = str_pad($orderProduct[ 'products_id' ], 4, "0", STR_PAD_LEFT);

      $optionsQuery = xtc_db_query( "
        SELECT orders_products_id,
               orders_products_options_id,
               orders_products_options_values_id,
               products_options, 
               products_options_values, 
               price_prefix, 
               options_values_price 
          FROM ".TABLE_ORDERS_PRODUCTS_ATTRIBUTES." 
         WHERE orders_id = '".$myOrder->getOrderId()."' 
           AND orders_products_id = '".$orderProducts[ $p ]->getOrdersProductId()."'" );

      $option        = array();
      $products_name = $orderProduct[ 'products_name' ];
      $optionLen     = xtc_db_num_rows($optionsQuery);

      if( 0 < $optionLen) {
        $uniqueId .= '_';
        for($j = 0; $j < $optionLen; $j++) {
          $options = xtc_db_fetch_array( $optionsQuery );
          $option[$j] = new OrderProductOption;
          $option[$j]->setName($options[ 'products_options' ]);
          $option[$j]->setValue($options[ 'products_options_values' ]);
          
          $uniqueId .= str_pad($options[ 'orders_products_options_id' ], 4, "0", STR_PAD_LEFT).'-'.str_pad($options[ 'orders_products_options_values_id' ], 4, "0", STR_PAD_LEFT);
          if($j < $optionLen-1) {
            $uniqueId .= 'x';
          }
          $products_name .= ', '. $options[ 'products_options' ].': '.$options[ 'products_options_values' ];
        }
      }
      $orderProducts[ $p ]->setName( $products_name );
      $orderProducts[ $p ]->setOptions( $option );
      $orderProducts[ $p ]->setProductId( $uniqueId );

		  $server = (defined('HTTPS_SERVER') && !empty(HTTPS_SERVER)) ? HTTPS_SERVER : HTTP_SERVER;
			$orderProducts[ $p ]->images = array( 0 => new ProductImage() );
			$orderProducts[ $p ]->images[0]->setIsDefault(true);
			$orderProducts[ $p ]->images[0]->setUrl($server."/".DIR_WS_ORIGINAL_IMAGES.$orderProduct[ 'products_image' ]);
			$orderProducts[ $p ]->images[0]->setPosition(1);

      $moreImagesQuery = xtc_db_query("SELECT image_name, image_nr FROM products_images WHERE products_id = '".(int)$orderProduct['products_id']."' ORDER BY image_nr");
			$i = 1;
      while ( $moreImages = xtc_db_fetch_array( $moreImagesQuery ) ) {
        $orderProducts[ $p ]->images[$i] = new ProductImage();
				$orderProducts[ $p ]->images[$i]->setIsDefault(false);
				$orderProducts[ $p ]->images[$i]->setUrl($server."/".DIR_WS_ORIGINAL_IMAGES.$moreImages['image_name']);
				$orderProducts[ $p ]->images[$i]->setPosition($moreImages['image_nr']);
				$i++;
			}

      $p++;
    }
    
    $myOrder->setItems($orderProducts);
    return $myOrder;
  }

  /**
   * Setzt Kommentare und Notizen zur Bestellung
   * @param Order $myOrder Das Order-Objekt
   * @param array $order Die Bestelldaten aus der Datenbank
   * @return Order Das Order-Objekt mit Kommentaren
   */
  private function setOrderComments(Order $myOrder, array $order): Order {
    $myOrder->comments = array();
    $myOrder->comments[ 0 ] = new OrderComment;
    $myOrder->comments[ 0 ]->setDateAdded( new DateTime( $order[ 'date_purchased' ] ) );
    $myOrder->comments[ 0 ]->setName( 'Kundenkommentar' );
    $myOrder->comments[ 0 ]->setComment( $order[ 'comments' ] );
    $myOrder->comments[ 0 ]->setFromCustomer( true );

    $sellerCommentQuery = xtc_db_query( "
      SELECT MAX(memo_id) AS memo_id,
             customers_id, 
             memo_date, 
             memo_title, 
             memo_text, 
             poster_id
        FROM customers_memo 
       WHERE customers_id = '".$order[ 'customers_id' ]."'" );

    $sellerComment = xtc_db_fetch_array( $sellerCommentQuery );
    $myOrder->setSellerComment( empty( $sellerComment[ 'memo_text' ] ) ? NULL : $sellerComment[ 'memo_text' ] );

    return $myOrder;
  }

  /**
   * Setzt zusätzliche Bestellinformationen (USt-ID, Versandprofil, Transaktions-ID)
   * @param Order $myOrder Das Order-Objekt
   * @param array $order Die Bestelldaten aus der Datenbank
   * @return Order Das Order-Objekt mit zusätzlichen Infos
   */
  private function setAdditionalOrderInfo(Order $myOrder, array $order): Order {
    $myOrder->setVatId( empty( $order[ 'customers_vat_id' ] ) ? NULL : $order[ 'customers_vat_id' ] );

    $shippingProfileId = NULL;
    if ( !empty( $order[ 'shipping_class' ] ) ) {
      $shippingProfileIdArr = explode( '_', $order[ 'shipping_class' ] );
      $shippingProfileId = $shippingProfileIdArr[ 0 ];
    }
    $myOrder->setShippingProfileId( $shippingProfileId );

    if ( defined("MODULE_PAYMENT_INSTALLED") && false !== strpos( MODULE_PAYMENT_INSTALLED, 'paypal' ) ) {
      $paymentTransactionIdQuery = xtc_db_query( "SELECT payment_id FROM paypal_payment WHERE orders_id = '".$myOrder->getOrderId()."'" );
      $paymentTransactionId = xtc_db_fetch_array( $paymentTransactionIdQuery );
      $myOrder->setPaymentTransactionId( empty($paymentTransactionId[ 'payment_id' ]) ? '' :  $paymentTransactionId[ 'payment_id' ]);
    }

    return $myOrder;
  }

  /**
   * Baut ein komplettes Order-Objekt aus den Datenbank-Daten
   *
   * Orchestriert alle Setter-Methoden um aus einem Datenbank-Array
   * ein vollständiges Billbee Order-Objekt zu erstellen. Ruft nacheinander
   * alle spezialisierten Setter-Methoden auf.
   *
   * @param array $order Assoziatives Array mit Bestelldaten aus der DB
   * 
   * @return Order Das vollständig befüllte und validierte Order-Objekt
   */
  private function buildOrderFromData(array $order): Order {
    $myOrder = new Order();
    
    $this->setOrderBasicInfo($myOrder, $order);
    $this->setInvoiceAddress($myOrder, $order);
    $this->setDeliveryAddress($myOrder, $order);
    $this->setPaymentMethod($myOrder, $order);
    $this->setPayDate($myOrder, $order);
    $this->setOrderStatus($myOrder, $order);
    $this->setOrderItems($myOrder);
    $this->setOrderComments($myOrder, $order);
    $this->setAdditionalOrderInfo($myOrder, $order);

    return $myOrder;
  }
}