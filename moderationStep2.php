<?php

define( '_VALID_MOS', 1 );

require_once( '/home1/ctcweb9/public_html/includes/alastair.php' );
require_once( '/home1/ctcweb9/public_html/mailchimp/moderation.config.php' );
require_once( '/home1/ctcweb9/public_html/mailchimp/PlancakeEmailParser.php' );
require_once( '/home1/ctcweb9/public_html/mailchimp/mailchimp.inc.php' );

$getpost =  $_GET["action"] == null ? $_POST : $_GET;
$isImg = intval($getpost["img"]) == 1;
$action = strval($getpost["action"]);
$prevaction = strval($getpost["prevaction"]);
$msgid = strval($getpost["msgid"]);
$ctcid = strval($getpost["ctcid"]);
$modid = strval($getpost["modid"]);
$listid = strval($getpost["listid"]);
$editedsubject = strval($getpost["editedsubject"]);
$editedbody = strval($getpost["editedbody"]);

$files = array_merge(	glob(ModerationConfig::GetUnmoderatedDir()."/cur/$msgid,*"),
			glob(ModerationConfig::GetModeratedDir()."/cur/$msgid,*"));
$location = (count($files) == 0 ? null :
	    (strpos($files[0],ModerationConfig::GetUnmoderatedDir()) === 0 ? "unmoderated" :
	    (strpos($files[0],ModerationConfig::GetModeratedDir()) === 0 ? "moderated" : null)));
	   
if ($location != null) {
	$raw = file_get_contents($files[0]);
	$msg = new PlancakeEmailParser($raw);
	$ctcaction = $msg->getHeader("ctc-action");
	$msg = $msg == null || $ctcid != $msg->getHeader("ctc-id") ? null : $msg;
}

if ($listid != "") {
	$query = SqlResultArray($con, "select listname from ctcweb9_ctc.mailchimp_lists where listid = '$listid'");
	$listname = $query[0]["listname"];
}

$subject   = $msg == null || $prevaction == "edit" ? $editedsubject : $msg->getHeader("Subject") ;
$body      = $msg == null || $prevaction == "edit" ? $editedbody    : $msg->getHtmlBody();
$body      = preg_replace(ModerationConfig::BodyClearPattern,"",$body);

if ($action == "list") {
	$captionimg = $captionweb = "List the emails";
	$subject = $body = $msgid = $raw = $ctcaction = "";
	
	if (!$isImg) {
		$list = array(array("name"=>"Unmoderated","folder" => ModerationConfig::GetUnmoderatedDir()."/cur"),
			      array("name"=>"Moderated",  "folder" => ModerationConfig::GetModeratedDir()."/cur"));
		foreach ($list as &$folder) {
			$files = scandir($folder["folder"]);
			foreach ($files as $file) {
				if (is_dir("$folder[folder]/$file") || preg_match("/\.edited\.html$/",$file)) {
					continue;
				}
				$raw = file_get_contents("$folder[folder]/$file");
				$msg = new PlancakeEmailParser($raw);
				$folder["emails"] []= array(
					"msgid" => preg_replace('/,.*$/',"",$file),
					"ctcid" => $msg->getHeader("ctc-id"),
					"ctcaction" => $msg->getHeader("ctc-action"),
					"subject" => $msg->getHeader("Subject"),
					"from" => $msg->getHeader("From"),
					"date" => $msg->getHeader("Date"));
			}
			unset($folder["folder"]);
		}
	}
} else if ($msg == null) {
	$action = "deleted";
	$captionimg = $captionweb = "This message does not exist";
	$subject = $body = $msgid = $raw = $ctcaction = "";
} else if ($location == "unmoderated" && $action == "sending") {
	$captionimg = "Click to send to the $listname list";
} else if ($location == "unmoderated" && ($action == "discard" || $action == "send")) {
	$query = SqlResultArray($con, "select firstname, lastname from ctcweb9_ctc.members where id = $modid");
	$modname = $query[0]["firstname"]." ".$query[0]["lastname"];
	
	if ($action == "discard") {
		$ctcaction = "discarded by $modname";
		$captionimg = "Click to discard this message";
	} else if (!ModerationConfig::Step2SendEnabled) {
		$ctcaction = "sent to $listname by $modname (disabled)";
	} else if (MailChimpSend($listid, $step2SubjectPrefix.$subject, $body) === false) {
		$ctcaction = "NOT sent to $listname by $modname - API call failed";
		$action = "warning";
	} else {
		$ctcaction = "sent to $listname by $modname";
	}
		
	$captionweb = "This message has just been $ctcaction";

	if (!$isImg) {
		$raw = "ctc-action: $ctcaction\n$raw";
		file_put_contents(ModerationConfig::GetModeratedDir()."/cur/$msgid,S=".strlen($raw),$raw);
		unlink($files[0]);
		
		if ($prevaction == "edit") {
			file_put_contents(ModerationConfig::GetModeratedDir()."/cur/$msgid.edited.html","<h3>".htmlentities($subject)."</h3>\n$body");
		}
	}
} else if ($location == "unmoderated" && $action == "edit") {
	$captionimg = "Click to edit before sending or discarding";
	$captionweb = "Edit before sending or discarding";
} else if ($location == "moderated" && $action == "undo") {

	$raw = str_replace("ctc-action: $ctcaction\n","",$raw);

	file_put_contents(ModerationConfig::GetUnmoderatedDir()."/cur/$msgid,S=".strlen($raw),$raw);
	unlink($files[0]);
	$ctcaction = "";
	$action = "edit";
	
	$captionimg = "Click to edit before sending or discarding";
	$captionweb = "Edit before sending or discarding";
} else {
	$action = "warning";
	$captionimg = "Already $ctcaction";
	$captionweb = "Already $ctcaction";
}

