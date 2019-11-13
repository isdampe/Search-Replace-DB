#!/usr/bin/php -q
<?php

function usage() {
	print("Usage: wp-release.php [/path/to/wordpress]\n");
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

	mysqli_free_result($result);
	mysqli_close($sql);

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

	$url_parts = parse_url($site_url);
	$host = $url_parts["host"];

	$out_dir = "/tmp/deploy-$host";
	mkdir($out_dir);
	if (! is_dir($out_dir)) {
		print("Error creating temporary directory /tmp/deploy-$host");
		exit(1);
	}

	$replace_with = readline(sprintf("What's the production url? Current dev URL is %s: ", $site_url));
	if (! $replace_with || $replace_with == "") {
		print("Invalid argument.\n");
		exit(1);
	}

	//Change database.
	print("Rewriting database...\n");
	rewrite_database($site_url, $replace_with);

	print("Done. Dumping database...\n");
	//Dump database.
	$cmd = sprintf("mysqldump -h'%s' -u'%s' -p'%s' %s > %s/%s.sql",
		escapeshellarg(DB_HOST), 
		escapeshellarg(DB_USER), 
		escapeshellarg(DB_PASSWORD), 
		escapeshellarg(DB_NAME), 
		escapeshellarg($out_dir), 
		escapeshellarg(DB_NAME));

	shell_exec($cmd);

	//Change it back.
	print("Done. Restoring database...\n");
	rewrite_database($replace_with, $site_url);

	print("Done. Clone " . $dir . "...\n");
	$cmd = sprintf("sudo cp %s %s/public_html -R", $dir, $out_dir);
	exec($cmd);

	print("Done. Fixing permissions...\n");
	$cmd = sprintf("sudo chown dampe:dampe %s/public_html -R", $out_dir);
	exec($cmd);

	$cmd = sprintf("rm %s/public_html/wp-config.php", $out_dir);
	exec($cmd);

	print(sprintf("Done. Find your files at %s\n", $out_dir));

}

function rewrite_database($site_url, $replace_with) {
	error_reporting(0);
	$cmd = sprintf("php %s/srdb.cli.php -h %s -u %s -p %s -n %s -s %s -r %s",
		dirname(__FILE__),
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
