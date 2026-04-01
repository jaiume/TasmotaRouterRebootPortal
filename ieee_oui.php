<?php
/**
 * IEEE MA-S / MA-M / MA-L registry CSVs: download when missing/stale, lazy per-OUI DB cache.
 * Vendor resolution uses longest prefix match on the full 12-hex MAC (9 → 7 → 6 hex).
 */
require_once __DIR__ . '/db.php';

function normalize_ap_mac(?string $raw): ?string {
    if ($raw === null || $raw === '') {
        return null;
    }
    $s = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', $raw));
    if (strlen($s) !== 12 || !ctype_xdigit($s)) {
        return null;
    }
    return implode(':', str_split($s, 2));
}

function mac_to_oui(string $canonical_mac): string {
    $parts = explode(':', strtoupper($canonical_mac));
    return $parts[0] . $parts[1] . $parts[2];
}

/** 12 uppercase hex digits from canonical AA:BB:CC:DD:EE:FF (or any 12-hex form). */
function mac_hex12(?string $canonical_mac): ?string {
    if ($canonical_mac === null || $canonical_mac === '') {
        return null;
    }
    $s = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', $canonical_mac));
    if (strlen($s) !== 12 || !ctype_xdigit($s)) {
        return null;
    }
    return $s;
}

function ieee_assignment_to_oui(string $assignment): ?string {
    $s = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', $assignment));
    if (strlen($s) !== 6 || !ctype_xdigit($s)) {
        return null;
    }
    return $s;
}

/** Strip non-hex, uppercase; accept only 6, 7, or 9 hex digits. */
function ieee_normalize_assignment(string $cell): ?string {
    $s = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', $cell));
    $len = strlen($s);
    if (!ctype_xdigit($s) || !in_array($len, [6, 7, 9], true)) {
        return null;
    }
    return $s;
}

function ieee_oui_csv_path(): string {
    global $ieee_oui_data_dir, $ieee_oui_csv_filename;
    $dir = isset($ieee_oui_data_dir) ? $ieee_oui_data_dir : (__DIR__ . '/data/ieee');
    $fn = isset($ieee_oui_csv_filename) ? $ieee_oui_csv_filename : 'oui.csv';
    return rtrim($dir, '/') . '/' . $fn;
}

function ieee_oui_mam_csv_path(): string {
    global $ieee_oui_data_dir, $ieee_oui_mam_csv_filename;
    $dir = isset($ieee_oui_data_dir) ? $ieee_oui_data_dir : (__DIR__ . '/data/ieee');
    $fn = isset($ieee_oui_mam_csv_filename) ? $ieee_oui_mam_csv_filename : 'mam.csv';
    return rtrim($dir, '/') . '/' . $fn;
}

function ieee_oui_mas_csv_path(): string {
    global $ieee_oui_data_dir, $ieee_oui_mas_csv_filename;
    $dir = isset($ieee_oui_data_dir) ? $ieee_oui_data_dir : (__DIR__ . '/data/ieee');
    $fn = isset($ieee_oui_mas_csv_filename) ? $ieee_oui_mas_csv_filename : 'oui36.csv';
    return rtrim($dir, '/') . '/' . $fn;
}

function ieee_oui_truncate_vendor_cache(): void {
    $mysqli = db_connect();
    if (!$mysqli->query('TRUNCATE TABLE oui_vendor_cache')) {
        error_log('ieee_oui: TRUNCATE oui_vendor_cache failed: ' . $mysqli->error);
    }
    $mysqli->close();
}

