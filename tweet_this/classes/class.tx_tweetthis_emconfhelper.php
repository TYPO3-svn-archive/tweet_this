<?php

require_once(t3lib_extMgm::extPath('tweet_this','res/twitteroauth/twitteroauth.php'));

class tx_tweetthis_emconfhelper {
	
	var $consumerKey = '0v5AbsCNzwscTTEYwSlDHw';
	var $consumerSecret = 'Fsqf5brQyzaam8ydRBKbpa9JsgMHQdv9Haw7KAdPWY';
	
	public function authenticateTwitter(array $params, $pObj) {
		$access_token = FALSE;
		
		if (!empty($_POST['reset_oauth'])) {
			t3lib_div::makeInstance('t3lib_Registry')->set('tx_tweetthis', 'accessToken', NULL);
		}
		
		if (!empty($_POST['oauth_verifier'])) {
			$connection = new TwitterOAuth($this->consumerKey, $this->consumerSecret, $_POST['oauth_token'], $_POST['oauth_token_secret']);
			$access_token = $connection->getAccessToken($_POST['oauth_verifier']);
			if (!empty($access_token['oauth_token']) && !empty($access_token['oauth_token_secret'])) {
				t3lib_div::makeInstance('t3lib_Registry')->set('tx_tweetthis', 'accessToken', $access_token);
			} else {
				t3lib_div::debug($access_token, 'error recieving access_token');
			}
		}
		
		$access_token = t3lib_div::makeInstance('t3lib_Registry')->get('tx_tweetthis', 'accessToken');
		
		if (!empty($access_token['oauth_token']) && !empty($access_token['oauth_token_secret'])) {
			$connection = new TwitterOAuth($this->consumerKey, $this->consumerSecret, $access_token['oauth_token'], $access_token['oauth_token_secret']);
			$response = $connection->get('account/verify_credentials');
			
			if (!empty($response->error) || empty($response->screen_name)) {
				t3lib_div::makeInstance('t3lib_Registry')->set('tx_tweetthis', 'accessToken', NULL);
				return $response->error;
				
			} else {
				return ' <b>Username:</b> ' . $response->screen_name .
					' <img src="' . $response->profile_image_url . '" />' .
					'<br/> <input type="checkbox" name="reset_oauth" value="on" /> Check this to reset authentication.';
			}
			
		} else {
			$content = '';
			
				$connection = new TwitterOAuth($this->consumerKey, $this->consumerSecret);
				$request_token = $connection->getRequestToken();
			
				if (empty($request_token['oauth_token']) || empty($request_token['oauth_token_secret'])) {
					t3lib_div::debug($request_token);
					$content = 'Error';
				
				} else {
					$content = '<a href="http://api.twitter.com/oauth/authorize?oauth_token=' . $request_token['oauth_token'] . '" target="_blank">' .
						'click here to get authentication PIN' .
						'</a>';
					$content .= '<br/><br/>' .
						'<label>PIN:</label><input name="oauth_verifier" type="text" />' .
						'<input name="oauth_token" type="hidden" value="' . $request_token['oauth_token'] . '" />' .
						'<input name="oauth_token_secret" type="hidden" value="' . $request_token['oauth_token_secret'] . '" />';
				}
			return $content;
		}
	}
}
?>