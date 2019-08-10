<?php
	// Set flag that this is a parent file
	define( '_VALID_MOS', 1 );
	
	require_once( 'mailchimp.connect.php' );
	        
	$method  = $_POST['method']  ? $_POST['method']  : 'GET';
	$url     = $_POST['url']     ? $_POST['url']     : 'lists';
	$replace = $_POST['replace'] ? $_POST['replace'] : '';
	$data    = $_POST['data']    ? $_POST['data']    : '{}';
	$result  = '';
	$pre  = '';
	$config	 = new JConfig();
	
	if ($url == "jksdfjhksdfawesdfsdfjklsdfkl")
	{
		$result = json_encode($config->mailchimp_apikey);
	}
	else if ($_POST['send'] == 'MailChimpAPI')
	{
		$result = json_encode(MailChimpRequest($method,$url,($method == "GET") ? null : json_decode($data,true)));
	}
	else if ($_POST['send'] == 'MailChimpUpdateLists')
	{
		MailChimpUpdateLists($con);
		$result = json_encode(MailChimpSqlResultToArray($con,'select * from ctc.mailchimp_lists'));
	}
	else if ($_POST['send'] == 'MailChimpResetSubscription')
	{
		MailChimpResetSubscription($con);
		$result = json_encode($_POST['send']);
	}
	else if ($_POST['send'] == 'MailChimpUpdateListFromDB')
	{
		$result = json_encode(MailChimpUpdateListFromDB($con,$data));
	}
	else if ($_POST['send'] == 'MailChimpUpdateListsFromDB')
	{
		$result = json_encode(MailChimpUpdateListsFromDB($con));
	}
	else if ($_POST['send'] == 'SQL')
	{
		$result = json_encode(MailChimpSqlResultToArray($con,$data));
	}
	else if ($_POST['send'] == 'preg_replace')
	{
		$result = json_encode(preg_replace($method,$replace,$data));
	}
	else if ($_POST['send'] == 'reconcilemailchimplists.log')
	{
		$filesize = filesize("reconcilemailchimplists.log");
		$filepos = max($filesize-100000,0);
		$filehandle = fopen("reconcilemailchimplists.log", "r") or die("Unable to open file!");
		fseek($filehandle,$filepos);
		$pre = "size:$filesize\n".fread($filehandle,$filesize-$filepos);
		fclose($filehandle);
	}

?>
<html>
	<head>
		<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script>
    <style>

        .jsonInfo {
            vertical-align: top;
            text-align: right;
            font-weight: bold;
            color: green;
        }

        .jsonHead {
            vertical-align: top;
            font-weight: bold;
            color: blue;
        }

        .jsonBody {
            vertical-align: top;
        }

        .jsonTail {
            vertical-align: bottom;
            font-weight: bold;
            color: blue;
        }

        .jsonShrink {
            height: 20px;
            border: dashed 1px darkgray;
            overflow-y: scroll;
        }

        .jsonTable {
            border-collapse: collapse;
        }

        .jsonButton {
            padding: 0px;
            margin: 0px;
            height: 10px;
            width: 10px;
            opacity: 0.3;
        }

        .jsonButton:hover {
            opacity: 1;
        }
    </style>
    <script>
    
	var escapes = { '"': '\\"', '\t': '\\t', '\r': '\\r', '\n': '\\n<span class="jsonHead">"+<br/>"</span>', '<': '&lt;', '>': '&gt;', '&': '&amp;' };

    	
        function jsontohtml(json) {
            var html = '';

            if (json == null) {
                return '<span class="jsonHead">null</span>';
            } else if (json.constructor == Array) {
                for (var i = 0; i < json.length; i++) {
                    html += '<tr><td class="jsonInfo">' + (i == 0 ? '<input type="button" class="jsonButton" onclick="ToggleTable(this)"/>' : '') + '</td>' +
                                '<td class="jsonInfo">/*' + i + '/' + json.length + '*/</td>' +
                                '<td class="jsonHead">' + (i == 0 ? '[' : '') + '</td>' +
                                '<td class="jsonBody">' + jsontohtml(json[i]) + '</td>' +
                                '<td class="jsonTail">' + (i == json.length - 1 ? ']' : ',') + '</td></tr>';
                }

                return html == '' ? '<span class="jsonHead">[]</span>' : '<div><table class="jsonTable">' + html + '</table></div>';
            } else if (json.constructor == Object) {
                var keys = [];

                for (var i in json) {
                    keys.push(i);
                }

                for (var i = 0; i < keys.length; i++) {
                    html += '<tr><td class="jsonInfo">' + (i == 0 ? '<input type="button" class="jsonButton" onclick="ToggleTable(this)"/>' : '') + '</td>' +
                                '<td class="jsonInfo">/*' + i + '/' + keys.length + '*/</td>' +
                                '<td class="jsonHead">' + (i == 0 ? '{' : '') + '</td>' +
                                '<td class="jsonBody">' + jsontohtml(keys[i]) + '</td>' +
                                '<td class="jsonHead">:</td>' +
                                '<td class="jsonBody">' + jsontohtml(json[keys[i]]) + '</td>' +
                                '<td class="jsonTail">' + (i == keys.length - 1 ? '}' : ',') + '</td></tr>';
                }

                return html == '' ? '<span class="jsonHead">{}</span>' : '<div><table class="jsonTable">' + html + '</table></div>';
            } else if (json.constructor == String) {
                for (var i = 0; i < json.length; i++) {
                    html += escapes[json.substr(i, 1)] || json.substr(i, 1);
                }

                return '<span class="jsonHead">"</span>' + html + '<span class="jsonHead">"</span>';
            } else {
                return json.toString();
            }
        }
        
        function ToggleTable(button) {
            $($(button).parents('div')[0]).toggleClass("jsonShrink");
        }

        $(document).ready(function(){
        	$('#result').html(jsontohtml( <?php echo $result ?> ));
        });

    </script>
	</head>
	<body>
	<form method="POST"  action="mailchimptest.php">
		<table>
			<tr>
				<td>Method</td>
				<td><input name="method" type="test" value="<?php echo $method ?>" style="width:500px"/></td>
			</tr>
			<tr>
				<td>Urlpattern</td>
				<td><input name="url" type="test" value="<?php echo $url ?>" style="width:500px"/></td>
			</tr>
			<tr>
				<td>Replace</td>
				<td><input name="replace" type="test" value="<?php echo $replace ?>" style="width:500px"/></td>
			</tr>
			<tr>
				<td>JSON</td>
				<td><textarea name="data" style="width:500px;height:200px"><?php echo $data ?></textarea></td>
			</tr>
			<tr>
				<td colspan="2">
					<input name="send" type="submit" value="MailChimpAPI"/>
					<input name="send" type="submit" value="MailChimpUpdateLists"/>
					<input name="send" type="submit" value="MailChimpResetSubscription"/>
					<input name="send" type="submit" value="MailChimpUpdateListFromDB"/>
					<input name="send" type="submit" value="MailChimpUpdateListsFromDB"/>
					<input name="send" type="submit" value="SQL"/>
					<input name="send" type="submit" value="preg_replace"/>
					<input name="send" type="submit" value="reconcilemailchimplists.log"/>
				</td>
			</tr>
			<tr>
				<td>Result</td>
				<td id='result'></td>
			</tr>
		</table>
		<form>
		<pre><?php echo $pre ?></pre>
	</body>
</html>
