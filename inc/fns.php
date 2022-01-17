<?php

namespace App;

function doDatabaseInsert($sv, $dbName, $tableName, $values, $keys, $hexKeys = [], $bulkSize = 1000)
{
    global $logger;

    if (!\is_array($values)) {
        die("Values should definitely be an array");
    }

    $kstr = "";
    foreach ($keys as $k) {
        $kstr .= "`".$k."`,";
    }
    $kstr = \substr($kstr, 0, -1);

    $run = function (&$v) use ($dbName, $tableName, $sv, $kstr) {
        $vstr = implode(",", $v);
        $sv->run("INSERT INTO $dbName.$tableName ($kstr) VALUES $vstr");
    };

    $v = [];
    $curInserted = 0;
    $lastRecordedPct = 0;
    $maxToInsert = count($values);
    foreach ($values as $value) {
        $vv = [];
        foreach ($keys as $key) {
            if (!isset($hexKeys[$key])) {
                $vv[] = '"'.addslashes($value[$key]).'"';
            } else {
                $vv[] = "0x".$value[$key];
            }
        }

        $v[] = "(".implode(",", $vv).")";

        if (count($v) > $bulkSize) {
            $run($v);
            $curInserted += $bulkSize;
            $v = [];

            $pctInserted = floor($curInserted/$maxToInsert * 100);
            if ($pctInserted != $lastRecordedPct) {
                $lastRecordedPct = $pctInserted;
                $logger->info(sprintf("Inserted <%s>: %d/%d entries [%d%%]", $tableName, $curInserted, $maxToInsert, $pctInserted));
            }
        }
    }

    if (!empty($v)) {
        $run($v);
        $v = [];
    }
}

function parse_proto($fname, $delimiter="\t")
{
    if (!file_exists($fname) || !is_readable($fname)) {
        return false;
    }

    $header = null;
    $data = array();
    if (($handle = fopen($fname, 'r')) !== false) {
        while (($row = fgetcsv($handle, 1000, $delimiter)) !== false) {
            if (!$header) {
                $header = $row;
            } elseif (!isset($data[$row[0]])) {
                $data[$row[0]] = $row;
            }
        }
        fclose($handle);
    }

    return $data;
}
