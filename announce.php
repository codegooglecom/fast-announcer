<?php

# Данные для доступа к БД MySQL

$DBHost="localhost";
$DBUser="root";
$DBPass="root";
$DBName="tracker";

# -------------- Дальше ничего менять не надо! --------------------------------

define('TIMESTART', utime());
define('TIMENOW',   time());

$announce_interval = 1200;
$expire_factor     = 4;
$peer_expire_time  = TIMENOW - floor($announce_interval * $expire_factor);

if (get_magic_quotes_gpc())
{
	array_deep($_GET, 'stripslashes');
}
	
mysql_connect("$DBHost","$DBUser","$DBPass") or msg_die("Could not connect: " . mysql_error());
mysql_select_db("$DBName");

mysql_query("DELETE FROM tracker WHERE update_time < $peer_expire_time")
 or msg_die("MySQL error: " . mysql_error());

// Input var names
// String
$input_vars_str = array(
	'info_hash',
	'peer_id',
	'event',
	'descr',
	'mt',
	'pu',
);
// Numeric
$input_vars_num = array(
	'port',
	'uploaded',
	'downloaded',
	'left',
	'numwant',
	'compact',
	'l',
);

// Init received data
// String
foreach ($input_vars_str as $var_name)
{
	$$var_name = isset($_GET[$var_name]) ? (string) $_GET[$var_name] : null;
}
// Numeric
foreach ($input_vars_num as $var_name)
{
	$$var_name = isset($_GET[$var_name]) ? (float) $_GET[$var_name] : null;
}

$info_hash = $_GET['info_hash'];

// Verify required request params (info_hash, peer_id, port, uploaded, downloaded, left)
if (!isset($info_hash) || strlen($info_hash) != 20)
{
	// Похоже, к нам зашли через браузер.
	// Вежливо отправим человека на инструкцию по псевдотрекеру.
	echo "<meta http-equiv=refresh content=0;url=/retracker/>";
	die;
//	msg_die("Invalid info_hash: '$info_hash' length ".strlen($info_hash));
}
if (!isset($peer_id) || strlen($peer_id) != 20)
{
	msg_die('Invalid peer_id');
}
if (!isset($port) || $port < 0 || $port > 0xFFFF)
{
	msg_die('Invalid port');
}
if (!isset($uploaded) || $uploaded < 0)
{
	msg_die('Invalid uploaded value');
}
if (!isset($downloaded) || $downloaded < 0)
{
	msg_die('Invalid downloaded value');
}
if (!isset($left) || $left < 0)
{
	msg_die('Invalid left value');
}

// IP
$ip = $_SERVER['REMOTE_ADDR'];

if (!$tr_cfg['ignore_reported_ip'] && isset($_GET['ip']) && $ip !== $_GET['ip'])
{
	if (!$tr_cfg['verify_reported_ip'])
	{
		$ip = $_GET['ip'];
	}
	else if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && preg_match_all('#\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}#', $_SERVER['HTTP_X_FORWARDED_FOR'], $matches))
	{
		foreach ($matches[0] as $x_ip)
		{
			if ($x_ip === $_GET['ip'])
			{
				if (!$tr_cfg['allow_internal_ip'] && preg_match("#^(10|172\.16|192\.168)\.#", $x_ip))
				{
					break;
				}
				$ip = $x_ip;
				break;
			}
		}
	}
}

// Check that IP format is valid
if (!verify_ip($ip))
{
	msg_die("Invalid IP: $ip");
}
// Convert IP to HEX format
$ip_sql = encode_ip($ip);

// ----------------------------------------------------------------------------
// Start announcer
//
$info_hash_sql = rtrim(mysql_real_escape_string($info_hash), ' ');

// Stopped event
if ($event === 'stopped')
{
	mysql_query("DELETE FROM tracker WHERE info_hash = '$info_hash_sql' AND ip = '$ip_sql' AND port = $port")
	 or msg_die("MySQL error: " . mysql_error());

	die();
}

$main_tracker = "$mt";
// Escape strings
$descr = mysql_real_escape_string($descr);
$main_tracker = mysql_real_escape_string($main_tracker);
$pu = mysql_real_escape_string($pu);

