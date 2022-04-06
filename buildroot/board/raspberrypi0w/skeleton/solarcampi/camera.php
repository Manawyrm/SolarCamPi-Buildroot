<?php

function camera_take_picture($i2c_time, $i2c_voltage, $i2c_current)
{
	$stdOut = "";
	$stdErr = "";
	if (file_exists("/solarcampi/capture.php"))
	{
		$process = proc_open('php /solarcampi/capture.php ' . escapeshellarg($i2c_time) . " " . escapeshellarg($i2c_voltage) . " " . escapeshellarg($i2c_current), array(
			0 => array('pipe', 'r'), // STDIN
			1 => array('pipe', 'w'), // STDOUT
			2 => array('pipe', 'w')  // STDERR
		), $pipes);

		if(is_resource($process))
		{
			fclose($pipes[0]);

			$stdOut = stream_get_contents($pipes[1]);
			fclose($pipes[1]);

			$stdErr = stream_get_contents($pipes[2]);
			fclose($pipes[2]);

			$returnCode = proc_close($process);

			$json = json_decode($stdOut, true);
			if ($json && $json['filename'] && file_exists($json['filename']))
			{
				return [
					"filename" => $json['filename'],
					"output" => $stdErr,
					"comment" => $json['comment'] ?? ''
				];
			}
		}
	}
	
	$fallback = camera_fallback();
	if ($stdOut || $stdErr)
	{
		$fallback['output'] .= "\ncapture.php stderr: " . $stdErr . "\ncapture.php stdout: " . $stdOut;
	}
	return $fallback;
}

function camera_fallback()
{
	$filename = "/tmp/" . time() . ".jpg";
	$cmd = "raspistill -o " . escapeshellarg($filename) . " " . $extra_arguments . " 2>&1";
	$result = run_cmd($cmd);
	$result['filename'] = $filename;
	$result['comment'] = "camera_fallback()";
	return $result;
}