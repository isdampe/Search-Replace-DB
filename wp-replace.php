#!/usr/bin/php -q
<?php

function usage() {
	print("Usage: wp-replace.php [/path/to/wordpress]\n");
	exit;
}

function valid_wp() {
	if (
		! defined('DB_HOST') ||
		! defined('DB_USER') ||
		! defined('DB_PASSWORD') ||
		! defined('DB_NAME')
	)
		return false;

	return true;
}

function get_own_site_url() {
	global $table_prefix;

	$sql = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
	if (! $sql) 
		return false;

	$query = sprintf('SELECT option_value FROM %soptions WHERE option_name = "siteurl"', $table_prefix);
	$result = mysqli_query($sql, $query);
	if (! $result)
		return false;

	$row = mysqli_fetch_assoc($result);
	if (! array_key_exists('option_value', $row))
		return false;

	return $row['option_value'];
}

function main() {

	if ($_SERVER['argc'] < 2)
		$dir = dirname(__FILE__);
	else
		$dir = $_SERVER['argv'][1];

	if ($dir == "-h" || $dir == "--help")
		usage();

	$fp = sprintf("%s/wp-config.php", $dir);
	if (! file_exists($fp) ) {
		print(sprintf("Could not find %s. Does it exist?\n", $fp));
		exit(1);
	}

	//Include config.
	require_once $fp;
	if (! valid_wp()) {
		print("The wp-config.php file specified is not valid.");
		exit(1);
	}

	//Get site url.
	$site_url = get_own_site_url();
	if (! $site_url) {
		print("Could not determine siteurl.\n");
		exit(1);
	}

	$replace_with = readline(sprintf("Replace %s with?: ", $site_url));
	if (! $replace_with || $replace_with == "") {
		print("Invalid argument.\n");
		exit(1);
	}

	$res = strtolower(readline(sprintf("Are you sure you want to replace all occurences of %s with %s? (Yes/No): ", $site_url, $replace_with)));
	if ( substr($res, 0, 1) !== "y" ) {
		print("Cancelled\n");
		exit(0);
	}

	$cmd = sprintf("php srdb.cli.php -h %s -u %s -p %s -n %s -s %s -r %s",
		escapeshellarg(DB_HOST),
		escapeshellarg(DB_USER),
		escapeshellarg(DB_PASSWORD),
		escapeshellarg(DB_NAME),
		escapeshellarg($site_url),
		escapeshellarg($replace_with)
	);

	echo shell_exec($cmd);

}

main();
