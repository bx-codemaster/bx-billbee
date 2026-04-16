<?php
/**
 * Billbee System Module für modified eCommerce
 * 
 * Stellt die Integration zwischen modified Shop und Billbee API bereit.
 * Verwaltet Bestellungen, Produkte, Zahlungsmethoden und Bestellstatus-Synchronisation.
 *
 * @package    Billbee
 * @author     benax
 * @copyright  2009-2026 modified eCommerce
 * @license    GPL-2.0
 * @version    1.3.5
 * @link       https://www.billbee.io
 */

defined( '_VALID_XTC' ) or die( 'Direct Access to this location is not allowed.' );

class bx_billbee {
  /** @var string Modul-Version */
  public $version;
  
  /** @var string Modul-Code/Identifier */
	public $code;
	
	/** @var string Modul-Titel für Admin-Anzeige */
	public $title;
	
	/** @var string Modul-Beschreibung */
  public $description;
  
  /** @var int Sortierreihenfolge in der Modulliste */
  public $sort_order;
  
  /** @var bool Modul aktiviert/deaktiviert */
  public $enabled;
  
  /** @var int|false Cache für check()-Ergebnis */
  private $_check;

  /**
   * Konstruktor - Initialisiert die Modul-Eigenschaften
   */
  function __construct() {
    $this->version     = '1.4.0';
		$this->code        = 'bx_billbee';
    $this->title       = MODULE_BILLBEE_TEXT_TITLE.' <small>(Version: '.$this->version.')</small>';
    $this->description = MODULE_BILLBEE_TEXT_DESCRIPTION.'<p><strong>Version: '.$this->version.'</strong></p>';
    $this->sort_order  = defined('MODULE_BILLBEE_SORT_ORDER') ? MODULE_BILLBEE_SORT_ORDER : 0;
    $this->enabled     = defined('MODULE_BILLBEE_STATUS') ? ((MODULE_BILLBEE_STATUS == 'true') ? true : false) : 0;
   }

  /**
   * Verarbeitet Modul-spezifische Aktionen
   * 
   * @param string $file Dateiname zur Verarbeitung
   * @return bool Immer true
   */
  public function process($file): bool {
		return true;
  }

  /**
   * Installiert das Billbee-Modul
   * 
   * Erstellt alle benötigten Tabellen, Konfigurationsgruppen und Standardwerte:
   * - Legt Admin-Berechtigung an

   * - Fügt Konfigurationsoptionen hinzu
   * - Initialisiert Standard-Zahlungsmethoden und Status-Mappings
   * 
   * @return void
   */

