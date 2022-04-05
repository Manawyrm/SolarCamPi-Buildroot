<?php
function run_log($cmd)
{
	global $log;
	$log .= "[run] $ " . $cmd . "\n";
	$log .= `$cmd 2>&1` . "\n";
}

function log_error( $num, $str, $file, $line, $context = null )
{
    return log_exception( new ErrorException( $str, 0, $num, $file, $line ) );
}

function log_exception( $e )
{
	global $log;
	$errorstring = "[error] Type: " . get_class( $e ) . "\nFile: {$e->getFile()}: {$e->getLine()};\nMessage: {$e->getMessage()}\nStacktrace: \n{$e->getTraceAsString()}\n\n";
	$log .= $errorstring;
	var_dump($errorstring);
	return true;
}

function check_for_fatal()
{
    $error = error_get_last();
    if ( $error && $error["type"] == E_ERROR )
    {
    	$post['data'] = json_encode($error, JSON_PRETTY_PRINT);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $config['server']['url'] . "/crash.php");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		$result = curl_exec($ch);
		curl_close ($ch);
    }
}

register_shutdown_function( "check_for_fatal" );
