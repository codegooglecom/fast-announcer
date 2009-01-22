<?php

require ('./common.php');

db_init();

$result = mysql_query("SELECT count(DISTINCT ip) FROM tracker")
 or msg_die("MySQL error: " . mysql_error());
$row = mysql_fetch_row($result);
$ip_num = $row[0];

$result = mysql_query("SELECT count(DISTINCT info_hash) FROM tracker")
 or msg_die("MySQL error: " . mysql_error());
$row = mysql_fetch_row($result);
$hash_num = $row[0];

$result = mysql_query("SELECT count(info_hash) - count(DISTINCT info_hash) FROM tracker")
 or msg_die("MySQL error: " . mysql_error());
$row = mysql_fetch_row($result);
$delta = $row[0];

$action = $_GET['action'];
$order = $_GET['order'];

echo "Всего клиентов: $ip_num, всего торрентов: $hash_num, общих торрентов: $delta\n";

exit;

function msg_die ($msg)
{
	die("<br><font color=red>$msg</font><br>\n");
}
