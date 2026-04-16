<?php
  /* ---------------------------------------------------------------------
   $Id: lang/german/admin/bx_billbee.php 1000 2023-03-20 13:00:00Z benax $
    _                           
   | |__   ___ _ __   __ ___  __
   | '_ \ / _ \ '_ \ / _ \ \/ /
   | |_) |  __/ | | | (_| |>  < 
   |_.__/ \___|_| |_|\__,_/_/\_\
   xxxxxxxxxxxxxxxxxxxxxxxxxxxxx

   modified eCommerce Shopsoftware
   http://www.modified-shop.org

   Copyright (c) 2009 - 2013 [www.modified-shop.org]
   ---------------------------------------------------------------------
   Released under the GNU General Public License
   ---------------------------------------------------------------------*/

  define('HEADING_BILLBEE_TITLE', 'BILLBEE');
  define('HEADING_BILLBEE_SUB_TITLE', 'Die einfache Multichannel-Software in der Cloud');
  define('AUTHENTICATOR_HEADING', 'Authentikator');
  define('AUTHENTICATOR_TEXT', 'Das Passwort, daß Sie hier eintragen, müssen Sie auch auf der <span style="color:#00C069; font-weight: bold;">Billbee</span> Konfigurationsseite für Ihren Shop hinterlegen.');
  define('MODULE_BILLBEE_DEBUG_HEADING', 'Protokollierung');
  define('MODULE_BILLBEE_DEBUG_TEXT', 'Wenn Sie die Protokollierung aktivieren, finden Sie im Ordner <em>log</em> die Aufrufe von Billbee an Ihren Shop.');  
  define('PRAEFIX_HEADING', 'Präfix Rechnungsnummer');
  define('PRAEFIX_TEXT', 'Eine Zeichenkette, die der Rechnungsnummer vorangestellt wird.');
  define('POSTFIX_HEADING', 'Postfix Rechnungsnummer');
  define('POSTFIX_TEXT', 'Eine Zeichenkette, die der Rechnungsnummer angehängt wird.');
  define('LANGUAGE_HEADING', 'Sprache');
  define('LANGUAGE_TEXT', 'Wählen Sie die Sprache aus, in der Sie <span style="color:#00C069; font-weight: bold;">Billbee</span> betreiben.');
  define('STATUSE_PAYMENT_HEADING', 'Zahlungsmethoden');
  define('STATUSE_PAYMENT_TEXT', 'Beim Abrufen der neuen Bestellungen durch <span style="color:#00C069; font-weight: bold;">Billbee</span> wird auch die Identifikationsnummer der angewandten Zahlungsmethode übermittelt.<br/><br/>Die von Billbee definierten Zalungsmethoden finden Sie auf dieser Seite (etwas nach unten scrollen):<br /><a href="https://hilfe.billbee.io/article/483-billbee-api-zur-anbindung-von-eines-eigenen-webshops" target="_blank">https://hilfe.billbee.io/article/483-billbee-api-zur-anbindung-von-eines-eigenen-webshops</a><br/><br/>Die Identifikationsnummer von <strong><span style="color:#B0347E;">mod</span>ified eCommerce</strong> und <span style="color:#00C069; font-weight: bold;">Billbee</span> stimmen nicht überein.<br/><br/>Falls <span style="color:#00C069; font-weight: bold;">Billbee</span> die gemeldete Identifikationsnummer nicht kennt, wird die Zahlungsmethode auf "Andere" gesetzt.<br/>Es kann auch sein, das die vom Shop gemeldete Identifikationsnummer bei <span style="color:#00C069; font-weight: bold;">Billbee</span> existert, jedoch auf eine andere Zahlungsmethode verweist.<br/><br/>Um diese Probleme zu umgehen, können Sie hier Ihren installierten Zahlungsmethoden, bzw Zahlungsmodulen, eine <span style="color:#00C069; font-weight: bold;">Billbee</span> Zahlungsmethode zuordnen. Alles was Sie nicht zuordnen wird, wie beschrieben, auf "Andere" gesetzt.');
  define('STATUSE_ORDER_HEADING', 'Bestellstatus');
  define('STATUSE_ORDER_TEXT', 'Wenn sich der Bestellstatus bei <span style="color:#00C069; font-weight: bold;">Billbee</span> ändert, meldet das <span style="color:#00C069; font-weight: bold;">Billbee</span> an den Shop, indem eine Status Identifikationsnummer gesendet wird.<br/><br/>Die von <span style="color:#00C069; font-weight: bold;">Billbee</span> definierten Statuswerte finden Sie auf dieser Seite (etwas weiter nach unten scrollen):<br /><a href="https://hilfe.billbee.io/article/483-billbee-api-zur-anbindung-von-eines-eigenen-webshops" target="_blank">https://hilfe.billbee.io/article/483-billbee-api-zur-anbindung-von-eines-eigenen-webshops</a><br/><br/>Die Identifikationsnummer von <strong><span style="color:#B0347E;">mod</span>ified eCommerce</strong> und <span style="color:#00C069; font-weight: bold;">Billbee</span> stimmen nicht überein.<br/><br/>Falls <strong><span style="color:#B0347E;">mod</span>ified eCommerce</strong> die gemeldete Identifikationsnummer nicht kennt oder es keine Zuordnung hierfür gibt, wird der Bestellstatus von <span style="color:#00C069; font-weight: bold;">Billbee</span> in die Bestellhistorie eingetragen.<br/>
  Es kann auch sein, das die vom Shop gemeldete Identifikationsnummer bei <span style="color:#00C069; font-weight: bold;">Billbee</span> existert, jedoch auf einen anderen Bestellstatus verweist.<br/><br/>Um diese Probleme zu umgehen, können Sie hier den <span style="color:#00C069; font-weight: bold;">Billbee</span> Bestellstatusen die Bestellstatuse von <strong><span style="color:#B0347E;">mod</span>ified eCommerce</strong> zuordnen.');
  define('PAYMENT_MODULE_INSTALLED', 'Installierte Zahlungsmethoden');
  define('PAYMENT_MODULE_BILLBEE', 'Billbee Zahlungsmethoden');
  define('ORDER_STATUSE_INSTALLED', 'Modified Order Status');
  define('ORDER_STATUSE_BILLBEE', 'Billbee Order Status');
  define('TEXT_PLEASE_CHOOSE', 'Bitte wählen');
  define('TEXT_SAVE_SUCCESS', 'Daten erfolgreich gespeichert!');
  define('TEXT_SAVE', 'Speichern');
  define('TEXT_VISIT_DEVELOPER', 'Besuchen Sie den Entwickler');
  define('TEXT_VISIT_DEVELOPER_CLAIM', 'Dekoartikel und Kunsthandwerk aus Metall');
?>