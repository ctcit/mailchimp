<?php
class ModerationConfig
{
    const SrcName = "members_test";
    const SrcDomain = "ctc.org.nz";
    const MailRoot = "C:\\wamp\\mail\\ctc.org.nz\\"; // Need better way to define this
    const Unmoderated = "/.Unmoderated";
    const Moderated = "/.Moderated";
    const ModeratorRoleName = "Email Moderator";
    const Step1Preamble = "The following message has just been received for you to moderate:";
    const Step1Url = "mailchimp/moderationStep1.php"; // Add root later
    const Step2Url = "index.php/mail-chimp";
    const Step2DirectUrl = "mailchimp/moderationstep2.php";
    const Step1SubjectPrefix = "[CTC-for-moderation] ";
    const Step2SubjectPrefix = "[CTC] ";
    const Step1SendEnabled = true;
    const Step2SendEnabled = true;
    const BodyClearPattern = "/<[\/]?(html|body)\b[^>]*>/i";
    const ActionDelay = 10;
    const CssFile = "moderation.css";

    public static function GetInboxDir() { return self::MailRoot.self::SrcName; }
    public static function GetModeratedDir() { return self::MailRoot.self::SrcName.self::Moderated; }
    public static function GetUnmoderatedDir() { return self::MailRoot.self::SrcName.self::Unmoderated; }
    public static function GetCss() { return $css = is_null($css) ? ParseCss(file_get_contents(self::CssFile)) : $css; }
};
?>
