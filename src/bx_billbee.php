<?php
/**
 * Billbee API Integration - Endpoint Handler
 * 
 * Diese Datei dient als Einsprungpunkt für die Billbee Custom Shop API.
 * Sie verarbeitet eingehende API-Anfragen von Billbee und leitet diese an die 
 * entsprechenden Repository-Handler weiter.
 * 
 * @package    modified
 * @subpackage Billbee
 * @category   API
 * @author     benax
 * @copyright  2009 - 2023 modified eCommerce Shopsoftware
 * @license    GNU General Public License
 * @link       http://www.modified-shop.org
 * @version    1.0.0
 * 
 * Unterstützte Repositories:
 * - OrderRepository: Bestellungsverwaltung und -synchronisation
 * - ProductsRepository: Produktdaten und Bestandsverwaltung
 * - ShippingProfileRepository: Versandprofile und -methoden
 * - StockSyncRepository: Lagerbestandssynchronisation
 * 
 * Authentifizierung:
 * Die API verwendet Key-basierte Authentifizierung über KeyAuthenticator.
 * Der Authentifizierungsschlüssel muss in der Konstante BILLBEE_AUTHENTICATOR 
 * definiert sein.
 * 
 * Debug-Modus:
 * Kann über die Konstante MODULE_BILLBEE_DEBUG aktiviert werden.
 * Bei aktiviertem Debug werden zusätzliche Test-Funktionen geladen.
 * 
 * @see includes/classes/bx_dependency_resolver.php
 * @see api/billbee/Repository/
 */

require_once ("includes/application_top_callback.php");
require_once ("includes/classes/split_page_results.php");
require_once ("includes/classes/bx_cartesian_builder.php");

require_once ("includes/classes/bx_dependency_resolver.php");

$modified_billbee = false;

try {
  bx_dependency_resolver::require('modified_billbee');    
  $modified_billbee = true;
} catch (Exception $e) {
  error_log('BX Billbee nicht verfügbar: ' . $e->getMessage());
}

if (!$modified_billbee) {
  http_response_code(503); // Service Unavailable
  exit('Billbee API ist momentan nicht verfügbar.');
}

if( !defined("MODULE_BILLBEE_DEBUG") )    define("MODULE_BILLBEE_DEBUG", "false");
if( !defined("CATEGORIES_CONDITIONS_C") ) define("CATEGORIES_CONDITIONS_C", "");
if( !defined("SPECIALS_CONDITIONS_S") )   define("SPECIALS_CONDITIONS_S", "");
if( !defined("PRODUCTS_CONDITIONS_P") )   define("PRODUCTS_CONDITIONS_P", "");

if(defined("MODULE_BILLBEE_DEBUG") && (MODULE_BILLBEE_DEBUG === 'true' || MODULE_BILLBEE_DEBUG === true)) {
  include_once("bx_billbee_test.php");
}

// Lade die ModifiedShopApi Repository-Klassen manuell
require_once(DIR_FS_API . 'billbee/Repository/OrderRepository.php');
require_once(DIR_FS_API . 'billbee/Repository/ProductsRepository.php');
require_once(DIR_FS_API . 'billbee/Repository/ShippingProfileRepository.php');
require_once(DIR_FS_API . 'billbee/Repository/StockSyncRepository.php');


use Billbee\CustomShopApi\Http\Request;
use Billbee\CustomShopApi\Http\RequestHandlerPool;
use Billbee\CustomShopApi\Security\KeyAuthenticator;
use Billbee\ModifiedShopApi\Repository\OrderRepository;
use Billbee\ModifiedShopApi\Repository\ProductsRepository;
use Billbee\ModifiedShopApi\Repository\ShippingProfileRepository;
use Billbee\ModifiedShopApi\Repository\StockSyncRepository;

// Authentifizierung
$authenticator = new KeyAuthenticator( BILLBEE_AUTHENTICATOR );

$handler = new RequestHandlerPool($authenticator, [
    new OrderRepository(),
    new ProductsRepository(),
    new ShippingProfileRepository(),
    new StockSyncRepository(),
]);

$request = Request::createFromGlobals();
$response = $handler->handle($request);

// Zuletzt wird die Response an den client gesendet
$response->send();
