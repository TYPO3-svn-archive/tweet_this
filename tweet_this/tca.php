<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

$TCA['tx_tweetthis_tweets'] = array (
	'ctrl' => $TCA['tx_tweetthis_tweets']['ctrl'],
	'interface' => array (
		'showRecordFieldList' => 'foreign_table,foreign_id,text,response'
	),
	'feInterface' => $TCA['tx_tweetthis_tweets']['feInterface'],
	'columns' => array (
		'crdate' => array (
			'exclude' => 0,
			'label' => 'LLL:EXT:tweet_this/locallang_db.xml:tx_tweetthis_tweets.crdate',
			'config' => array (
				'type' => 'input',
				'eval' => 'datetime',
			)
		),
		'cruser_id' => array (
			'exclude' => 0,
			'label' => 'LLL:EXT:tweet_this/locallang_db.xml:tx_tweetthis_tweets.cruser_id',
			'config' => array (
				'type' => 'select',
				'foreign_table' => 'be_users',
				'size' => 1,
			)
		),
		'foreign_table' => array (		
			'exclude' => 0,		
			'label' => 'LLL:EXT:tweet_this/locallang_db.xml:tx_tweetthis_tweets.foreign_table',		
			'config' => array (
				'type' => 'none',
			)
		),
		'foreign_id' => array (		
			'exclude' => 0,		
			'label' => 'LLL:EXT:tweet_this/locallang_db.xml:tx_tweetthis_tweets.foreign_id',		
			'config' => array (
				'type' => 'none',
			)
		),
		'text' => array (
			'exclude' => 0,		
			'label' => 'LLL:EXT:tweet_this/locallang_db.xml:tx_tweetthis_tweets.text',
			'config' => array (
				'type' => 'none',
			)
		),
		'response' => array (		
			'exclude' => 0,		
			'label' => 'LLL:EXT:tweet_this/locallang_db.xml:tx_tweetthis_tweets.response',		
			'config' => array (
				'type' => 'none',
			)
		),
	),
	'types' => array (
		'0' => array('showitem' => 'crdate, cruser_id, foreign_table;;;;1-1-1, foreign_id, text, response')
	),
	'palettes' => array (
		'1' => array('showitem' => '')
	)
);

?>