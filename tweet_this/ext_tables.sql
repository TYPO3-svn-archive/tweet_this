#
# Table structure for table 'tx_tweetthis_tweets'
#
CREATE TABLE tx_tweetthis_tweets (
	uid int(11) NOT NULL auto_increment,
	pid int(11) DEFAULT '0' NOT NULL,
	tstamp int(11) DEFAULT '0' NOT NULL,
	crdate int(11) DEFAULT '0' NOT NULL,
	cruser_id int(11) DEFAULT '0' NOT NULL,
	deleted tinyint(4) DEFAULT '0' NOT NULL,
	foreign_table tinytext,
	foreign_id tinytext,
	text tinytext,
	response text,
	
	PRIMARY KEY (uid),
	KEY parent (pid)
);