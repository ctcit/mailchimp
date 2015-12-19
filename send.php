<?php
// Set flag that this is a parent file
define( '_VALID_MOS', 1 );

require_once( 'mailchimp.connect.php' );

$listid  = $_POST['listid']  ? $_POST['listid']  : '';
$subject = $_POST['subject'] ? $_POST['subject'] : 'Enter Subject here';
$body    = $_POST['body']    ? $_POST['body']    : 'Enter Body here';
$result  = 'Not yet sent';

if ($_POST['send'] == 'Send')
{
	$result = MailChimpSend($listid,$subject,$body);
}

?>
<html>
	<head>
		<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script>
		<script type="text/javascript" src="/mambots/editors/tinymce/jscripts/tiny_mce/tiny_mce.js"></script>
		<script type="text/javascript">
			tinyMCE.init({
				mode : "textareas",
				theme : "simple"
			});
		</script>
	

    <style>
    </style>
    <script>
    

    </script>
	</head>
	<body>
	<form method="POST"  action="send.php">
		<table>
			<tr>
				<td>List</td>
				<td>
					<select name="listid">
						<?php
						$lists = MailChimpRequest("lists/list");
						foreach ($lists['data'] as $item)
						{
							echo "<option value='".$item['id']."' >".$item['name']."</option>";
						}
						?>
					</select>
				</td>
			</tr>
			<tr>
				<td>Subject</td>
				<td><input name="subject" type="text" value="<?php echo $subject ?>"/></td>
			</tr>
			<tr>
				<td>Body</td>
				<td><textarea name="body" id="body" style="width:500px;height:200px"><?php echo $body ?></textarea></td>
			</tr>
			<tr>
				<td><input name="send" type="submit" value="Send"/></td>
			</tr>
			<tr>
				<td>Result</td>
				<td><?php echo json_encode($result)?></td>
			</tr>
		</table>
		<form>
	</body>
</html>