function ieee_oui_download_to_path(string $path, ?string $url = null): bool {
    global $ieee_oui_csv_url, $ieee_oui_download_timeout_seconds;
    if ($url === null || $url === '') {
        $url = $ieee_oui_csv_url ?? 'http://standards-oui.ieee.org/oui/oui.csv';
    }
    $timeout = isset($ieee_oui_download_timeout_seconds) ? (int) $ieee_oui_download_timeout_seconds : 30;
    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
        error_log("ieee_oui: cannot create directory: $dir");
        return false;
    }
    $tmp = $path . '.tmp.' . getmypid();
    $ctx = stream_context_create([
        'http' => ['timeout' => $timeout, 'follow_location' => 1],
        'https' => ['timeout' => $timeout, 'follow_location' => 1],
    ]);
    $data = @file_get_contents($url, false, $ctx);
    if ($data === false || $data === '') {
        error_log('ieee_oui: download failed from ' . $url);
        if (is_file($tmp)) {
            @unlink($tmp);
        }
        return false;
    }
    if (file_put_contents($tmp, $data) === false) {
        @unlink($tmp);
        return false;
    }
    $fh = @fopen($tmp, 'r');
    if (!$fh) {
        @unlink($tmp);
        return false;
    }
    $header = fgetcsv($fh);
    fclose($fh);
    if (!$header || !in_array('Assignment', $header, true) || !in_array('Organization Name', $header, true)) {
        error_log('ieee_oui: downloaded file missing expected IEEE CSV headers');
        @unlink($tmp);
        return false;
    }
    if (!@rename($tmp, $path)) {
        @unlink($tmp);
        return false;
    }
    return true;
}

/**
 * Refresh oui.csv, mam.csv, and oui36.csv when missing/stale.
 * TRUNCATE oui_vendor_cache once if any file was replaced and all three are readable (avoids cache wipe on partial failure).
 */
function ensure_ieee_registry_files(): bool {
    global $ieee_oui_enabled, $ieee_oui_max_age_seconds;
    global $ieee_oui_csv_url, $ieee_oui_mam_csv_url, $ieee_oui_mas_csv_url;
    if (empty($ieee_oui_enabled)) {
        return false;
    }
    $maxAge = isset($ieee_oui_max_age_seconds) ? (int) $ieee_oui_max_age_seconds : 604800;
    $jobs = [
        [
            'path' => ieee_oui_csv_path(),
            'url' => $ieee_oui_csv_url ?? 'http://standards-oui.ieee.org/oui/oui.csv',
        ],
        [
            'path' => ieee_oui_mam_csv_path(),
            'url' => $ieee_oui_mam_csv_url ?? 'http://standards-oui.ieee.org/oui28/mam.csv',
        ],
        [
            'path' => ieee_oui_mas_csv_path(),
            'url' => $ieee_oui_mas_csv_url ?? 'http://standards-oui.ieee.org/oui36/oui36.csv',
        ],
    ];
    $anyReplaced = false;
    foreach ($jobs as $job) {
        $path = $job['path'];
        $url = $job['url'];
        $needDownload = !is_file($path);
        if (!$needDownload && (time() - filemtime($path) > $maxAge)) {
            $needDownload = true;
        }
        if ($needDownload) {
            if (ieee_oui_download_to_path($path, $url)) {
                $anyReplaced = true;
            } elseif (!is_file($path)) {
                // Required MA-L file missing after failed download
                if ($path === ieee_oui_csv_path()) {
                    return false;
                }
            }
        }
    }
    $pMal = ieee_oui_csv_path();
    $pMam = ieee_oui_mam_csv_path();
    $pMas = ieee_oui_mas_csv_path();
    if ($anyReplaced && is_readable($pMal) && is_readable($pMam) && is_readable($pMas)) {
        ieee_oui_truncate_vendor_cache();
    }
    return is_file($pMal) && is_readable($pMal);
}

/** @deprecated Use ensure_ieee_registry_files(); kept for compatibility. */
function ensure_ieee_oui_csv(): bool {
    return ensure_ieee_registry_files();
}

