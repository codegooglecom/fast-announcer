<?php

// MySQL settings
$cfg['dbhost'] = "localhost";
$cfg['dbuser'] = "root";
$cfg['dbpass'] = "root";
$cfg['dbname'] = "tracker";

// Tracker
$cfg['announce_interval'] = 3600;
$cfg['expire_factor']     = 2;
$cfg['peers_limit']       = 100;   // Limit peers to select from DB
$cfg['cleanup_interval']  = 3600;  // Interval to execute cleanup
$cfg['compact_always']    = false; // Enable compact mode always (don't check clien capability)

// Cache
$cfg['cache_type']  = 'filecache';  // Available cache types: none, APC, memcached, sqlite, filecache
	
$cfg['cache']['memcached'] = array(
	'host'         => '127.0.0.1',
	'port'         => 11211,
	'pconnect'     => true,  // use persistent connection
	'con_required' => true,  // exit script if can't connect
);

$cfg['cache']['sqlite'] = array(
	'db_file_path' => '/path/to/sqlite.cache.db',  #  /dev/shm/sqlite.db
	'table_name'   => 'cache',
	'table_schema' => 'CREATE TABLE cache (
	                     cache_name        VARCHAR(255),
	                     cache_expire_time INT,
	                     cache_value       TEXT,
	                     PRIMARY KEY (cache_name)
	                   )',
	'pconnect'     => true,
	'con_required' => true,
	'log_name'     => 'CACHE',
);

$cfg['cache']['filecache']['path'] = './cache/';

define('PEER_HASH_PREFIX',  'peer_');
define('PEERS_LIST_PREFIX', 'peers_list_');

define('PEER_HASH_EXPIRE',  round($cfg['announce_interval'] * (0.85 * $cfg['expire_factor'])));  // sec
define('PEERS_LIST_EXPIRE', round($cfg['announce_interval'] * 0.7));  // sec

// Misc
define('DBG_LOG',   false); // Debug log