// Update peer info
mysql_query("DELETE FROM tracker WHERE info_hash = '$info_hash_sql' AND ip = '$ip_sql' AND port = $port")
 or msg_die("MySQL error: " . mysql_error());
mysql_query("INSERT INTO tracker (info_hash, ip, port, update_time, descr, tracker, ip_real, publisherurl, pleft, downloaded, length)
 VALUES ('$info_hash_sql', '$ip_sql', $port, ". time() .", '$descr', '$main_tracker', '$ip', '$pu', '$left', '$downloaded', '$l')")
 or msg_die("MySQL error: " . mysql_error());

// Select peers

$result = mysql_query("SELECT ip, port FROM tracker WHERE info_hash = '$info_hash_sql'")
 or msg_die("MySQL error: " . mysql_error());

$rowset = array();

while ($row = mysql_fetch_array($result))
{
	$rowset[] = $row;
}

if ($compact_mode)
{
	$peers = '';

	foreach ($rowset as $peer)
	{
		$peers .= pack('Nn', ip2long(decode_ip($peer['ip'])), $peer['port']);
	}
}
else
{
	$peers = array();

	foreach ($rowset as $peer)
	{
		$peers[] = array(
			'ip'   => decode_ip($peer['ip']),
			'port' => intval($peer['port']),
		);
	}
}

// Generate output

$output = array(
	'interval'     => $announce_interval, // tracker config: announce interval (sec?)
	'min interval' => $announce_interval, // tracker config: min interval (sec?)
	'peers'        => $peers,
);

// Return data to client
echo bencode($output);

exit;

// ----------------------------------------------------------------------------
// Functions
//
function utime ()
{
	return array_sum(explode(' ', microtime()));
}

function msg_die ($msg)
{
	$output = bencode(array(
		'min interval'    => (int) 60,
		'failure reason'  => (string) $msg,
	));

	die($output);
}

function dummy_exit ($interval = 60)
{
	$output = bencode(array(
		'interval'     => (int)    $interval,
		'min interval' => (int)    $interval,
		'peers'        => (string) DUMMY_PEER,
	));

	die($output);
}

function encode_ip ($ip)
{
	$d = explode('.', $ip);

	return sprintf('%02x%02x%02x%02x', $d[0], $d[1], $d[2], $d[3]);
}

function decode_ip ($ip)
{
	return long2ip("0x{$ip}");
}

function verify_ip ($ip)
{
	return preg_match('#^(\d{1,3}\.){3}\d{1,3}$#', $ip);
}

function str_compact ($str)
{
	return preg_replace('#\s+#', ' ', trim($str));
}

function array_deep (&$var, $fn, $one_dimensional = false, $array_only = false)
{
	if (is_array($var))
	{
		foreach ($var as $k => $v)
		{
			if (is_array($v))
			{
				if ($one_dimensional)
				{
					unset($var[$k]);
				}
				else if ($array_only)
				{
					$var[$k] = $fn($v);
				}
				else
				{
					array_deep($var[$k], $fn);
				}
			}
			else if (!$array_only)
			{
				$var[$k] = $fn($v);
			}
		}
	}
	else if (!$array_only)
	{
		$var = $fn($var);
	}
}

// based on OpenTracker [http://whitsoftdev.com/opentracker]
function bencode ($var)
{
	if (is_int($var))
	{
		return 'i'. $var .'e';
	}
	else if (is_float($var))
	{
		return 'i'. sprintf('%.0f', $var) .'e';
	}
	else if (is_array($var))
	{
		if (count($var) == 0)
		{
			return 'de';
		}
		else
		{
			$assoc = false;

			foreach ($var as $key => $val)
			{
				if (!is_int($key) && !is_float($var))
				{
					$assoc = true;
					break;
				}
			}

			if ($assoc)
			{
				ksort($var, SORT_REGULAR);
				$ret = 'd';

				foreach ($var as $key => $val)
				{
					$ret .= bencode($key) . bencode($val);
				}
				return $ret .'e';
			}
			else
			{
				$ret = 'l';

				foreach ($var as $val)
				{
					$ret .= bencode($val);
				}
				return $ret .'e';
			}
		}
	}
	else
	{
		return strlen($var) .':'. $var;
	}
}

