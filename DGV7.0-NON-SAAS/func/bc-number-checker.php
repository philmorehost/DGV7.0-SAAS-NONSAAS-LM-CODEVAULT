<?php
/**
 * bc-number-checker.php  —  Bulk Number Credit Checker helper (DGV7.0).
 *
 * Lets a user / admin paste a list of phone numbers and find out, for a chosen
 * month, whether each number was successfully credited with DATA or AIRTIME
 * (optionally restricted to a specific data service category). Green = credited
 * (shows the credited date), Red = not credited that month.
 *
 * "Credited" is defined as a real provider top-up row in `sas_transactions`
 * with status = 1, a real API id + product id (NOT a Refund / Wallet row), and
 * `date` falling inside the selected month.
 *
 * DEPENDENCIES (already loaded by every user/admin page via bc-config.php /
 * bc-admin-config.php -> bc-connect.php -> bc-func.php):
 *   $connection_server, sanitize_phone_number(), identifyISP(), tranStatus()
 *
 * This file only declares functions; it does NOT output anything on include.
 */

if (!function_exists('bc_checker_month_label')) {
    function bc_checker_month_label($ym) {
        if (!preg_match('/^\d{4}-\d{2}$/', $ym)) return htmlspecialchars($ym);
        return date('F Y', mktime(0, 0, 0, (int)substr($ym, 5, 2), 1, (int)substr($ym, 0, 4)));
    }
}

if (!function_exists('bc_checker_normalize_phones')) {
    /**
     * Normalize raw pasted text (comma/newline separated) into:
     *  - a unique 0-format list of valid NGN numbers (sanitize_phone_number)
     *  - a count of invalid entries that were dropped
     * Returns array('phones'=>[], 'invalid'=>int, 'raw_count'=>int).
     */
    function bc_checker_normalize_phones($raw) {
        $raw = (string)$raw;
        // Split on commas, whitespace or new lines.
        $parts = preg_split('/[\s,;]+/', trim($raw));
        $seen = array();
        $invalid = 0;
        $raw_count = 0;
        foreach ($parts as $part) {
            $part = trim((string)$part);
            if ($part === '') continue;
            $raw_count++;
            $clean = sanitize_phone_number($part);   // 234... -> 0...
            $clean = preg_replace('/[^0-9]/', '', $clean);
            if (strlen($clean) === 11 && $clean[0] === '0') {
                $seen[$clean] = true;
            } else {
                $invalid++;
            }
        }
        return array(
            'phones'    => array_keys($seen),
            'invalid'   => $invalid,
            'raw_count' => $raw_count,
        );
    }
}

if (!function_exists('bc_checker_service_where')) {
    /**
     * Build the SQL fragment that filters sas_transactions to a service.
     * Uses both the API-type join (a.api_type) and the descriptive label
     * (t.type_alternative) so historical rows survive deleted APIs.
     *
     * $service   = 'data' | 'airtime'
     * $data_type = 'sme-data' | 'cg-data' | 'dd-data' | 'shared-data' | 'any'
     * Aliases: t = sas_transactions, a = sas_apis (LEFT JOIN).
     */
    function bc_checker_service_where($service, $data_type = 'any') {
        $service = $service === 'airtime' ? 'airtime' : 'data';

        if ($service === 'airtime') {
            return "( a.api_type = 'airtime' OR LOWER(t.type_alternative) LIKE '%airtime%' )";
        }

        // DATA — restrict by chosen service category when provided.
        $known = array('sme-data', 'cg-data', 'dd-data', 'shared-data');
        $dt = in_array($data_type, $known, true) ? $data_type : 'any';

        if ($dt === 'any') {
            return "( a.api_type IN ('sme-data','cg-data','dd-data','shared-data')
                     OR ( LOWER(t.type_alternative) LIKE '%data%'
                          AND LOWER(t.type_alternative) NOT LIKE '%airtime%' ) )";
        }

        $keywords = array(
            'sme-data'    => 'sme',
            'cg-data'     => 'cg|gifting',
            'dd-data'     => 'dd|direct data',
            'shared-data' => 'shared',
        );
        $kw = $keywords[$dt];
        return "( a.api_type = '" . $dt . "' OR LOWER(t.type_alternative) REGEXP '" . $kw . "' )";
    }
}

