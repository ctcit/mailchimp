<?php

define('_JEXEC', 1);
define('JPATH_BASE', dirname(__DIR__));// Assume mailchimp at top level in website
require_once ( JPATH_BASE.'/includes/defines.php' );
require_once ( JPATH_BASE.'/includes/framework.php' );
require_once('alastair.php');
require_once( 'mailchimp.inc.php' );

$app = JFactory::getApplication('site');
$user = JFactory::getUser();
$config = JFactory::getConfig();


$username = $user->username;
$userrow 	= SqlResultArray($con,"SELECT primaryEmail,firstName,lastName FROM ctc.view_members where loginname = '$username' ");
if (count($userrow))
{
    $userid = $userobj->id;
    $email  = $userrow[0]['primaryEmail'];
    $fname  = $userrow[0]['firstName'];
    $lname  = $userrow[0]['lastName'];
}
else
{
    $url = $config->get('live_site');
    echo "<script>window.location.replace('$url');</script>";
    die('not logged on');
}
    
?>
