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

require_once(t3lib_extMgm::extPath('tweet_this','res/twitteroauth/twitteroauth.php'));

/**
 * class.tx_tweetthis_Helper.php
 *
 * @author Tobias Liebig <liebig@networkteam.com>
 */
class tx_tweetthis_Helper {
	
	var $consumerKey = '0v5AbsCNzwscTTEYwSlDHw';
	var $consumerSecret = 'Fsqf5brQyzaam8ydRBKbpa9JsgMHQdv9Haw7KAdPWY';
	
	/**
	 * instance for singleton
	 * @var tx_tweetthis_Helper
	 */
	protected static $instance;

	/**
	 * Collection of messages
	 * @var array
	 */
	protected $messages = array();

	/**
	 * Extension configuration
	 * @var array
	 */
	protected $extConf = array();

	/**
	 * get/create singelton
	 * @return tx_tweetthis_Helper
	 */
	public static function getInstance() {
		if (!self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		  $this->extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['tweet_this']);
	}

	/**
	 * build a tweet or read the last saved tweet for a given record
	 *
	 * @param string $table Table name
	 * @param array $row record data
	 * @param array $config configuration from TCA config
	 * @return string the text of the tweet
	 */
	public function getTweetFor($table, $row, $config) {
		$this->messages = array();

		$recentTweets = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('*', 'tx_tweetthis_tweets',
			'deleted = 0 ' .
				' AND foreign_table = "' . $table . '"' .
				' AND foreign_id = ' . intval($row['uid']),
			'',
			'crdate DESC',
			'1'
		);

		if (count($recentTweets) == 1) {
			$text = $recentTweets[0]['text'];
			$response = unserialize($recentTweets[0]['response']);
			list($success, $message) = $this->getMessageByResponse($response);
			$this->messages[] = $message;
		} else {
			$text = $this->buildNewTweet($table, $row, $config);
		}

		if ($text === FALSE) {
			$text = '';
		}

		return $text;
	}

	/**
	 * get and reset all collected messages
	 * @return string collected messages
	 */
	public function getMessages() {
		$messages = implode('<br />', $this->messages);
		$this->messages = array();
		return $messages;
	}

	/**
	 * compile a new tweet
	 * 
	 * @param <type> $table
	 * @param <type> $row
	 * @param <type> $config
	 * @return <type> string
	 */
	protected function buildNewTweet($table, $row, $config) {
		$url = $this->buildUrl($table, $row);

		$tweet = $this->extConf['tweet'];
		$tweet = str_replace('###TEXT###', $row[$config['tweetthis_title']], $tweet);
		$tweet = str_replace('###URL###', $url, $tweet);
		$tweet = str_replace('###SIGNATURE###', $GLOBALS['BE_USER']->user['tx_tweetthis_signature'], $tweet);

		return $tweet;
	}

	/**
	 * build and shorten a url for a given record
	 *
	 * @param <type> $table
	 * @param <type> $row
	 * @return string url for the given record
	 */
	protected function buildUrl($table, $row) {
		$cObj = $this->createCObj(intval($this->extConf['pageid']));

		if ($table == 'pages') {
			$parameter = $row['uid'];
			
		} else if ($table != 'tt_content' 
		  && t3lib_extMgm::isLoaded('linkhandler')) {
			$parameter = 'record:'.$table.':'.$row['uid'];
		}
		
		if (empty($parameter) 
		  && !empty($row['pid'])) {
			$parameter = $row['pid'];
		}

		$link = $cObj->getTypoLink('', $parameter);
		if ($link == '') {
			$this->messages[] = 'Can\'t create link.';
			return FALSE;
		}
		// getTypoLink_URL does not work with EXT:linkhandler, so we need to preg_match the URL from the anchor
		preg_match('/href=\"([^"]*)\"/', $link, $matches);
		$url = html_entity_decode($matches[1]);

		// FIXME may not work if installed in sub directory
		$url = t3lib_div::getIndpEnv('TYPO3_REQUEST_HOST') .'/'. $url;

		$url = $this->shortenUrl($url);

		return $url;
	}

	/**
	 * shorten url using bit.ly
	 * 
	 * @param string $url long url
	 * @return string short url
	 */
	protected function shortenUrl($url) {
	
		if (empty($this->extConf['bitly_username']) 
		  || empty($this->extConf['bitly_apikey'])) {
			return $url;
		} 
	
		$ch = curl_init();

		$apiUrl = 'http://api.bit.ly/shorten?version=2.0.1' .
			'&longUrl=' . urlencode($url) . 
			'&login=' . $this->extConf['bitly_username'] . 
			'&apiKey=' . $this->extConf['bitly_apikey'];

		curl_setopt($ch, CURLOPT_URL, $apiUrl);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($ch);
		$response = json_decode($response, true);
		$info = curl_getinfo($ch);
		curl_close($ch);

		if ($info['http_code'] == 200 && $response['statusCode'] == 'OK') {
			list($result,) = $response['results'];
			return $response['results'][$url]['shortUrl'];
		} else {
			if ($response['errorCode']) {
				$this->messages[] = 'Can\'t shorten URL: ' . $response['errorCode'] . ':' . $response['errorMessage'];
			} else {
				$this->messages[] = 'Can\'t shorten URL: HTTP status ' . $info['http_code'];
			}
			return $url;
		}
	}

