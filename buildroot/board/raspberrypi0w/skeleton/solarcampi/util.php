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

function fullboot()
{
	global $config;
	// /boot is a FAT32 partition without permissions, ssh doesn't like that.
	copy("/boot/id_rsa", "/tmp/id_rsa");
	`chmod 600 /tmp/id_rsa`;
	proc_close( proc_open( '/usr/bin/autossh -M 0 -N -q -i /tmp/id_rsa -o "StrictHostKeyChecking no" -o "UserKnownHostsFile /dev/null" -o "ServerAliveInterval 60" -o "ServerAliveCountMax 3" ' . $config['server']['autossh_args'] . ' &', array(), $foo ) );

	i2c_write_register("disableTimeout", 1);
	exit(42);
}