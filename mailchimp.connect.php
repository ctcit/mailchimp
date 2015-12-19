<?php

require_once( '../globals.php' );
require_once( '../configuration.php' );
require_once( '../includes/joomla.php' );
require_once( '../includes/sef.php' );
require_once( 'mailchimp.inc.php' );


// mainframe is an API workhorse, lots of 'core' interaction routines
$mainframe = new mosMainFrame( $database, '', '.' );
$mainframe->initSession();
$userobj	= $mainframe->getUser();
$username	= $userobj->username;
$userrow 	= MailChimpSqlResultToArray($con,"SELECT primaryEmail,firstName,lastName FROM ctcweb9_ctc.view_members where loginname = '$username' ");
if (count($userrow))
{
	$userid = $userobj->id;
	$email  = $userrow[0]['primaryEmail'];
	$fname  = $userrow[0]['firstName'];
	$lname  = $userrow[0]['lastName'];
}
else
{
	echo "<script>window.location.replace('http://www.ctc.org.nz');</script>";
	die('not logged on');
}
    
?>