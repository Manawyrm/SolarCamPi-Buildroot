<?php
$gitFolder = "/home/tobias/Entwicklung/Code/SolarCamPi-Buildroot/buildroot-solarcampi/buildroot/board/raspberrypi0w/skeleton";

$filesToUpdate = [
	"/solarcampi/camera.php",
	"/solarcampi/init.sh",
	"/solarcampi/run.php",
];

foreach ($filesToUpdate as $filename)
{
	$base64 = base64_encode(file_get_contents($gitFolder . $filename));

	$json = mysql_escape_string(
		json_encode([
			"filename" => $filename,
			"content" => $base64,
		], JSON_PRETTY_PRINT)
	);
	echo "INSERT INTO `queue`(`id`, `deviceID`, `type`, `json`) VALUES (NULL, 2, 'set_file_content', '" . $json . "');\n";
}

// this is unsafe, obviously! don't use this, ever! 
function mysql_escape_string(string $unescaped_string): string
{
    $replacementMap = [
        "\0" => "\\0",
        "\n" => "\\n",
        "\r" => "\\r",
        "\t" => "\\t",
        chr(26) => "\\Z",
        chr(8) => "\\b",
        '"' => '\"',
        "'" => "\'",
        '_' => "\_",
        "%" => "\%",
        '\\' => '\\\\'
    ];

    return \strtr($unescaped_string, $replacementMap);
}
