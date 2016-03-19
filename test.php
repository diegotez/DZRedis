<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

if(isset($_GET['server']) && !empty($_GET['server'])) $server = $_GET['server'];
else $server = '127.0.0.1';
if(isset($_GET['port']) && !empty($_GET['port'])) $port = $_GET['port'];
else $port = '6379';
if(isset($_GET['command']) && !empty($_GET['command'])) $rawCommand = builRequest($_GET['command']);
else die('no command');

echo 'COMMAND EXECUTED: '.$_GET['command'].'<br/>'.'REDIS RAW COMMAND EXECUTED: '.$rawCommand.'<br/>RESULT: <br/><br/>';

$ini = microtime(true);

$timeout = 1;
$c = fsockopen($server, $port, $errCode, $errStr, $timeout);
if(!empty($errStr)) die($errStr);

fwrite($c, $rawCommand);
echo '<pre>'; 
print_r(pharseResult($c));
echo '</pre>';

echo '<br/><br/>EXECUTION TIME: '.(microtime(true)-$ini)*10000;


function pharseResult($input)
{
	$toReturn = '';	
	//stream_set_timeout($input, 0, 10000);	

	$responseHeader = fgets($input);
	$responseType = substr($responseHeader,0,1);
	
	switch($responseType)
	{
		case '+':	// Simple Strings
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
				$toReturn[] = pharseResult($input);
			}
			break;
	}
	
	return $toReturn;
}

function builRequest($input)
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




