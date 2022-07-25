<?php
function safeCLIRun($cmd, $timeout = 60)
{
	$stdoutBuffer = "";
	$stderrBuffer = "";
	$exitStatus = "";

	$descriptorspec = [
		0 => array( "pipe", "r" ),  // stdin is a pipe that the child will read from
		1 => array( "pipe", "w" ),  // stdout is a pipe that the child will write to
		2 => array( "pipe", "w" )   // stderr is a pipe that the child will write to
	];

	$cwd = null; // use the current working dir
	$env = null; // use the current env

	$startTime = time();
	$finished = false;
	$process = proc_open( $cmd, $descriptorspec, $pipes, $cwd, $env );
	if ( !is_resource( $process ) )
	{
		throw new \Exception( "Could not start process" );
	}

	// We need non-blocking pipes for all handles,
	// otherwise we'll be stuck until the process terminates
	stream_set_blocking($pipes[0], 0);
	stream_set_blocking($pipes[1], 0);
	stream_set_blocking($pipes[2], 0);

	while (!$finished && (time() - $timeout) < $startTime)
	{
		$status = proc_get_status($process);
		if (!$status)
		{
			throw new \Exception("Could not retrieve process status");
		}

		if (!$status['running'])
		{
			$exitStatus = "finished";
			$finished = true;
		}

		$stdout = stream_get_contents($pipes[1]);
		if ($stdout)
			$stdoutBuffer .= $stdout;

		$stderr = stream_get_contents($pipes[2]);
		if ($stderr)
			$stderrBuffer .= $stderr;

		usleep(5000);
	}

	// Read one final time to fetch all data
	$stdout = stream_get_contents($pipes[1]);
	if ($stdout)
		$stdoutBuffer .= $stdout;

	$stderr = stream_get_contents($pipes[2]);
	if ($stderr)
		$stderrBuffer .= $stderr;

	// If we're here, but the process hasn't finished yet, let's kill it!
	if (!$finished)
	{
		$exitStatus = "killed";
		proc_terminate ( $process , 9 /* SIGKILL */ );
	}

	fclose($pipes[0]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	proc_close($process);

	return [
		"stderr" => $stderrBuffer,
		"stdout" => $stdoutBuffer,
		"exitStatus" => $exitStatus,
		"duration" => time() - $startTime
	];
}


function camera_take_picture($i2c_time, $i2c_voltage, $i2c_current)
{
	if (file_exists("/solarcampi/capture.php"))
	{
		$return = safeCLIRun('php /solarcampi/capture.php ' . escapeshellarg($i2c_time) . " " . escapeshellarg($i2c_voltage) . " " . escapeshellarg($i2c_current), 120);

		if ($return["exitStatus"] != "killed")
		{
			$json = json_decode($return['stdout'], true);
			if ($json && $json['filename'] && file_exists($json['filename']))
			{
				$endtime = time();
				return [
					"filename" => $json['filename'],
					"output" => $return['stderr'],
					"comment" => $json['comment'] ?? '',
					"duration" => $return['duration']
				];
			}
		}
	}
	
	if ($return["exitStatus"] != "killed")
	{
		$fallback = camera_fallback();
		if ($return && ($return['stdout'] || $return['stderr']))
		{
			$fallback['output'] .= "\ncapture.php stderr: " . $return['stderr'] . "\ncapture.php stdout: " . $return['stdout'];
		}
	}
	else
	{
		return [
			"filename" => "",
			"output" => "\ncapture.php stderr: " . $return['stderr'] . "\ncapture.php stdout: " . $return['stdout'],
			"comment" => "capture.php timeout!",
			"duration" => $return['duration']
		];
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
