<?php
/**
 * ProductsRepository - Verwaltung von Produkten für die Billbee API Integration.
 *
 * Diese Klasse implementiert die ProductsRepositoryInterface und stellt Methoden
 * zum Abrufen und Verarbeiten von Produkten und Varianten aus dem Modified Shop
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

use Billbee\CustomShopApi\Exception\ProductNotFoundException;
use Billbee\CustomShopApi\Model\PagedData;
use Billbee\CustomShopApi\Model\Product;
use Billbee\CustomShopApi\Model\ProductImage;
use Billbee\CustomShopApi\Repository\ProductsRepositoryInterface;
use bxCartesianBuilder;

class ProductsRepository implements ProductsRepositoryInterface {

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

	private function prepareVariantExportEntries(int $productId): void {
		$getProducts = new bxCartesianBuilder($productId);
		$uniqueArr   = $getProducts->getCartesian();
		$hasVariants = false;

		foreach ($uniqueArr as $uniqueId) {
			$tmp = explode('_', $uniqueId, 2);
			$options = isset($tmp[1]) ? trim($tmp[1]) : '';
			if ($options === '') {
				continue;
			}

			$hasVariants    = true;
			$attributesHash = $this->buildAttributesHash($options);
			$existsQuery    = xtc_db_query("SELECT identifier_id FROM " . TABLE_BB_STOCK . " WHERE products_id = '" . (int)$productId . "' AND attributes_hash = '" . xtc_db_input($attributesHash) . "' LIMIT 1");

			if (xtc_db_num_rows($existsQuery) > 0) {
				xtc_db_query("UPDATE " . TABLE_BB_STOCK . " SET products_stock_attributes = '" . xtc_db_input($options) . "', bx_exported = 'n' WHERE products_id = '" . (int)$productId . "' AND attributes_hash = '" . xtc_db_input($attributesHash) . "'");
				continue;
			}

			xtc_db_query("INSERT INTO " . TABLE_BB_STOCK . " (products_id, products_stock_attributes, attributes_hash, bx_exported) VALUES ('" . (int)$productId . "', '" . xtc_db_input($options) . "', '" . xtc_db_input($attributesHash) . "', 'n')");
		}

		if ($hasVariants) {
			xtc_db_query("UPDATE " . TABLE_PRODUCTS . " SET bx_exported = 'y' WHERE products_id = '" . (int)$productId . "'");
		}
	}

	public function getProducts($page, $pageSize): PagedData {
		$safePage = max((int)$page, 1);
		$safePageSize = max((int)$pageSize, 1);
		$offset = ($safePage - 1) * $safePageSize;

		$productsIdsQuery = xtc_db_query("SELECT products_id AS id FROM " . TABLE_PRODUCTS . " WHERE bx_exported = 'n' ORDER BY products_id");
		if (0 < xtc_db_num_rows($productsIdsQuery)) {
			while ($products = xtc_db_fetch_array($productsIdsQuery)) {
				$this->prepareVariantExportEntries((int)$products['id']);
			}
		}

		$totalSimpleQuery = xtc_db_query("SELECT COUNT(*) AS total FROM " . TABLE_PRODUCTS . " p WHERE p.bx_exported = 'n' AND NOT EXISTS (SELECT 1 FROM " . TABLE_PRODUCTS_ATTRIBUTES . " pa WHERE pa.products_id = p.products_id)");
		$totalSimple = xtc_db_fetch_array($totalSimpleQuery);
		$totalVariantQuery = xtc_db_query("SELECT COUNT(*) AS total FROM " . TABLE_BB_STOCK . " WHERE bx_exported = 'n'");
		$totalVariant = xtc_db_fetch_array($totalVariantQuery);
		$totalRows = (int)$totalSimple['total'] + (int)$totalVariant['total'];

		$productsQuery = xtc_db_query("SELECT id, attributes FROM (SELECT p.products_id AS id, '' AS attributes FROM " . TABLE_PRODUCTS . " p WHERE p.bx_exported = 'n' AND NOT EXISTS (SELECT 1 FROM " . TABLE_PRODUCTS_ATTRIBUTES . " pa WHERE pa.products_id = p.products_id) UNION ALL SELECT products_id AS id, COALESCE(products_stock_attributes, '') AS attributes FROM " . TABLE_BB_STOCK . " WHERE bx_exported = 'n') billbee_export_queue ORDER BY id, attributes ASC LIMIT " . max($offset, 0) . ", " . $safePageSize);
		if (0 >= xtc_db_num_rows($productsQuery)) {
			throw new ProductNotFoundException();
		}

		$myProducts = array();
		$i = 0;
		while ($products = xtc_db_fetch_array($productsQuery)) {
			$billbeeId = str_pad($products['id'], 4, '0', STR_PAD_LEFT);
			if ($products['attributes'] !== '') {
				$billbeeId .= '_' . $products['attributes'];
			}

			$myProducts[$i] = $this->getProduct($billbeeId);
			if ($products['attributes'] !== '') {
				xtc_db_query("UPDATE " . TABLE_BB_STOCK . " SET bx_exported = 'y' WHERE products_id = '" . (int)$products['id'] . "' AND products_stock_attributes = '" . xtc_db_input($products['attributes']) . "'");
			} else {
				xtc_db_query("UPDATE " . TABLE_PRODUCTS . " SET bx_exported = 'y' WHERE products_id = '" . (int)$products['id'] . "'");
			}
			$i++;
		}

		return new PagedData($myProducts, $totalRows);
	}

	public function getProduct($billbeeId): Product {
		$billbeeId = xtc_db_input($billbeeId);
		$tmp = explode('_', $billbeeId, 2);
		$productId = (int)$tmp[0];
		$options = isset($tmp[1]) ? xtc_db_input(trim($tmp[1])) : '';
		$title = '';

		if (xtc_not_null($options)) {
			$attributes = explode('x', $options);
			$options_name = array();
			$value_name = array();
			foreach ($attributes as $attribute) {
				list($optionsId, $optionsValuesId) = explode('-', $attribute);
				$optionsNameQuery = xtc_db_query("SELECT products_options_name AS oname FROM products_options WHERE products_options_id = '" . (int)$optionsId . "' AND language_id = '" . (int)BILLBEE_LANGUAGE_ID . "'");
				if (0 < xtc_db_num_rows($optionsNameQuery)) {
					$options_name = xtc_db_fetch_array($optionsNameQuery);
				}
				$valueNameQuery = xtc_db_query("SELECT products_options_values_name AS vname FROM products_options_values WHERE products_options_values_id = '" . (int)$optionsValuesId . "' AND language_id = '" . (int)BILLBEE_LANGUAGE_ID . "'");
				if (0 < xtc_db_num_rows($valueNameQuery)) {
					$value_name = xtc_db_fetch_array($valueNameQuery);
				}
				if (!empty($options_name['oname']) && !empty($value_name['vname'])) {
					$title .= ', ' . $options_name['oname'] . ': ' . $value_name['vname'];
				}
			}
		}

		$productQueryRaw = "SELECT p.products_id, p.products_ean, p.products_quantity, p.products_shippingtime, p.products_model, p.products_image, p.products_price, p.products_discount_allowed, p.products_date_added, p.products_last_modified, p.products_date_available, p.products_weight, p.products_status, p.products_tax_class_id, p.manufacturers_id, p.products_manufacturers_model, p.products_vpe, p.products_vpe_status, p.products_vpe_value, pd.products_name, pd.products_description, pd.products_short_description FROM " . TABLE_PRODUCTS . " p INNER JOIN " . TABLE_PRODUCTS_DESCRIPTION . " pd ON pd.products_id = p.products_id WHERE p.products_id = '" . $productId . "' AND pd.language_id = '" . (int)BILLBEE_LANGUAGE_ID . "'";
		$productsQuery = xtc_db_query($productQueryRaw);
		if (0 >= xtc_db_num_rows($productsQuery)) {
			throw new ProductNotFoundException();
		}

		$products = xtc_db_fetch_array($productsQuery);
		$myProducts = new Product();
		$myProducts->setId($billbeeId);
		$variantSku = $products['products_model'];
		$variantEan = $products['products_ean'];
		$variantQty = (int)$products['products_quantity'];

		if (xtc_not_null($options)) {
			$variantQuery = xtc_db_query("SELECT products_sku, products_ean, products_stock_quantity FROM " . TABLE_BB_STOCK . " WHERE products_id = '" . (int)$productId . "' AND products_stock_attributes = '" . xtc_db_input($options) . "' LIMIT 1");
			if (0 < xtc_db_num_rows($variantQuery)) {
				$variantData = xtc_db_fetch_array($variantQuery);
				if (!empty($variantData['products_sku'])) {
					$variantSku = $variantData['products_sku'];
				}
				if (!empty($variantData['products_ean'])) {
					$variantEan = $variantData['products_ean'];
				}
				$variantQty = (int)$variantData['products_stock_quantity'];
			} else {
				$attributes = explode('x', $options);
				if (!empty($attributes[0])) {
					list($optionsId, $optionsValuesId) = explode('-', $attributes[0]);
					$fallbackQuery = xtc_db_query("SELECT attributes_model, attributes_ean FROM " . TABLE_PRODUCTS_ATTRIBUTES . " WHERE products_id = '" . $productId . "' AND options_id = '" . (int)$optionsId . "' AND options_values_id = '" . (int)$optionsValuesId . "'");
					if (0 < xtc_db_num_rows($fallbackQuery)) {
						$fallbackData = xtc_db_fetch_array($fallbackQuery);
						if (!empty($fallbackData['attributes_model'])) {
							$variantSku = $fallbackData['attributes_model'];
						}
						if (!empty($fallbackData['attributes_ean'])) {
							$variantEan = $fallbackData['attributes_ean'];
						}
					}
				}
			}
		}

		$myProducts->setEan($variantEan);
		$myProducts->setShortDescription(trim(preg_replace('/\s+/', ' ', str_replace(array("\r", "\n", "\t", "\s"), ' ', strip_tags($products['products_short_description'])))));
		$myProducts->setDescription(trim(preg_replace('/\s+/', ' ', str_replace(array("\r", "\n", "\t", "\s"), ' ', strip_tags($products['products_description'])))));

		$variantPrice = (float)$products['products_price'];
		if (xtc_not_null($options)) {
			$attributes = explode('x', $options);
			foreach ($attributes as $attribute) {
				list($optionsId, $optionsValuesId) = explode('-', $attribute);
				$priceQuery = xtc_db_query("SELECT price_prefix, options_values_price FROM " . TABLE_PRODUCTS_ATTRIBUTES . " WHERE products_id = '" . $productId . "' AND options_id = '" . (int)$optionsId . "' AND options_values_id = '" . (int)$optionsValuesId . "'");
				if (0 < xtc_db_num_rows($priceQuery)) {
					$priceData = xtc_db_fetch_array($priceQuery);
					$optionPrice = (float)$priceData['options_values_price'];
					if ($priceData['price_prefix'] === '+') {
						$variantPrice += $optionPrice;
					} elseif ($priceData['price_prefix'] === '-') {
						$variantPrice -= $optionPrice;
					}
				}
			}
		}

		$myProducts->setTitle(htmlspecialchars($products['products_name'] . $title));
		$myProducts->setPrice($variantPrice);
		$myProducts->setQuantity(xtc_not_null($options) ? $variantQty : $products['products_quantity']);
		$myProducts->setSku($variantSku);
		$myProducts->setWeightInKg($products['products_weight']);

		$manufacturerQuery = xtc_db_query("SELECT manufacturers_name FROM manufacturers WHERE manufacturers_id = '" . (int)$products['manufacturers_id'] . "'");
		$manufacturer = xtc_db_fetch_array($manufacturerQuery);
		$myProducts->setManufacturer(isset($manufacturer['manufacturers_name']) && !empty($manufacturer['manufacturers_name']) ? $manufacturer['manufacturers_name'] : '');

		$mainImageQuery = xtc_db_query("SELECT products_image FROM products WHERE products_id = '" . (int)$products['products_id'] . "'");
		$mainImage = xtc_db_fetch_array($mainImageQuery);
		$server = (defined('HTTPS_SERVER') && !empty(HTTPS_SERVER)) ? HTTPS_SERVER : HTTP_SERVER;
		$myProducts->images = array(0 => new ProductImage());
		$myProducts->images[0]->setIsDefault(true);
		$myProducts->images[0]->setUrl($server . "/" . DIR_WS_ORIGINAL_IMAGES . $mainImage['products_image']);
		$myProducts->images[0]->setPosition(1);

		$moreImagesQuery = xtc_db_query("SELECT image_name, image_nr FROM products_images WHERE products_id = '" . (int)$products['products_id'] . "' ORDER BY image_nr");
		$p = 1;
		while ($moreImages = xtc_db_fetch_array($moreImagesQuery)) {
			$myProducts->images[$p] = new ProductImage();
			$myProducts->images[$p]->setIsDefault(false);
			$myProducts->images[$p]->setUrl($server . "/" . DIR_WS_ORIGINAL_IMAGES . $moreImages['image_name']);
			$myProducts->images[$p]->setPosition($moreImages['image_nr']);
			$p++;
		}

		return $myProducts;
	}
}
