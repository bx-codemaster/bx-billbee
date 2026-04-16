<?php
/**
 * StockSyncRepository - Verwaltung der Lagerbestandssynchronisation für Billbee API
 *
 * Diese Klasse implementiert die StockSyncRepositoryInterface und stellt Methoden
 * zur Synchronisation von Lagerbestand zwischen Billbee und Modified Shop bereit.
 * Unterstützt sowohl einfache Produkte als auch komplexe Varianten-Produkte mit
 * automatischer Aggregation der Bestands-Summen auf allen Ebenen (Attribut, Variante, Hauptprodukt).
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

use Billbee\CustomShopApi\Exception\ProductNotFoundException;
use Billbee\CustomShopApi\Repository\StockSyncRepositoryInterface;

class StockSyncRepository implements StockSyncRepositoryInterface {

	/**
	 * Setzt den Lagerbestand für ein Produkt oder eine Variante
	 *
	 * Die Methode verarbeitet Bestandsaktualisierungen für:
	 * 1. Einfache Produkte ohne Varianten (productId = "0001")
	 * 2. Varianten-Produkte (productId = "0001_0002-0003x0004-0005")
	 *
	 * Bei Varianten-Produkten:
	 * - Aktualisiert TABLE_BB_STOCK für die spezifische Varianten-Kombination
	 * - Summiert Bestand für jedes beteiligte Attribut in TABLE_PRODUCTS_ATTRIBUTES
	 * - Summiert Gesamt-Bestand aller Varianten im Hauptprodukt (TABLE_PRODUCTS)
	 * - Verwendet präzise String-Matching um falsche Attribut-Zuordnungen zu verhindern
	 *
	 * Format productId: "XXXX_YYYY-ZZZZxAAAA-BBBB" wobei:
	 * - XXXX = products_id (4-stellig mit führenden Nullen)
	 * - YYYY-ZZZZ = options_id-options_values_id (erstes Attribut)
	 * - xAAAA-BBBB = weiteres Attribut (mit 'x' verknüpft)
	 *
	 * @param string $productId      Die Billbee Produkt-/Varianten-ID
	 * @param int    $AvailableStock Der neue verfügbare Lagerbestand
	 * 
	 * @return void
	 * 
	 * @throws ProductNotFoundException Wenn das Produkt nicht existiert
	 */
	public function setStock($productId, $AvailableStock): void {
    // 1. Eingangsdaten strikt validieren und typisieren
    $tmp            = explode('_', $productId);
    $cleanProductId = (int)$tmp[0];
    $options        = isset($tmp[1]) ? trim($tmp[1]) : '';
    $newStock       = (int)$AvailableStock; // Direkter Cast zu Integer

    // Prüfen, ob das Produkt überhaupt existiert
    $exist_query = xtc_db_query("SELECT products_id FROM " . TABLE_PRODUCTS . " WHERE products_id = " . $cleanProductId);
    if (xtc_db_num_rows($exist_query) === 0) {
        throw new ProductNotFoundException();
    }

    if (!empty($options)) {
        // 2. BB_STOCK Tabelle aktualisieren (Spezifische Kombination)
        $safeOptions = xtc_db_input($options);
        $sql_data = [
            'products_id' => $cleanProductId,
            'billbee_attributes' => $safeOptions,
            'billbee_attributes_quantity' => $newStock
        ];

        $check_query = xtc_db_query("SELECT billbee_stock_id FROM " . TABLE_BB_STOCK . " 
                                    WHERE products_id = " . $cleanProductId . " 
                                    AND billbee_attributes = '" . $safeOptions . "'");

        if (xtc_db_num_rows($check_query) > 0) {
            xtc_db_perform(TABLE_BB_STOCK, $sql_data, 'update', "products_id = $cleanProductId AND billbee_attributes = '$safeOptions'");
        } else {
            xtc_db_perform(TABLE_BB_STOCK, $sql_data);
        }

        // 3. Alle beteiligten Attribute verarbeiten
        $attributes = explode('x', $options);
        foreach ($attributes as $attribute) {
            $parts = explode('-', $attribute);
            if (count($parts) !== 2) continue;

            $optionsId       = (int)$parts[0];
            $optionsValuesId = (int)$parts[1];
            $safeAttr        = xtc_db_input($attribute);
            // Escape LIKE Wildcards in Attribut-String
            $safeLikeAttr    = str_replace(['%', '_'], ['\\%', '\\_'], $safeAttr);

            /** 
             * PRÄZISE SUCHE: Verhindert, dass ID '1' bei ID '11' matcht.
             * Wir prüfen, ob das Attribut exakt der String ist oder von 'x' umschlossen wird.
             */
            $whereClause = "products_id = $cleanProductId AND (
                billbee_attributes = '$safeAttr' OR 
                billbee_attributes LIKE '{$safeLikeAttr}x%' OR 
                billbee_attributes LIKE '%x{$safeLikeAttr}x%' OR 
                billbee_attributes LIKE '%x{$safeLikeAttr}'
            )";

            $attr_query = xtc_db_query("SELECT SUM(billbee_attributes_quantity) AS options_qty 
                                         FROM " . TABLE_BB_STOCK . " 
                                         WHERE " . $whereClause);
            
            $attr_qty       = xtc_db_fetch_array($attr_query);
            $totalAttrStock = (int)($attr_qty['options_qty'] ?? 0);

            // Modified-Standardtabelle für Attribut-Bestand aktualisieren
            xtc_db_query("UPDATE " . TABLE_PRODUCTS_ATTRIBUTES . " 
                         SET attributes_stock = $totalAttrStock 
                         WHERE products_id = $cleanProductId 
                         AND options_id = $optionsId 
                         AND options_values_id = $optionsValuesId");
        }

        // 4. Hauptbestand des Produkts aktualisieren (Summe aller Varianten)
        $total_query = xtc_db_query("SELECT SUM(attributes_stock) AS qty FROM " . TABLE_PRODUCTS_ATTRIBUTES . " WHERE products_id = $cleanProductId");
        $total = xtc_db_fetch_array($total_query);
        $totalQuantity = isset($total['qty']) && $total['qty'] !== null ? (int)$total['qty'] : 0;
        
        xtc_db_query("UPDATE " . TABLE_PRODUCTS . " 
                     SET products_quantity = $totalQuantity 
                     WHERE products_id = $cleanProductId");

    } else {
        // Einfaches Produkt ohne Varianten
        xtc_db_query("UPDATE " . TABLE_PRODUCTS . " 
                     SET products_quantity = $newStock 
                     WHERE products_id = $cleanProductId");
    }
	}
}