if (!function_exists('bc_checker_run')) {
    /**
     * Execute the check.
     *
     * @param mysqli  $connection_server
     * @param int|string $vendor_id        scope: vendor
     * @param string  $username            scope: '' = any user (admin) | non-empty = that user
     * @param array   $phones              normalized 0-format list
     * @param string  $month               'YYYY-MM'
     * @param string  $service             'data' | 'airtime'
     * @param string  $data_type           category (only for data)
     *
     * @return array{
     *   credited:   array phone => array of credited rows,
     *   attempts:   array phone => array of failed/pending rows (for requery),
     *   summary:    array{credited:int, not_credited:int},
     * }
     */
    function bc_checker_run($connection_server, $vendor_id, $username, $phones, $month, $service, $data_type = 'any') {
        $result = array('credited' => array(), 'attempts' => array(), 'summary' => array('credited' => 0, 'not_credited' => 0));

        if (!$connection_server || !is_array($phones) || count($phones) === 0) {
            return $result;
        }
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            return $result;
        }

        // First / last instant of the selected month.
        $y = (int)substr($month, 0, 4);
        $m = (int)substr($month, 5, 2);
        $month_start = sprintf('%04d-%02d-01 00:00:00', $y, $m);
        $month_end   = date('Y-m-t 23:59:59', mktime(0, 0, 0, $m, 1, $y));

        $vid  = mysqli_real_escape_string($connection_server, (string)$vendor_id);
        $uname = ($username === '') ? '' : mysqli_real_escape_string($connection_server, (string)$username);

        // IN (...) list — match BOTH the 0-format ('08031234567') and the
        // 234-format ('2348031234567') that some legacy rows may have stored.
        $in_set = array();
        foreach ($phones as $p) {
            $p0 = preg_replace('/[^0-9]/', '', (string)$p);
            $in_set[$p0] = true;
            if (strlen($p0) === 11 && $p0[0] === '0') {
                $in_set['234' . substr($p0, 1)] = true;
            }
        }
        $in_list = array();
        foreach ($in_set as $p0 => $_) {
            $in_list[] = "'" . mysqli_real_escape_string($connection_server, $p0) . "'";
        }
        $in_sql = implode(',', $in_list);

        $user_where = ($uname === '') ? '' : " AND t.username = '$uname'";
        $service_where = bc_checker_service_where($service, $data_type);

        // Single query covering credited (1) + failed/pending (2,3) in the month.
        // Real top-up only: api_id + product_id NOT NULL, excludes Refund/Wallet rows.
        $q = "SELECT t.reference, t.product_unique_id AS phone, t.type_alternative,
                     t.description, t.amount, t.discounted_amount,
                     t.status, t.date, t.username,
                     p.product_name AS network, a.api_type
              FROM sas_transactions t
              LEFT JOIN sas_products p ON t.product_id = p.id AND t.vendor_id = p.vendor_id
              LEFT JOIN sas_apis     a ON t.api_id   = a.id AND t.vendor_id = a.vendor_id
              WHERE t.vendor_id = '$vid'
                $user_where
                AND t.api_id IS NOT NULL AND t.product_id IS NOT NULL
                AND t.type_alternative NOT LIKE '%refund%'
                AND t.type_alternative NOT LIKE '%wallet%'
                AND t.status IN (1,2,3)
                AND t.product_unique_id IN ($in_sql)
                AND t.date >= '$month_start' AND t.date <= '$month_end'
                AND $service_where
              ORDER BY t.product_unique_id ASC, t.date ASC";

        $res = mysqli_query($connection_server, $q);

        // Seed every requested phone so red results show for numbers with no rows.
        foreach ($phones as $ph) {
            $result['credited'][$ph] = array();
            $result['attempts'][$ph] = array();
        }

        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                // Bucket by the canonical 0-format phone so legacy 234-format
                // rows map back to the number the user actually pasted.
                $ph = sanitize_phone_number((string)$row['phone']);
                if (!isset($result['credited'][$ph])) continue;
                if ((int)$row['status'] === 1) {
                    $result['credited'][$ph][] = $row;
                } else {
                    $result['attempts'][$ph][] = $row;
                }
            }
        }

        // Summary + drop empty attempt arrays.
        foreach ($phones as $ph) {
            if (count($result['credited'][$ph]) > 0) {
                $result['summary']['credited']++;
            } else {
                $result['summary']['not_credited']++;
            }
            if (count($result['attempts'][$ph]) === 0) {
                unset($result['attempts'][$ph]);
            }
            if (count($result['credited'][$ph]) === 0) {
                unset($result['credited'][$ph]);
            }
        }

        return $result;
    }
}

