<?php

function network_configure()
{
	global $config;
	exec("ip link set wlan0 up");
	exec("ip addr add " . escapeshellarg($config['network']['wifi_ip']) . " dev wlan0");
	exec("ip route add default via " . escapeshellarg($config['network']['wifi_gateway']));
	exec("iw reg set DE");
	exec("iw wlan0 set power_save off");
$wpa_supplicant_conf = 'ctrl_interface=DIR=/var/run/wpa_supplicant
update_config=1
country=DE

network={
    ssid="'.$config['network']['wifi_ssid'].'"
    psk="'.$config['network']['wifi_psk'].'"
}';

	file_put_contents("/tmp/wpa_supplicant.conf", $wpa_supplicant_conf);
	proc_close( proc_open( "wpa_supplicant -Dnl80211 -iwlan0 -c/tmp/wpa_supplicant.conf &", array(), $foo ) );
}

function network_wait_for_gateway()
{
	global $config;
	$connected = false;
	$timeout = 0;
	while (!$connected)
	{
		$cmd = "ping -c 1 " . escapeshellarg($config['network']['wifi_gateway']) . " -q -A -W 1 2>&1";
		$return = `$cmd`;
		$connected = (strpos($return, "1 packets received") !== false);
		$timeout++;

		if ($timeout >= 10)
			return false;

		if (!$connected)
			sleep(1);
	}

	return true;
}

function network_station_dump()
{
	$dump_raw = `iw dev wlan0 link`;
	$return = [];

	foreach (explode("\n", $dump_raw) as $line)
	{
		$return[] = trim($line);
	}
	return $return;
}