	/**
	 * read a file from the res folder and replace the markers
	 * @param string $file
	 * @param array $markerArray
	 * @return string content of the given file with replaced marker
	 */
	public function getTemplated($file, array $markerArray) {
		$content = t3lib_div::getURL(t3lib_div::getFileAbsFileName('EXT:tweet_this/res/' . $file));

		foreach ($markerArray as $key => $value) {
			$content = str_replace('###' . $key . '###', $value, $content);
		}
		return $content;
	}

	/**
	 * send a request to the twitter api
	 *
	 * @param string $type update
	 * @param array $values additional data for post request
	 * @return array response ('twitterResponse' => Twitter APi Response)
	 */
	public function requestTwitterApi($type, $values = null) {
		$access_token = t3lib_div::makeInstance('t3lib_Registry')->get('tx_tweetthis', 'accessToken');
		
		if (!empty($access_token['oauth_token']) && !empty($access_token['oauth_token_secret'])) {
			$connection = new TwitterOAuth($this->consumerKey, $this->consumerSecret, $access_token['oauth_token'], $access_token['oauth_token_secret']);
			$connection->decode_json = FALSE;
			$post = json_decode($connection->post('statuses/update', $values), TRUE);
			
			return array(
				'twitterResponse' => $post
			);
		}
	}

	/**
	 * store a tweet record
	 * 
	 * @param string $record_id identifier table:uid:pid like 'tt_news:1:42'
	 * @param array $response response array returned from requestTwitterApi
	 * @param string $text The text of the tweet
	 * @return void
	 */
	public function storeTweet($record_id, $response, $text) {
		if (empty($record_id)) {
			return FALSE;
		}
		list($table, $uid, $pid) = explode(':', $record_id);
		$GLOBALS['TYPO3_DB']->exec_INSERTquery(
			'tx_tweetthis_tweets',
			array(
				'pid'		=> $pid,
				'crdate'	=> time(),
				'cruser_id'	=> $GLOBALS['BE_USER']->user['uid'],
				'tstamp'	=> time(),
				'foreign_table' => $table,
				'foreign_id'	=> intval($uid),
				'response'	=> serialize($response),
				'text'		=> $text,
			)
		);
	}

	/**
	 * get the twitter url for the tweet
	 *
	 * @param array $response response array returned from requestTwitterApi
	 * @return string url
	 */
	public function getTweetUrl($twitterResponse) {
		// FIXME
		$tweetUrl = 'http://twitter.com/' . $twitterResponse['user']['screen_name'];
			// . '/status/' . $twitterResponse['id'] ;
		return $tweetUrl;
	}

	/**
	 * get a link (<a>) for a tweet
	 * 
	 * @param array $response response array returned from requestTwitterApi
	 * @return string link tag
	 */
	public function getTweetLink($twitterResponse) {
		$tweetUrl = $this->getTweetUrl($twitterResponse);
		$link = '<a href="' .
			$tweetUrl .
			'" target="_blank">' .
			'tweeted at ' .
			date('d.m.Y H:i:s', strtotime($twitterResponse['created_at'])) .
			' (click here)</a>';
		return $link;
	}

	/**
	 * takes a response (requestTwitterApi) and returns a information "message"
	 *
	 * @param array $response response array returned from requestTwitterApi
	 * @return array true/false if tweet was successful; message
	 */
	public function getMessageByResponse($response) {
		if (!empty($response['twitterResponse']['error'])) {
			$message = $response['twitterResponse']['error'];
			return array(FALSE, $message);

		} else {
			$message = $this->getTweetLink($response['twitterResponse']);

			return array(TRUE, $message);
		}
	}

