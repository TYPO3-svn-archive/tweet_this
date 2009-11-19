<?php


require_once(t3lib_extMgm::extPath('tweet_this', 'classes/class.tx_tweetthis_Helper.php'));

class tx_tweetthis_userField {

	public function renderFieldTweetThis($PA, $fobj) {
		$this->PA = $PA;
		$helper = tx_tweetthis_Helper::getInstance();

		if (intval($PA['row']['uid']) <= 0 || $PA['row']['hidden'] == 1 ) {
			// TODO l10n
			return 'Please save your record first.';
		}

		$tweet = $helper->getTweetFor(
			$PA['table'],
			$PA['row'],
			$PA['fieldConf']['config']
		);

		$content = $helper->getContent(
			'userfield_tweetthis.html',
			array(
				'PREFIX' => 'tx_tweetthis',
				'TWEETTEXT' => $tweet['text'],
				'MESSAGES' =>  $tweet['messages'],
				'RECORD_ID' => $PA['table'] . ':' . $PA['row']['uid'] . ':' . $PA['row']['pid'],
				'URL_TYPO3' => t3lib_div::getIndpEnv('TYPO3_SITE_URL') . TYPO3_mainDir,
			)
		);

		return $content;
	}

}

?>
