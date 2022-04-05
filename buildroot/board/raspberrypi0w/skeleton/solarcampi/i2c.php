<?php
define("I2C_DEVICE_ADDRESS", "0x08");
define("I2C_BUS", "1");
define("I2C_RETRIES", 3);

$registers = [
    "voltage" => ["type" => "uint16_t"],
    "sleepIntervalFast" => ["type" => "uint16_t"],
    "sleepIntervalSlow" => ["type" => "uint16_t"],
    "sleepIntervalSlowVoltage" => ["type" => "uint16_t"],
    "undervoltageLockout" => ["type" => "uint16_t"],
    "undervoltageHysteresis" => ["type" => "uint16_t"],
    "disableTimeout" => ["type" => "uint8_t"],
    "timeout" => ["type" => "uint16_t"],
    "current" => ["type" => "int16_t"],
];

function i2c_read_all()
{
	global $registers;

	$return = []; 
	foreach ($registers as $key => $value)
	{
		$return[$key] = i2c_read_register($key);
	}

	return $return;
}

function i2c_calculate_register_offset($registername)
{
	global $registers;
	$offset = 0;

	foreach ($registers as $key => $value)
	{
		if ($key == $registername)
		{
			return $offset;
		}

		switch ($value['type'])
		{
			case 'uint16_t':
				$offset += 2;
				break;
			case 'int16_t':
				$offset += 2;
				break;
			case 'uint8_t':
				$offset += 1;
				break;
			default:
				error_log("Unknown register type " . $value['type'] . "! Aborting!");
				return false;
		}
	}

	return false;
}

function i2c_read_register($registername)
{
	global $registers;

	if (!array_key_exists($registername, $registers))
	{
		throw new Exception("Register " . $registername . " doesn't exist!");
	}
	
	$offset = i2c_calculate_register_offset($registername);
	$register_definition = $registers[$registername];


	switch ($register_definition['type'])
	{
		case 'uint16_t':
			$data0 = i2c_get_raw($offset + 0);
			$data1 = i2c_get_raw($offset + 1);

			if (!$data0['success'] || !$data1['success'])
			{
				return ['success' => false, 'value' => false];
			}

			$data = unpack("v", chr($data0['value']) . chr($data1['value']))[1];
			return ['success' => true, 'value' => $data];
			break;
		case 'int16_t':
			$data0 = i2c_get_raw($offset + 0);
			$data1 = i2c_get_raw($offset + 1);

			if (!$data0['success'] || !$data1['success'])
			{
				return ['success' => false, 'value' => false];
			}

			$data = unpack("s", chr($data0['value']) . chr($data1['value']))[1];
			return ['success' => true, 'value' => $data];
			break;
		case 'uint8_t':
			$data0 = i2c_get_raw($offset + 0);

			if (!$data0['success'])
			{
				return ['success' => false, 'value' => false];
			}

			return ['success' => true, 'value' => $data0['value']];
			break;
	}	
}

function i2c_write_register($registername, $value)
{
	global $registers;
	$value = (int) $value;

	if (!array_key_exists($registername, $registers))
	{
		throw new Exception("Register " . $registername . " doesn't exist!");
	}
	
	$offset = i2c_calculate_register_offset($registername);
	$register_definition = $registers[$registername];

	switch ($register_definition['type'])
	{
		case 'uint16_t':
			$data = pack("v", $value);
			$data0 = i2c_set_raw($offset + 0, ord($data[0]));
			$data1 = i2c_set_raw($offset + 1, ord($data[1]));

			if (!$data0['success'] || !$data1['success'])
			{
				return ['success' => false, 'value' => false];
			}

			return ['success' => true];
			break;
		case 'int16_t':
			$data = pack("s", $value);
			$data0 = i2c_set_raw($offset + 0, ord($data[0]));
			$data1 = i2c_set_raw($offset + 1, ord($data[1]));

			if (!$data0['success'] || !$data1['success'])
			{
				return ['success' => false, 'value' => false];
			}

			return ['success' => true];
			break;
		case 'uint8_t':
			return i2c_set_raw($offset, $value);
			break;
	}	
}



function i2c_set_raw($register, $data)
{
	$register = (string)((int) $register);
	$data = (string)((int) $data);
	$cmd = "i2cset -y ". I2C_BUS ." " . I2C_DEVICE_ADDRESS . " " . $register . " " . $data . " b";

	$retried = 0;
	while (true)
	{
		$result = run_cmd($cmd);
		if ($result['returncode'] == 0)
		{
			if (strpos($result['output'], "Error") === false &&
				strpos($result['output'], "Warning") === false)
			{
				return [
					"success" => true
				];
			}
			else
			{
				error_log("i2c_set_raw() failed! - error: " . $result['output']);
			}
		}
		else
		{
			error_log("i2c_set_raw() encountered returncode " . $result['returncode'] . " - error: " . $result['output']);
		}

		$retried++;
		if ($retried >= I2C_RETRIES)
		{
			return [
				"success" => false
			];
		}
	}
}

function i2c_get_raw($register)
{
	$register = (string)((int) $register);
	$cmd = "i2cget -y ". I2C_BUS ." " . I2C_DEVICE_ADDRESS . " " . $register . " c";

	$retried = 0;
	while (true)
	{
		$result = run_cmd($cmd);
		if ($result['returncode'] == 0)
		{
			if (strpos($result['output'], "Error") === false &&
				strpos($result['output'], "Warning") === false)
			{
				return [
					"success" => true,
					"value" => intval(str_replace("0x", "", trim($result['output'])), 16)
				];
			}
			else
			{
				error_log("i2c_get_raw() failed! - error: " . $result['output']);
			}
		}
		else
		{
			error_log("i2c_get_raw() encountered returncode " . $result['returncode'] . " - error: " . $result['output']);
		}

		$retried++;
		if ($retried >= I2C_RETRIES)
		{
			return [
				"success" => false,
				"value" => false
			];
		}
	}
}