function lookup_vendor_prefix_in_file(string $path, string $hex12, int $prefixLen): ?string {
    if (!is_readable($path) || strlen($hex12) < $prefixLen) {
        return null;
    }
    $fh = fopen($path, 'r');
    if (!$fh) {
        return null;
    }
    $header = fgetcsv($fh);
    if (!$header) {
        fclose($fh);
        return null;
    }
    $idxAssign = array_search('Assignment', $header, true);
    $idxOrg = array_search('Organization Name', $header, true);
    if ($idxAssign === false || $idxOrg === false) {
        fclose($fh);
        return null;
    }
    while (($row = fgetcsv($fh)) !== false) {
        $need = max($idxAssign, $idxOrg) + 1;
        if (count($row) < $need) {
            continue;
        }
        $a = ieee_normalize_assignment($row[$idxAssign] ?? '');
        if ($a !== null && strlen($a) === $prefixLen && strncmp($hex12, $a, $prefixLen) === 0) {
            $name = trim((string) ($row[$idxOrg] ?? ''));
            fclose($fh);
            return $name !== '' ? $name : null;
        }
    }
    fclose($fh);
    return null;
}

/** Longest-prefix match: MA-S (9) then MA-M (7) then MA-L (6). */
function lookup_vendor_longest_prefix(string $hex12): ?string {
    $hex12 = strtoupper($hex12);
    if (strlen($hex12) !== 12 || !ctype_xdigit($hex12)) {
        return null;
    }
    $v = lookup_vendor_prefix_in_file(ieee_oui_mas_csv_path(), $hex12, 9);
    if ($v !== null) {
        return $v;
    }
    $v = lookup_vendor_prefix_in_file(ieee_oui_mam_csv_path(), $hex12, 7);
    if ($v !== null) {
        return $v;
    }
    return lookup_vendor_prefix_in_file(ieee_oui_csv_path(), $hex12, 6);
}

function lookup_vendor_in_ieee_file(string $oui): ?string {
    $path = ieee_oui_csv_path();
    if (!is_readable($path)) {
        return null;
    }
    $oui = strtoupper($oui);
    $fh = fopen($path, 'r');
    if (!$fh) {
        return null;
    }
    $header = fgetcsv($fh);
    if (!$header) {
        fclose($fh);
        return null;
    }
    $idxAssign = array_search('Assignment', $header, true);
    $idxOrg = array_search('Organization Name', $header, true);
    if ($idxAssign === false || $idxOrg === false) {
        fclose($fh);
        return null;
    }
    while (($row = fgetcsv($fh)) !== false) {
        $need = max($idxAssign, $idxOrg) + 1;
        if (count($row) < $need) {
            continue;
        }
        $a = ieee_assignment_to_oui($row[$idxAssign] ?? '');
        if ($a !== null && $a === $oui) {
            $name = trim((string) ($row[$idxOrg] ?? ''));
            fclose($fh);
            return $name !== '' ? $name : null;
        }
    }
    fclose($fh);
    return null;
}

/**
 * Resolve vendor from full AP MAC (MA-S / MA-M / MA-L). Cache keyed by first 3 octets (oui6).
 */
