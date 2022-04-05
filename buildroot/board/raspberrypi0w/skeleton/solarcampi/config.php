<?php
$config_file = "/boot/solarcampi.ini";
$config = config_load($config_file);

require_once("logging.php");
require_once("util.php");
require_once("i2c.php");
require_once("camera.php");
require_once("network.php");
require_once("server.php");
require_once("downlink.php");

function config_load(string $config_file): array|bool
{
	// evil hack
	if (!file_exists($config_file))
	{
		$cmd = "mount -o ro " . escapeshellarg(find_config_partition()) . " /boot";
		`$cmd`;
	}

	$config = parse_ini_file ( $config_file , true , INI_SCANNER_TYPED );
	return $config;
}

function config_save(string $config_file, array $config): bool
{
	$config_string = create_ini_string($config);

	if (file_get_contents($config_file) != $config_string)
	{
		// configuration has changed

		// even more evil hack
		$configpartition = escapeshellarg(find_config_partition());
		`mount -o remount,rw $configpartition`;

		file_put_contents($config_file, $config_string);
		`sync`;
		`mount -o remount,ro /boot`;
		`sync`;
		return true; 
	}

	return false;
}

function find_config_partition(): string
{
	return "/dev/mmcblk0p1";
}

function create_ini_string ( array $config ): string
{
	$result = "; SolarCamPi Configuration file\n".
			  "; Make sure to quote special characters, e.g. key = \"value\"\n\n";

	foreach ($config as $section => $elements)
	{
		$result .= "[" . $section . "]\n"; 
		foreach ($elements as $key => $value)
		{
			if (is_string($value))
			{
				$result .= $key . " = \"" . $value . "\"\n";
			}
			elseif (is_bool($value))
			{
				$result .= $key . " = " . ($value ? 'true' : 'false') . "\n";
			}
			else
			{
				$result .= $key . " = " . $value . "\n";
			}
		}
		$result .= "\n";
	}
	return $result;
}

function put_file_if_different(string $filename, string $content): bool
{
	if (trim(file_get_contents($filename)) != trim($content))
	{
		file_put_contents($filename, $content);
		return true;
	}

	return false; 
}
