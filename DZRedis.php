<?php

class DZRedis
{
	protected $_server = null;
	protected $_port = null;
	protected $_timeout = 1;
	protected $_errorCode = 0;
	protected $_errorStr = '';
	protected $_resource = null;
	
	function __construct($server, $port)
	{
		$this->connect($server, $port);
	}
	
	public function connect($server, $port)
	{
		$this->_server = $server;
		$this->_port = $port;
		$this->_resource = fsockopen($server, $port, $errCode, $errStr, $this->_timeout);
		if(!empty($errStr)) 
		{
			$this->_errorCode = -$errCode;
			$this->_errorStr = $errStr;
			return $this->_errorCode;
		}
		return true;
	}
	
	public function run($input)
	{
		$rawCommand = $this->buildRequest($input);
		fwrite($this->_resource, $rawCommand);
		return $this->pharseResult($this->_resource);
	}
	
	private function buildRequest($input)
	{
		// Format example	
		// "*2\r\n\$3\r\nGet\r\n\$4\r\ntest\r\n";
		// "*1\r\n\$4\r\nInfo\r\n";
		
		$request = explode(' ', $input);
		$redisFormat = "*".count($request)."\r\n";
		
		foreach($request as $key => $command)
		{
			$redisFormat .= "\$".strlen($command)."\r\n".$command."\r\n";
		}
		
		return $redisFormat;
	}
	
	private function pharseResult($input)
	{
		$toReturn = '';	
		//stream_set_timeout($input, 0, 10000);	

		$responseHeader = fgets($input);
		$responseType = substr($responseHeader,0,1);
		
		switch($responseType)
		{
			case '+': // Simple Strings
			case '-': // Errors
			case ':': // Integers
				$toReturn = substr($responseHeader,1,-2);
				break;
			case '$': // Bulk Strings
				$responseLen = substr($responseHeader,1,-2);
				if($responseLen == '-1')
				{
					$toReturn = -1;
					break;
				}
				$partialLen = 0;
				$toReturn = '';
				do
				{
					$response = fgets($input);
					if($response === false || $response === '')
					{
						$toReturn .= ' #error while reading the response# ';
						break;
					}
					$partialLen += strlen($response);
					$toReturn .= $response;
				} while ($partialLen + 4 <= $responseLen);
				break;
			case '*': // Arrays
				$itemsAmount = strval(substr($responseHeader,1,-2));
				$toReturn = array();
				for($i=1;$i<=$itemsAmount;$i++)
				{
					$toReturn[] = $this->pharseResult($input);
				}
				break;
		}
		
		return $toReturn;
	}
}
