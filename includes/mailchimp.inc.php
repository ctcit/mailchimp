<?php

if (!defined('JPATH_BASE')) {
    define('JPATH_BASE', dirname(dirname(__DIR__)));// Assume mailchimp at top level in website
}
require_once ( JPATH_BASE.'/configuration.php' );

function mailChimpRequest($method, $url, $data = null, $audit = null, $verbose = false)
{
    $config = new JConfig();
    $completeurl = $config->mailchimp_apiurl."/$url";
    $streamdata =  array(
        "http" => array(
            "protocol_version" => 1.1,
            "method"           => $method,
            "header"           => "Authorization: apikey ".$config->mailchimp_apikey."\r\n".
                                    "Connection: close\r\n"
        )
    );

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

    $status_line = $http_response_header[0];
    preg_match('{HTTP\/\S*\s(\d{3})}', $status_line, $match);
    $status = $match[1];

    $success = ($status == "200" || $status == "204");

    if ($verbose && !$success) {
        echo "mailChimpRequest failed, method:<b>$method</b> completeurl:<b>$completeurl</b> info:<b>$audit</b> data:<b>$json_data</b> http_response_header:<b>".json_encode($http_response_header)."</b> result:<b>".json_encode($result)."</b>";
    }
    
    return ($success) ? json_decode($result, true) : false;
}

// creates and sends a campaign using the given list
function mailChimpSend($listid,$subject,$body,$from_name,$to_name)
{
    $config = new JConfig();
    $templates = mailChimpRequest("GET","templates?type=user",null,"mailChimpSend");
    /* The free plan on mailchimp now only supprots one "audience"
     * so we can't have multiple lists any more. But you can segment
     * the audience using tags and then target a campaign at a particular segment
     * For testsing, add the following to the "recipients" array to target
     * only developers.
     * TODO - migrate the sub-lists to tags
     *
     "segment_opts" => array(
     	"saved_segment_id" => 627837
     )
     */
    $args = array(
        "type"    =>"regular",
        "recipients" => array(
            "list_id" => $listid,
        ),
        "settings" => array(
            "subject_line"     => $subject,
            "reply_to"  => $config->mailchimp_mailfrom,
            "from_name"   => $from_name,
            )
        );

    $campaign = mailChimpRequest("POST","campaigns",$args,"mailChimpSend", true);

    $result = false;

    if ($campaign)
    {
        $content_args = array(
            "template" => array(
                "id" => $templates['templates'][0]['id'],
                "sections" => Array("body" => $body)
            )
        );

        mailChimpRequest("PUT","campaigns/".$campaign['id']."/content",$content_args,"mailChimpSend", true);

        $result = mailChimpRequest("POST","campaigns/".$campaign['id']."/actions/send",null,"mailChimpSend", true);
    }
    return $result;
}

// updates the an email list in ctc.mailchimp_lists from
// the ctc.memberships table
function mailChimpResetSubscription($con, $listname = "Members", $onListVariable = "onEmailListBool")
{
    mailChimpUpdateLists($con);

    $lists = SqlResultArray($con,"SELECT listid FROM ctc.mailchimp_lists where listname = '$listname'");
    $listid = $lists[0]["listid"];

    SqlExecOrDie($con,"DELETE from ctc.mailchimp_subscriptions where listid = '$listid'");
    SqlExecOrDie($con,"INSERT into ctc.mailchimp_subscriptions(listid,memberid) ".
                 "SELECT '$listid', m.id ".
                 "from ctc.members m ".
                 "join ctc.memberships ms on ms.id = m.membershipid ".
                 "where ms.statusAdmin='Active' and m.$onListVariable ='Yes'");
}


// this updates the ctc.mailchimp_lists table from the list of lists from mailchimp
function mailChimpUpdateLists($con)
{
    $lists = mailChimpRequest("GET","lists?fields=lists.id,lists.name",null,"mailChimpUpdateLists");
    $listids = array();

    foreach ($lists['lists'] as &$list) {
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
    for ($offset = 0, $count = 10; ; $offset += $count)	{
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
                $members[$key] = array("email_address"=>$email);
                $members[$key]["Create"] = "";
                $members[$key]["Update"] = "";
                $members[$key]["FNAME"] = $fname;
                $members[$key]["LNAME"] = $lname;
                $members[$key]["Subscribe"] = $status == "subscribed" ? "unsubscribed" : "";
            } else {
                $members[$key]["Create"] = "";
                $members[$key]["Update"] = $members[$key]["FNAME"] == $fname && $members[$key]["LNAME"] == $lname ? "" : "Update";
                // I'm not exactly sure what the intent was here. Possible statuses are:
                // "subscribed", "unsubscribed", "cleaned", "pending", or "transactional"
                // We don't want to re-seibscribe someone who unsubscribed, or who was cleaned
                #$members[$key]["Subscribe"] = ($status == "subscribed" || $status == "pending") ? "" : "pending";
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
            $memberid = $member["id"];
            $email = $member["email_address"];
            $fname = $member["FNAME"];
            $lname = $member["LNAME"];
            $audit = "update $email $fname $lname";
            $data = array("merge_fields"=> array("FNAME" => $fname, "LNAME"	=> $lname));
            $changed []= $audit;
            $changed []= mailChimpRequest("PATCH","lists/$listid/members/$memberid",$data,$audit);
        }

        if ($member["Subscribe"] != "") {
            $memberid = $member["id"];
            $email = $member["email_address"];
            $fname = $member["FNAME"];
            $lname = $member["LNAME"];
            $audit = $member["Subscribe"]." $email $fname $lname";
            if (!array_key_exists("FNAME",$member)) {
                echo "Member has no FNAME";
                print_r($member);
            }
            $data = array("status" => $member["Subscribe"]);
            $changed []= $audit;
            $changed []= mailChimpRequest("PATCH", "lists/$listid/members/$memberid",$data,$audit);
        }
    }

    // Send all the actions to Mailchimp
    return $changed;
}

?>
