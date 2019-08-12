<?php

define('JPATH_BASE', dirname(__DIR__));// Assume mailchimp at top level in website
require_once ( JPATH_BASE.'/configuration.php' );

function mailChimpRequest($method, $url, $data = null, $audit = null, $verbose = false)
{
    $config = new JConfig();
    $completeurl = "https://us8.api.mailchimp.com/3.0/$url";
    $streamdata =  array(
            "http" => array(
                "protocol_version" => 1.1,
                "method"           => $method,
                "header"           => "Authorization: apikey ".$config->mailchimp_apikey."\r\n".
                                      "Connection: close\r\n"));

    if ($data != null) {
        $contenttype = $method == "PATCH" ? "application/json-patch+json" : "application/json";
        $json_data = json_encode($data);
        $streamdata["http"]["header"] .= "Content-type: $contenttype\r\n".
                                         "Content-length: " . strlen($json_data) . "\r\n".
                                         "Connection: close\r\n";
        $streamdata["http"]["content"] = $json_data;
    }

    $stream = stream_context_create($streamdata);
    if ($verbose) {
        $result = file_get_contents($completeurl, null, $stream);
    } else {
        $result = @file_get_contents($completeurl, null, $stream);
    }

    if ($result) {
        return json_decode($result, true);
    }

    if ($verbose) {
        echo "mailChimpRequest failed, method:<b>$method</b> completeurl:<b>$completeurl</b> info:<b>$audit</b> data:<b>$json_data</b> http_response_header:<b>".json_encode($http_response_header)."</b> result:<b>".json_encode($result)."</b>";
    }
    
    return false;
}

// creates and sends a campaign using the given list
function mailChimpSend($listid,$subject,$body,$from_email,$from_name,$to_name)
{
    $templates = mailChimpRequest("GET","templates?type=user",null,"mailChimpSend");
    $args = array(
        "type"    =>"regular",
        "options" =>array(
            "list_id"     => $listid,
            "subject"     => $subject,
            "from_email"  => $from_email,
            "from_name"   => $from_name,
            "to_name"     => $to_name,
            "template_id" => $templates['templates'][0]['id']
            ),
        "content" =>array(
            "sections" => Array("body" => $body)
            )
        );

    $campaign = mailChimpRequest("POST","campaigns",$args,"mailChimpSend");

    if (!$campaign)
    {
        return $campaign;
    }

    return mailChimpRequest("POST","campaigns/".$campaign['id']."/actions/send",null,"mailChimpSend");
}

// updates the 'Members' list from the ctc.phplist_listuser view
function mailChimpResetSubscription($con)
{
    mailChimpUpdateLists($con);

    $lists = SqlResultArray($con,"SELECT listid FROM ctc.mailchimp_lists where listname = 'Members'");
    $listid = $lists[0]["listid"];

    SqlExecOrDie($con,"DELETE from ctc.mailchimp_subscriptions where listid = '$listid'");
    SqlExecOrDie($con,"INSERT into ctc.mailchimp_subscriptions(listid,memberid) ".
                 "SELECT '$listid', m.id ".
                 "from ctc.members m ".
                 "join ctc.memberships ms on ms.id = m.membershipid ".
                 "where ms.statusAdmin='Active' and m.onEmailListBool ='Yes'");
}


