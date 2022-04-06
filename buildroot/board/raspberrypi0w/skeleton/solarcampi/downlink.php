<?php

function downlink_set_clock($queueEntry)
{
	// Special funtion: No ACK!
	if ($queueEntry['json'])
	{
		i2c_write_register("timestamp", (int) $queueEntry['json']);
	}
}

function downlink_get_registers($queueEntry)
{
	server_acknowledge([
		"id" => $queueEntry['id'],
		"return" => ["success" => true, "registers" => i2c_read_all()]
	]);
}

function downlink_set_registers($queueEntry)
{
	if ($queueEntry['json'])
	{
		$registers = [];
		foreach ($queueEntry['json'] as $registername => $value)
		{
			$registers[$registername] = i2c_write_register($registername, $value);
		}
		server_acknowledge([
			"id" => $queueEntry['id'],
			"return" => ["success" => true, "registers" => $registers]
		]);
	}
}

function downlink_fullboot($queueEntry)
{
	server_acknowledge([
		"id" => $queueEntry['id'],
		"return" => ["success" => true]
	]);
	fullboot();
}

function downlink_get_file_content($queueEntry)
{
	if (file_exists($queueEntry['json']))
	{
		server_acknowledge([
			"id" => $queueEntry['id'],
			"return" => ["success" => true, "data" => base64_encode(file_get_contents($queueEntry['json']))]
		]);
	}
	else
	{
		server_acknowledge([
			"id" => $queueEntry['id'],
			"return" => ["success" => false, "error" => "file_does_not_exist", "log" => $log]
		]);
	}
}

function downlink_set_file_content($queueEntry)
{
	global $log;
	if ($queueEntry['json'] && $queueEntry['json']['filename'] && $queueEntry['json']['content'])
	{
		$log = "";
		$content = base64_decode($queueEntry['json']['content']);
		if ($content === false)
		{
			server_acknowledge([
				"id" => $queueEntry['id'],
				"return" => ["success" => false]
			]);
			return;
		}
		
		if (str_starts_with($queueEntry['json']['filename'], "/tmp"))
		{
			// tmpfs, no mount changes
		}
		elseif (str_starts_with($queueEntry['json']['filename'], "/boot"))
		{
			// FAT32 partition
			run_log('mount -o remount,rw /boot');
		}
		else
		{
			// root partition
			run_log('mount -o remount,rw /');
		}

		$return = file_put_contents($queueEntry['json']['filename'], $content);

		if (str_starts_with($queueEntry['json']['filename'], "/tmp"))
		{
			// tmpfs, no mount changes
		}
		elseif (str_starts_with($queueEntry['json']['filename'], "/boot"))
		{
			// FAT32 partition
			run_log('sync');
			run_log('mount -o remount,ro /boot');
			run_log('sync');
		}
		else
		{
			// root partition
			run_log('sync');
			run_log('mount -o remount,ro /');
			run_log('sync');
		}

		server_acknowledge([
			"id" => $queueEntry['id'],
			"return" => ["success" => ($return !== false), "log" => $log]
		]);
	}
}

function downlink_get_config($queueEntry)
{
	global $config;
	
	server_acknowledge([
		"id" => $queueEntry['id'],
		"return" => ["success" => true, "config" => $config]
	]);
}

function downlink_set_config_option($queueEntry)
{
	global $config, $config_file;

	if ($queueEntry['json'] && is_array($queueEntry['json']))
	{
		foreach ($queueEntry['json'] as $option)
		{
			if ($option['section'] && $option['name'] && $option['value'])
			{
				$section = trim($option['section']);
				$name = trim($option['name']);
				$value = trim($option['value']);

				if (!isset($config[$section]))
				{
					$config[$section] = [];
				}
				$config[$section][$name] = $value;
			}
		}
		
		config_save($config_file, $config);
		server_acknowledge([
			"id" => $queueEntry['id'],
			"return" => ["success" => true, "config" => $config]
		]);
	}
	
}

function downlink_shell_command($queueEntry)
{
	global $log;
	if ($queueEntry['json'] && $queueEntry['json']['command'])
	{
		$log = "";
		
		run_log($queueEntry['json']['command']);
		
		server_acknowledge([
			"id" => $queueEntry['id'],
			"return" => ["success" => true, "log" => $log]
		]);
	}
}

function downlink_avrdude($queueEntry)
{
	global $log;
	if ($queueEntry['json'] && $queueEntry['json']['firmware'] && $queueEntry['json']['registers'])
	{
		$log = "";

		$firmware = base64_decode($queueEntry['json']['firmware']);
		if (!$firmware)
		{
			server_acknowledge([
				"id" => $queueEntry['id'],
				"return" => [
					"success" => false, 
					"error" => "Invalid base64 firmware!",
				]
			]);
			return;
		}
		file_put_contents("/tmp/firmware.hex", $firmware);

		// enable power override (Raspberry Pi will stay on, forced)
		run_log('echo 27 > /sys/class/gpio/export');
		run_log('echo out > /sys/class/gpio/gpio27/direction');
		run_log('echo 1 > /sys/class/gpio/gpio27/value');

		// enable AVR reset pin
		run_log('echo 17 > /sys/class/gpio/export');
		run_log('echo out > /sys/class/gpio/gpio17/direction');
		run_log('echo 1 > /sys/class/gpio/gpio17/value');

		// load SPI kernel modules
		run_log('modprobe spidev');
		run_log('modprobe spi-bcm2835');

		// wait for the SPI kernel modules to be loaded & ready
		// and AVR to be ready for being programmed
		sleep(1);

		run_log('avrdude -p m328p -c linuxspi -P /dev/spidev0.0:/dev/gpiochip0:24  -U flash:w:/tmp/firmware.hex -U lfuse:w:0xe2:m -U hfuse:w:0xd9:m -U efuse:w:0xfd:m -s');

		if (strpos($log, "verification error") !== false || 
			strpos($log, "can't determine file format") !== false)
		{
			// oh boy! flashing failed and now the AVR is bricked.
			// We'll keep the enable line high, flash the fallback firmware,
			// boot the full system and ask the user to help us!
			run_log('avrdude -p m328p -c linuxspi -P /dev/spidev0.0:/dev/gpiochip0:24  -U flash:w:/solarcampi/fallback_firmware.hex -U lfuse:w:0xe2:m -U hfuse:w:0xd9:m -U efuse:w:0xfd:m -s');

			server_acknowledge([
				"id" => $queueEntry['id'],
				"return" => [
					"success" => false,
					"error" => "Firmware upload failed! Full system booted!\nSolarCamPi is now running the fallback firmware binary!",
					"log" => $log
				]
			]);

			fullboot();
		}
		
		// disable AVR reset pin
		run_log('echo 0 > /sys/class/gpio/gpio17/value');
		run_log('echo in > /sys/class/gpio/gpio17/direction');
		run_log('echo 17 > /sys/class/gpio/unexport');

		// wait for AVR to be up-and-running
		sleep(1);

		// EEPROM is empty after programming. Set all registers again.
		$registers = [];
		foreach ($queueEntry['json']['registers'] as $registername => $value)
		{
			$registers[$registername] = i2c_write_register($registername, $value);
		}

		// disable power override
		run_log('echo 0 > /sys/class/gpio/gpio27/value');
		run_log('echo in > /sys/class/gpio/gpio27/direction');
		run_log('echo 27 > /sys/class/gpio/unexport');

		server_acknowledge([
			"id" => $queueEntry['id'],
			"return" => [
				"success" => true, 
				"log" => trim($log),
				"registers" => $registers
			]
		]);
	}
}

