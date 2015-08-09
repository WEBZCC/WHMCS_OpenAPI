<?php
/**
 * @package		WHMCS openAPI 
 * @version     1.2.3
 * @author      Stergios Zgouletas <info@web-expert.gr>
 * @link        http://www.web-expert.gr
 * @copyright   Copyright (C) 2010 Web-Expert.gr All Rights Reserved
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
**/
if(!defined("WHMCS")) die("This file cannot be accessed directly");

class WOAAPI{
	private static $version='1.2.3';
	protected $debug=false;
	protected $moduleConfig=array();
	protected $whmcsconfig=null;
	protected $timeout=30;
	protected $updateServers=array();
	private static $instance;
	protected $db=null;
	protected $emailHash=null;
	
	function __construct()
	{
		$this->db=WOADB::getInstance();
		$whmcs=$this->getWhmcsConfig();
		list($Version,$Release)=@explode('-',$whmcs["Version"]);
		if(!defined('WHMCSV')) define('WHMCSV',$Version);
		$this->setUpdateServer('https://raw.githubusercontent.com/zstergios/WHMCS_OpenAPI/master/update.ini','openAPI');
	}
	
	public static function getInstance()
	{
		if(!self::$instance) self::$instance = new self();
		return self::$instance;
	}
	
	public static function getVersion()
	{
		return self::$version;
	}
	
	function setDebug($status)
	{
		$this->debug=(boolean)$status;
	}
	
	public function microtime_float()
	{
		list ($msec, $sec) = @explode(' ', microtime());
		$microtime = (float)$msec + (float)$sec;
		return $microtime;
	}
	
	public function getLang($key,$language=null)
	{
		global $_LANG;
		$languageTxt=isset($_LANG[$key])?$_LANG[$key]:'';
		if($this->debug && empty($languageTxt)) $languageTxt='*'.$key.'*';
		return $languageTxt;
	}
	
	public function getUpdateServer($module)
	{
		return isset($this->updateServers[$module])?$this->updateServers[$module]:NULL;
	}
	
	public function setUpdateServer($url,$module)
	{
		$this->updateServers[$module]=$url;
	}
	
	public function checkUpdate($currentVersion,$module,$url='')
	{
		
		if(!empty($url))
		{
			$this->setUpdateServer($url,$module);
		}
			
		if(isset($this->updateServers[$module]))
		{
			$data=$this->getRemoteData($this->updateServers[$module],array('version'=>$currentVersion));
		}
		else
		{
			$data=array('response'=>null,'error'=>'Update server URL for "'.$module.'" has not been set');
		}
		
		return $data;
	}
	
	public function callAPI($values,$username=NULL)
	{
		$rs = localAPI($values["action"],$values,$username);
		return $rs;
	}
	
	function printJSON($data=array()){
		header('Content-Type: application/json; charset=utf-8',true);
		exit(json_encode($data));
	}
	
	public static function redirect($url,$seconds=0)
	{
		if(headers_sent() || $seconds>0)
		{
			echo "<script>setTimeout(\"location.href = '".$url."';\",".($seconds*1000).");</script>";
		}
		else
		{
			@header('Location:'.$url);
			exit();
		}
	}
	
	/*Current Page URL*/
	public function curPageURL($onlyDomain=false) {
		$pageURL = 'http';
		if($_SERVER["HTTPS"] == "on") $pageURL .= "s";
		$pageURL .= "://".$_SERVER["SERVER_NAME"];
		if($onlyDomain) return $pageURL;
		$pageURL.=$_SERVER["REQUEST_URI"];
		return str_replace('&amp;','&',$pageURL);
	}
	
	
	//Utf-8 String Handling
	################################################
	function strpos($str,$needle,$offset=0)
	{
		return (function_exists('mb_strpos'))?mb_strpos($str,$needle,$offset):substr($str,$needle,$offset);
	}
	
	function substr($str,$i=null,$j=null)
	{
		return (function_exists('mb_substr'))?mb_substr($str,$i,$j):substr($str,$i,$j);
	}
	
	function strlen($str)
	{
		return (function_exists('mb_strlen'))?mb_strlen($str):strlen($str);
	}
	
	function cfirst($str){
		return ucfirst(strtolower($str));
	}
	
