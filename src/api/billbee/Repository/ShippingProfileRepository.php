<?php
/**
 * ShippingProfileRepository - Verwaltung von Versandprofilen für die Billbee API Integration
 *
 * Diese Klasse implementiert die ShippingProfileRepositoryInterface und stellt Methoden
 * zum Abrufen der installierten Versandmodule aus dem Modified Shop bereit.
 * Die Versandprofile werden dynamisch aus den installierten Shipping-Modulen geladen.
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

use Billbee\CustomShopApi\Exception\NotImplementedException;
use Billbee\CustomShopApi\Repository\ShippingProfileRepositoryInterface;

class ShippingProfileRepository implements ShippingProfileRepositoryInterface {
	
	/**
	 * Ruft alle installierten Versandprofile ab
	 *
	 * Lädt dynamisch alle installierten Versandmodule aus MODULE_SHIPPING_INSTALLED,
	 * instanziiert jedes Modul und extrahiert dessen Code und Titel.
	 * Die Versandprofile werden als Array mit Id (Modul-Code) und Name (Modul-Titel) zurückgegeben.
	 *
	 * @return array Array von Versandprofilen im Format: [['Id' => string, 'Name' => string], ...]
	 *               Beispiel: [['Id' => 'dp', 'Name' => 'Deutsche Post'], ...]
	 */
	public function getShippingProfiles():array {
		$shippingFiles = array_filter(explode(";", MODULE_SHIPPING_INSTALLED));
		$profiles = array();
		
		// Sprache sicher ermitteln mit Fallback
		$language = isset($_SESSION['language']) ? basename($_SESSION['language']) : 'german';
		
		foreach($shippingFiles as $moduleFile) {
			// Sicherheitsprüfung: nur .php Dateien, kein Path Traversal
			$moduleFile = basename($moduleFile);
			if (empty($moduleFile) || substr($moduleFile, -4) !== '.php') {
				continue;
			}
			
			// Language-Datei laden (optional)
			$langFile = DIR_WS_LANGUAGES . $language . '/modules/shipping/' . $moduleFile;
			if (is_file($langFile)) {
				include_once($langFile);
			}
			
			// Modul-Datei laden
			$modulePath = DIR_WS_MODULES . 'shipping/' . $moduleFile;
			if (!is_file($modulePath)) {
				continue;
			}
			include_once($modulePath);
			
			// Klasse instanziieren
			$className = basename($moduleFile, ".php");
			if (!class_exists($className)) {
				continue;
			}
			
			$moduleInstance = new $className();
			
			// Prüfen ob benötigte Properties existieren
			if (isset($moduleInstance->code) && isset($moduleInstance->title)) {
				$profiles[] = array(
					"Id" => $moduleInstance->code,
					"Name" => $moduleInstance->title
				);
			}
		}
		
		return $profiles;
	}
}