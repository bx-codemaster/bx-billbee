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
     * Liefert den Tabellennamen der Variantentabelle.
     *
     * @return string
     */
    private function getStockmanagerTable(): string {
        return defined('TABLE_STOCKMANAGER_PRO') ? TABLE_STOCKMANAGER_PRO : 'bx_product_variants';
    }

    /**
     * Prueft, ob eine Tabelle existiert.
     *
     * @param string $tableName Tabellenname
     *
     * @return bool
     */
    private function hasTable(string $tableName): bool {
        $tableQuery = xtc_db_query("SHOW TABLES LIKE '" . xtc_db_input($tableName) . "'");

        return xtc_db_num_rows($tableQuery) > 0;
    }

    /**
     * Prueft, ob die Variantentabelle verfuegbar ist.
     *
     * @return bool
     */
    private function hasStockmanagerTable(): bool {
        return $this->hasTable($this->getStockmanagerTable());
    }

    /**
     * Konvertiert einen Attribut-String in ein sortiertes Attribut-Array.
     *
     * @param string $options Variantenstring
     *
     * @return array<int, int>
     */
    private function parseOptionsString(string $options): array {
        $attributes = [];

        if ($options === '') {
            return $attributes;
        }

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

        ksort($attributes);

        return $attributes;
    }

    /**
     * Erzeugt den sortierten Attribut-String fuer die Variantentabelle.
     *
     * @param array<int, int> $attributes Sortierte Attribute
     *
     * @return string
     */
    private function buildOptionsString(array $attributes): string {
        $parts = [];

        foreach ($attributes as $optionsId => $optionsValueId) {
            $parts[] = str_pad((string)$optionsId, 4, '0', STR_PAD_LEFT) . '-' . str_pad((string)$optionsValueId, 4, '0', STR_PAD_LEFT);
        }

        return implode('x', $parts);
    }

    /**
     * Normalisiert einen Variantenstring auf das Stockmanager-Format.
     *
     * @param string $options Variantenstring
     *
     * @return string
     */
    private function normalizeOptionsString(string $options): string {
        return $this->buildOptionsString($this->parseOptionsString($options));
    }

    /**
     * Erzeugt den Hash fuer eine Attributkombination.
     *
     * @param array<int, int> $attributes Sortierte Attribute
     *
     * @return string
     */
    private function generateAttributesHash(array $attributes): string {
        if (empty($attributes)) {
            return '';
        }

        ksort($attributes);

        return md5(serialize($attributes));
    }

    /**
     * Liest vorhandene SKU/EAN-Werte fuer eine Variante.
     *
     * @param int    $productId         Produkt-ID
     * @param string $normalizedOptions Normalisierter Variantenstring
     * @param string $attributesHash    Hash der Kombination
     *
     * @return array{products_sku: string, products_ean: string}
     */
    private function getVariantIdentifiers(int $productId, string $normalizedOptions, string $attributesHash): array {
        $identifiers = [
            'products_sku' => '',
            'products_ean' => ''
        ];

        $tableName = $this->getStockmanagerTable();
        $variantQuery = xtc_db_query("SELECT products_sku, products_ean
                                      FROM " . $tableName . "
                                     WHERE products_id = " . $productId . "
                                       AND attributes_hash = '" . xtc_db_input($attributesHash) . "'
                                     LIMIT 1");

        if (xtc_db_num_rows($variantQuery) > 0) {
            $variantRow = xtc_db_fetch_array($variantQuery);
            $identifiers['products_sku'] = (string)($variantRow['products_sku'] ?? '');
            $identifiers['products_ean'] = (string)($variantRow['products_ean'] ?? '');
            return $identifiers;
        }

        $billbeeQuery = xtc_db_query("SELECT products_sku, products_ean
                                      FROM " . TABLE_BB_STOCK . "
                                     WHERE products_id = " . $productId . "
                                       AND billbee_attributes = '" . xtc_db_input($normalizedOptions) . "'
                                     LIMIT 1");

        if (xtc_db_num_rows($billbeeQuery) > 0) {
            $billbeeRow = xtc_db_fetch_array($billbeeQuery);
            $identifiers['products_sku'] = (string)($billbeeRow['products_sku'] ?? '');
            $identifiers['products_ean'] = (string)($billbeeRow['products_ean'] ?? '');
        }

        return $identifiers;
    }

    /**
     * Schreibt Variantenbestaende in die Fuehrungstabelle bx_product_variants.
     *
     * @param int    $productId         Produkt-ID
     * @param string $normalizedOptions Normalisierter Variantenstring
     * @param int    $newStock          Neuer Bestand
     *
     * @return void
     */
    private function upsertVariantStock(int $productId, string $normalizedOptions, int $newStock): void {
        $attributes     = $this->parseOptionsString($normalizedOptions);
        $attributesHash = $this->generateAttributesHash($attributes);
        $tableName      = $this->getStockmanagerTable();

        $existsQuery = xtc_db_query("SELECT identifier_id
                                     FROM " . $tableName . "
                                    WHERE products_id = " . $productId . "
                                      AND attributes_hash = '" . xtc_db_input($attributesHash) . "'");

        if (xtc_db_num_rows($existsQuery) > 0) {
            xtc_db_query("UPDATE " . $tableName . "
                         SET products_stock_attributes = '" . xtc_db_input($normalizedOptions) . "',
                             products_stock_quantity = " . $newStock . "
                       WHERE products_id = " . $productId . "
                         AND attributes_hash = '" . xtc_db_input($attributesHash) . "'");
            return;
        }

        $identifiers = $this->getVariantIdentifiers($productId, $normalizedOptions, $attributesHash);

        xtc_db_query("INSERT INTO " . $tableName . " (
                        products_id,
                        products_stock_attributes,
                        attributes_hash,
                        products_stock_quantity,
                        products_sku,
                        products_ean
                    ) VALUES (
                        " . $productId . ",
                        '" . xtc_db_input($normalizedOptions) . "',
                        '" . xtc_db_input($attributesHash) . "',
                        " . $newStock . ",
                        '" . xtc_db_input($identifiers['products_sku']) . "',
                        '" . xtc_db_input($identifiers['products_ean']) . "'
                    )");
    }

    /**
     * Aktualisiert Attribute-Summen aus einer frei wählbaren Variantenquelle.
     *
     * @param int    $productId     Produkt-ID
     * @param string $options       Normalisierter Variantenstring
     * @param string $tableName     Quelltabelle
     * @param string $attrField     Attribut-Feldname
     * @param string $quantityField Mengen-Feldname
     *
     * @return void
     */
    private function syncAttributeSums(int $productId, string $options, string $tableName, string $attrField, string $quantityField): void {
        $attributes = explode('x', $options);

        foreach ($attributes as $attribute) {
            $parts = explode('-', $attribute);
            if (count($parts) !== 2) {
                continue;
            }

            $optionsId = (int)$parts[0];
            $optionsValuesId = (int)$parts[1];
            $safeAttr = xtc_db_input($attribute);
            $safeLikeAttr = str_replace(['%', '_'], ['\\%', '\\_'], $safeAttr);

            $whereClause = "products_id = " . $productId . " AND ("
                . $attrField . " = '" . $safeAttr . "' OR "
                . $attrField . " LIKE '" . $safeLikeAttr . "x%' OR "
                . $attrField . " LIKE '%x" . $safeLikeAttr . "x%' OR "
                . $attrField . " LIKE '%x" . $safeLikeAttr . "')";

            $sumQuery = xtc_db_query("SELECT IFNULL(SUM(" . $quantityField . "), 0) AS options_qty
                                      FROM " . $tableName . "
                                     WHERE " . $whereClause);
            $sumRow = xtc_db_fetch_array($sumQuery);
            $totalAttrStock = (int)($sumRow['options_qty'] ?? 0);

            xtc_db_query("UPDATE " . TABLE_PRODUCTS_ATTRIBUTES . "
                         SET attributes_stock = " . $totalAttrStock . "
                       WHERE products_id = " . $productId . "
                         AND options_id = " . $optionsId . "
                         AND options_values_id = " . $optionsValuesId);
        }
    }

    /**
     * Aktualisiert den Hauptbestand aus der Variantentabelle.
     *
     * @param int $productId Produkt-ID
     *
     * @return void
     */
    private function syncProductQuantityFromVariants(int $productId): void {
        $tableName = $this->getStockmanagerTable();
        $totalQuery = xtc_db_query("SELECT IFNULL(SUM(products_stock_quantity), 0) AS qty
                                     FROM " . $tableName . "
                                    WHERE products_id = " . $productId . "
                                      AND products_stock_quantity > 0");
        $total = xtc_db_fetch_array($totalQuery);
        $totalQuantity = isset($total['qty']) && $total['qty'] !== null ? (int)$total['qty'] : 0;

        xtc_db_query("UPDATE " . TABLE_PRODUCTS . "
                     SET products_quantity = " . $totalQuantity . "
                   WHERE products_id = " . $productId);
    }

    /**
     * Behaelt das bisherige Schreiben in bx_billbee_stock fuer Shops ohne Stockmanager bei.
     *
     * @param int    $productId         Produkt-ID
     * @param string $normalizedOptions Normalisierter Variantenstring
     * @param int    $newStock          Neuer Bestand
     *
     * @return void
     */
    private function upsertBillbeeStock(int $productId, string $normalizedOptions, int $newStock): void {
        $safeOptions = xtc_db_input($normalizedOptions);
        $sqlData = [
            'products_id' => $productId,
            'billbee_attributes' => $safeOptions,
            'billbee_attributes_quantity' => $newStock
        ];

        $checkQuery = xtc_db_query("SELECT billbee_stock_id FROM " . TABLE_BB_STOCK . "
                                  WHERE products_id = " . $productId . "
                                    AND billbee_attributes = '" . $safeOptions . "'");

        if (xtc_db_num_rows($checkQuery) > 0) {
            xtc_db_perform(TABLE_BB_STOCK, $sqlData, 'update', "products_id = " . $productId . " AND billbee_attributes = '" . $safeOptions . "'");
            return;
        }

        xtc_db_perform(TABLE_BB_STOCK, $sqlData);
    }

    /**
     * Aktualisiert den Hauptbestand aus den Attributsummen.
     *
     * @param int $productId Produkt-ID
     *
     * @return void
     */
    private function syncProductQuantityFromAttributes(int $productId): void {
        $totalQuery = xtc_db_query("SELECT SUM(attributes_stock) AS qty FROM " . TABLE_PRODUCTS_ATTRIBUTES . " WHERE products_id = " . $productId);
        $total = xtc_db_fetch_array($totalQuery);
        $totalQuantity = isset($total['qty']) && $total['qty'] !== null ? (int)$total['qty'] : 0;

        xtc_db_query("UPDATE " . TABLE_PRODUCTS . "
                     SET products_quantity = " . $totalQuantity . "
                   WHERE products_id = " . $productId);
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
            $normalizedOptions = $this->normalizeOptionsString($options);

            if ($this->hasStockmanagerTable()) {
                $this->upsertVariantStock($cleanProductId, $normalizedOptions, $newStock);
                $this->syncAttributeSums($cleanProductId, $normalizedOptions, $this->getStockmanagerTable(), 'products_stock_attributes', 'products_stock_quantity');
                $this->syncProductQuantityFromVariants($cleanProductId);
                return;
            }

            $this->upsertBillbeeStock($cleanProductId, $normalizedOptions, $newStock);
            $this->syncAttributeSums($cleanProductId, $normalizedOptions, TABLE_BB_STOCK, 'billbee_attributes', 'billbee_attributes_quantity');
            $this->syncProductQuantityFromAttributes($cleanProductId);

        } else {
            // Einfaches Produkt ohne Varianten
            xtc_db_query("UPDATE " . TABLE_PRODUCTS . " 
                        SET products_quantity = $newStock 
                        WHERE products_id = $cleanProductId");
        }
	}
}