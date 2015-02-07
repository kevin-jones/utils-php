<?php

namespace UtilsPHP;

class Utils {
	public static function escape_data($data) {
		global $db;
		
		if(ini_get('magic_quotes_gpc')) {
			$data = stripslashes($data);
		}
		
		return $db->real_escape_string(trim($data));
	}
	
	public static function pa($array, $return = false) {
		$string = '<pre>'.print_r($array, 1).'</pre>';
		if($return) {
			return $string;
		} else {
			echo $string;
		}
	}

	/**
	 * Gets the contents (HTML or otherwise) of a URL
	 *
	 * @param $url - A valid URL
	 * @param $cache_path - A local filesystem path to store the contents in a cache
	 *                      If this exists the URL's contents will be retrieved from
	 *                      here instead of being downloaded
	 * @return array - [info] => (array) connection info
	 *               - [data] => The actual contents as a string
	 */
	public static function getUrl($url, $cache_path = false) {
		if($cache_path === false || !file_exists($cache_path)) {	
		    $ch = curl_init();
		    curl_setopt($ch, CURLOPT_URL, $url);
		    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
		    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
			$html = curl_exec($ch);
			$info = curl_getinfo($ch);
			curl_close($ch);
		    
		    $info['source'] = 'download';
		    
		    if($info['http_code'] == 200) {
		    	$info['status'] = 'Successful connection';		    	
		    	// save to cache
		    	if($cache_path !== false) {
		    		file_put_contents($cache_path, $html);			
				}
		    } else {
		    	$info['status'] = 'HTTP error';
		    }     
		} else {
			$html = file_get_contents($cache_path);
			$info['source'] = 'cache';
			$info['status'] = 'Content retrieved from cache';
		}
		
		$info['datasize'] = strlen($html);
		
		return array('info' => $info, 'data' => $html);
	}

	/*
	 * send email
	 *
	 * if $to is an array it should be key value pairs: array($email => $name) or just email addresses
	 *
	 * @param mixed $to
	 */
	public static function sendEmail($to, $subject, $body, $plain_text = FALSE) {
		$transport = Swift_MailTransport::newInstance();
		
		$message = Swift_Message::newInstance();
		$message->setTo($to);
		$message->setSubject($subject);
		
		if($plain_text) {
			$message->setBody($body);
		} else {
			$message->setBody($body, 'text/html');
			$message->addPart(strip_tags($body), 'text/plain');
		}
		
		$message->setFrom(EMAIL_FROM_ADDRESS, EMAIL_FROM_NAME);
				
		$mailer = Swift_Mailer::newInstance($transport);
		if($mailer->send($message)) {
			return true;
		} else {
			return false;
		}
	}

	/*
	 * Add a line to the log file
	 */
	public static function log($msg = '') {
		$msg = '['.date('Y-m-d H:i:s').'] ['.substr($_SERVER['REQUEST_URI'],0,10).'] '.$msg."\n";
		file_put_contents(LOG, $msg, FILE_APPEND);
	}
		
	/*
	 * Add user message (errors, information and succcess messages) to the user_msg array
	 */
	public static function msg($type, $msg = '') {
		// method has multiple implementations to allow quick message with type unset
		if(empty($msg)) {
			$msg = $type;
			$type = 'info';
		}
		
		if($type == 'error') {
			$type = 'danger';
		}
		
		if(isset($_SESSION)) {
			$_SESSION['user_msg'][] = '<div class="alert alert-'.$type.'">'.$msg.'</div>';
		}
	}
	
	public static function displayUserMessages() {
		if(isset($_SESSION['user_msg']) && !empty($_SESSION['user_msg'])) {

			echo '<div id="user_messages">';
			$previous_messages = array();
			foreach($_SESSION['user_msg'] as $msg) {
				if(!in_array($msg, $previous_messages)) {
					echo $msg;
					$previous_messages[] = $msg;
				}
			}
			
			echo '</div>';
			
			// clear message array
			$_SESSION['user_msg'] = FALSE;
		}
	}

	public static function importCsv($file, $limit = false, $selected_delimiter = ',') {	
		switch($selected_delimiter) {
			case "\t":
			case 'tab':
				$delimiter = "\t";
			break;
			case '|':
			case 'pipe':
				$delimiter = '|';
			break;
			default;
				$delimiter = ',';
			break;
		}
	
		if(!file_exists($file)) {
			echo 'file does not exist: '.$file.'<br>';
			return false;
		}

		$result = array(); 
		$size = filesize($file) + 1; 
		if($file = fopen($file, 'r')) {		
			$keys = fgetcsv($file, $size, $delimiter); 
			if(is_array($keys)) {
				$keys = array_map('trim',$keys);
				$column_count = count($keys);
				$row_count = 0;
				while ($row = fgetcsv($file, $size, $delimiter))  {					
					for($i = 0; $i < $column_count; $i++)  { 		
						$field = $keys[$i];
									
						if(array_key_exists($i, $keys)) {
							$result[$row_count][$field] = $row[$i]; 
						} 
					}
					
					if($limit !== false && ($row_count + 1) >= $limit) {
						break;
					}
					
					$row_count++;
				} 	
				
				fclose($file); 
				return $result;
			} else {
				return false; // could not find columns
			}
		} else {
			return false; // could not open file
		}
	}
	
	public static function hash($string, $temporary = false) {
		if(!$temporary) {
			return sha1($string.SALT);
		} else {
			return sha1($string.SALT.date('ymd'));
		}		
	}
	
	public static function simple_encrypt_number($number) {
		if(is_numeric($number)) {
			return strrev(base_convert($number, 10, 36));	
		} else {
			return false;
		}
	}
	
	public static function simple_decrypt_number($string) {
		$string = strrev($string);
		return (base_convert($string, 36, 10));
	}
	
	public static function sendDownloadHeaders($filename = 'example.txt') {
	    // disable caching
	    $now = gmdate("D, d M Y H:i:s");
	    header("Expires: Tue, 03 Jul 2001 06:00:00 GMT");
	    header("Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate");
	    header("Last-Modified: {$now} GMT");
	
	    // force download  
	    header("Content-Type: application/force-download");
	    header("Content-Type: application/octet-stream");
	    header("Content-Type: application/download");
	
	    // disposition / encoding on response body
	    header("Content-Disposition: attachment;filename={$filename}");
	    header("Content-Transfer-Encoding: binary");
	}
	
	public static function arrayToCsv(array &$array) {
		if (count($array) == 0) {
			return null;
		}
		
		ob_start();
		$df = fopen("php://output", 'w');
		fputcsv($df, array_keys(reset($array)));
		
		foreach ($array as $row) {
			fputcsv($df, $row);
		}
		
		fclose($df);
		return ob_get_clean();
	}

	public static function friendlyDate($date)
	{
		if(is_numeric($date)) {
			$timestamp = $date;
		} else {
			$timestamp = strtotime($date);
		}
		
		$timediff = time() - $timestamp;
		if ($timediff < 3600)
		{
			if ($timediff < 120)
			{
				$returndate = '1 minute ago';
			}
			else
			{
				$returndate =  intval($timediff / 60) . ' minutes ago';
			}
		}
		else if ($timediff < 7200)
		{
			$returndate = '1 hour ago';
		}
		else if ($timediff < 86400)
		{
			$returndate = intval($timediff / 3600) . ' hours ago';
		}
		else if ($timediff < 172800)
		{
			$returndate = 'yesterday';
		}
		else
		{
			$returndate = date('d M Y', $timestamp);
		}
		
		return $returndate;
	}
}
