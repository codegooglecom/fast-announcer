CREATE TABLE `tracker` (
  `info_hash` char(20) collate utf8_bin NOT NULL,
  `ip` char(8) collate utf8_bin NOT NULL,
  `port` int(11) NOT NULL,
  `update_time` int(11) NOT NULL,
  `descr` varchar(255) collate utf8_bin default NULL,
  `tracker` varchar(255) collate utf8_bin default NULL,
  `ip_real` varchar(32) collate utf8_bin default NULL,
  `publisherurl` varchar(255) collate utf8_bin default NULL,
  `pleft` bigint(16) default NULL,
  `downloaded` bigint(16) NOT NULL,
  `length` int(11) NOT NULL,
  PRIMARY KEY  (`info_hash`,`ip`,`port`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
