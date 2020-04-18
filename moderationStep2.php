<?php
define('_JEXEC', 1);
require_once( 'includes/alastair.php' );
require_once( 'config/moderation.config.php' );
require_once( 'includes/PlancakeEmailParser.php' );
require_once( 'includes/mailchimp.inc.php' );

$getpost =  $_GET["action"] == null ? $_POST : $_GET;
//var_dump($getpost);
$isImg = intval($getpost["img"]) == 1;
$action = strval($getpost["action"]);
$prevaction = strval($getpost["prevaction"]);
$msgid = strval($getpost["msgid"]);
$ctcid = strval($getpost["ctcid"]);
$modid = strval($getpost["modid"]);
$listid = strval($getpost["listid"]);
$editedsubject = strval($getpost["editedsubject"]);
$editedbody = strval($getpost["editedbody"]);
$editedfrom = strval($getpost["editedfrom"]);

if (!$isImg) {
    // Do this now before anything gets changed
    GetLogonDetails($con,$username, $params, "role = ".SqlVal(ModerationConfig::ModeratorRoleName));
}

$unmoderateddir = ModerationConfig::GetUnmoderatedDir()."/cur/$msgid,*";
$moderateddir = ModerationConfig::GetModeratedDir()."/cur/$msgid,*";
$files = array_merge(glob($unmoderateddir), glob($moderateddir));
$location = (count($files) == 0 ? null :
        (strpos($files[0],ModerationConfig::GetUnmoderatedDir()) === 0 ? "unmoderated" :
        (strpos($files[0],ModerationConfig::GetModeratedDir()) === 0 ? "moderated" : null)));
if ($location != null) {
    $raw = file_get_contents($files[0]);
    $msg = new PlancakeEmailParser($raw);
    //var_dump($ctcid, $msg);
    $ctcaction = $msg->getHeader("ctc-action");
    $msg = $msg == null || $ctcid != $msg->getHeader("ctc-id") ? null : $msg;
 }

if ($listid != "") {
    $query = SqlResultArray($con, "select listname from ctc.mailchimp_lists where listid = '$listid'");
    $listname = $query[0]["listname"];
}

$subject   = $msg == null || $prevaction == "edit" ? $editedsubject : "[CTC] ".$msg->getHeader("Subject") ;
$body      = $msg == null || $prevaction == "edit" ? $editedbody    : $msg->getHtmlBody();
$body      = preg_replace(ModerationConfig::BodyClearPattern,"",$body);
// $from      = $msg == null || $prevaction == "edit" ? $editedfrom    : $msg->getHeader("From");
// Don't set the "From" field as who it's actually from. Unfortunately mail-chimp
// doesn't let you specify a different "reply-to" address, and the "From" address has to be a validated address
// (ege webmaster@ctc.org.nz). This causes no end of confusion with people reply directly to the emails
// thinking they are emailing the person who the email says it is from. Worse, webmaster@ctc.org.nz often
// gets ssaved to people's address books under the name of someone who sent a club email, resulting in the
// webmaster receiving personal emails. Instead just default to "Christchurch Tramping Club"
// This can of course be over-ridden in the editor
$from      = $msg == null || $prevaction == "edit" ? $editedfrom    : "Christchurch Tramping Club";

