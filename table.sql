DROP TABLE IF EXISTS `tracker`;
CREATE TABLE `tracker` (
  `info_hash` char(20) collate utf8_bin NOT NULL,
  `peer_hash` char(32) collate utf8_bin NOT NULL,
  `ip` char(8) collate utf8_bin NOT NULL,
  `port` int(11) NOT NULL,
  `seeder` tinyint(1) NOT NULL,
  `update_time` int(11) NOT NULL,
  `name` varchar(255) collate utf8_bin default NULL,
  `size` bigint(20) NOT NULL,
  `tracker` varchar(255) collate utf8_bin default NULL,
  `comment` varchar(255) collate utf8_bin default NULL,
  `ip_real` varchar(32) collate utf8_bin default NULL,
  `pleft` bigint(16) default NULL,
  `downloaded` bigint(16) NOT NULL,
  PRIMARY KEY  (`info_hash`),
  UNIQUE KEY `peer_hash` (`peer_hash`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin;