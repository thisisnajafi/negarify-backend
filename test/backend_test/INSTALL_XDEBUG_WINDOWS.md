# Xdebug Installation for Windows (PHP 8.4)

**Required:** Xdebug DLL for PHP 8.4 Windows 64-bit Thread Safe (TS)

## Manual Installation Steps

Since automated download failed due to SSL/TLS issues, please follow these steps:

### 1. Download Xdebug DLL

**Download URL:**
```
https://xdebug.org/files/php_xdebug-3.5.2-8.4-ts-vs17-x86_64.dll
```

**Or visit:** https://xdebug.org/download

**Select:**
- PHP Version: 8.4
- Thread Safety: TS (Thread Safe)
- Architecture: x86_64 (64-bit)
- VS Version: VS17 (Visual Studio 2022)

### 2. Place DLL in Extension Directory

Copy the downloaded DLL to:
```
C:\php8.4\ext\php_xdebug.dll
```

### 3. Verify php.ini Configuration

The following has been added to `C:\php8.4\php.ini`:

```ini
; Xdebug extension for code coverage
zend_extension=xdebug
xdebug.mode=coverage
xdebug.start_with_request=no
```

### 4. Verify Installation

Run:
```bash
php -m | grep xdebug
php -i | grep xdebug
php -r "echo extension_loaded('xdebug') ? 'Xdebug: LOADED' : 'Xdebug: NOT LOADED';"
```

Expected output should show Xdebug is loaded.

---

**Note:** This file is a temporary installation guide. Once Xdebug is installed, this can be removed.

