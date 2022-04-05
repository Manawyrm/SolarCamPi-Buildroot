<?php
require_once("config.php");

network_configure();

$i2c_voltage = i2c_read_register("voltage")['value'];
$i2c_current = i2c_read_register("current")['value'];

$picture = camera_take_picture("");

if (!network_wait_for_gateway())
{
	// no network connectivity! :(
	error_log("network connection failed!");
	return;
}

$wifi_link = network_station_dump();

$server_response = server_upload([
	"voltage" => $i2c_voltage,
	"current" => $i2c_current,
	"picture_log" => $picture['output'],
	"wifi_link" => $wifi_link
], $picture['filename']);

server_handle_response($server_response);