	/**
	 * create a TSFE and return the cObj
	 * http://www.typo3-scout.de/2008/05/28/cobject-im-backend/
	 *
	 * @param int page id to initialize the TSFE
	 * @return tslib_content
	 */
	 protected function createCObj($pid = 1) {
		if ($GLOBALS['TSFE']) {
			return $GLOBALS['TSFE']->cObj;
		}

                require_once(PATH_site.'typo3/sysext/cms/tslib/class.tslib_fe.php');
                require_once(PATH_site.'t3lib/class.t3lib_userauth.php');
                require_once(PATH_site.'typo3/sysext/cms/tslib/class.tslib_feuserauth.php');
                require_once(PATH_site.'t3lib/class.t3lib_cs.php');
                require_once(PATH_site.'typo3/sysext/cms/tslib/class.tslib_content.php') ;
                require_once(PATH_site.'t3lib/class.t3lib_tstemplate.php');
                require_once(PATH_site.'t3lib/class.t3lib_page.php');
                require_once(PATH_site.'t3lib/class.t3lib_timetrack.php');

                // Finds the TSFE classname
                $TSFEclassName = t3lib_div::makeInstanceClassName('tslib_fe');

                // Create the TSFE class.
                $GLOBALS['TSFE'] = new $TSFEclassName($GLOBALS['TYPO3_CONF_VARS'], $pid, '0', 0, '','','','');

                $temp_TTclassName = t3lib_div::makeInstanceClassName('t3lib_timeTrack');
                $GLOBALS['TT'] = new $temp_TTclassName();
                $GLOBALS['TT']->start();

                $GLOBALS['TSFE']->config['config']['language']=$_GET['L'];

                // Fire all the required function to get the typo3 FE all set up.
                $GLOBALS['TSFE']->id = $pid;
                // $GLOBALS['TSFE']->connectToMySQL();

                // Prevent mysql debug messages from messing up the output
                $sqlDebug = $GLOBALS['TYPO3_DB']->debugOutput;
                $GLOBALS['TYPO3_DB']->debugOutput = FALSE;

                $GLOBALS['TSFE']->initLLVars();
                $GLOBALS['TSFE']->initFEuser();

                // Look up the page
                $GLOBALS['TSFE']->sys_page = t3lib_div::makeInstance('t3lib_pageSelect');
                $GLOBALS['TSFE']->sys_page->init($GLOBALS['TSFE']->showHiddenPage);

                // If the page is not found (if the page is a sysfolder, etc), then return no URL, preventing any further processing which would result in an error page.
                $page = $GLOBALS['TSFE']->sys_page->getPage($pid);

                if (count($page) == 0) {
                        $GLOBALS['TYPO3_DB']->debugOutput = $sqlDebug;
                        return FALSE;
                }

                // If the page is a shortcut, look up the page to which the shortcut references, and do the same check as above.
                if ($page['doktype'] == 4 && count($GLOBALS['TSFE']->getPageShortcut($page['shortcut'],$page['shortcut_mode'],$page['uid'])) == 0) {
                        $GLOBALS['TYPO3_DB']->debugOutput = $sqlDebug;
                        return FALSE;
                }

                // Spacer pages and sysfolders result in a page not found page too…
                if ($page['doktype'] == 199 || $page['doktype'] == 254) {
                        $GLOBALS['TYPO3_DB']->debugOutput = $sqlDebug;
                        // return FALSE;
                }

                $GLOBALS['TSFE']->getPageAndRootline();
                $GLOBALS['TSFE']->initTemplate();
                $GLOBALS['TSFE']->forceTemplateParsing = 1;

                // Find the root template
                $GLOBALS['TSFE']->tmpl->start($GLOBALS['TSFE']->rootLine);

                // Fill the pSetup from the same variables from the same location as where tslib_fe->getConfigArray will get them, so they can be checked before this function is called
                $GLOBALS['TSFE']->sPre = $GLOBALS['TSFE']->tmpl->setup['types.'][$GLOBALS['TSFE']->type];        // toplevel - objArrayName
                $GLOBALS['TSFE']->pSetup = $GLOBALS['TSFE']->tmpl->setup[$GLOBALS['TSFE']->sPre.'.'];

                // If there is no root template found, there is no point in continuing which would result in a 'template not found' page and then call exit php. Then there would be no clickmenu at all.
                // And the same applies if pSetup is empty, which would result in a "The page is not configured" message.
                if (!$GLOBALS['TSFE']->tmpl->loaded || ($GLOBALS['TSFE']->tmpl->loaded && !$GLOBALS['TSFE']->pSetup)) {
                        $GLOBALS['TYPO3_DB']->debugOutput = $sqlDebug;
                        return FALSE;
                }

                $GLOBALS['TSFE']->getConfigArray();
                // $GLOBALS['TSFE']->getCompressedTCarray();

                $GLOBALS['TSFE']->inituserGroups();
                $GLOBALS['TSFE']->connectToDB();
                $GLOBALS['TSFE']->determineId();
                $GLOBALS['TSFE']->newCObj();
			return  $GLOBALS['TSFE']->cObj;
        }

	/**
	 *
	 * @param string $status tweet text
	 * @param string $record_id record identifier
	 * @return string message
	 */
	public function twitterUpdate($status, $record_id = '') {
		$values = array(
			'status' => substr($status, 0, 140)
		);
		
		$access_token = t3lib_div::makeInstance('t3lib_Registry')->get('tx_tweetthis', 'accessToken');
		
		$response = $this->requestTwitterApi('update', $values);
		$this->storeTweet($record_id, $response, $response['twitterResponse']['text']);

		return $this->getMessageByResponse($response);
	}

}

?>