if (!function_exists('bc_checker_network_badge')) {
    /**
     * Small colored network badge for a row. $network comes from sas_products
     * (product_name) when present, else we fall back to the phone prefix.
     */
    function bc_checker_network_badge($network, $phone) {
        $net = strtolower(trim((string)$network));
        if ($net === '' || $net === 'unknown' || $net === null) {
            $net = strtolower((string)identifyISP($phone));
        }
        $colors = array(
            'mtn'      => 'text-primary',
            'airtel'   => 'text-danger',
            'glo'      => 'text-success',
            '9mobile'  => 'text-warning',
            '9mobile ' => 'text-warning',
        );
        $cls = isset($colors[$net]) ? $colors[$net] : 'text-secondary';
        return '<span class="badge bg-light border text-dark">' . strtoupper(htmlspecialchars($net)) . '</span>';
    }
}

if (!function_exists('bc_checker_network_name')) {
    /** Plain-text network name (no HTML) for exports. */
    function bc_checker_network_name($network, $phone) {
        $net = strtolower(trim((string)$network));
        if ($net === '' || $net === 'unknown' || $net === null) {
            $net = strtolower((string)identifyISP($phone));
        }
        return $net === '' ? 'Unknown' : strtoupper($net);
    }
}

if (!function_exists('bc_checker_service_label')) {
    /** Human label for the report, e.g. "Data (SME Data)" or "Airtime". */
    function bc_checker_service_label($form) {
        if (($form['service'] ?? 'data') === 'airtime') return 'Airtime';
        $dt = $form['data_type'] ?? 'any';
        return 'Data' . ($dt === 'any' ? ' (All)' : ' (' . strtoupper(str_replace('-', ' ', $dt)) . ')');
    }
}

if (!function_exists('bc_checker_build_report')) {
    /**
     * Flatten a checker result into export-ready rows.
     * Returns array('meta' => [...], 'header' => [...], 'rows' => [[...], ...]).
     * Columns: S/N, Phone, Network, Status, Credited Date, Amount, Reference / Detail.
     * When $include_user is true, a leading 'Username' column is added (admin view).
     */
    function bc_checker_build_report($result, $form, $include_user = false) {
        $phones = $result['_norm']['phones'] ?? array();
        $summary = $result['summary'] ?? array('credited' => 0, 'not_credited' => 0);
        $invalid = (int)($result['_norm']['invalid'] ?? 0);

        $meta = array(
            'Report'     => 'Number Credit Checker Report',
            'Month'      => bc_checker_month_label($form['month'] ?? ''),
            'Service'    => bc_checker_service_label($form),
            'Generated'  => date('Y-m-d H:i:s'),
            'Total'      => count($phones),
            'Credited'   => (int)($summary['credited'] ?? 0),
            'NotCredited'=> (int)($summary['not_credited'] ?? 0),
            'Invalid'    => $invalid,
        );

        $header = array('S/N', 'Phone Number', 'Network', 'Status', 'Credited Date', 'Amount (₦)', 'Reference / Detail');
        if ($include_user) array_unshift($header, 'Username');

        $rows = array();
        $n = 0;
        foreach ($phones as $phone) {
            $n++;
            $credited = $result['credited'][$phone] ?? array();
            $attempts = $result['attempts'][$phone] ?? array();

            $network = bc_checker_network_name(($credited[0]['network'] ?? '') ?: '', $phone);
            $username = '';
            $status = 'Not Credited';
            $dates = array();
            $amount = 0.0;
            $detail = '';

            if (count($credited) > 0) {
                $status = 'Credited';
                $seen_dates = array();
                foreach ($credited as $c) {
                    $username = $username ?: ($c['username'] ?? '');
                    $d = date('Y-m-d', strtotime($c['date']));
                    if (!in_array($d, $seen_dates, true)) $seen_dates[] = $d;
                    $amount += (float)($c['discounted_amount'] ?? $c['amount'] ?? 0);
                    $detail = ($detail === '') ? ($c['type_alternative'] ?? '') : $detail;
                }
                $dates = $seen_dates;
                if (count($credited) > count($seen_dates)) {
                    $detail .= ' (' . count($credited) . ' credit(s) this month)';
                }
            }

            if ($status === 'Not Credited' && count($attempts) > 0) {
                $attempt_parts = array();
                foreach (array_slice($attempts, 0, 5) as $att) {
                    $attempt_parts[] = $att['reference'] . (($att['status'] ?? '') == 2 ? ' [PENDING]' : ' [FAILED]');
                }
                $detail = implode(', ', $attempt_parts);
            }

            $amount_str = $amount > 0 ? number_format($amount, 2) : '';
            $date_str = implode(', ', $dates);

            $row = array($n, $phone, $network, $status, $date_str, $amount_str, $detail);
            if ($include_user) array_unshift($row, $username);
            $rows[] = $row;
        }

        return array('meta' => $meta, 'header' => $header, 'rows' => $rows);
    }
}

