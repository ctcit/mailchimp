<?php

define('JPATH_BASE', dirname(__DIR__));// Assume mailchimp at top level in website
require_once ( JPATH_BASE.'/configuration.php' );

function MailChimpRequest($method, $args=array(), $timeout = 10)
{
    $config = new JConfig();
    $args['apikey'] = $config->mailchimp_apikey;
    $url = "https://us8.api.mailchimp.com/2.0/$method.json";
    $json_data = json_encode($args);
    $stream =  stream_context_create(array(
            'http' => array(
                'protocol_version' => 1.1,
                'user_agent'       => 'PHP-MCAPI/2.0',
                'method'           => 'POST',
                'header'           => "Content-type: application/json\r\n".
                                      "Connection: close\r\n" .
                                      "Content-length: " . strlen($json_data) . "\r\n",
                'content'          => $json_data,
           ),
        ));
    $result    = file_get_contents($url, null, $stream);

    if ($result)
    {
        return json_decode($result, true);
    }
    else
    {
        echo "MailChimpRequest failed, json_data:<br>$json_data<br>";
        return false;
    }
}

// creates and sends a campaign using the given list
function MailChimpSend($listid,$subject,$body,$from_email,$from_name,$to_name)
{
    $templates = MailChimpRequest("templates/list");
    $args = array(
        "type"    =>"regular",
        "options" =>array(
            "list_id"     => $listid,
            "subject"     => $subject,
            "from_email"  => $from_email,
            "from_name"   => $from_name,
            "to_name"     => $to_name,
            "template_id" => $templates['user'][0]['id']
            ),
        "content" =>array(
            "sections" => Array("body" => $body)
            )
        );

    return ($result = MailChimpRequest("campaigns/create",$args)) &&
        MailChimpRequest("campaigns/send",array("cid"=>$result['id']));
}

// updates the 'Members' list from the ctcweb9_ctc.phplist_listuser view
function MailChimpResetSubscription($con)
{
    MailChimpUpdateLists($con);

    $lists = SqlResultArray($con,"SELECT listid FROM ctcweb9_ctc.mailchimp_lists where listname = 'Members'");
    $listid = $lists[0]["listid"];

    SqlExecOrDie($con,"DELETE from ctcweb9_ctc.mailchimp_subscriptions where listid = '$listid'");
    SqlExecOrDie($con,"INSERT into ctcweb9_ctc.mailchimp_subscriptions(listid,memberid) ".
                 "SELECT '$listid', m.id ".
                 "from ctcweb9_ctc.members m ".
                 "join ctcweb9_ctc.memberships ms on ms.id = m.membershipid ".
                 "where ms.statusAdmin='Active' and m.onEmailListBool ='Yes'");
}


// this updates the ctcweb9_ctc.mailchimp_lists table from the list of lists from mailchimp
function MailChimpUpdateLists($con)
{
    $lists = MailChimpRequest("lists/list");
    $listids = array();

    foreach ($lists['data'] as &$list)
    {
        $id = $list['id'];
        $name = SqlVal($list['name']);
        $listids []= "'$id'";

        SqlExecOrDie($con,"insert into ctcweb9_ctc.mailchimp_lists(listid,listname)
                   values('$id',$name)
                   on duplicate key update listname = $name ");

    }

    $listids = implode(",",$listids);

    SqlExecOrDie($con,"delete from ctcweb9_ctc.mailchimp_subscriptions where listid not in ( $listids )");
    SqlExecOrDie($con,"delete from ctcweb9_ctc.mailchimp_lists         where listid not in ( $listids )");
}

function MailChimpUpdateListsFromDB($con)
{
    $list = SqlResultArray($con, "select listid, listname from ctcweb9_ctc.mailchimp_lists");
    $changed = array();

    foreach ($list as $item)
    {
        $id = $item["listid"];
        $name = $item["listname"];
        $changed = array_merge($changed, MailChimpUpdateListFromDB($con,$id));
    }

    return $changed;
}

// updates the desired mailChimp subscription list from ctcweb9_ctc.mailchimp_subscriptions
// creating, updating or deleting as necessary
function MailChimpUpdateListFromDB($con,$listid)
{
    $list = SqlResultArray($con,"select listname from ctcweb9_ctc.mailchimp_lists where listid='$listid'");
    $listname = $list[0]["listname"];
    //$list = MailChimpSqlResultToArray($con,"select listname from ctcweb9_ctc.mailchimp_lists where listid='$listid'");
    //$listname = $list[0]["listname"];

    // Get the current state from the table
    $sql = "SELECT UCASE(primaryEmail) as K, primaryEmail as EMAIL,trim(firstName) as FNAME,trim(lastName) as LNAME, memberid, 'create' as Action
            from ctcweb9_ctc.members m
            join ctcweb9_ctc.mailchimp_subscriptions ms on ms.memberid = m.id
            where listid='$listid' and primaryEmail != '' and not (primaryEmail is null)
            order by primaryEmail";
    $list = SqlResultArray($con,$sql,"K");
    $changed = array();

    $changed []= "listname=$listname listid=$listid sql returned ".count($list)." items";

    // Get the current state from MailChimp
    for ($start = 0;;$start++)
    {
        $data = MailChimpRequest("lists/members",array("id"=>$listid,"opts"=>array("start"=>$start,"limit"=>50)));

        $changed []= "lists/members listname=$listname listid=$listid start=$start returned ".count($data["data"])." items";

        if (count($data["data"]) == 0)
        {
            break;
        }

        foreach ($data["data"] as $member)
        {
            $email = $member["merges"]["EMAIL"];
            $fname = $member["merges"]["FNAME"];
            $lname = $member["merges"]["LNAME"];
            $key = strtoupper($email);

            if (!array_key_exists($key,$list))
            {
                $list[$key] = $member["merges"];
                $list[$key]["Action"] = "delete";
            }
            else if ($list[$key]["FNAME"] == $fname && $list[$key]["LNAME"] == $lname)
            {
                $list[$key]["Action"] = "ignore";
            }
            else
            {
                $list[$key]["Action"] = "update";
                $list[$key]["old"] = "$fname $lname";
            }
        }
    }

    $actions = array();

    // Generate subscribe or unsubscribe actions as necessary
    foreach ($list as $item)
    {
        $email = $item["EMAIL"];
        $fname = $item["FNAME"];
        $lname = $item["LNAME"];

        $changed []= json_encode($item);

        if ($item["Action"] == 'delete' || $item["Action"] == 'update')
        {
            $changed []= "remove $email from $listname";
            $actions []= array("method" => "lists/unsubscribe",
                       "args"   => array("id" => $listid,
                                 "email" => array("email"=>$email),
                                 "delete_member" => true,
                                 "send_goodbye" => false,
                                 "send_notify" => false));
        }

        if ($item["Action"] == 'create' || $item["Action"] == 'update')
        {
            $changed []= "add $email to $listname";
            $actions []= array("method" => "lists/subscribe",
                       "args"   => array("id" => $listid,
                                         "email" => array("email"=>$email),
                                         "merge_vars" => array("FNAME"=>$fname,"LNAME"=>$lname),
                                         "double_optin" => false,
                                         "update_existing" => true));
        }
    }

    // Send all the actions to Mailchimp
    foreach ($actions as $action)
    {
        MailChimpRequest($action["method"],$action["args"]);
    }

    return $changed;
}


?>
