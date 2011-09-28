<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2009 Tobias Liebig <liebig@networkteam.com>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  A copy is found in the textfile GPL.txt and important notices to the license
 *  from the author is found in LICENSE.txt distributed with these scripts.
 *
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

require_once(t3lib_extMgm::extPath('tweet_this', 'classes/class.tx_tweetthis_Helper.php'));

/**
 * class.tx_tweetthis_AjaxHandler.php
 *
 * @author Tobias Liebig <liebig@networkteam.com>
 */
class tx_tweetthis_AjaxHandler {
	/**
	 * @var tx_tweetthis_Helper 
	 */
	protected $helper;

	function __construct() {
		$this->helper = t3lib_div::makeInstance('tx_tweetthis_Helper');
	}

	/**
	 * send the tweet
	 * @param <type> $params
	 * @param TYPO3AJAX $ajaxObj
	 */
	public function sendTweet($params, $ajaxObj) {
		if((TYPO3_REQUESTTYPE & TYPO3_REQUESTTYPE_AJAX)) {
			$ajaxObj->setContentFormat('json');
			$tweet = t3lib_div::_GP('tweet');
			$record = t3lib_div::_GP('record');

			list($success, $message) = $this->helper->twitterUpdate($tweet, $record);

			$ajaxObj->setContent( array(
				'success' => $success,
				'message' => $message
			));
		}
	}
}

?>