function resolve_vendor_for_ap_mac(string $canonical_mac): ?string {
    global $ieee_oui_enabled;
    if (empty($ieee_oui_enabled)) {
        return null;
    }
    $norm = normalize_ap_mac($canonical_mac);
    if ($norm === null) {
        return null;
    }
    $hex12 = mac_hex12($norm);
    if ($hex12 === null) {
        return null;
    }
    $oui = mac_to_oui($norm);
    if (!ensure_ieee_registry_files()) {
        return null;
    }
    $mysqli = db_connect();
    $stmt = $mysqli->prepare('SELECT vendor FROM oui_vendor_cache WHERE oui = ?');
    $stmt->bind_param('s', $oui);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if ($row !== null) {
        $mysqli->close();
        $v = $row['vendor'];
        return ($v !== null && $v !== '') ? $v : null;
    }
    $vendor = lookup_vendor_longest_prefix($hex12);
    if ($vendor === null) {
        $stmt = $mysqli->prepare('INSERT INTO oui_vendor_cache (oui, vendor) VALUES (?, NULL)');
        $stmt->bind_param('s', $oui);
    } else {
        $stmt = $mysqli->prepare('INSERT INTO oui_vendor_cache (oui, vendor) VALUES (?, ?)');
        $stmt->bind_param('ss', $oui, $vendor);
    }
    if (!$stmt->execute()) {
        if ($mysqli->errno === 1062) {
            $stmt->close();
            $stmt = $mysqli->prepare('SELECT vendor FROM oui_vendor_cache WHERE oui = ?');
            $stmt->bind_param('s', $oui);
            $stmt->execute();
            $r2 = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $mysqli->close();
            if ($r2 && $r2['vendor'] !== null && $r2['vendor'] !== '') {
                return $r2['vendor'];
            }
            return null;
        }
        error_log('ieee_oui: INSERT oui_vendor_cache failed: ' . $stmt->error);
    }
    $stmt->close();
    $mysqli->close();
    return $vendor;
}

/** MA-L (6 hex) only; cannot resolve MA-M/S without full MAC. Prefer resolve_vendor_for_ap_mac. */
function resolve_vendor_for_oui(string $oui): ?string {
    global $ieee_oui_enabled;
    if (empty($ieee_oui_enabled)) {
        return null;
    }
    if (!preg_match('/^[0-9A-Fa-f]{6}$/', $oui)) {
        return null;
    }
    $oui = strtoupper($oui);
    if (!ensure_ieee_registry_files()) {
        return null;
    }
    $mysqli = db_connect();
    $stmt = $mysqli->prepare('SELECT vendor FROM oui_vendor_cache WHERE oui = ?');
    $stmt->bind_param('s', $oui);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if ($row !== null) {
        $mysqli->close();
        $v = $row['vendor'];
        return ($v !== null && $v !== '') ? $v : null;
    }
    $vendor = lookup_vendor_in_ieee_file($oui);
    if ($vendor === null) {
        $stmt = $mysqli->prepare('INSERT INTO oui_vendor_cache (oui, vendor) VALUES (?, NULL)');
        $stmt->bind_param('s', $oui);
    } else {
        $stmt = $mysqli->prepare('INSERT INTO oui_vendor_cache (oui, vendor) VALUES (?, ?)');
        $stmt->bind_param('ss', $oui, $vendor);
    }
    if (!$stmt->execute()) {
        if ($mysqli->errno === 1062) {
            $stmt->close();
            $stmt = $mysqli->prepare('SELECT vendor FROM oui_vendor_cache WHERE oui = ?');
            $stmt->bind_param('s', $oui);
            $stmt->execute();
            $r2 = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $mysqli->close();
            if ($r2 && $r2['vendor'] !== null && $r2['vendor'] !== '') {
                return $r2['vendor'];
            }
            return null;
        }
        error_log('ieee_oui: INSERT oui_vendor_cache failed: ' . $stmt->error);
    }
    $stmt->close();
    $mysqli->close();
    return $vendor;
}

function ieee_extract_bssid_from_decoded(array $decoded): ?string {
    $wifi = $decoded['Wifi'] ?? null;
    if (is_array($wifi)) {
        $raw = $wifi['BSSId'] ?? $wifi['APMac'] ?? $wifi['BSSID'] ?? null;
        if (is_string($raw) && $raw !== '') {
            return $raw;
        }
    }
    $sts = $decoded['StatusSTS'] ?? null;
    if (is_array($sts)) {
        $w2 = $sts['Wifi'] ?? null;
        if (is_array($w2)) {
            $raw = $w2['BSSId'] ?? $w2['APMac'] ?? $w2['BSSID'] ?? null;
            if (is_string($raw) && $raw !== '') {
                return $raw;
            }
        }
    }
    return null;
}
