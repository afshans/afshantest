<?php

/**
 * TODO :-
 * Impelement business logic for getBitBucketCommitCount function
 */

/**
 * Check if curl_init function exists or not
 */

if (!function_exists('curl_init')) {
	trigger_error('Application needs CURL PHP extension.');
}

/**
 * Check if json_decode function available
 */
if (!function_exists('json_decode')) {
	trigger_error('Application needs JSON PHP extension.');
}
/**
 * check if register_argc_argv variable is set as true to get command line arguments
 */
if (!ini_get('register_argc_argv')) {
	trigger_error('Application needs register_argc_argv PHP variable as true.');
}

// set max time 
set_time_limit(0);

/**
 * CommitCount class
 */
class CommitCount{
	
	private $errors = array();	// get all errors
	private $errorCount = 0;

	private $user = null;	// provided user name
	private $passwd = null;
	private $url = '';		// provided URL for github and bitbucket
	private $contributor = '';	// contributor name

	private $host = '';		// host (extracted from URL) 
	private $repo = '';		// repository name extracted from URL
	private $account_name = '';	// user name extracted from URL
	
	/**
	 * Constructor to run application
	 */
	public function __construct($argv = array()){
		
		$this->setParameter($argv);			
		$this->run();		
	}
	
	/**
	 * Set parameters from command line arguments
	 */
	private function setParameter($argv){
			
		// CLI argv count which must be 7 including filename
		if(count($argv) == 7){

			if( ( isset($argv[1]) && ($argv[1] == '-u') ) && (isset($argv[3]) && ($argv[3] == '-p')) ){

				$this->setUser($argv[2]);
				$this->setPassword($argv[4]);
				$this->setUrl($argv[5]);
				$this->setContributor($argv[6]);
			}
			else{
				$this->setError('Invalid parameters.');
			}
		}
		else{
			$this->setError('Insufficient parameters.');
		}
	}
	
	// setter function to set user
	public function setUser($user){
		$this->user = $user;
	}

	public function setPassword($pwd){
		$this->passwd = $pwd;
	}

	public function setUrl($url){
		$this->url = $url;
	}

	public function setContributor($contributor){
		$this->contributor = $contributor;
	}
	
	// run application
	public function run(){	

		$this->validate();

		if(!$this->errorCount){
			
			$this->extractParamaters();

			switch($this->host){
				case 'github.com':
					$count = $this->getGitHubCommitCount();
					echo "\n Total commit by `".$this->contributor. "` is ".$count;
				break;
				case 'bitbucket.org':
					$count = $this->getBitBucketCommitCount();
					//$count = $this->getGitHubCommitCount();
					echo "\n Total commit by `".$this->contributor. "` is ".$count;
				break;
				default:
					$this->setError('Invalid Host');
				break;
			}

		}
		else{
			//$this->logErrors();
		}
	}

	private function getResponse($url){

		echo "\nProcessing request,It might take few minutes. Please wait....";

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch,CURLOPT_USERAGENT,'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch,CURLOPT_TIMEOUT,1000);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT ,0); 

		$response = curl_exec($ch);	

		if(curl_errno($ch))
		{
		    $this->setError('Error occurred while processing request...');
		}

		curl_close($ch);
		return $response;
	}

	private function getGitHubCommitCount(){

		$count = 0;

		$url = 'https://api.github.com/repos/' . $this->account_name.'/'. $this->repo .'/stats/contributors';
		
		$data = json_decode($this->getResponse($url), true);

		if(isset($data['message'])){
			$this->setError('Record not found.');
		}
		else if(is_array($data)){

			$found = false;

			foreach($data as $_data){
				if(isset($_data['author']) && ($_data['author']['login'] == $this->contributor)){

					$count = isset($_data['total']) ? $_data['total'] : 0;
					$found = true;
					break;
				}
			}

			if(!$found){
				$this->setError('Contributor not found.');
			}
		}

		return $count;
	}
	
	 
	private function getBitBucketCommitCount(){
		$count = 0;

		$url = 'https://bitbucket.org/api/2.0/repositories/' . $this->account_name.'/'. $this->repo .'/commits';

		$data = json_decode($this->getResponse($url), true);
		
		if(isset($data['message'])){
			$this->setError('Record not found.');
		}
		else if(is_array($data)){

			$found = false;

			foreach($data as $_data){
				if(isset($_data['author']) && ($_data['author']['username'] == $this->contributor)){

					$count = isset($_data['total']) ? $_data['total'] : 0;
					$found = true;
					break;
				}
			}

			if(!$found){
				$this->setError('Contributor not found.');
			}
		}

		return $count;
	}

	private function extractParamaters(){

		if(preg_match('/^https:\/\/(.+)\/(.+)\/(.+)$/i', $this->url, $matches)){

			if(count($matches) == 4){

				$this->host = $matches[1];
				$this->account_name = $matches[2];
				$this->repo = $matches[3];
			}
		}
	}

	
	private function logErrors(){
		if($this->errorCount){
			foreach($this->errors as $i => $error){
				echo "\n"."- ".$error;
			}
		}
	}

	/**
	private function setError($error){

		$this->errors[] = $error;
		$this->errorCount++;
	}
	**/

	private function setError($error){
		die("\n- ".$error);
	}

	private function validate(){
		
		if(empty($this->user)){

			$this->setError('User is required.');
		}

		if(empty($this->passwd)){

			$this->setError('Password is required.');
		}

		if(empty($this->url)){

			$this->setError('URL is required.');
		}
		else if(!preg_match('/^https:\/\/(github.com|bitbucket.org)\/([a-z0-9_-]+)\/([a-z0-9_-]+)$/i', $this->url)){
			
			$this->setError('Invalid repository URL.');
		}

		if(empty($this->passwd)){

			$this->setError('Password is required.');
		}

	}


}

// checks if command line argument variable is set or not
if(isset($argv)){	
	// run application
	new CommitCount($argv);
}
?>