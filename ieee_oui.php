<?php
/**
 * IEEE MA-L oui.csv: download when missing/stale, lazy per-OUI lookup with DB cache.
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

function ieee_assignment_to_oui(string $assignment): ?string {
    $s = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', $assignment));
    if (strlen($s) !== 6 || !ctype_xdigit($s)) {
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

function ieee_oui_truncate_vendor_cache(): void {
    $mysqli = db_connect();
    if (!$mysqli->query('TRUNCATE TABLE oui_vendor_cache')) {
        error_log('ieee_oui: TRUNCATE oui_vendor_cache failed: ' . $mysqli->error);
    }
    $mysqli->close();
}

function ieee_oui_download_to_path(string $path): bool {
    global $ieee_oui_csv_url, $ieee_oui_download_timeout_seconds;
    $url = $ieee_oui_csv_url ?? 'http://standards-oui.ieee.org/oui/oui.csv';
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
 * Ensure oui.csv exists and is fresh. On successful new download, truncate oui_vendor_cache.
 */
function ensure_ieee_oui_csv(): bool {
    global $ieee_oui_enabled, $ieee_oui_max_age_seconds;
    if (empty($ieee_oui_enabled)) {
        return false;
    }
    $path = ieee_oui_csv_path();
    $maxAge = isset($ieee_oui_max_age_seconds) ? (int) $ieee_oui_max_age_seconds : 604800;
    $needDownload = !is_file($path);
    if (!$needDownload && (time() - filemtime($path) > $maxAge)) {
        $needDownload = true;
    }
    if ($needDownload) {
        if (ieee_oui_download_to_path($path)) {
            ieee_oui_truncate_vendor_cache();
        } elseif (!is_file($path)) {
            return false;
        }
    }
    return is_file($path) && is_readable($path);
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

function resolve_vendor_for_oui(string $oui): ?string {
    global $ieee_oui_enabled;
    if (empty($ieee_oui_enabled)) {
        return null;
    }
    if (!preg_match('/^[0-9A-Fa-f]{6}$/', $oui)) {
        return null;
    }
    $oui = strtoupper($oui);
    if (!ensure_ieee_oui_csv()) {
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
