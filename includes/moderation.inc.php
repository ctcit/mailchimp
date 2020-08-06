<?php

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

function SendModerationEmail($recipient, $subject, $body) {
    $sender = ModerationConfig::ModerationEmailFrom."@".ModerationConfig::SrcDomain;
    # Invoke JMail Class
    $mailer = JFactory::getMailer();

    # Set sender array so that my name will show up neatly in your inbox
    $mailer->setSender($sender);

    # Add a recipient -- this can be a single address (string) or an array of addresses
    $mailer->addRecipient($recipient);

    $mailer->isHtml(true);
    $mailer->setBody($body);
    $mailer->setSubject(ModerationConfig::Step1SubjectPrefix.$subject);

    # Send once you have set all of your options
    $result = $mailer->send();
}

?>