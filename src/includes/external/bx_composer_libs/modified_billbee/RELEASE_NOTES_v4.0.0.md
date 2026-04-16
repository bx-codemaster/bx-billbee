# Release Notes v4.0.0

## Deutsch

### Zusammenfassung
Dieses Release aktualisiert die HTTP-Message-Abhängigkeiten auf PSR-7 v2 und hebt die Mindest-PHP-Version an.

### Breaking Changes
- Mindestversion PHP auf `>= 8.2` angehoben.
- `psr/http-message` auf `^2.0` aktualisiert.
- Eigene PSR-7-Implementierungen auf typisierte Methodensignaturen umgestellt (kompatibel zu PSR-7 v2).

### Dependency-Updates
- `mintware-de/streams` auf `^3.0.0`.
- Entwicklungsabhängigkeiten modernisiert (u. a. PHPUnit/PHPStan auf aktuelle Major-Linien).

### Migration
1. PHP-Laufzeit auf mindestens 8.2 aktualisieren.
2. Abhängigkeiten mit Composer neu auflösen.
3. Eigene Erweiterungen/Custom-Code prüfen, die PSR-7-Interfaces implementieren oder erweitern.
4. API-Flows mit Postman oder vergleichbaren Tools erneut testen.

### Verifikation
- Smoke-Test erfolgreich (Autoload + zentrale Klasseninstanziierung).
- API-Test via Postman erfolgreich.

---

## English

### Summary
This release upgrades HTTP message dependencies to PSR-7 v2 and raises the minimum PHP version.

### Breaking Changes
- Minimum PHP version raised to `>= 8.2`.
- `psr/http-message` upgraded to `^2.0`.
- Custom PSR-7 implementations updated to typed method signatures (PSR-7 v2 compatible).

### Dependency Updates
- `mintware-de/streams` upgraded to `^3.0.0`.
- Dev dependencies modernized (including PHPUnit/PHPStan major lines).

### Migration
1. Upgrade runtime to PHP 8.2 or newer.
2. Re-resolve dependencies with Composer.
3. Review custom extensions/code that implement or extend PSR-7 interfaces.
4. Re-test API flows with Postman or similar tools.

### Verification
- Smoke test passed (autoload + core class instantiation).
- Postman API test passed.