if ($action == "list") {
    $captionimg = $captionweb = "List the emails";
    $subject = $body = $msgid = $raw = $ctcaction = "";
    $list = array();
    if (!$isImg) {
        $templist = array(array("name"=>"Unmoderated","folder" => ModerationConfig::GetUnmoderatedDir()."/cur"),
                  array("name"=>"Moderated",  "folder" => ModerationConfig::GetModeratedDir()."/cur"));
        foreach ($templist as &$folder) {
            $files = scandir($folder["folder"]);
            $count = 0;
            foreach ($files as $file) {
                if (is_dir("$folder[folder]/$file") || preg_match("/\.edited\.html$/",$file)) {
                    continue;
                }
                $count++;
                $raw = file_get_contents("$folder[folder]/$file");
                $msg = new PlancakeEmailParser($raw);
                $classname = explode(" ",$msg->getHeader("ctc-action"));
                $folder["emails"] []= array(
                    "msgid" => preg_replace('/,.*$/',"",$file),
                    "ctcid" => $msg->getHeader("ctc-id"),
                    "ctcaction" => $msg->getHeader("ctc-action"),
                    "classname" => ($classname[0] == "NOT" || $classname[0] == "" ? "warning" : $classname[0]), //blank classname happens sometimes after stuffup
                    "subject" => $msg->getHeader("Subject"),
                    "from" => $msg->getHeader("From"),
                    "date" => $msg->getHeader("Date"));
            }
            if ($count !== 0){
              unset($folder["folder"]);
              $list[] = $folder;
            }
        }
    }
} else if ($msg == null) {
    $action = "deleted";
    $captionimg = $captionweb = "This message does not exist";
    $subject = $body = $msgid = $raw = $ctcaction = "";
} else if ($location == "unmoderated" && $action == "sending") {
    $captionimg = "Click to send to the $listname list";
} else if ($location == "unmoderated" && ($action == "discard" || $action == "send")) {
    $query = SqlResultArray($con, "select firstname, lastname from ctc.members where id = $modid");
    $modname = $query[0]["firstname"]." ".$query[0]["lastname"];
    
    if ($action == "discard") {
        $ctcaction = "discarded by $modname";
        $captionimg = "Click to discard this message";
    } else if (!ModerationConfig::Step2SendEnabled) {
        $ctcaction = "sent to $listname by $modname (disabled)";
    } else if (mailChimpSend($listid, $step2SubjectPrefix.$subject, $body, $from, "You") === false) {
        $ctcaction = "NOT sent to $listname by $modname - API call failed";
        $action = "warning";
    } else {
        $ctcaction = "sent to $listname by $modname";
    }
        
    $captionweb = "This message has just been $ctcaction";

    if (!$isImg) {
        // Add ctcaction to the message
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
} else if ($location == "moderated" && ($action == "undo" || $action = "retry")) {
    // remove ctcaction from message
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
if ($isImg){
    $css = ModerationConfig::GetCss();
    $actionSizeX = $css[".action"]["width"];
    $actionSizeY = $css[".action"]["height"];
    $actionGap = $css[".actiongap"]["height"];
    $icon = @imagecreatefrompng("images/$action.png") or die("cannot create png image - images/$action.png");
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
    
    imagettftext($image,$fillY*0.6,0,$fillY+$actionBorder,$fillY*0.8,GetColor($image,$colorfg),"styles/arial.ttf",$captionimg);
    imagecopyresized($image,$icon,$actionBorder,$actionBorder,0,0,imagesx($icon),imagesy($icon),imagesx($icon),imagesy($icon));
            
    header("Content-type: image/png");
    imagepng($image);
    imagedestroy($image);
    imagedestroy($icon);
} else {
    $config = JFactory::getConfig();
    $step2Url = $config->get('live_site')."/".ModerationConfig::Step2DirectUrl;
    $moderationconfig = new ReflectionClass("ModerationConfig");
    $data = array(	"action" => $action,
            "ctcid" => $ctcid,
            "msgid" => $msgid,
            "modid" => $modid,
            "ctcaction" => $ctcaction,
            "subject" => $subject,
            "body" => $body,
            "from" => $from,
            "list" => $list,
            "lists" => $action == "edit" ? SqlResultArray($con, "select listid, listname from ctc.mailchimp_lists") : null,
            "listid" => $listid,
            "listname" => $listname,
            "captionweb" => $captionweb,
            "formaction" => $step2Url,
            "livesite"=> $config->get('live_site'),
            "editedhtml" => @file_get_contents("$moderated/cur/$msgid.edited.html"),
            "config" => $moderationconfig->getConstants(),
            "raw" => $raw);
    echo str_replace('$data',json_encode($data),file_get_contents("moderationStep2.html"));
}
?> 
