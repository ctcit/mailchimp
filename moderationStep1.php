<?php

define('_JEXEC', 1);
require_once( 'alastair.php' );
require_once( 'moderation.config.php' );
require_once( 'PlancakeEmailParser.php' );
jimport( 'joomla.mail.helper' );
jimport( 'joomla.mail.mail' );


function GetHtmlFromMessage($msg) {

	if ($msg->getHeader("Content-Transfer-Encoding") == "quoted-printable")	{
		return preg_replace_callback(
			"/=[0-9a-f][0-9a-f]|[<>&=]|\n/i",
			function ($matches) {
				$match = strlen($matches[0]) == 3 ? chr(hexdec($matches[0])) : $matches[0];
				
		        	return	($matches[0] == "=" ? "" : 
		        		($matches[0] == "\n" ? "" : 
		        		($match == "<" ? "&lt;" :
		        		($match == ">" ? "&gt;" :
		        		($match == "&" ? "&amp;" :
		        		($match == "\n" ? "<br/>\n" : chr(hexdec($matches[0]))))))));
		        },
		        $msg->getPlainBody());
        } else {
		return preg_replace(ModerationConfig::BodyClearPattern,"",$msg->getHtmlBody());
	}
	
}
$config = JFactory::getConfig();
$live_site = $config->get("live_site");
echo "<style>".file_get_contents(ModerationConfig::CssFile)."</style>";

$dirs = array(	ModerationConfig::GetInboxDir()."/new", 
		ModerationConfig::GetInboxDir()."/cur");

foreach ($dirs as $dir) {

	$files = scandir($dir);
	
	foreach ($files as $file) {
		if (is_dir("$dir/$file")) {
			continue;
		}
	
		$raw = "ctc-id: ".str_replace("-","",MakeGuid())."\n".
			file_get_contents("$dir/$file");
		$msg = new PlancakeEmailParser($raw);
		$msgid = preg_replace('/,.*$/',"",$file);
		$from = $msg->getHeader("From");
		$subject = $msg->getHeader("Subject");
		$ctcid = $msg->getHeader("ctc-id");
		$body = GetHtmlFromMessage($msg);
		$css = ModerationConfig::GetCss();
		$step2Url = $live_site."/".ModerationConfig::Step2Url;
        $step2ImageUrl = $live_site."/".ModerationConfig::Step2DirectUrl;
			
		$lists = SqlResultArray($con, "select listid, listname from ctc.mailchimp_lists");
		$headers = "MIME-Version: 1.0\r\n".
			   "Content-type: text/html;charset=UTF-8\r\n".
			   "From: <".ModerationConfig::SrcName."@".ModerationConfig::SrcDomain.">\r\n";
		$sender = ModerationConfig::SrcName."@".ModerationConfig::SrcDomain;
		echo "	<table>
			<tr><th>msgid</th><td>$msgid</td>
			<tr><th>ctcid</th><td>$ctcid</td>
			<tr><th>Body</th><td>$body</td>";
		
		$moderators = SqlResultArray($con, "
			select memberid, primaryemail, firstname, lastname
			from ctc.members m
			join ctc.members_roles mr on mr.memberid = m.id 
			join ctc.roles r on r.id = mr.roleid and r.role = ".SqlVal(ModerationConfig::ModeratorRoleName)."");
		
		foreach ($moderators as $moderator) {
			$modid = $moderator["memberid"];
			$modemail = $moderator["primaryemail"];
			$modname = "$moderator[firstname] $moderator[lastname]";
			$props = " width='".$css[".action"]["width"]."px' 
				   height='".$css[".action"]["height"]."px' target='ctcwindow' ";
			$th = "th style='border:solid 1px gray;'";
			$td = "td style='border:solid 1px gray;'";
						
			$modbody = "	<p>".ModerationConfig::Step1Preamble."</p>
					<table style='border-collapse:collapse'>
					<tr><$th>Original Sender</th><$td>".htmlentities($from)."</td></tr>
					<tr><$th>Original Subject</th><$td>".htmlentities($subject)."</td></tr>
					<tr><$th>Original Body</th><$td>$body</td></tr>
					<tr><$th>Options</th><$td>";
			foreach ($lists as $list) {
				$args = array("action" => "sending", "msgid" => $msgid, "ctcid" => $ctcid, "modid" => $modid, "listid" => $list["listid"]);
				$title = "Click to send to the ".$list["listname"]." list";
				$modbody .= "<a href='$step2Url?".http_build_query($args)."'>
				                  <img src='$step2ImageUrl?img=1&".http_build_query($args)."' $props title='".htmlentities($title)."'/></a><br/>";
			}
			
			$args = array("action" => "edit", "msgid" => $msgid, "ctcid" => $ctcid, "modid" => $modid);
			$title = "Click to edit before sending";
			$modbody .= "<a href='$step2Url?".http_build_query($args)."'>
			                  <img src='$step2ImageUrl?img=1&".http_build_query($args)."' $props title='".htmlentities($title)."'/></a><br/>";
			$args = array("action" => "discard", "msgid" => $msgid, "ctcid" => $ctcid, "modid" => $modid);
			$title = "Click to discard";
			$modbody .= "<a href='$step2Url?".http_build_query($args)."'>
			                  <img src='$step2ImageUrl?img=1&".http_build_query($args)."' $props title='".htmlentities($title)."'/></a><br/>";
			$modbody .= "		</td></tr>
					</table>";
		
			if (ModerationConfig::Step1SendEnabled) {

                # Invoke JMail Class
                $mailer = JFactory::getMailer();

                # Set sender array so that my name will show up neatly in your inbox
                $mailer->setSender($sender);

                # Add a recipient -- this can be a single address (string) or an array of addresses
                $mailer->addRecipient($modemail);

                $mailer->isHtml(true);
                $mailer->setBody($modbody);
                $mailer->setSubject(ModerationConfig::Step1SubjectPrefix.$subject);


                # Send once you have set all of your options
                $result = $mailer->send();

                
				//$result = mail($modemail, ModerationConfig::Step1SubjectPrefix.$subject, $modbody, $headers);
			} else {
				$result = "Send Disabled";
			}
				
			echo "	<tr><th>Moderator</th><td>$modemail $modname</td></tr>
				<tr><th>mail() result</th><td>$result</td></tr>
				<tr><th>Moderation message</th><td>$modbody</td></tr>";
		}
		echo "</table>";
		
		file_put_contents(ModerationConfig::GetUnmoderatedDir()."/cur/$msgid,S=".strlen($raw),$raw);
		unlink("$dir/$file");
	}
}	
?>
