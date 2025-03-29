<?php

define( '_VALID_MOS', 1 );

require_once( 'includes/alastair.php' );
require_once( 'includes/mailchimp.inc.php' );

echo gmdate('Y-m-d H:i:s')." MailChimpResetSubscription\n";

mailChimpResetSubscription($con, "Members", "onEmailListBool");
mailChimpResetSubscription($con, "TripLeaders", "onTripLeaderEmailListBool");

echo gmdate('Y-m-d H:i:s')." MailChimpUpdateLists\n";

mailChimpUpdateLists($con);

echo gmdate('Y-m-d H:i:s')." MailChimpUpdateListsFromDB\n";

mailChimpUpdateListsFromDB($con);

echo gmdate('Y-m-d H:i:s')." reconcilliation complete\n";

?>