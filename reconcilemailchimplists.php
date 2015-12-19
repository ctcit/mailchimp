<?php

define( '_VALID_MOS', 1 );

require_once( '/home/ctcweb9/public_html/includes/alastair.php' );
require_once( '/home/ctcweb9/public_html/mailchimp/mailchimp.inc.php' );

echo gmdate('Y-m-d H:i:s')." MailChimpResetSubscription\n";

MailChimpResetSubscription($con);

echo gmdate('Y-m-d H:i:s')." MailChimpUpdateLists\n";

MailChimpUpdateLists($con);

echo gmdate('Y-m-d H:i:s')." MailChimpUpdateListsFromDB\n";

MailChimpUpdateListsFromDB($con);

echo gmdate('Y-m-d H:i:s')." reconcilliation complete\n";

?>