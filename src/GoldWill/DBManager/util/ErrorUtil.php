<?php
namespace GoldWill\DBManager\util;

class ErrorUtil
{
	public static function errorToString(\Throwable $e)
	{
		$trace = $e->getTrace();
		$errstr = $e->getMessage();
		$errfile = $e->getFile();
		$errno = $e->getCode();
		$errline = $e->getLine();
		
		$errorConversion = [
			0 => "EXCEPTION",
			E_ERROR => "E_ERROR",
			E_WARNING => "E_WARNING",
			E_PARSE => "E_PARSE",
			E_NOTICE => "E_NOTICE",
			E_CORE_ERROR => "E_CORE_ERROR",
			E_CORE_WARNING => "E_CORE_WARNING",
			E_COMPILE_ERROR => "E_COMPILE_ERROR",
			E_COMPILE_WARNING => "E_COMPILE_WARNING",
			E_USER_ERROR => "E_USER_ERROR",
			E_USER_WARNING => "E_USER_WARNING",
			E_USER_NOTICE => "E_USER_NOTICE",
			E_STRICT => "E_STRICT",
			E_RECOVERABLE_ERROR => "E_RECOVERABLE_ERROR",
			E_DEPRECATED => "E_DEPRECATED",
			E_USER_DEPRECATED => "E_USER_DEPRECATED",
		];
		
		$errno = isset($errorConversion[$errno]) ? $errorConversion[$errno] : $errno;
		
		if (($pos = strpos($errstr, "\n")) !== false)
		{
			$errstr = substr($errstr, 0, $pos);
		}
		
		$errfile = \pocketmine\cleanPath($errfile);
		
		
		$result = tmpfile();
		
		fwrite($result, get_class($e));
		fwrite($result, ': "');
		fwrite($result, $errstr);
		fwrite($result, '" (');
		fwrite($result, $errno);
		fwrite($result, ') in "');
		fwrite($result, $errfile);
		fwrite($result, '" at line ');
		fwrite($result, $errline);
		fwrite($result, PHP_EOL);
		
		foreach (@\pocketmine\getTrace(1, $trace) as $line)
		{
			fwrite($result, $line);
			fwrite($result, PHP_EOL);
		}
		
		fseek($result, 0);
		
		$stringResult = stream_get_contents($result);
		
		fclose($result);
		
		
		return $stringResult;
	}
}