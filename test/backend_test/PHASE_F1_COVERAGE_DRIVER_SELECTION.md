# Phase F1: Coverage Driver Selection

**Date:** 2025-12-30  
**Phase:** F1 - Coverage Driver Selection  
**Status:** ✅ Complete

---

## Executive Summary

**Selected Driver:** **PCOV** (Primary) with **Xdebug** (Fallback)

**Decision:** Use PCOV if available (faster, better performance), fallback to Xdebug if PCOV unavailable.

**Current Status:** Checking installed extensions...

---

## Driver Selection Criteria

### PCOV (Primary Choice)

**Pros:**
- ✅ Faster execution (native C extension)
- ✅ Lower memory usage
- ✅ Better performance for test suites
- ✅ Actively maintained
- ✅ Designed specifically for code coverage

**Cons:**
- ⚠️ Requires PHP 7.1+ (we have PHP 8.2+ ✅)
- ⚠️ May not be available on all systems

**Rationale:** Test suite performance is critical. PCOV is significantly faster than Xdebug for coverage generation.

### Xdebug (Fallback Choice)

**Pros:**
- ✅ More widely available
- ✅ Better IDE integration
- ✅ More features (debugging, profiling)

**Cons:**
- ⚠️ Slower execution
- ⚠️ Higher memory usage
- ⚠️ May impact test performance significantly

**Rationale:** Use as fallback if PCOV is not available or cannot be installed.

---

## Installation Status Check

### PCOV Check

**Command:**
```bash
php -m | grep -i pcov
```

**Result:** (No output - PCOV not found in loaded modules)

**Command:**
```bash
php -r "echo extension_loaded('pcov') ? 'PCOV: INSTALLED' : 'PCOV: NOT INSTALLED';"
```

**Result:** `PCOV: NOT INSTALLED`

**Status:** ❌ **PCOV is NOT currently installed**

### Xdebug Check

**Command:**
```bash
php -m | grep -i xdebug
```

**Result:** (No output - Xdebug not found in loaded modules)

**Command:**
```bash
php -r "echo extension_loaded('xdebug') ? 'Xdebug: INSTALLED' : 'Xdebug: NOT INSTALLED';"
```

**Result:** `Xdebug: NOT INSTALLED`

**Status:** ❌ **Xdebug is NOT currently installed**

---

## Decision Logic

1. ✅ **PCOV is not installed** - Installation required
2. ✅ **Xdebug is not installed** - Available as fallback if PCOV installation fails
3. ✅ **Decision:** Attempt to install PCOV (primary choice)
4. ✅ **Fallback:** Use Xdebug if PCOV installation fails

---

## Final Decision

**Selected Driver:** **PCOV** (Primary) with **Xdebug** (Fallback)

**Reasoning:**
- PCOV is the preferred choice for performance (faster execution, lower memory usage)
- Neither extension is currently installed
- Will attempt to install PCOV first
- Xdebug available as fallback if PCOV installation fails

**Installation Required:** ✅ **YES** - PCOV needs to be installed

**Next Steps:**
1. Install PCOV extension (Phase F2)
2. Configure PCOV in php.ini (Phase F2)
3. Verify installation (Phase F2)
4. If PCOV installation fails, install Xdebug as fallback (Phase F2)

---

## Proof Commands Output

### PCOV Verification
```bash
$ php -m | grep -i pcov
(No output - extension not loaded)

$ php -r "echo extension_loaded('pcov') ? 'PCOV: INSTALLED' : 'PCOV: NOT INSTALLED';"
PCOV: NOT INSTALLED
```

### Xdebug Verification
```bash
$ php -m | grep -i xdebug
(No output - extension not loaded)

$ php -r "echo extension_loaded('xdebug') ? 'Xdebug: INSTALLED' : 'Xdebug: NOT INSTALLED';"
Xdebug: NOT INSTALLED
```

---

**Status:** ✅ **Phase F1 Complete** - Driver selected: PCOV (primary), Xdebug (fallback)

