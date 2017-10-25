# simpl_webpush
very simple  php web push asn1 parser


example:

$wpObj=new simpl_webpush();

/*
echo $wpObj->get_curl_cmd('https://updates.push.services.mozilla.com/wpush/v2/gAAAAABZ1M6lr0S1B0_h2hs47YE6u6dYJ3JN90HhFMIsRBORtCnJfASwcKZZYulq5ZgS');
*/


if(isset($_REQUEST['registr_endpoint']))  {

    echo $wpObj->registr_endpoint();
    exit();
}


if(isset($_REQUEST['get_endpoint_msg']))  {
    $wpObj->get_endpoint_msg();
    exit();
}

if(isset($argv) && isset($argv[1]) && intval($argv[1])>0) {
    $wpObj->cron_child(); 
} else {
    $wpObj->cron();
}
exit();
