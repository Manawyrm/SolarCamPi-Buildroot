<?php

function camera_take_picture($extra_arguments = "")
{
	$filename = "/tmp/" . time() . ".jpg";
	$cmd = "raspistill -o " . escapeshellarg($filename) . " " . $extra_arguments . " 2>&1";
	$result = run_cmd($cmd);
	$result['filename'] = $filename;
	return $result;
}