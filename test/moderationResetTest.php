<?php

define( '_VALID_MOS', 1 );

require_once( '../includes/alastair.php' );
require_once( '../config/moderation.config.php' );
GetLogonDetails($con,$username);

$dirs = array(	ModerationConfig::GetInboxDir()."/cur",
		ModerationConfig::GetInboxDir()."/new", 
		ModerationConfig::GetUnmoderatedDir()."/cur",
		ModerationConfig::GetUnmoderatedDir()."/new",
		ModerationConfig::GetModeratedDir()."/cur",
		ModerationConfig::GetModeratedDir()."/new");

foreach ($dirs as $dir) {

	$files = scandir($dir);
	
	foreach ($files as $file) {
		if (is_dir("$dir/$file")) continue;
		echo "deleting $dir/$file</br>";
		unlink("$dir/$file");
	}
}

$dir = "TestEmails";
$files = scandir($dir);
foreach ($files as $file) {
	if (is_dir("$dir/$file")) continue;
	echo "copying $dir/$file to ".ModerationConfig::GetInboxDir()."/cur/$file</br>";
	file_put_contents(ModerationConfig::GetInboxDir()."/cur/$file",file_get_contents("$dir/$file"));
}
?>