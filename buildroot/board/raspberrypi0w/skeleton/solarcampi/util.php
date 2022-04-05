<?php
function run_cmd($cmd)
{
	$output = [];
	$retval = false;
	exec($cmd . " 2>&1", $output, $retval);

	return [
		"output" => trim(implode("\n", $output)),
		"returncode" => $retval
	];
}