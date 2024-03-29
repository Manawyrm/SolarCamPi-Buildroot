<?php
require_once("config.php");

network_configure();

$i2c_timestamp = i2c_read_register("timestamp")['value'];
$i2c_voltage = i2c_read_register("voltage")['value'];
$i2c_current = i2c_read_register("current")['value'];

$picture = camera_take_picture($i2c_timestamp, $i2c_voltage, $i2c_current);

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
	"picture_log" => $picture['output'] ?? '',
	"picture_comment" => $picture['comment'] ?? '',
	"picture_duration" => $picture['duration'] ?? '',
	"wifi_link" => $wifi_link
], $picture['filename']);

server_handle_response($server_response);

`halt -n -f`;
