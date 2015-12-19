<?php
// Set flag that this is a parent file
define( '_VALID_MOS', 1 );

require_once( 'mailchimp.connect.php' );

$lists = MailChimpRequest("lists/list");

if ($_POST['update'] == 'Update') {
	$listids = Array();
	foreach ($lists['data'] as &$list) {
		if ($_POST[$list['id']] == 'on') {
			$listids []= "'".$list['id']."'";
		}
	}
	echo "$userid ".join(",",$listids)."<br>";
	$sql = "DELETE from ctcweb9_ctc.mailchimp_subscriptions where memberId = $userid and listid not in (".join(",",$listids).")";
	echo "<pre>$sql</pre>";
	echo mysql_query($sql,$con) or die('delete');
	$sql = "INSERT into ctcweb9_ctc.mailchimp_subscriptions(listid,memberid)
			SELECT listid,$userid 
			FROM ctcweb9_ctc.mailchimp_lists
			WHERE listid in (".join(",",$listids).")
			AND listid not in (select listid from ctcweb9_ctc.mailchimp_subscriptions where memberid = $userid)";
	echo "<pre>$sql</pre>";
	echo mysql_query($sql,$con) or die('insert');
	
			
	$result = MailChimpUpdateListsFromDB($con);
} else {
	$result = Array("Waiting");
}
	
echo json_encode(MailChimpSqlResultToArray($con,"select ml.listname, ms.* 
						from ctcweb9_ctc.mailchimp_subscriptions ms
						join ctcweb9_ctc.mailchimp_lists ml on ml.listid = ms.listid
						where memberId = $userid"));

foreach ($lists['data'] as &$list)
{
	$memberinfoargs = Array("id"=>$list['id'],"emails"=>Array(Array("email"=>$email)));
	$memberinfo = MailChimpRequest("lists/member-info",$memberinfoargs);
	$list['is_subscribed'] = sizeof($memberinfo['data']) > 0;
}
    
?>
<html>
	<head>
		<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script>
    <style>

    </style>
    <script>
    

    </script>
	</head>
	<body>
	<form method="POST"  action="subscribe.php">
		<table>
			<tr>
				<td>UserId</td>
				<td><?php echo $userobj->id; ?></td>
			</tr>
			<tr>
				<td>UserName</td>
				<td><?php echo $username ?></td>
			</tr>
			<tr>
				<td>Name</td>
				<td><?php echo "$fname $lname" ?></td>
			</tr>
			<tr>
				<td>Email</td>
				<td><?php echo $email ?></td>
			</tr>
			<tr>
				<td>Lists</td>
				<td>
					<?php
					foreach ($lists['data'] as &$list)
					{
						$id = $list['id'];
						$name = $list['name'];
						$checked = $list['is_subscribed'] ? 'checked' : '';
						echo "<input type='checkbox' name='$id' id='$id' $checked><label for='$id'>$name</label><br/>";
					}
					?>
				</td>
			</tr>
			<tr>
				<td><input name="update" type="submit" value="Update"/></td>
			</tr>
			<tr>
				<td>Result</td>
				<td><?php echo join("<br/>",$result)?></td>
			</tr>
		</table>
		<form>
	</body>
</html>