	//File/Folder Functions
	public function getFiles($folderPath,$ext=array())
	{
		$folderPath=rtrim($folderPath,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
		if(!is_dir($folderPath)) return array();
		$files=array();
		$results = scandir($folderPath);
		foreach ($results as $file)
		{
			if ($file === '.' || $file === '..' || !is_file($folderPath.$file)) continue;
			$extension=@end(@explode(".",$file,2));
			$filename=str_replace('.'.$extension,'',$file);
			if(count($ext)>0 && !in_array(".".$extension,$ext)) continue;
			$files[]=$file;
		}
		return $files;
	}
	
	public function getFolders($folderPath)
	{
		if(!is_dir($folderPath)) return array();
		
		$folders=array();
		foreach (new DirectoryIterator($folderPath) as $file)
		{
			if($file->isDot()) continue;
			if($file->isDir()) $folders[]=$file->getFilename();
		}
		return $folders;
	}
	
	//Delete Folder with all files
	//Returns true|false
	public function deleteFolder($folderPath)
	{
		$folderPath=rtrim($folderPath,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
		if(!is_dir($folderPath)) return false;
		
	 	$files = glob($folderPath.'*'); // get all file names
		if(count($files))
		{
			foreach($files as $file)
			{
			  if(is_file($file)) unlink($file); // delete file
			}
		}
		return rmdir($folder);
	}
	
	/*Array Functions*/
	public function array_remove($input=array(),$remove=array())
	{
		$arr = array_diff((array)$input,(array)$remove);
		return array_values($arr);
	}
	
	public function getCountries()
	{
		include(ROOTDIR.'/includes/countries.php');
		return $countries;
	}

	public function getCallingCodes()
	{
		include(ROOTDIR.'/includes/countriescallingcodes.php');
		return $countrycallingcodes;
	}
	
	#Get WHMCS Config
	public function getWhmcsConfig($key='')
	{
		global $CONFIG;
		//Read from Global Var if is set
		if(is_array($CONFIG) && count($CONFIG))
		{
			$this->whmcsconfig=$CONFIG;
		}
		if(!is_array($this->whmcsconfig))
		{
			$this->whmcsconfig=array();
			$rs=$this->db->query("SELECT * FROM `tblconfiguration`;");
			while ($data = $this->db->fetch_array($rs))
			{
				$this->whmcsconfig[$data["setting"]]=$data["value"];
			}
		}
		if(!empty($key) && $key=='SystemSSLURL' && empty($this->whmcsconfig[$key])) $key='SystemURL';
		if(!empty($key) && is_array($this->whmcsconfig) && isset($this->whmcsconfig[$key])) return $this->whmcsconfig[$key];
		return $this->whmcsconfig;
	}
	
	#Get Addon Config
	public function getModuleParams($key=null,$module)
	{
		if(!count($this->moduleConfig) || !isset($this->moduleConfig[$module]))
		{
			
			$this->db->query('SELECT setting,value FROM `tbladdonmodules` WHERE module="'.$module.'";');
			$this->moduleConfig[$module]=array();
			while($row=$this->db->fetch_array())
			{
				$this->moduleConfig[$module][$row['setting']]=trim($row['value']);
			}
		}
		if(!empty($key) && is_array($this->moduleConfig[$module]) && isset($this->moduleConfig[$module][$key])) return $this->moduleConfig[$module][$key];
		return $this->moduleConfig[$module];
	}
	
	public function setModuleParams($name,$value='',$module)
	{
		$this->db->query('UPDATE `tbladdonmodules` SET value="'.$this->db->safe($value).'" WHERE setting="'.$this->db->safe($name).'" AND module="'.$this->db->safe($module).'";');
		$this->moduleConfig[$module][$name]=$value;
	}
	
	public function logActivity($logtxt='',$module='openAPI')
	{
		if(empty($logtxt)) return false;
		LogActivity($module.' - '.$logtxt);
		return true;
	}
	
	function highlightKeyword($haystack,$needle,$color = "#daa732") {
		return preg_replace("/($needle)/i",sprintf('<span style="color:%s;">$1</span>',$color),$haystack);
	}
	
	//Set Email Hash | String
	function setEmailHash($emailHash)
	{
		$this->emailHash=$emailHash;
	}
	
	public function sendEmail($to,$subject,$body,$frommail='',$fromname='WHMCS System',$AllowHTML=true,$charset="utf-8",$extraHeaders='')
	{
		if(empty($to) || strpos($to,"@")===false ) return false;
		$whmcs=$this->getWhmcsConfig();
		if($charset!="utf-8") $charset=$whmcs["Charset"];
		if($frommail==''){
			$frommail=trim($whmcs["SystemEmailsFromEmail"]);
			$fromname=trim($whmcs["SystemEmailsFromName"]);
		}
		$plainbody= strip_tags(preg_replace("/<br(.*)>/iU", "\n", $body));
		
		if(is_null($this->emailHash)) $this->emailHash = "OpenAPIInterface_".md5(time());
		$eol = PHP_EOL;
		
		$headers  = "From: ".$fromname." <".$frommail.">".$eol;
		$headers .= "Reply-To: ". $frommail.$eol;
		$headers .= 'MIME-Version: 1.0' .$eol;
		$headers .= "Content-Type: multipart/mixed; boundary=\"".$this->emailHash."\"".$eol.$eol;
		
		#Multi-Language Support
		if(function_exists('iconv') && function_exists('mb_detect_encoding') && mb_detect_encoding($plainbody)!=strtoupper($charset)){
			$plainbody=@iconv(mb_detect_encoding($plainbody),$charset,$plainbody); //plain
			$body=@iconv(mb_detect_encoding($body),$charset,$body); #html
		}
		
		$prepare="--".$this->emailHash.$eol;
		$prepare.="Content-Type: multipart/alternative; boundary=\"".$this->emailHash."\"".$eol.$eol;
		$prepare.="--".$this->emailHash.$eol;
		$prepare.="Content-Type: text/plain; charset=\"".$charset."\"".$eol;
		$prepare.="Content-Transfer-Encoding: base64".$eol.$eol;
		$prepare.=chunk_split(base64_encode($plainbody)).$eol.$eol;
		if($AllowHTML)
		{
			$prepare.="--".$this->emailHash.$eol;
			$prepare.="Content-Type: text/html; charset=\"".$charset."\"".$eol;
			$prepare.="Content-Transfer-Encoding: base64".$eol.$eol;
			$prepare.=chunk_split(base64_encode($body)).$eol.$eol;
		}
		$prepare.="--".$this->emailHash."--";
		ob_start(); //Turn on output buffering
		echo $prepare;
		$message = ob_get_clean();
		
		return @mail($to,stripslashes($subject),$message,$headers.str_replace('{EMAILHASH}',$this->emailHash,$extraHeaders));
	}
	
	//Set Connection Timeout | int
	function setTimeout($timeout)
	{
		$this->timeout=(int)$timeout;
	}
	
	function getRemoteData($url,$fields=null,$headers=array(),$method='GET')
	{
		//Prepare Variables
		$url=trim($url);
		$values=array('response'=>null,'error'=>null);
		$method=strtoupper($method);
		$fields=($fields!==null && is_array($fields))?http_build_query($fields):$fields;
		$user_agent=(!empty($_SERVER['HTTP_USER_AGENT']))?$_SERVER['HTTP_USER_AGENT']:'OpenWHMCSAPI-'.self::$version;
		
		if(function_exists('curl_init')) 
		{
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url );
			if($method=='POST'){				
				curl_setopt($ch, CURLOPT_POST,1);
				curl_setopt($ch, CURLOPT_POSTFIELDS,$fields);
			}
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_USERAGENT,$user_agent);
			if ((ini_get('open_basedir')!==false && (int)ini_get('open_basedir')==1) && (ini_get('safe_mode')!==false && (int)ini_get('safe_mode')==1)){
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION,1);
				curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
			}
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->timeout);
			if(substr($url,0,5)=='https'){
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
			}
			if(count($headers)>0) curl_setopt($ch, CURLOPT_HTTPHEADER,$headers);
			$contents = curl_exec($ch);
			$info = curl_getinfo($ch);
			if(curl_errno($ch)>0) $values["error"].=curl_error($ch)." (Error:".curl_errno($ch)." HttpCode:".$info['http_code]'].")";
			curl_close($ch);
			$values["headers"]=$info ;
			$values["response"]=trim($contents);
		}
		elseif(function_exists('file_get_contents') || empty($values["response"]))
		{
			if($method=='POST')
			{
				if(!count($headers)) $headers[]='Content-type: application/x-www-form-urlencoded'; //default
				$params = array('http' => array('method' => 'POST','content' => $fields,'follow_location'=>1,'timeout'=>$this->timeout));
			}
			else
			{
				$params = array('http' => array('method' => 'GET','follow_location' =>1,'timeout'=>$this->timeout));
			}
			
			$headers[]='User-agent: '.$user_agent;
			if (count($headers)>0)	$params['http']['header'] = implode("\r\n",$headers);
			$ctx=@stream_context_create($params);
			$contents=@file_get_contents($url,false,$ctx);
			
			if($contents!==false){
				$values["response"]=trim($contents);
			}
			else
			{
				$errr=error_get_last();
				$values["error"]="file_get_contents() error ".$errr['message'];
			}
		}
		else
		{
			$values["error"]='Error';
		}
		return $values;
	}
	
	function print_data($values,$ret=false)
	{
		$data='<pre>'.htmlspecialchars(print_r($values,true)).'</pre>';
		if($ret) return $data;
		echo $data;
	}
	
	public function dump($var)
	{
		ob_start();
		var_dump($var);
		return ob_get_clean();
	}
}