if ($isImg)
{
	$css = ModerationConfig::GetCss();
	$actionSizeX = $css[".action"]["width"];
	$actionSizeY = $css[".action"]["height"];
	$actionGap = $css[".actiongap"]["height"];
	$icon = @imagecreatefrompng("$action.png") or die("cannot create png image - $action.png");
	$image = @imagecreatetruecolor($actionSizeX,$actionSizeY) or die("cannot create image");
	
	$colorfg = $css[".$action"]["color"];	
	$colorbg = $css[".$action"]["background"];
	$fillX = $actionSizeX;
	$fillY = $actionSizeY - $actionGap;
	
	imagefilledrectangle($image,0,0,$actionSizeX,$actionSizeY,GetColor($image,"white"));
	imagefilledrectangle($image,0,0,$fillX-1,$fillY-1,GetColor($image,$colorbg));

	for ($x1 = 0, $y1 = 0, $x2 = $fillX-1, $y2 = $fillY-1; $x1 < $actionGap; $x1++,$y1++,$x2--,$y2--)
	{
		imagefilledrectangle($image, $x1, $y2, $x2, $y2, GetColor($image,$colorbg,"black"));
		imagefilledrectangle($image, $x2, $y1, $x2, $y2, GetColor($image,$colorbg,"black"));
		imagefilledrectangle($image, $x1, $y1, $x2, $y1, GetColor($image,$colorbg,"white"));
		imagefilledrectangle($image, $x1, $y1, $x1, $y2, GetColor($image,$colorbg,"white"));
	}
	
	imagettftext($image,$fillY*0.6,0,$fillY+$actionBorder,$fillY*0.8,GetColor($image,$colorfg),"arial.ttf",$captionimg);
	imagecopyresized($image,$icon,$actionBorder,$actionBorder,0,0,imagesx($icon),imagesy($icon),imagesx($icon),imagesy($icon));
			
	header("Content-type: image/png");
	imagepng($image);
	imagedestroy($image);
	imagedestroy($icon);
} else {
	GetLogonDetails($con,$username,
			"action=$action&ctcid=$ctcid&msgid=$msgid&modid=$modid&redirect=mailchimp/moderationStep2.php",
			"role = ".SqlVal(ModerationConfig::ModeratorRoleName));

	$config = new ReflectionClass("ModerationConfig");
	$data = array(	"action" => $action,
			"ctcid" => $ctcid,
			"msgid" => $msgid,
			"modid" => $modid,
			"ctcaction" => $ctcaction,
			"subject" => $subject,
			"body" => $body,
			"list" => $list,
			"lists" => $action == "edit" ? SqlResultArray($con, "select listid, listname from ctcweb9_ctc.mailchimp_lists") : null,
			"listid" => $listid,
			"listname" => $listname,
			"captionweb" => $captionweb,
			"formaction" => $step2Url,
			"editedhtml" => @file_get_contents("$moderated/cur/$msgid.edited.html"),
			"config" => $config->getConstants(),
			"raw" => $raw);
	echo str_replace('$data',json_encode($data),file_get_contents("/home1/ctcweb9/public_html/mailchimp/moderationStep2.html"));
}
?> 