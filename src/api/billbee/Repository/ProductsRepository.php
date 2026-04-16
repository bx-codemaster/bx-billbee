<?php
/**
 * ProductsRepository - Verwaltung von Produkten für die Billbee API Integration
 *
 * Diese Klasse implementiert die ProductsRepositoryInterface und stellt Methoden
 * zum Abrufen und Verarbeiten von Produkten und Varianten aus dem Modified Shop
 * für die Übertragung an Billbee bereit. Unterstützt kartesische Produkt-Varianten
 * mit SKU/EAN-Zuordnung.
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

use Billbee\CustomShopApi\Repository\ProductsRepositoryInterface;
use Billbee\CustomShopApi\Exception\NotImplementedException;
use Billbee\CustomShopApi\Exception\ProductNotFoundException;
use Billbee\CustomShopApi\Model\PagedData;
use Billbee\CustomShopApi\Model\Product;
use Billbee\CustomShopApi\Model\ProductImage;
use splitPageResults;
use bxCartesianBuilder;

class ProductsRepository implements ProductsRepositoryInterface {
	
	/**
	 * Ruft eine paginierte Liste von Produkten und Varianten ab
	 *
	 * Exportiert zunächst neue Produkte in die Billbee-Stock-Tabelle durch
	 * Generierung aller Varianten-Kombinationen (kartesisches Produkt).
	 * Lädt dann die noch nicht exportierten Produkte/Varianten aus TABLE_BB_STOCK.
	 *
	 * @param int $page     Die Seitennummer (1-basiert)
	 * @param int $pageSize Anzahl der Produkte pro Seite
	 * 
	 * @return PagedData Objekt mit Produkt-Array und Gesamtanzahl
	 * 
	 * @throws ProductNotFoundException Wenn keine Produkte gefunden wurden
	 */
  public function getProducts($page, $pageSize): PagedData {
		// Eingaben sanitieren
		$safePage     = (int)$page;
		$safePageSize = (int)$pageSize;
		$offset       = ($safePage - 1) * $safePageSize;

		// Falls es noch Produkte gibt, die noch nicht in die TABLE_BB_STOCK übertragen wurden
		$productsIdsQuery = xtc_db_query("SELECT products_id AS id 
																							FROM ".TABLE_PRODUCTS." 
																					   WHERE bx_exported = 'n' 
																					ORDER BY products_id 
																					   LIMIT ".$offset.", ".$safePageSize);

		if( 0 < xtc_db_num_rows($productsIdsQuery) ) {
			while($products = xtc_db_fetch_array($productsIdsQuery)) {
				$getProducts = new bxCartesianBuilder($products["id"]);
				$uniqueArr   = $getProducts->getCartesian();
			
				foreach($uniqueArr as $uniqueId) {
					$tmp    = explode("_", $uniqueId);
					$tmp[1] = !empty($tmp[1]) ? $tmp[1] : '';
					$data_array = array("products_id" => $tmp[0], "billbee_attributes" => $tmp[1]);
					xtc_db_perform(TABLE_BB_STOCK, $data_array);
				}
			
				$data_array = array('bx_exported' => 'y');
				xtc_db_perform(TABLE_PRODUCTS, $data_array, 'update', "products_id = '".xtc_db_input($products["id"])."'");
			}
		}

		// Abfrage der Produkte aus TABLE_BB_STOCK
		$myProducts = array();
		$products_query_raw = "SELECT products_id AS id, 
																	billbee_attributes AS attributes 
														 FROM ".TABLE_BB_STOCK." 
														WHERE bx_exported = 'n' 
											   ORDER BY products_id, billbee_attributes ASC";

	$products_split = new splitPageResults( $products_query_raw, $safePage, $safePageSize );
    $products_query = xtc_db_query( $products_split->sql_query );
		
		if( 0 < xtc_db_num_rows($products_query) ) {
			$i = 0;
			while ( $products = xtc_db_fetch_array( $products_query ) ) {
				$billbeeId      = str_pad($products["id"],4,'0',STR_PAD_LEFT).'_'.$products["attributes"];
	     		$myProducts[$i] = $this->getProduct($billbeeId);

				$data_array = array("bx_exported" => "y");
				xtc_db_perform(TABLE_BB_STOCK, $data_array, "update", "products_id = '".xtc_db_input($products["id"])."' AND billbee_attributes = '".xtc_db_input($products["attributes"])."'");
				$i++;
			}
			return new PagedData( $myProducts, $products_split->number_of_rows );
		} else {
			throw new ProductNotFoundException();
		}
	}
	
	/**
	 * Ruft ein einzelnes Produkt oder eine Variante anhand der Billbee-ID ab
	 *
	 * Die Billbee-ID hat das Format: "XXXX_YYYY-ZZZZ" wobei:
	 * - XXXX = products_id (4-stellig, mit führenden Nullen)
	 * - YYYY-ZZZZ = Attribut-Kombination (options_id-options_values_id)
	 * - Multiple Attribute werden mit 'x' verbunden: "0001_0002-0005x0003-0007"
	 *
	 * Lädt Produktdaten, variantenspezifische SKU/EAN, berechnet Variantenpreise
	 * durch Addition/Subtraktion der Attribut-Aufpreise und lädt alle Produktbilder.
	 *
	 * @param string $billbeeId Die Billbee Produkt-/Varianten-ID
	 * 
	 * @return Product Das vollständig befüllte Product-Objekt mit allen Varianten-Infos
	 * 
	 * @throws ProductNotFoundException Wenn das Produkt nicht gefunden wurde
	 */
	public function getProduct($billbeeId): Product {
		$billbeeId = xtc_db_input($billbeeId);
		$tmp       = explode('_', $billbeeId);
		$productId = (int)$tmp[0];
		$options   = isset($tmp[1]) ? xtc_db_input(trim($tmp[1])) : '';
		$title     = '';

		if(xtc_not_null($options)) {
			$attributes = explode('x', $options);
			$options_name = array();
			$value_name   = array();
		
			foreach($attributes as $attribute) {
				list($optionsId, $optionsValuesId) = explode('-', $attribute);
				$options_name_query = xtc_db_query("SELECT products_options_name AS oname FROM products_options WHERE products_options_id = '".(int)$optionsId."' AND language_id = '".(int)BILLBEE_LANGUAGE_ID."'");
				if(0 < xtc_db_num_rows($options_name_query)) {
					$options_name = xtc_db_fetch_array($options_name_query);
				}
				$value_name_query	= xtc_db_query("SELECT products_options_values_name AS vname FROM products_options_values WHERE products_options_values_id = '".(int)$optionsValuesId."' AND language_id = '".(int)BILLBEE_LANGUAGE_ID."'");
				if(0 < xtc_db_num_rows($value_name_query)) {
					$value_name = xtc_db_fetch_array($value_name_query);
				}
				if( !empty($options_name["oname"]) && !empty($value_name["vname"]) ) {
					$title .= ', '.$options_name["oname"].': '.$value_name["vname"];
				}
			}
		
		}

		$myProducts = new Product();

		$product_query_raw = "SELECT p.products_id, 
																p.products_ean, 
																p.products_quantity, 
																p.products_shippingtime, 
																p.products_model, 
																p.products_image, 
																p.products_price, 
																p.products_discount_allowed, 
																p.products_date_added, 
																p.products_last_modified, 
																p.products_date_available, 
																p.products_weight, 
																p.products_status, 
																p.products_tax_class_id, 
																p.manufacturers_id, 
																p.products_manufacturers_model, 
																p.products_vpe, 
																p.products_vpe_status,
																p.products_vpe_value, 
																pd.products_name, 
																pd.products_description,
																pd.products_short_description
														FROM ".TABLE_PRODUCTS." p
											INNER JOIN ".TABLE_PRODUCTS_DESCRIPTION." pd 
															ON pd.products_id = p.products_id
													WHERE p.products_id = '".$productId."' 
														AND pd.language_id = '".(int)BILLBEE_LANGUAGE_ID."'";
		
		$products_query = xtc_db_query( $product_query_raw );		
		
		if( 0 < xtc_db_num_rows($products_query) ) {
			$products = xtc_db_fetch_array( $products_query );
			$myProducts->setId($billbeeId);
			
			// SKU und EAN für Varianten
			$variantSku = $products['products_model'];
			$variantEan = $products['products_ean'];
			
			if(xtc_not_null($options)) {
				// Versuche variantenspezifische SKU/EAN zu laden
				$attributes = explode('x', $options);
				
				// Nimm die erste Attribut-Kombination für die Abfrage
				if(!empty($attributes[0])) {
					list($optionsId, $optionsValuesId) = explode('-', $attributes[0]);
					
					$variant_query = xtc_db_query("SELECT attributes_model, attributes_ean 
													 FROM ".TABLE_PRODUCTS_ATTRIBUTES." 
													WHERE products_id = '".$productId."' 
													  AND options_id = '".(int)$optionsId."' 
													  AND options_values_id = '".(int)$optionsValuesId."'");
					
					if(0 < xtc_db_num_rows($variant_query)) {
						$variant_data = xtc_db_fetch_array($variant_query);
						
						// Nur überschreiben, wenn variantenspezifische Werte vorhanden
						if(!empty($variant_data['attributes_model'])) {
							$variantSku = $variant_data['attributes_model'];
						}
						if(!empty($variant_data['attributes_ean'])) {
							$variantEan = $variant_data['attributes_ean'];
						}
					}
				}
			}
			
			$myProducts->setEan($variantEan);
			$myProducts->setShortDescription( 
				trim( 
					preg_replace('/\s+/', ' ', 
					  str_replace(array("\r", "\n", "\t", "\s"), ' ',
						  strip_tags($products['products_short_description'])
						)
					)
				)
			);
			$myProducts->setDescription( 
				trim( 
					preg_replace('/\s+/', ' ', 
					  str_replace(array("\r", "\n", "\t", "\s"), ' ',
						  strip_tags($products['products_description'])
						)
					)
				)
			);

			// Preis: Basis-Preis + Varianten-Aufpreis berechnen
			$variantPrice = (float)$products['products_price'];
			
			if(xtc_not_null($options)) {
				$attributes = explode('x', $options);
				
				foreach($attributes as $attribute) {
					list($optionsId, $optionsValuesId) = explode('-', $attribute);
					
					$price_query = xtc_db_query("SELECT price_prefix, options_values_price 
													FROM ".TABLE_PRODUCTS_ATTRIBUTES." 
												   WHERE products_id = '".$productId."' 
												     AND options_id = '".(int)$optionsId."' 
												     AND options_values_id = '".(int)$optionsValuesId."'");
					
					if(0 < xtc_db_num_rows($price_query)) {
						$price_data = xtc_db_fetch_array($price_query);
						$optionPrice = (float)$price_data['options_values_price'];
						
						// Preis-Präfix: + oder -
						if($price_data['price_prefix'] === '+') {
							$variantPrice += $optionPrice;
						} elseif($price_data['price_prefix'] === '-') {
							$variantPrice -= $optionPrice;
						}
					}
				}
			}

      $myProducts->setTitle( htmlspecialchars($products['products_name'].$title) );
			$myProducts->setPrice($variantPrice);
			if(xtc_not_null($options)) {
				$quantity_query = xtc_db_query("SELECT billbee_attributes_quantity AS qty 
								 				  FROM ".TABLE_BB_STOCK." 
												 WHERE products_id = '".$productId."' 
												   AND billbee_attributes = '".$options."'" );
				$quantity = xtc_db_fetch_array( $quantity_query );
				$myProducts->setQuantity(!empty($quantity['qty']) ? $quantity['qty'] : 0);
			} else {
				$myProducts->setQuantity($products['products_quantity']);
			}
			
			// Setze SKU (vorher ermittelt: variantenspezifisch oder Hauptprodukt)
			$myProducts->setSku($variantSku);
			$myProducts->setWeightInKg($products['products_weight']);

			$manufacturer_query = xtc_db_query("SELECT manufacturers_name FROM manufacturers WHERE manufacturers_id = '".(int)$products['manufacturers_id']."'");
			$manufacturer = xtc_db_fetch_array($manufacturer_query);
			if( isset($manufacturer['manufacturers_name']) && !empty($manufacturer['manufacturers_name']) ) {
				$myProducts->setManufacturer($manufacturer['manufacturers_name']);
			} else {
				$myProducts->setManufacturer('');
			}
			
			$mainImageQuery = xtc_db_query("SELECT products_image FROM products WHERE products_id = '".(int)$products['products_id']."'");
			$mainImage = xtc_db_fetch_array($mainImageQuery);
			$server = (defined('HTTPS_SERVER') && !empty(HTTPS_SERVER)) ? HTTPS_SERVER : HTTP_SERVER;
			$myProducts->images = array( 0 => new ProductImage() );
			$myProducts->images[0]->setIsDefault(true);
			$myProducts->images[0]->setUrl($server."/".DIR_WS_ORIGINAL_IMAGES.$mainImage['products_image']);
			$myProducts->images[0]->setPosition(1);
			
			$moreImagesQuery = xtc_db_query("SELECT image_name, image_nr FROM products_images WHERE products_id = '".(int)$products['products_id']."' ORDER BY image_nr");
			$p = 1;
      while ( $moreImages = xtc_db_fetch_array( $moreImagesQuery ) ) {
        $myProducts->images[$p] = new ProductImage();
				$myProducts->images[$p]->setIsDefault(false);
				$myProducts->images[$p]->setUrl($server."/".DIR_WS_ORIGINAL_IMAGES.$moreImages['image_name']);
				$myProducts->images[$p]->setPosition($moreImages['image_nr']);
				$p++;
			}

		return $myProducts;
		} else {
			throw new ProductNotFoundException();
		}
	}
}