<?php
/**
 * StockSyncRepository - Verwaltung eingehender Billbee-Bestandsupdates.
 *
 * Variantenbestände werden direkt in die gemeinsame Tabelle bx_product_variants
 * geschrieben. Anschließend werden die Attributsummen und der aggregierte
 * Produktbestand aktualisiert.
 *
 * @package   Billbee\ModifiedShopApi\Repository
 * @author    Axel Benkert - BX Coding
 * @copyright 2024-2026 BX Coding
 * @license   Proprietary
 * @version   2.0.0
 * @link      https://www.bx-coding.de/
 * @link      https://www.billbee.de/
 * @link      https://www.billbee.de/
 */
namespace Billbee\ModifiedShopApi\Repository;

use Billbee\CustomShopApi\Exception\ProductNotFoundException;
use Billbee\CustomShopApi\Repository\StockSyncRepositoryInterface;

class StockSyncRepository implements StockSyncRepositoryInterface {
    private function buildAttributesHash(string $options): string {
        $attributes = array();

        if ($options !== '') {
            foreach (explode('x', $options) as $attributePair) {
                $parts = explode('-', trim($attributePair));
                if (count($parts) !== 2) {
                    continue;
                }

                $optionsId = (int)$parts[0];
                $optionsValueId = (int)$parts[1];
                if ($optionsId <= 0 || $optionsValueId <= 0) {
                    continue;
                }

                $attributes[$optionsId] = $optionsValueId;
            }
        }

        ksort($attributes);

        return md5(serialize($attributes));
    }

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
        $attributesHash = $this->buildAttributesHash($options);
        $sql_data = [
            'products_id' => $cleanProductId,
            'attributes_hash' => $attributesHash,
            'products_stock_attributes' => $safeOptions,
            'products_stock_quantity' => $newStock
        ];

        $check_query = xtc_db_query("SELECT identifier_id FROM " . TABLE_BB_STOCK . " 
                                    WHERE products_id = " . $cleanProductId . " 
                                    AND attributes_hash = '" . xtc_db_input($attributesHash) . "'");

        if (xtc_db_num_rows($check_query) > 0) {
            xtc_db_perform(TABLE_BB_STOCK, $sql_data, 'update', "products_id = $cleanProductId AND attributes_hash = '" . xtc_db_input($attributesHash) . "'");
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

             * Wir prüfen, ob das Attribut exakt der String ist oder von 'x' umschlossen wird.
             */
            $whereClause = "products_id = $cleanProductId AND (
               	products_stock_attributes  = '$safeAttr' OR 
                products_stock_attributes LIKE '{$safeLikeAttr}x%' OR 
                products_stock_attributes LIKE '%x{$safeLikeAttr}x%' OR 
                products_stock_attributes LIKE '%x{$safeLikeAttr}'
            )";

            $attr_query = xtc_db_query("SELECT SUM(products_stock_quantity) AS options_qty 
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
        // Nutze TABLE_BB_STOCK statt TABLE_PRODUCTS_ATTRIBUTES um korrekte Summe zu berechnen.
        // TABLE_PRODUCTS_ATTRIBUTES würde bei Multi-Attribut-Produkten Doppelzählungen verursachen,
        // da dort pro Attribut-Wert eine Zeile existiert (nicht pro Varianten-Kombination).
        $total_query   = xtc_db_query("SELECT SUM(products_stock_quantity) AS qty FROM " . TABLE_BB_STOCK . " WHERE products_id = $cleanProductId");
        $total         = xtc_db_fetch_array($total_query);
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