// this updates the ctc.mailchimp_lists table from the list of lists from mailchimp
function mailChimpUpdateLists($con)
{
    $lists = mailChimpRequest("GET","lists?fields=lists.id,lists.name",null,"mailChimpUpdateLists");
    $listids = array();

    foreach ($lists['data'] as &$list) {
        $id = $list['id'];
        $name = SqlVal($list['name']);
        $listids []= "'$id'";

        SqlExecOrDie($con,"insert into ctc.mailchimp_lists(listid,listname)
                   values('$id',$name)
                   on duplicate key update listname = $name ");

    }

    if (count($listids) > 0) {
        $listids = implode(",",$listids);

        SqlExecOrDie($con,"delete from ctc.mailchimp_subscriptions where listid not in ( $listids )");
        SqlExecOrDie($con,"delete from ctc.mailchimp_lists         where listid not in ( $listids )");
    }
}

function mailChimpUpdateListsFromDB($con)
{
    $list = SqlResultArray($con, "select listid, listname from ctc.mailchimp_lists");
    $changed = array();

    foreach ($list as $item) {
        $id = $item["listid"];
        $name = $item["listname"];
        $changed = array_merge($changed, mailChimpUpdateListFromDB($con,$id));
    }

    return $changed;
}

// updates the desired mailChimp subscription list from ctc.mailchimp_subscriptions
// creating, updating or deleting as necessary
function mailChimpUpdateListFromDB($con,$listid)
{
    $list = SqlResultArray($con,"select listname from ctc.mailchimp_lists where listid='$listid'");
    $listname = $list[0]["listname"];

    // Get the current state from the table
    $sql = "SELECT primaryEmail as email_address,
                    trim(firstName) as FNAME,
                    trim(lastName) as LNAME, 
                    'Create' as `Create`,
                    '' as `Update`,
                    '' as `Subscribe`,
                    UCASE(primaryEmail) as `Key`					
            from ctc.members m
            join ctc.mailchimp_subscriptions ms on ms.memberid = m.id
            where listid='$listid' and primaryEmail != '' and not (primaryEmail is null)
            order by primaryEmail";
    $members = SqlResultArray($con,$sql,"Key");
    $changed = array();

    $changed []= "listname='$listname' listid='$listid' sql returned ".count($members)." items";

    // Get the current state from MailChimp
    for ($offset = 1, $count = 10; ; $offset += $count)	{
        $geturl = "lists/$listid/members?fields=members.id,members.status,members.email_address,members.merge_fields&count=$count&offset=$offset";
        $mailchimpmembers = mailChimpRequest("GET", $geturl, null, "mailChimpUpdateListFromDB get members");
        $changed []= "$geturl returned ".count($mailchimpmembers["members"])." items";

        foreach ($mailchimpmembers["members"] as $member) {
            $id = $member["id"];
            $status = $member["status"];
            $email = $member["email_address"];
            $fname = $member["merge_fields"]["FNAME"];
            $lname = $member["merge_fields"]["LNAME"];
            $key = strtoupper($email);

            if (!array_key_exists($key,$members)) {
                $members[$key] = array("email"=>$email);
                $members[$key]["Create"] = "";
                $members[$key]["Update"] = "";
                $members[$key]["Subscribe"] = $status == "subscribed" ? "unsubscribed" : "";
            } else  {
                $members[$key]["Create"] = "";
                $members[$key]["Update"] = $members[$key]["FNAME"] == $fname && $members[$key]["LNAME"] == $lname ? "" : "Update";
                $members[$key]["Subscribe"] = ($status == "subscribed" || $status == "pending") ? "" : "pending";
            }

            $members[$key]["id"] = $id;
            $members[$key]["status"] = $status;
        }

        if (count($mailchimpmembers["members"]) < $count){
            break;
        }
    }

    // Generate subscribe or unsubscribe actions as necessary
    foreach ($members as $member) {
        $memberid = $member["id"];

        $changed []= $member;

        if ($member["Create"] != "") {
            $email = $member["email_address"];
            $fname = $member["FNAME"];
            $lname = $member["LNAME"];
            $audit = "create $email $fname $lname";
            $data = array(	"email_address" => $email,
                            "status"		=> "subscribed",
                            "merge_fields"	=> array("FNAME" => $fname, "LNAME"	=> $lname));
            $changed []= $audit;
            $changed []= mailChimpRequest("POST","lists/$listid/members",$data,$audit);
        }

        if ($member["Update"] != "") {
            $email = $member["email_address"];
            $fname = $member["FNAME"];
            $lname = $member["LNAME"];
            $audit = "update $email $fname $lname";
            $data = array("merge_fields"=> array("FNAME" => $fname, "LNAME"	=> $lname));
            $changed []= $audit;
            $changed []= mailChimpRequest("PATCH","lists/$listid/members/$memberid",$data,$audit);
        }

        if ($member["Subscribe"] != "") {
            $email = $member["email_address"];
            $fname = $member["FNAME"];
            $lname = $member["LNAME"];
            $audit = $member["Subscribe"]." $email $fname $lname";
            $data = array("status" => $member["Subscribe"]);
            $changed []= $audit;
            $changed []= mailChimpRequest("PATCH", "lists/$listid/members/$memberid",$data,$audit);
        }
    }

    // Send all the actions to Mailchimp
    return $changed;
}

?>
