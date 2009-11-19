<?php
require_once(t3lib_extMgm::extPath('tweet_this', 'classes/class.tx_tweetthis_Helper.php'));

class tx_tweetthis_AjaxHandler {
	/**
	 *
	 * @var tx_tweetthis_Helper 
	 */
	protected $helper;

	function __construct() {
		$this->helper = tx_tweetthis_Helper::getInstance();
	}

	public function sendTweet($params, $ajaxObj) {
		if((TYPO3_REQUESTTYPE & TYPO3_REQUESTTYPE_AJAX)) {
			$ajaxObj->setContentFormat('json');
			$tweet = t3lib_div::_GP('tweet');
			$record = t3lib_div::_GP('record');

			list($success, $message) = $this->twitterUpdate($tweet, $record);

			$ajaxObj->setContent( array(
				'success' => $success,
				'message' => $message
			));
		}
	}

	protected function twitterUpdate($status, $record_id = '') {
		$values = array(
			'status' => substr($status, 0, 140)
		);
		$response = $this->helper->requestTwitterApi('update', $values);
		$this->helper->storeTweet($record_id, $response, $response['twitterResponse']['text']);

		return $this->helper->getMessageByResponse($response);
	}


}

?>
