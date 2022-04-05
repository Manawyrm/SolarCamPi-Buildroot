<?php

function server_upload($data, $imagefile)
{
	global $config;

	$data['name'] = $config['general']['camera_name'];
	$data['key'] = $config['general']['key'];

	$post['data'] = json_encode($data, JSON_PRETTY_PRINT);

	if (file_exists($imagefile))
		$post['image'] = curl_file_create($imagefile);

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $config['server']['url'] . "/upload.php");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
	$result = curl_exec($ch);
	curl_close ($ch);

	return $result;
}

function server_acknowledge($data)
{
	global $config;

	$post['data'] = json_encode($data, JSON_PRETTY_PRINT);
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $config['server']['url'] . "/acknowledge.php");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
	$result = curl_exec($ch);
	curl_close ($ch);

	return $result;
}

function server_handle_response($result)
{
	global $log;
	
	$json = json_decode(trim($result), true);
	if ($json)
	{
		foreach ($json as $queueEntry)
		{
			set_error_handler( "log_error" );
			set_exception_handler( "log_exception" );
			$log = "";

			switch ($queueEntry['type'])
			{
				case 'avrdude':
					downlink_avrdude($queueEntry);
					break;
				case 'set_registers':
					downlink_set_registers($queueEntry);
					break;
				case 'get_registers':
					downlink_get_registers($queueEntry);
					break;
				case 'set_file_content':
					downlink_set_file_content($queueEntry);
					break;
				case 'get_file_content':
					downlink_get_file_content($queueEntry);
					break;
				case 'shell_command':
					downlink_shell_command($queueEntry);
					break;
				case 'get_config':
					downlink_get_config($queueEntry);
					break;
				case 'set_config_options':
					downlink_set_config_option($queueEntry);
					break;
				case 'fullboot':
					downlink_fullboot($queueEntry);
					break;
				default:
					server_acknowledge([
						"id" => $queueEntry['id'],
						"return" => ["success" => false, "error" => "unknown type!"]
					]);
					break;
			}

			restore_error_handler();
			restore_exception_handler();
		}
	}
}