  public function check(): mixed {
    if (!isset($this->_check)) {
      $check_query = xtc_db_query("SELECT configuration_value 
                                     FROM ".TABLE_CONFIGURATION."
                                    WHERE configuration_key = 'MODULE_BILLBEE_STATUS'");
      $this->_check = xtc_db_num_rows($check_query);
    }
    return $this->_check;
  }

	/**
	* Additional HTML to show during module configuration.
	*
	* @return array
	*/
	  
	public function display() {
    return array('text' => '<div style="text-align: center;">'.xtc_button(BUTTON_SAVE).xtc_button_link(BUTTON_CANCEL, xtc_href_link(FILENAME_MODULE_EXPORT, 'set='.$_GET['set'].'&module='.$this->code))."</div>");
  }
    
  public function install(): void {
		xtc_db_query("ALTER TABLE ".TABLE_ADMIN_ACCESS." ADD bx_billbee INTEGER(1)");
		xtc_db_query("UPDATE ".TABLE_ADMIN_ACCESS." SET bx_billbee = 1");

		xtc_db_query("CREATE TABLE ".TABLE_BB_STOCK." ( billbee_stock_id int(11) UNSIGNED NOT NULL,
																										products_id int(11) UNSIGNED NOT NULL DEFAULT 0,
																										products_sku varchar(125) NOT NULL,
																										products_ean varchar(125) NOT NULL,
																										billbee_attributes varchar(255) NOT NULL,
																										billbee_attributes_quantity int(11) UNSIGNED NOT NULL DEFAULT 0,
																										bx_exported varchar(1) NOT NULL DEFAULT 'n'
																									) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
																									
		xtc_db_query("ALTER TABLE ".TABLE_BB_STOCK." ADD PRIMARY KEY (billbee_stock_id), ADD UNIQUE KEY idx_billbee_attributes (products_id,billbee_attributes);");
		xtc_db_query("ALTER TABLE ".TABLE_BB_STOCK." MODIFY billbee_stock_id int(11) UNSIGNED NOT NULL AUTO_INCREMENT;");

		xtc_db_query("ALTER TABLE ".TABLE_ORDERS." ADD bx_exported VARCHAR(1) NOT NULL DEFAULT 'n'");
		xtc_db_query("ALTER TABLE ".TABLE_PRODUCTS." ADD bx_exported VARCHAR(1) NOT NULL DEFAULT 'n'");

		xtc_db_query("CREATE TABLE ".TABLE_BB_PAYMENT_METHOD." ( billbee_payment_id int(11) NOT NULL,
																														 language_id int(11) NOT NULL,
																														 billbee_payment_name varchar(128) DEFAULT NULL,
																														 modified_payment_code varchar(128) DEFAULT 'default',
																														 PRIMARY KEY (billbee_payment_id,language_id)
																													 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");																											

		xtc_db_query("INSERT INTO ".TABLE_BB_PAYMENT_METHOD." (billbee_payment_id, language_id, billbee_payment_name, modified_payment_code) 
		                                               VALUES (1, 1, 'Bank transfer', 'default'),
																													(1, 2, 'Banküberweisung', 'default'),
																													(2, 1, 'Cash on delivery', 'default'),
																													(2, 2, 'Nachnahme', 'default'),
																													(3, 1, 'Paypal', 'default'),
																													(3, 2, 'Paypal', 'default'),
																													(4, 1, 'Cash', 'default'),
																													(4, 2, 'Barzahlung', 'default'),
																													(5, 1, NULL, 'default'),
																													(5, 2, NULL, 'default'),(6, 1, 'Voucher', 'default'),
																													(6, 2, 'Gutschein', 'default'),
																													(7, 1, NULL, 'default'),
																													(7, 2, NULL, 'default'),
																													(8, 1, NULL, 'default'),
																													(8, 2, NULL, 'default'),
																													(9, 1, NULL, 'default'),
																													(9, 2, NULL, 'default'),
																													(10, 1, NULL, 'default'),
																													(10, 2, NULL, 'default'),
																													(11, 1, NULL, 'default'),
																													(11, 2, NULL, 'default'),
																													(12, 1, NULL, 'default'),
																													(12, 2, NULL, 'default'),
																													(13, 1, NULL, 'default'),
																													(13, 2, NULL, 'default'),
																													(14, 1, NULL, 'default'),
																													(14, 2, NULL, 'default'),
																													(15, 1, NULL, 'default'),
																													(15, 2, NULL, 'default'),
																													(16, 1, NULL, 'default'),
																													(16, 2, NULL, 'default'),
																													(17, 1, NULL, 'default'),
																													(17, 2, NULL, 'default'),
																													(18, 1, NULL, 'default'),
																													(18, 2, NULL, 'default'),
																													(19, 1, 'Immediate bank transfer', 'default'),
																													(19, 2, 'Sofortüberweisung', 'default'),
																													(20, 1, 'Payment order', 'default'),
																													(20, 2, 'Zahlungsanweisung', 'default'),
																													(21, 1, 'Cheque', 'default'),
																													(21, 2, 'Scheck', 'default'),
																													(22, 1, 'Other', 'default'),
																													(22, 2, 'Andere', 'default'),
																													(23, 1, 'Direct debit', 'default'),
																													(23, 2, 'Lastschrift', 'default'),
																													(24, 1, 'Moneybookers', 'default'),
																													(24, 2, 'Moneybookers', 'default'),
																													(25, 1, 'KLARNA', 'default'),
																													(25, 2, 'KLARNA', 'default'),
																													(26, 1, 'Invoice', 'default'),
																													(26, 2, 'Rechnung', 'default'),
																													(27, 1, 'Moneybookers CC', 'default'),
																													(27, 2, 'Moneybookers CC', 'default'),
																													(28, 1, 'Moneybookers Direct Debit', 'default'),
																													(28, 2, 'Moneybookers Lastschrift', 'default'),
																													(29, 1, 'BILLPAY Rechnung', 'default'),
																													(29, 2, 'BILLPAY Invoice', 'default'),
																													(30, 1, 'BILLPAY Direct Debit', 'default'),
																													(30, 2, 'BILLPAY Lastschrift', 'default'),
																													(31, 1, 'Credit card', 'default'),
																													(31, 2, 'Kreditkarte', 'default'),
																													(32, 1, 'Maestro', 'default'),
																													(32, 2, 'Maestro', 'default'),
																													(33, 1, 'iDEAL', 'default'),
																													(33, 2, 'iDEAL', 'default'),
																													(34, 1, 'EPS', 'default'),
																													(34, 2, 'EPS', 'default'),
																													(35, 1, 'P24', 'default'),
																													(35, 2, 'P24', 'default'),
																													(36, 1, 'ClickAndBuy', 'default'),
																													(36, 2, 'ClickAndBuy', 'default'),
																													(37, 1, 'GiroPay', 'default'),
																													(37, 2, 'GiroPay', 'default'),
																													(38, 1, 'Novalnet direct debit', 'default'),
																													(38, 2, 'Novalnet Lastschrift', 'default'),
																													(39, 1, 'KLARNA PartPayment', 'default'),
																													(39, 2, 'KLARNA Teilzahlung', 'default'),
																													(40, 1, 'iPayment CC', 'default'),
																													(40, 2, 'iPayment CC', 'default'),
																													(41, 1, 'Billsafe', 'default'),
																													(41, 2, 'Billsafe', 'default'),
																													(42, 1, 'Test order', 'default'),
																													(42, 2, 'Testbestellung', 'default'),
																													(43, 1, 'WireCard credit card', 'default'),
																													(43, 2, 'WireCard Kreditkarte', 'default'),
																													(44, 1, 'AmazonPayments', 'default'),
																													(44, 2, 'AmazonPayments', 'default'),
																													(45, 1, 'Secupay credit card', 'default'),
																													(45, 2, 'Secupay Kreditkarte', 'default'),
																													(46, 1, 'Secupay Direct debit', 'default'),
																													(46, 2, 'Secupay Lastschrift', 'default'),
																													(47, 1, 'WireCard Direct debit', 'default'),
																													(47, 2, 'WireCard Lastschrift', 'default'),
																													(48, 1, 'EC', 'default'),
																													(48, 2, 'EC', 'default'),
																													(49, 1, 'Paymill Credit card', 'default'),
																													(49, 2, 'Paymill Kreditkarte', 'default'),
																													(50, 1, 'Novalnet Credit card', 'default'),
																													(50, 2, 'Novalnet Kreditkarte', 'default'),
																													(51, 1, 'Novalnet Invoice', 'default'),
																													(51, 2, 'Novalnet Rechnung', 'default'),
																													(52, 1, 'Novalnet PayPal', 'default'),
																													(52, 2, 'Novalnet PayPal', 'default'),
																													(53, 1, 'Paymill', 'default'),
																													(53, 2, 'Paymill', 'default'),
																													(54, 1, 'PayPal Invoice', 'default'),
																													(54, 2, 'PayPal Rechnung', 'default'),
																													(55, 1, 'Selekkt', 'default'),
																													(55, 2, 'Selekkt', 'default'),
																													(56, 1, 'Avocadostore', 'default'),
																													(56, 2, 'Avocadostore', 'default'),
																													(57, 1, 'DirectCheckout', 'default'),
																													(57, 2, 'DirectCheckout', 'default'),
																													(58, 1, 'Rakuten', 'default'),
																													(58, 2, 'Rakuten', 'default'),
																													(59, 1, 'Prepayment', 'default'),
																													(59, 2, 'Vorkasse', 'default'),
																													(60, 1, 'Commission settlement', 'default'),
																													(60, 2, 'Kommissionsabrechnung', 'default'),
																													(61, 1, 'Amazon Marketplace', 'default'),
																													(61, 2, 'Amazon Marktplatz', 'default'),
																													(62, 1, 'Amazon Payments Advanced', 'default'),
																													(62, 2, 'Amazon Vorauszahlungen', 'default'),
																													(63, 1, 'Stripe', 'default'),
																													(63, 2, 'Stripe', 'default'),
																													(64, 1, 'BILLPAY PayLater', 'default'),
																													(64, 2, 'BILLPAY Später bezahlen', 'default'),
																													(65, 1, 'PostFinance', 'default'),
																													(65, 2, 'PostFinance', 'default'),
																													(66, 1, 'Zettle', 'default'),
																													(66, 2, 'Zettle', 'default'),
																													(67, 1, 'SumUp', 'default'),
																													(67, 2, 'SumUp', 'default'),
																													(68, 1, 'payleven', 'default'),
																													(68, 2, 'payleven', 'default'),
																													(69, 1, 'atalanda', 'default'),
																													(69, 2, 'atalanda', 'default'),
																													(70, 1, 'Saferpay Credit card', 'default'),
																													(70, 2, 'Saferpay Kreditkarte', 'default'),
																													(71, 1, 'WireCard PayPal', 'default'),
																													(71, 2, 'WireCard PayPal', 'default'),
																													(72, 1, 'Micropayment', 'default'),
																													(72, 2, 'Micropayment', 'default'),
																													(73, 1, 'Hire purchase', 'default'),
																													(73, 2, 'Ratenkauf', 'default'),
																													(74, 1, 'Wayfair', 'default'),
																													(74, 2, 'Wayfair', 'default'),
																													(75, 1, 'MangoPay PayPal', 'default'),
																													(75, 2, 'MangoPay PayPal', 'default'),
																													(76, 1, 'MangoPay Instant bank transfer', 'default'),
																													(76, 2, 'MangoPay Sofortüberweisung', 'default'),
																													(77, 1, 'MangoPay Credit card', 'default'),
																													(77, 2, 'MangoPay Kreditkarte', 'default'),
																													(78, 1, 'MangoPay iDeal', 'default'),
																													(78, 2, 'MangoPay iDeal', 'default'),
																													(79, 1, 'PayPal Express', 'default'),
																													(79, 2, 'PayPal Express', 'default'),
																													(80, 1, 'PayPal Direct debit', 'default'),
																													(80, 2, 'PayPal Lastschrift', 'default'),
																													(81, 1, 'PayPal Credit card', 'default'),
																													(81, 2, 'PayPal Kreditkarte', 'default'),
																													(82, 1, 'Wish', 'default'),
																													(82, 2, 'Wish', 'default'),
																													(83, 1, 'Bancontact Mister Cash', 'default'),
																													(83, 2, 'Bancontact Mister Cash', 'default'),
																													(84, 1, 'Belfius Direct Net', 'default'),
																													(84, 2, 'Belfius Direct Net', 'default'),
																													(85, 1, 'KBC CBC Betaalknop', 'default'),
																													(85, 2, 'KBC CBC Betaalknop', 'default'),
																													(86, 1, 'Novalnet Przelewy24', 'default'),
																													(86, 2, 'Novalnet Przelewy24', 'default'),
																													(87, 1, 'Novalnet Prepayment', 'default'),
																													(87, 2, 'Novalnet Vorkasse', 'default'),
																													(88, 1, 'Novalnet Instantbank', 'default'),
																													(88, 2, 'Novalnet Instantbank', 'default'),
																													(89, 1, 'Novalnet IDEAL', 'default'),
																													(89, 2, 'Novalnet IDEAL', 'default'),
																													(90, 1, 'Novalnet EPS', 'default'),
																													(90, 2, 'Novalnet EPS', 'default'),
																													(91, 1, 'Novalnet GiroPay', 'default'),
																													(91, 2, 'Novalnet GiroPay', 'default'),
																													(92, 1, 'Novalnet Cash payments', 'default'),
																													(92, 2, 'Novalnet Barzahlen', 'default'),
																													(93, 1, 'Real', 'default'),
																													(93, 2, 'Real', 'default'),
																													(94, 1, 'Fruugo', 'default'),
																													(94, 2, 'Fruugo', 'default'),
																													(95, 1, 'Cdiscount', 'default'),
																													(95, 2, 'Cdiscount', 'default'),
																													(96, 1, 'PayDirekt', 'default'),
																													(96, 2, 'PayDirekt', 'default'),
																													(97, 1, 'EtsyPayments', 'default'),
																													(97, 2, 'EtsyPayments', 'default'),
																													(98, 1, 'KLARNA', 'default'),
																													(98, 2, 'KLARNA', 'default'),
																													(99, 1, 'limango', 'default'),
																													(99, 2, 'limango', 'default'),
																													(100, 1, 'Santander Hire purchase', 'default'),
																													(100, 2, 'Santander Ratenkauf', 'default'),
																													(101, 1, 'Santander Purchase on account', 'default'),
																													(101, 2, 'Santander Rechnungskauf', 'default'),
																													(102, 1, 'Cashpresso', 'default'),
																													(102, 2, 'Cashpresso', 'default'),
																													(103, 1, 'Tipser', 'default'),
																													(103, 2, 'Tipser', 'default'),
																													(104, 1, 'Ebay', 'default'),
																													(104, 2, 'Ebay', 'default'),
																													(105, 1, 'Mollie', 'default'),
																													(105, 2, 'Mollie', 'default'),
																													(106, 1, 'Mollie Rechnung', 'default'),
																													(106, 2, 'Mollie Invoice', 'default'),
																													(107, 1, 'Mollie Credit Card', 'default'),
																													(107, 2, 'Mollie Kreditkarte', 'default'),
																													(108, 1, 'Mollie Instantly', 'default'),
																													(108, 2, 'Mollie Sofort', 'default'),
																													(109, 1, 'Mollie GiroPay', 'default'),
																													(109, 2, 'Mollie GiroPay', 'default'),
																													(110, 1, 'Mollie Maestro', 'default'),
																													(110, 2, 'Mollie Maestro', 'default'),
																													(111, 1, 'Mollie Klarna PayLater', 'default'),
																													(111, 2, 'Mollie Klarna Später bezahlen', 'default'),
																													(112, 1, 'Mollie PayPal', 'default'),
																													(112, 2, 'Mollie PayPal', 'default');");
		
		xtc_db_query("CREATE TABLE ".TABLE_BB_ORDER_STATUS." ( billbee_status_id int(11) NOT NULL,
																													 language_id int(11) NOT NULL,
																													 billbee_status_name varchar(128) DEFAULT NULL,
																													 modified_status_id int(11) DEFAULT 0
																												 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
		
		xtc_db_query("ALTER TABLE ".TABLE_BB_ORDER_STATUS." ADD PRIMARY KEY (billbee_status_id,language_id)");

		xtc_db_query("INSERT INTO ".TABLE_BB_ORDER_STATUS." (billbee_status_id, language_id, billbee_status_name, modified_status_id)
																									 VALUES (1, 1, 'Ordered', 0),
																									 				(1, 2, 'Bestellt', 0),
																													(2, 1, 'Confirmed', 0),
																													(2, 2, 'Bestätigt', 0),
																													(3, 1, 'Paid', 103),
																													(3, 2, 'Bezahlt', 0),
																													(4, 1, 'Dispatched', 0),
																													(4, 2, 'Versendet', 0),
																													(5, 1, 'Complained', 0),
																													(5, 2, 'Reklamiert', 0),
																													(6, 1, 'Deleted', 0),
																													(6, 2, 'Gelöscht', 0),
																													(7, 1, 'Completed', 0),
																													(7, 2, 'Abgeschlossen', 0),
																													(8, 1, 'Cancelled', 0),
																													(8, 2, 'Storniert', 0),
																													(9, 1, 'Archived', 0),
																													(9, 2, 'Archiviert', 0),
																													(10, 1, 'Rated', 0),
																													(10, 2, 'Bewertet', 0),
																													(11, 1, '1st reminder', 0),
																													(11, 2, '1. Mahnung', 0),
																													(12, 1, '2nd reminder', 0),
																												  (12, 2, '2. Mahnung', 0),
																													(13, 1, 'Packed', 0),
																													(13, 2, 'Gepackt', 0),
																													(14, 1, 'Offered', 0),
																													(14, 2, 'Angeboten', 0),
																													(15, 1, 'Payment reminder', 0),
																													(15, 2, 'Zahlungserinnerung', 0),
																													(16, 1, 'Transferred to external fulfilment', 0),
																													(16, 2, 'Übergeben an externes Fulfillment', 0);");
		
		$freeId_query = xtc_db_query("SELECT (configuration_group_id+1) AS id 
	                                FROM " . TABLE_CONFIGURATION_GROUP . " 
																	WHERE (configuration_group_id+1) NOT IN (SELECT configuration_group_id FROM " . TABLE_CONFIGURATION_GROUP . " WHERE configuration_group_id IS NOT NULL) 
																	LIMIT 1;");
		$freeId = xtc_db_fetch_array($freeId_query);

		$freeSort_query = xtc_db_query("SELECT (sort_order+1) AS sort_order 
	                                  FROM ".TABLE_CONFIGURATION_GROUP." 
																		WHERE (sort_order+1) NOT IN (SELECT sort_order FROM ".TABLE_CONFIGURATION_GROUP." WHERE sort_order IS NOT NULL) 
																		LIMIT 1;");
		$freeSort = xtc_db_fetch_array($freeSort_query);
		
		xtc_db_query("INSERT INTO ".TABLE_CONFIGURATION_GROUP." ( configuration_group_id,
																															configuration_group_title, 
																															configuration_group_description, 
																															sort_order, 
																															visible) 
																										 VALUES ( ".$freeId["id"].", 
																										 					'Billbee Konfiguration', 
																															'Einstellungen zum Billbee-Modul', 
																															".$freeSort["sort_order"].", 
																															1)");

		xtc_db_query("INSERT INTO ".TABLE_CONFIGURATION." ( configuration_id,
		                                                    configuration_key, 
																												configuration_value, 
																												configuration_group_id, 
																												sort_order, 
																												date_added, 
																												use_function, 
																												set_function )
																							 VALUES ( '', 
																							          'MODULE_BILLBEE_STATUS',
																												'true', 
																												'6', 
																												'1', 
																												now(), 
																												'', 
																												'xtc_cfg_select_option(array(\'true\', \'false\'), ')");

		xtc_db_query("INSERT INTO ".TABLE_CONFIGURATION." ( configuration_id, 
		                                                    configuration_key, 
																												configuration_value, 
																												configuration_group_id, 
																												sort_order, 
																												date_added, 
																												use_function, 
																												set_function )
																							 VALUES ( '', 
																							          'MODULE_BILLBEE_CONFIG_ID',
																							          '".$freeId["id"]."', 
																												'6', 
																												'2', 
																												now(), 
																												'bx_billbee_get_group_id', 
																												'xtc_convert_value( ')");

		xtc_db_query("INSERT INTO ".TABLE_CONFIGURATION." ( configuration_id,
		                                                    configuration_key, 
																												configuration_value, 
																												configuration_group_id, 
																												sort_order, 
																												date_added, 
																												use_function, 
																												set_function )
																							 VALUES ( '', 
																							          'MODULE_BILLBEE_DEBUG',
																												'false', 
																												'".$freeId["id"]."', 
																												'1', 
																												now(), 
																												'', 
																												'xtc_cfg_select_option(array(\'true\', \'false\'), ')");
		
		xtc_db_query("INSERT INTO ".TABLE_CONFIGURATION." ( configuration_id, 
		                                                    configuration_key, 
																												configuration_value, 
																												configuration_group_id, 
																												sort_order, 
																												date_added, 
																												use_function, 
																												set_function )
																							 VALUES ( '', 
																							          'BILLBEE_AUTHENTICATOR',
																							          'GEHEIM', 
																												'".$freeId["id"]."', 
																												'2', 
																												now(), 
																												'', 
																												'')");

		xtc_db_query("INSERT INTO ".TABLE_CONFIGURATION." ( configuration_id, 
		                                                    configuration_key, 
																												configuration_value, 
																												configuration_group_id, 
																												sort_order, 
																												date_added, 
																												use_function, 
																												set_function )
																							 VALUES ( '', 
																							          'BILLBEE_INVOICE_NUMBER_PREFIX',
																							          'MES-', 
																												'".$freeId["id"]."', 
																												'3', 
																												now(), 
																												'', 
																												'')");
		
		xtc_db_query("INSERT INTO ".TABLE_CONFIGURATION." ( configuration_id, 
		                                                    configuration_key, 
																												configuration_value, 
																												configuration_group_id, 
																												sort_order, 
																												date_added, 
																												use_function, 
																												set_function )
																							 VALUES ( '', 
																							          'BILLBEE_INVOICE_NUMBER_POSTFIX',
																							          '', 
																												'".$freeId["id"]."', 
																												'4', 
																												now(), 
																												'', 
																												'')");
		xtc_db_query("INSERT INTO ".TABLE_CONFIGURATION." ( configuration_id, 
		                                                    configuration_key, 
																												configuration_value, 
																												configuration_group_id, 
																												sort_order, 
																												date_added, 
																												use_function, 
																												set_function )
																							 VALUES ( '', 
																							          'BILLBEE_LANGUAGE_ID',
																							          '', 
																												'".$freeId["id"]."', 
																												'5', 
																												now(), 
																												'',
																												'xtc_draw_pull_down_menu( \'BILLBEE_LANGUAGE_ID\', bx_get_language_ids(), bx_get_billbee_language(), \'\', false, true , ')");

	}

  /**
   * Deinstalliert das Billbee-Modul
   * 
   * Entfernt alle Konfigurationen, Tabellen und Datenbankfelder:
   * - Löscht Konfigurationseinträge
   * - Entfernt Admin-Berechtigung
   * - Löscht bx_exported-Spalten
   * - Droppt alle Billbee-Tabellen
   * 
   * @return void
   */
  public function remove(): void {
    xtc_db_query("DELETE FROM ".TABLE_CONFIGURATION." WHERE configuration_key in ('".implode("', '", $this->keys())."')");
		xtc_db_query("DELETE FROM ".TABLE_CONFIGURATION." WHERE configuration_key in ('".implode("', '", $this->keys2())."')");
		xtc_db_query("DELETE FROM ".TABLE_CONFIGURATION_GROUP." WHERE configuration_group_title = 'Billbee Konfiguration'");
		xtc_db_query("ALTER TABLE ".TABLE_ADMIN_ACCESS." DROP bx_billbee");
		xtc_db_query("ALTER TABLE ".TABLE_PRODUCTS." DROP bx_exported");
		xtc_db_query("ALTER TABLE ".TABLE_ORDERS." DROP bx_exported");
		xtc_db_query("DROP TABLE ".TABLE_BB_PAYMENT_METHOD.";");
		xtc_db_query("DROP TABLE ".TABLE_BB_ORDER_STATUS.";");
		xtc_db_query("DROP TABLE ".TABLE_BB_STOCK.";");
  }

  /**
   * Gibt die primären Konfigurations-Schlüssel zurück
   * 
   * @return array Array mit Hauptkonfigurations-Keys
   */
  public function keys(): array {
    $key = array(
      'MODULE_BILLBEE_STATUS',
			'MODULE_BILLBEE_CONFIG_ID'
    );
    return $key;
  }

  /**
   * Gibt die sekundären Konfigurations-Schlüssel zurück
   * 
   * @return array Array mit zusätzlichen Konfigurations-Keys
   */
	public function keys2(): array {
    $key = array(
      'BILLBEE_AUTHENTICATOR',
			'MODULE_BILLBEE_DEBUG',
			'BILLBEE_INVOICE_NUMBER_PREFIX',
			'BILLBEE_INVOICE_NUMBER_POSTFIX',
			'BILLBEE_LANGUAGE_ID'
    );
    return $key;
  }
}
?>