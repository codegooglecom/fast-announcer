<?php

require ('./common.php');

define('TIMESTART', utime());
define('TIMENOW',   time());

$announce_interval = $cfg['announce_interval'];
$peer_expire_time  = TIMENOW - floor($cfg['announce_interval'] * $cfg['expire_factor']);

if (get_magic_quotes_gpc())
{
	stripslashes($_GET['info_hash']);
}
	
db_init();

if (!$cache->used || ($cache->get('next_cleanup') < TIMENOW))
{
	cleanup($peer_expire_time);
}

// Input var names
// String
$input_vars_str = array(
	'info_hash',
	'peer_id',
	'event',
	'name',
	'mt',
	'comment',
	'isp',
);
// Numeric
$input_vars_num = array(
	'port',
	'uploaded',
	'downloaded',
	'left',
	'numwant',
	'compact',
	'size',
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

// Verify required request params (info_hash, peer_id, port, uploaded, downloaded, left)
if (!isset($info_hash) || strlen($info_hash) != 20)
{
	// Похоже, к нам зашли через браузер.
	// Вежливо отправим человека на инструкцию по псевдотрекеру.
	echo "<meta http-equiv=refresh content=0;url=http://re-tracker.ru/>";
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
$info_hash_hex = bin2hex($info_hash);
$info_hash_sql = rtrim(mysql_real_escape_string($info_hash), ' ');

// Peer unique id
$peer_hash = md5(
	rtrim($info_hash, ' ') . $peer_id . $ip . $port
);

// It's seeder?
$seeder  = ($left == 0) ? 1 : 0;

// Stopped event
if ($event === 'stopped')
{
	mysql_query("DELETE FROM tracker WHERE peer_hash = '$peer_hash'") or msg_die("MySQL error: " . mysql_error());
	die();
}

$mt = explode('/', $mt);
$main_tracker =& $mt[2];

// Escape strings
$name = mysql_real_escape_string($name);
$main_tracker = mysql_real_escape_string($main_tracker);
$comment = mysql_real_escape_string($comment);

$sql_data = array(
	'info_hash'    => $info_hash_sql,
	'peer_hash'    => $peer_hash,
	'ip'           => $ip_sql,
	'port'         => $port,
	'seeder'       => $seeder,
	'update_time'  => TIMENOW,
	'name'         => $name,
	'tracker'      => $main_tracker,
	'ip_real'      => $ip,
	'comment'      => $comment,
	'pleft'        => $left,
	'downloaded'   => $downloaded,
	'size'         => $size,
);

$columns = $values = $dupdate = array();

foreach ($sql_data as $column => $value)
{
	$columns[] = $column;
	$values[]  = "'" . $value . "'";
	$dupdate[] = $column . " = '" . $value . "'";
}

$columns_sql = implode(', ', $columns);
$values_sql = implode(', ', $values);
$dupdate_sql = implode(', ', $dupdate);

// Update peer info
mysql_query("INSERT INTO tracker ($columns_sql) VALUES ($values_sql)
			ON DUPLICATE KEY UPDATE $dupdate_sql") or msg_die("MySQL error: " . mysql_error());

unset($sql_data, $columns, $values, $dupdate, $columns_sql, $values_sql, $dupdate_sql);

// Select peers
$output = $cache->get(PEERS_LIST_PREFIX . $info_hash_hex);

if (!$output)
{
	$limit = (int) (($numwant > $cfg['peers_limit']) ? $cfg['peers_limit'] : $numwant);
	$result = mysql_query("SELECT ip, port, COUNT(ip) AS peers_count, seeder
						   FROM tracker WHERE info_hash = '$info_hash_sql' GROUP BY ip LIMIT $limit") 
	or msg_die("MySQL error: " . mysql_error());
	
	$rowset = array();
	$seeders = $leechers = 0;

	while ($row = mysql_fetch_array($result))
	{
		$rowset[] = $row;

		if($row['seeder'])
		{
			$seeders += 1;
		}
		$leechers = $row['peers_count'] - $seeders;
	}	

	$compact_mode = ($cfg['compact_always'] || !empty($compact));

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
		'interval'     => (int) $announce_interval, // tracker config: announce interval (sec?)
		'min interval' => (int) 1, // tracker config: min interval (sec?)
		'peers'        => $peers,
		'complete'     => (int) $seeders,
		'incomplete'   => (int) $leechers,
	);

	$peers_list_cached = $cache->set(PEERS_LIST_PREFIX . $info_hash_hex, $output, PEERS_LIST_EXPIRE);
}

// Return data to client
echo bencode($output);

exit;