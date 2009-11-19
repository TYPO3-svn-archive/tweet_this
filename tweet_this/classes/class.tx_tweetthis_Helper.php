<?php

class tx_tweetthis_Helper {
	protected static $instance;
	protected $messages = array();
	protected $extConf = array();

	public static function getInstance() {
		if (!self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		  $this->extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['tweet_this']);
	}

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

		if ($text === false) {
			$text = '';
		}

		return array(
			'text' => $text,
			'messages' => implode('<br />', $this->messages)
		);

/*
		$lastUpdate = unserialize($PA['row']['tx_nwtt3blogtweet_lastUpdate']);
		$response = $lastUpdate['response']['response'];
		if (intval($lastUpdate['tstamp']) > 0 && intval($response['id']) > 0) {
			$tweetUrl = 'http://twitter.com/' . $response['user']['screen_name'] . '/status/' . $response['id'] ;
			$this->messages[] = '<a href="' . $tweetUrl . '" target="_blank">'.
				'tweeted at ' . date('d.m.Y H:i:s') .
				'</a>';
		}
*/
	}

	protected function buildNewTweet($table, $row, $config) {
		$url = $this->buildUrl($table, $row);
		/*
		if ($url == false) {
			return false;
		}
		*/

		// TODO make it configurable
		$tweet = '###TITLE### - ###URL###';
		$tweet = str_replace('###TITLE###', $row[$config['tweetthis_title']], $tweet);
		$tweet = str_replace('###URL###', $url, $tweet);

		return $tweet;
	}

	protected function buildUrl($table, $row) {
		$cObj = $this->createCObj(intval($this->extConf['pageid']));
			// EXT:linkhandler record:<tablename>:<ui>
		$link = $cObj->getTypoLink('','record:'.$table.':'.$row['uid']);
		if ($link == '') {
			$this->messages[] = 'Can\'t create link.';
			return false;
		}
		preg_match('/href=\"([^"]*)\"/', $link, $matches);
		$url = html_entity_decode($matches[1]);
		$url = t3lib_div::locationHeaderUrl($url);
		$url = $this->shortenUrl($url);

		return $url;
	}

	protected function shortenUrl($url) {
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
			return false;
		}
	}

	public function getContent($file, $data) {
		$content = t3lib_div::getURL(t3lib_div::getFileAbsFileName('EXT:tweet_this/res/' . $file));

		foreach ($data as $key => $value) {
			$content = str_replace('###' . $key . '###', $value, $content);
		}
		return $content;
	}

	public function requestTwitterApi($type, $values = null) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, 'http://twitter.com/statuses/' . $type . '.json');
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		if (is_array($values)) {
			$posQuery = '';
			foreach($values as $key => $value) {
				$postQuery .= urlencode($key) . '=' . urlencode($value) . '&';
			}
			rtrim($postQuery, '&');

			curl_setopt($ch, CURLOPT_POST, count($values));
			curl_setopt($ch, CURLOPT_POSTFIELDS, $postQuery);
		}

		// FIXME
		$userAuth = $this->extConf['twitter_username'] . ':' . $this->extConf['twitter_secret'];
		// 'etobi_dev:verySecret';

		curl_setopt($ch, CURLOPT_USERPWD, $userAuth);

		$response = curl_exec($ch);
		$info = curl_getinfo($ch);
		curl_close($ch);

		return array(
			'httpInfo' => $info,
			'twitterResponse' => json_decode($response, true)
		);
	}

	public function storeTweet($record_id, $response, $text) {
		if (empty($record_id)) {
			return false;
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

	public function getTweetUrl($twitterResponse) {
		$tweetUrl = 'http://twitter.com/' . $twitterResponse['user']['screen_name'] . '/status/' . $twitterResponse['id'] ;
		return $tweetUrl;
	}

	public function getTweetLink($twitterResponse) {
		$tweetUrl = $this->getTweetUrl($twitterResponse);
		$link = '<a href="' . $tweetUrl . '" target="_blank">'.  'tweeted at ' . date('d.m.Y H:i:s', strtotime($twitterResponse['created_at'])) .  ' (click here)</a>';
		return $link;
	}

	/**
	 *
	 * @param <type> $response
	 * @return <type> 
	 */
	public function getMessageByResponse($response) {
		if ($response['httpInfo']['http_code'] != 200) {
			$message = 'Status: ' . intval($response['httpInfo']['http_code']) .  ' - '.  $response['twitterResponse']['error'];
			return array(false, $message);

		} else {
			$message = $this->getTweetLink($response['twitterResponse']);

			return array(true, $message);
		}
	}

	/**
	 * http://www.typo3-scout.de/2008/05/28/cobject-im-backend/
	 *
	 * @return tslib_content
	 */
	 protected function createCObj($pid = 1) {
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
                $GLOBALS['TYPO3_DB']->debugOutput = false;

                $GLOBALS['TSFE']->initLLVars();
                $GLOBALS['TSFE']->initFEuser();

                // Look up the page
                $GLOBALS['TSFE']->sys_page = t3lib_div::makeInstance('t3lib_pageSelect');
                $GLOBALS['TSFE']->sys_page->init($GLOBALS['TSFE']->showHiddenPage);

                // If the page is not found (if the page is a sysfolder, etc), then return no URL, preventing any further processing which would result in an error page.
                $page = $GLOBALS['TSFE']->sys_page->getPage($pid);

                if (count($page) == 0) {
                        $GLOBALS['TYPO3_DB']->debugOutput = $sqlDebug;
                        return false;
                }

                // If the page is a shortcut, look up the page to which the shortcut references, and do the same check as above.
                if ($page['doktype'] == 4 && count($GLOBALS['TSFE']->getPageShortcut($page['shortcut'],$page['shortcut_mode'],$page['uid'])) == 0) {
                        $GLOBALS['TYPO3_DB']->debugOutput = $sqlDebug;
                        return false;
                }

                // Spacer pages and sysfolders result in a page not found page too…
                if ($page['doktype'] == 199 || $page['doktype'] == 254) {
                        $GLOBALS['TYPO3_DB']->debugOutput = $sqlDebug;
                        // return false;
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
                        return false;
                }

                $GLOBALS['TSFE']->getConfigArray();
                // $GLOBALS['TSFE']->getCompressedTCarray();

                $GLOBALS['TSFE']->inituserGroups();
                $GLOBALS['TSFE']->connectToDB();
                $GLOBALS['TSFE']->determineId();
                $GLOBALS['TSFE']->newCObj();
		return  $GLOBALS['TSFE']->cObj;
        }
}


?>