if (!function_exists('bc_checker_export_csv')) {
    /** Stream the report as a CSV download. */
    function bc_checker_export_csv($result, $form, $include_user = false) {
        $report = bc_checker_build_report($result, $form, $include_user);
        $stamp = date('Ymd-His');

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=NumberChecker-' . $stamp . '.csv');

        $out = fopen('php://output', 'w');
        // UTF-8 BOM so Excel renders ₦ / accented text correctly.
        fwrite($out, "\xEF\xBB\xBF");

        fputcsv($out, array($report['meta']['Report'], 'Month: ' . $report['meta']['Month'], 'Service: ' . $report['meta']['Service'], 'Generated: ' . $report['meta']['Generated']));
        fputcsv($out, array('Total Checked', $report['meta']['Total'], 'Credited', $report['meta']['Credited'], 'Not Credited', $report['meta']['NotCredited'], 'Invalid', $report['meta']['Invalid']));
        fputcsv($out, array()); // blank spacer row

        fputcsv($out, $report['header']);
        foreach ($report['rows'] as $row) {
            fputcsv($out, $row);
        }
        fclose($out);
        exit;
    }
}

if (!function_exists('bc_checker_export_excel')) {
    /**
     * Stream the report as an Excel-compatible .xls (SpreadsheetML 2003).
     * Phone/reference cells are forced to text so leading zeros are preserved.
     */
    function bc_checker_export_excel($result, $form, $include_user = false) {
        $report = bc_checker_build_report($result, $form, $include_user);
        $stamp = date('Ymd-His');

        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename=NumberChecker-' . $stamp . '.xls');

        $e = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";
        echo '<Styles><Style ss:ID="hdr"><Font ss:Bold="1"/><Interior ss:Color="#D9E2F3" ss:Pattern="Solid"/></Style></Styles>' . "\n";
        echo '<Worksheet ss:Name="Report"><Table>' . "\n";

        // Meta block (title + filter summary), one cell per line.
        $meta_lines = array(
            $report['meta']['Report'],
            'Month: ' . $report['meta']['Month'] . '   |   Service: ' . $report['meta']['Service'],
            'Generated: ' . $report['meta']['Generated'],
            'Total Checked: ' . $report['meta']['Total'] . '   |   Credited: ' . $report['meta']['Credited'] . '   |   Not Credited: ' . $report['meta']['NotCredited'] . '   |   Invalid: ' . $report['meta']['Invalid'],
        );
        foreach ($meta_lines as $line) {
            echo '<Row><Cell><Data ss:Type="String">' . $e($line) . '</Data></Cell></Row>' . "\n";
        }
        echo '<Row><Cell><Data ss:Type="String"></Data></Cell></Row>' . "\n"; // spacer

        // Header row
        echo '<Row>';
        foreach ($report['header'] as $h) {
            echo '<Cell ss:StyleID="hdr"><Data ss:Type="String">' . $e($h) . '</Data></Cell>';
        }
        echo '</Row>' . "\n";

        // Data rows. Phone / reference / status columns are kept as text so
        // Excel never strips leading zeros or reformats phone numbers.
        $header = $report['header'];
        $number_col = null;
        foreach ($header as $ci => $colname) {
            if ($colname === 'Amount (₦)') $number_col = $ci;
        }
        foreach ($report['rows'] as $row) {
            echo '<Row>';
            foreach ($row as $ci => $val) {
                $val = (string)$val;
                if ($val === '') {
                    echo '<Cell><Data ss:Type="String"></Data></Cell>';
                } elseif ($ci === $number_col) {
                    echo '<Cell><Data ss:Type="Number">' . $e($val) . '</Data></Cell>';
                } else {
                    echo '<Cell><Data ss:Type="String">' . $e($val) . '</Data></Cell>';
                }
            }
            echo '</Row>' . "\n";
        }

        echo '</Table></Worksheet></Workbook>';
        exit;
    }
}
