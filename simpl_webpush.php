<?php

//chrome In v51 and less, the `gcm_sender_id` is needed to get a push subscription.
//opera the `gcm_sender_id` is needed to get a push subscription. 
//Samsung Internet Browser `gcm_sender_id` is needed to get a push subscription. 

/*

CREATE TABLE `webpush` (
  `id` int(10) UNSIGNED NOT NULL,
  `endpoint_host` varchar(255) NOT NULL,
  `endpoint_path` varchar(255) NOT NULL,
  `key` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `status` tinyint(3) UNSIGNED NOT NULL,
  `reg_timestamp` int(11) UNSIGNED NOT NULL,
  `last_send_timestamp` int(11) UNSIGNED NOT NULL,
  `ajax_timestamp` int(11) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `uui` varchar(50) NOT NULL,
  `http_code` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
 
ALTER TABLE `webpush`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `endpoint_full` (`endpoint_host`,`endpoint_path`) USING BTREE,
  ADD KEY `key` (`key`),
  ADD KEY `token` (`token`),
  ADD KEY `status` (`status`),
  ADD KEY `last_send_timestamp` (`last_send_timestamp`),
  ADD KEY `endpoint_host` (`endpoint_host`) USING BTREE,
  ADD KEY `endpoint_path` (`endpoint_path`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `uui` (`uui`),
  ADD KEY `ajax_timestamp` (`ajax_timestamp`);
 
ALTER TABLE `webpush`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
 
CREATE TABLE `webpush_log` (
  `id` int(11) UNSIGNED NOT NULL,
  `cnt_to_send` int(11) UNSIGNED NOT NULL,
  `cnt_sended` int(11) UNSIGNED NOT NULL,
  `cnt_ajax` int(11) UNSIGNED NOT NULL,
  `cnt_rcv_code_200` int(11) UNSIGNED NOT NULL,
  `cnt_rcv_code_300` int(11) UNSIGNED NOT NULL,
  `cnt_rcv_code_400` int(11) UNSIGNED NOT NULL,
  `cnt_rcv_code_500` int(11) UNSIGNED NOT NULL,
  `last_send_timestamp` int(11) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
 
 ALTER TABLE `webpush_log` ADD `message` TEXT NOT NULL AFTER `last_send_timestamp`; 
 ALTER TABLE `webpush_log` ADD `url` VARCHAR(255) NOT NULL AFTER `message`; 
 
ALTER TABLE `webpush_log`
  ADD PRIMARY KEY (`id`)
  ADD KEY `last_send_timestamp` (`last_send_timestamp`) USING BTREE;
 
ALTER TABLE `webpush_log`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
 * */    
 
class simpl_webpush {
    public $GCM_URL= 'https://android.googleapis.com/gcm/send';
    public $options = array(
        'TTL' => 3600, 
        'urgency' => 'normal',
        'topic' => 'news',
        'openssl_bin' => '/usr/bin/openssl'
    );

    public $auth_GCM = 'AIza123456789011111111111111111111111QU';
    public $auth_vapid = array( 
        'subject' => 'mailto:arkadev.a@example.ru', 
        'publicKey' => "BJs/+12345678901111111111111111111111111111111111111111111111111111111111111111111111KQ=",
        'privateKey' => "kn7n123456789011111111111111111111111111114=",
        'privateKey.pem'=>'-----BEGIN EC PRIVATE KEY-----
MHcCA11111111111111111111111111111111111111111111111111111qGSM49
AwE11111111111111111111111111111111111111111111111111111111XVxur
7E11111111111111111111111111111111==
-----END EC PRIVATE KEY-----'
    );

    public $max_forks=10;
    public $is_debug=true;
    
    public $phpbin='/opt/atomic/atomic_php70/root/usr/bin/php';

    function __construct() {
        
    }

    function get_endpoint_msg() {
        $ret=array('body'=>'');
        $data=file_get_contents('php://input');
        $page_tab_status='';
         
        $today_timestamp=strtotime(date('Y-m-d'));

        $ret['body']='Обратите внимание';
        $ret['icon']='/favicon.ico';
        $ret['tag']='news'.$today_timestamp; 
        $ret['title']='EXAMPLE.ru';         
        
        if(trim($data)!='') {
            @$data=json_decode($data,true);
        }
        $endpoint=array('host'=>'','path'=>'');
        if(is_array($data)) {
            
            $page_tab_status=(isset($data['page_tab_status'])?trim($data['page_tab_status']):'');
            $page_tab_status=(!in_array($page_tab_status,array('closed','opened','hidden'))?'':$page_tab_status);
            if(isset($data['endpoint']) && trim($data['endpoint'])!='') {
                $data['endpoint']=strtolower($data['endpoint']);
                $endpoint=parse_url($data['endpoint']);
 
            }
        }

        @$result=mysql_query("SELECT message,url FROM webpush_log WHERE last_send_timestamp = '". $today_timestamp."' LIMIT 1");
        if($result && mysql_num_rows($result)){
            $row=mysql_fetch_assoc($result); 
            if(trim($row['message'])!='')
                $ret['body']=$row['message'];
            if(trim($row['url'])!='' && stripos(trim($row['url']),'http')===0)
                $ret['url']=trim($row['url']);
        }
         
        foreach($ret as $k=>$v) {
            $ret[$k]=iconv('cp1251','utf8',$v);
        }
        
        header('Content-Type: application/json');
        echo json_encode($ret);

        if(trim($endpoint['host'])!='' && trim($endpoint['path'])!='') {   
            $sql="UPDATE webpush
                    SET   
                        ajax_timestamp='".time()."'
                    WHERE  
                         endpoint_host='".mysql_real_escape_string( trim($endpoint['host']) )."' AND endpoint_path='".mysql_real_escape_string( trim($endpoint['path']) )."'  
                    LIMIT 1";
             
            @mysql_query($sql);
        }

        $sql="UPDATE webpush_log
                SET   
                    cnt_ajax=(1+cnt_ajax)
                WHERE  
                    last_send_timestamp ='".$today_timestamp."' 
                LIMIT 1";        
        @mysql_query($sql);
        
        exit();
    }

    function registr_endpoint() {

        if(isset($_REQUEST['registr_endpoint']))  {
            
            $data=file_get_contents('php://input');

            if(trim($data)!='') {
                @$data=json_decode($data,true);
            }
            
            if(is_array($data) && isset($data['endpoint']) && isset($data['key']) && isset($data['token']) && trim($data['endpoint'])!='') {
                
                //dont strtolower!!!
                //$data['endpoint']=strtolower($data['endpoint']);
                
                $endpoint=parse_url($data['endpoint']);
                if(trim($endpoint['host'])!='' && trim($endpoint['path'])!='') {
                    
                    @session_start();
                    $user_id=0;
                    $uui='';
                    if(isset($_SESSION['sess_user_id'])) {
                        $user_id=intval($_SESSION['sess_user_id']);
                    }elseif(isset($_COOKIE['uai']) && trim($_COOKIE['uai']) != '' && strlen(trim($_COOKIE['uai'])) > 5){ 
                        
                        $result=mysql_query("SELECT user_id FROM myuser WHERE user_uai = '". mysql_real_escape_string(trim($_COOKIE['uai']))."' AND user_uai != '' LIMIT 1"); 
                        if($result && mysql_num_rows($result)){
                            $row=mysql_fetch_assoc($result); 
                            $user_id=intval($row['user_id']);   
                        }
                        
                    }elseif(isset($_COOKIE['uui']) && trim($_COOKIE['uui']) != ''){
                        $uui=trim($_COOKIE['uui']);
                    }
                    
                    $sql="INSERT INTO webpush SET `endpoint_host`='".mysql_real_escape_string(strtolower($endpoint['host']))."',
                                                  `endpoint_path`='".mysql_real_escape_string($endpoint['path'])."',
                                                 `key`='".mysql_real_escape_string($data['key'])."',
                                                 `token`='".mysql_real_escape_string($data['token'])."',
                                                 `status`='1',
                                                 `reg_timestamp`=unix_timestamp(),
                                                 `user_id`='".intval($user_id)."',
                                                 `uui`='".mysql_real_escape_string($uui)."'
                                                 ";
                    mysql_query($sql);
                    return 1;
                }
            }
            
            
        }
        
        return 0;
    }

    function get_headers($endpoint) 
    {
        $http_headers=array();
        if (substr(strtolower($endpoint), 0, strlen($this->GCM_URL)) == $this->GCM_URL) {
            $http_headers['Content-Type']='application/json';
            $http_headers['Authorization'] = 'key='.$this->auth_GCM;
            return $http_headers;
        }        
        
        $audience = parse_url($endpoint, PHP_URL_SCHEME).'://'.parse_url($endpoint, PHP_URL_HOST);
        $expiration = time() + 43200; 
        $header = array(
            'typ' => 'JWT',
            'alg' => 'ES256',
        );

        $jwtPayload = json_encode(array(
            'aud' => $audience,
            'exp' => $expiration,
            'sub' => $this->auth_vapid['subject'],
        ), JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);

        $data=$this->base64url_encode(json_encode($header)).'.'.$this->base64url_encode($jwtPayload);
        
        
        $signature=false;
        openssl_sign($data, $signature, $this->auth_vapid['privateKey.pem'], 'sha256');
        
        $signature=$this->parse_asn1($signature);

        $Authorization=$data.'.'.$this->base64url_encode(pack('H*', $signature));
        
        
        $today_timestamp=strtotime(date('Y-m-d'));
        $http_headers=array(
            'User-Agent'=>'php simpl_webpush',
            'Content-Length'=>0,
            'TTL'=>$this->options['TTL'],
            'Urgency'=>$this->options['urgency'],
            'Topic'=>$this->options['topic'].$today_timestamp,
            'Authorization' => 'WebPush '.$Authorization,
            'Crypto-Key' => 'p256ecdsa='.$this->base64url_encode(base64_decode($this->auth_vapid['publicKey'])),
        );        
        
        
        return $http_headers;
    }
    
    function get_content($endpoint) {
        
        if (substr(strtolower($endpoint), 0, strlen($this->GCM_URL)) == $this->GCM_URL) {
                $endpoint=parse_url($endpoint);
                $reg_id=explode('/',trim($endpoint['path'],' /'));
                $reg_id=end($reg_id);
                return json_encode(array('registration_ids'=>array($reg_id)));
        }
        return '';
    }
    
    function get_curl_cmd($endpoint) {
        $http_headers=$this->get_headers($endpoint);
        $http_content=$this->get_content($endpoint);
        
        $curl= 'curl -v -X POST ';
        foreach($http_headers as $hk=>$hv) {
            $curl.=' -H "'.$hk.': '.$hv.'"';
        }
        $curl.=' "'.$endpoint.'" '.(!empty($http_content)?' -d '.escapeshellarg($http_content):'');
        return $curl;
    }

    function cron_child() {  

        if($this->is_debug) {        
            error_reporting(E_ALL);
            ini_set('display_errors',1); 
            header('X-Accel-Buffering: no');
            ob_end_clean(); 
            ob_implicit_flush(true); 
        }          
        
        global $argv;
        if(PHP_SAPI=='cli') {
            if(isset($argv) && isset($argv[1])) {
                $child_id=intval($argv[1]);
                if($child_id<=0) {
                    $this->log('error child_id empty'."\r\n"); 
                    exit();
                }
                $this->log('$child_id='.$child_id."\r\n");
                //sleep(($this->max_forks-$child_id));
                
                $this->cron_send_endpoints4child($child_id);
                
                $this->log('$child_END='.$child_id."\r\n"); 
            } 
        }
        exit();
    }

    function cron_fork() {
 
        $phpbin=$this->phpbin;
        $php_script=$_SERVER['DOCUMENT_ROOT'].'/admin/cron/webpush.php'; 
  
        $procs=array();
        $pipes=array(); 
        $this->log("go\r\n");
        for($procs_i=0;$procs_i<$this->max_forks;$procs_i++) {
            $pipes[$procs_i]=array();
            $this->log("popen\r\n");
            $procs[$procs_i]=proc_open($phpbin.' '.$php_script.' '.($procs_i+1).'  2>&1 ', array(1 => array("pipe", "w")), $pipes[$procs_i]);
            if($procs[$procs_i] && is_resource($procs[$procs_i])) {   
                 stream_set_blocking($pipes[$procs_i][1], 0);
            } else {
                unset($procs[$procs_i]);
                $procs_i--;
            } 
        }
         
        $read_pipes=array();
        foreach($pipes as $pipe)
            $read_pipes[]=$pipe[1];
        $read=$read_pipes;
        $write=null;
        $except=null; 
        $this->log("while select\r\n");
        while( false !== ($num = stream_select($read, $write,  $except, 1)) ) {
       
            foreach($read as $read_pipe) {
                $proc_data=stream_get_contents($read_pipe); 
                if(!empty($proc_data))
                    $this->log($proc_data."\r\n"); 
            }
            $read=$read_pipes;


            $eof_i=0;
            foreach($procs as $proc) {
                $proc_status = proc_get_status( $proc );
                if(empty($proc_status['running']) ) {
                    $eof_i++;
                }                
            }
            if($eof_i>=$this->max_forks) {
                break;
            }
                        
            /*$eof_i=0;
            foreach($read_pipes as $read_pipe) {
                $stream_meta_data = stream_get_meta_data($read_pipe);  
                if(!empty($stream_meta_data['eof']) ) {
                    $eof_i++;
                }
            }
            if($eof_i>=3) {
                break;
            } */           

        } 
        
        $this->log("close\r\n");
        foreach($procs as $procs_i=>$proc) {
            fclose($pipes[$procs_i][1]); 
            proc_close($procs[$procs_i]); 
        }
    }

    function cron_send_endpoints4child($child_id) {
        if($child_id<1|| $child_id>$this->max_forks) {
            $this->log(" wrong child_id \r\n");
            exit();
        }

        $batch_limit=100;

        $today_timestamp=strtotime(date('Y-m-d'));
        $sql="SELECT id,endpoint_host,endpoint_path 
                FROM webpush 
                WHERE `status`='1' 
                AND mod(id,".$this->max_forks.")=".($child_id==$this->max_forks?0:$child_id)."
                AND last_send_timestamp<'".$today_timestamp."'
                
                LIMIT 1000";

         
        $res=mysql_query($sql);
        $mozilla=array();
        $google_fcm=array();
        $google_android=array();
        
        if($res && mysql_num_rows($res)) {
            while(($row=mysql_fetch_assoc($res))) {
                if($row['endpoint_host']=='fcm.googleapis.com') {
                    $google_fcm[$row['id']]=$row['endpoint_path'];
                    if(count($google_fcm)>=$batch_limit) {
                        $this->send($google_fcm,$row['endpoint_host'],$child_id);
                        $google_fcm=array();
                    }
                } else if($row['endpoint_host']=='updates.push.services.mozilla.com') {
                    $mozilla[$row['id']]=$row['endpoint_path'];
                    if(count($mozilla)>=$batch_limit) {
                        $this->send($mozilla,$row['endpoint_host'],$child_id);
                        $mozilla=array();
                    }                        
                } else if($row['endpoint_host']=='android.googleapis.com') {
                    $google_android[$row['id']]=$row['endpoint_path'];
                    if(count($google_android)>=$batch_limit) {
                        $this->send_json($google_android,$row['endpoint_host'],$child_id);
                        $google_android=array();
                    }
                }
            }
            if(!empty($google_fcm)) {
                $this->send($google_fcm,'fcm.googleapis.com',$child_id); 
            } 
            if(!empty($mozilla)) {
                $this->send($mozilla,'updates.push.services.mozilla.com',$child_id);
            }
            if(!empty($google_android)) {
                $this->send_json($google_android,'android.googleapis.com',$child_id);
            }
        }
        
    }

    function cron() {
        if($this->is_debug) {        
            error_reporting(E_ALL);
            ini_set('display_errors',1); 
            header('X-Accel-Buffering: no');
            ob_end_clean(); 
            ob_implicit_flush(true);
            $this->log('<pre>');
        }        
     
     
        $today_timestamp=strtotime(date('Y-m-d'));

        mysql_query("DELETE FROM webpush WHERE reg_timestamp<'".strtotime('now -3 day')."'  AND http_code!='' AND  ((http_code>=300 AND http_code<500) OR http_code>507) ");
        
        $webpush_log_id=0;
        @$result=mysql_query("SELECT id,message FROM webpush_log WHERE last_send_timestamp ='".$today_timestamp."'  LIMIT 1");
        if($result && mysql_num_rows($result)){
            $webpush_log_id=mysql_fetch_assoc($result);
            if(trim($webpush_log_id['message'])!='') {
                $webpush_log_id=$webpush_log_id['id'];
            } else {
                $webpush_log_id=0;
            }
        }
        
        if(empty($webpush_log_id)) {
            
            $this->log('empty webpush_log message !!!'."\r\n");
            exit();
        }
          
        
        $cnt_to_send=0;
        $sql="SELECT count(id) as cnt
                FROM webpush 
                WHERE `status`='1'  
                AND last_send_timestamp<'".$today_timestamp."' ";    
       
        $res=mysql_query($sql);
        if($res && mysql_num_rows($res)) {
            $cnt_to_send=mysql_fetch_assoc($res);
            $cnt_to_send=$cnt_to_send['cnt'];
        }
        
        $sql="UPDATE webpush_log
                SET   
                    cnt_to_send ='".$cnt_to_send."',
                    last_send_timestamp ='".$today_timestamp."' 
                    WHERE
                        id='".$webpush_log_id."'
                ";        
        mysql_query($sql);     
     
        $this->cron_fork();
        
        $this->log('CRON FINISHED'."\r\n");
    }    

    function send_json($endpoinds,$endpoint_host='android.googleapis.com',$child_id=0) {
        
        $this->log('#'.$child_id.' $endpoinds='.var_export($endpoinds,1)."\r\n");

        $today_timestamp=strtotime(date('Y-m-d'));
        
        $firstendpoint_path=current($endpoinds);
        $endpoint='https://'.$endpoint_host.$firstendpoint_path;
        $http_headers=$this->get_headers($endpoint);
        
        $ctx_params =array(
            'ssl'=>array(
                'verify_peer'=>false,
            ),
            'http' => array(
                'ignore_errors'=>true,
                'timeout'=>(count($endpoinds)*1.5),
                'method'  => 'POST',
                'header'  => join("\r\n",array_map(function($k) use ($http_headers) { return $k.': '.$http_headers[$k];},array_keys($http_headers)))                 
            )
        );    
        
        $registration_ids=array();
        foreach($endpoinds as $endpoint_id=>$endpoint_path) {            
            $endpoint='https://'.$endpoint_host.$endpoint_path; 
            $http_content=$this->get_content($endpoint);
            if(!empty($http_content)) {
                $http_content=json_decode($http_content,1);
                if(isset($http_content['registration_ids'])) {
                    $registration_ids=array_merge($registration_ids,$http_content['registration_ids']);
                }
            }          
        }        
        $ctx_params['http']['content']=json_encode(array('registration_ids'=>$registration_ids));


        $log_id='#'.$child_id.'.'.crc32(serialize($ctx_params)).' ';
        
        $this->log('#'.$log_id.' JSON_START'."\r\n");
 
        $http_response_header=false;
        $ret=file_get_contents('https://android.googleapis.com/gcm/send', false, stream_context_create($ctx_params)); 
        $this->log('#'.$log_id.' '.' JSON_send'."\r\n" );        

        $sql="UPDATE webpush_log
                SET   
                    cnt_sended=(".count($registration_ids)."+cnt_sended)
                WHERE  
                    last_send_timestamp ='".$today_timestamp."' 
                LIMIT 1";        
        mysql_query($sql);
        
        $http_code=500;        
        if(!empty($http_response_header)) { 
                $m=array();
                preg_match('#^\s*HTTP/1\.[0-1]\s+([0-9]+).+?\r\n#im',join("\r\n",$http_response_header),$m);
                if(!empty($m) &&  isset($m[1])) { 
                    $http_code=intval($m[1]);
                }    
        }
        
        $this->log('#'.$log_id.'  $http_code:'.$http_code."  EEEND\r\n\r\n\r\n");        
        
        $endpoind_status=array();
        $i=0;
        foreach($endpoinds as $endpoint_id=>$endpoint_path) {
            $endpoind_status[$i]=array('id'=>$endpoint_id,'status'=>500);
            $i++;
        }

        if(!empty($ret)) {

            $ret=json_decode($ret,true);
            if(!empty($ret) && is_array($ret) && isset($ret['results'])) {
                foreach($ret['results'] as $i=>$status_info) {
                    if(isset($status_info['error']) && isset($endpoind_status[$i])) {
                        $endpoind_status[$i]['status']=500;
                    } else if(isset($endpoind_status[$i])) {
                        $endpoind_status[$i]['status']=200;
                    }
                }
            }
        } 
         
        foreach($endpoind_status as $inf) {
                
                if($inf['status']==500) {
                    $http_code=' http_code = if(http_code>=500,(http_code+1),500), ';
                }
                
                $sql="UPDATE webpush
                        SET  
                            `status`='".(in_array($inf['status'],array(200,500))?1:0)."',
                            $http_code
                            last_send_timestamp='".time()."',
                            ajax_timestamp='0'
                        WHERE 
                            `id`=".intval($inf['id'])." 
                        LIMIT 1";

                mysql_query($sql);
                 
                $sql="UPDATE webpush_log
                        SET   
                            cnt_rcv_code_".$inf['status']."=(1+cnt_rcv_code_".$inf['status'].")
                        WHERE  
                            last_send_timestamp ='".$today_timestamp."' 
                        LIMIT 1";        
                mysql_query($sql);  
                
        } 
        
    }
     
    function send($endpoinds,$endpoint_host='fcm.googleapis.com',$child_id=0) {
  
        $this->log('#'.$child_id.' $endpoinds='.var_export($endpoinds,1)."\r\n");
  
        $context = stream_context_create();
        $result = stream_context_set_option($context, 'ssl', 'verify_peer', false);
        $result = stream_context_set_option($context, 'ssl', 'verify_host', false);
        $connect_timeout=30;
        $read_timeout=1;
        $read_bytes=1024;
        $fp = stream_socket_client('ssl://'.$endpoint_host.':443', $err, $errstr, $connect_timeout, STREAM_CLIENT_CONNECT, $context);
        
        //socket_set_option($fp, SOL_SOCKET, SO_RCVTIMEO, array('sec'=>$read_timeout, 'usec'=>0));
        stream_set_timeout($fp, $read_timeout);
        stream_set_chunk_size($fp,1024);
        stream_set_read_buffer($fp,0); 
        
        $today_timestamp=strtotime(date('Y-m-d'));
        if($fp) {
            foreach($endpoinds as $endpoint_id=>$endpoint_path) {
                $endpoint='https://'.$endpoint_host.$endpoint_path;
                $http_headers=$this->get_headers($endpoint);
                $http_content=$this->get_content($endpoint);
                
                $url=parse_url($endpoint); 
                $request_data="POST ".(!empty($url['path'])?$url['path']:'/')." HTTP/1.1\r\n";
                $request_data.="Host: ".$url['host']."\r\n";
                foreach($http_headers as $h_name=>$h_val) {
                    $request_data.=$h_name.': '.$h_val."\r\n";
                } 
                
                $request_data.="Connection: keep-alive\r\n";
                
                $request_data.="\r\n";
                if(!empty($http_content))
                    $request_data.=$http_content;
                
                $log_id='#'.$child_id.'.'.crc32($request_data).' ';
                
                $this->log('#'.$log_id.' '.$request_data."\r\n");

                $wrn=$this->fwrite_stream($fp,$request_data);

                $sql="UPDATE webpush_log
                        SET   
                            cnt_sended=(1+cnt_sended)
                        WHERE  
                            last_send_timestamp ='".$today_timestamp."' 
                        LIMIT 1";        
                mysql_query($sql);

                $this->log('#'.$log_id.' '.' WRITEN:'.$wrn."\r\n" );
                
                $response_data=$this->fread_stream($fp,$read_bytes);

                $m=array();
                $http_code=500;
                preg_match('#^\s*HTTP/1\.[0-1]\s+([0-9]+).+?\r\n#im',$response_data,$m);
                if(!empty($m) &&  isset($m[1])) { 
                    $http_code=intval($m[1]);
                }
 
                $this->log('#'.$log_id.'  $http_code:'.$http_code."  EEEND\r\n\r\n\r\n");

                $cnt_rcv_code=(intval($http_code/100)*100);

//404,410 -expired
//429 - Too many requests
if($cnt_rcv_code==400 && !in_array(intval($http_code),array(404,410))) {
   $cnt_rcv_code=500;
}
                $cnt_rcv_code=(in_array($cnt_rcv_code,array(200,300,400,500))?$cnt_rcv_code:500);
                
                $http_code=$cnt_rcv_code;
                if($cnt_rcv_code==500) {
                    $http_code=' http_code = if(http_code>=500,(http_code+1),500), ';
                } else if($cnt_rcv_code!=200) {
                    $http_code=" http_code = '".$cnt_rcv_code."', ";
                } 
                
                $sql="UPDATE webpush
                        SET  
                            `status`='".(in_array($cnt_rcv_code,array(200,500))?1:0)."',
                            ".$http_code."
                            last_send_timestamp='".time()."',
                            ajax_timestamp='0'
                        WHERE 
                            `id`=".intval($endpoint_id)." 
                        LIMIT 1";
            
                mysql_query($sql);
                
                
                
                $sql="UPDATE webpush_log
                        SET   
                            cnt_rcv_code_".$cnt_rcv_code."=(1+cnt_rcv_code_".$cnt_rcv_code.")
                        WHERE  
                            last_send_timestamp ='".$today_timestamp."' 
                        LIMIT 1";        
                mysql_query($sql);                
 
            }

            fclose($fp);            
        }
         

    }
    
    function log($str) {
        if($this->is_debug) {
            echo $str;
            flush();
            @ob_flush();
        }
    }

    function fread_stream($fp,$read_bytes=1024) {
        $response_data=''; 
        $try_cnt=0; 
        $stop=0; 
         
        while(!feof($fp)) { 
            
            $fread_data=fread($fp,  $read_bytes); 
            $response_data .= $fread_data;
            
            $stream_meta_data = stream_get_meta_data($fp); 
            if($stream_meta_data['unread_bytes']<=0) {
                break;
            } else {
                $read_bytes=$stream_meta_data['unread_bytes'];
            }
 
            if($fread_data==false||$fread_data=='') { 
// || !empty($stream_meta_data['timed_out'])                                
                if(!empty($stream_meta_data['eof']) || $stream_meta_data['unread_bytes'] <= 0) {
                    $this->log(' $stream_meta_data:'.var_export($stream_meta_data,1) ."   \r\n\r\n\r\n");                          
                    break;
                }
            } else {
                $try_cnt=0; 
            } 
                
            if($try_cnt>=2
            // || preg_match('/[^0-9]+\r\n\r\n/i',$response_data)
            ) { 
$this->log(' $try_cnt:'.$try_cnt ."   \r\n\r\n\r\n");                
                break;
            }
            $try_cnt++;
        }
        
        return $response_data;
    }
    
    function fwrite_stream($fp, $string) {
        $try_cnt=0;
        for ($written = 0; $written < strlen($string); $written += $fwrite) {
            $fwrite = fwrite($fp, substr($string, $written));
            if ($fwrite === false) {
                return $written;
            }
            if($try_cnt>=5){
                return $written;
            }
            $try_cnt++;
        }
        return $written;
    }    

    function base64url_encode($data, $use_padding = false)
    {
        $encoded = strtr(base64_encode($data), '+/', '-_');

        return true === $use_padding ? $encoded : rtrim($encoded, '=');
    }

    function base64url_decode($data)
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    function parse_asn1($signature) 
    {
        $asnraw=false;
        $pipes=array();
        //$cmd='openssl asn1parse -inform DER | cut -d":" -f4| tr \'\\n\'  "\\0"';
        $cmd=$this->options['openssl_bin'].' asn1parse -inform DER';
        $process = proc_open($cmd, array(0 => array("pipe", "r"),1 => array("pipe", "w")), $pipes);
        if($process && is_resource($process)) {  
            fwrite($pipes[0], $signature);
            fclose($pipes[0]);
            
            $asnraw=stream_get_contents($pipes[1]);
            $m=array();
            if(preg_match_all('/:[A-z0-9]{2,}/',$asnraw,$m)) {
                $asnraw=strtolower(join('',array_map(function($a) { return trim($a,':');},$m[0])));
            } else {
                $asnraw=false;
            } 
            fclose($pipes[1]); 
            proc_close($process); 
        }      
        return $asnraw;  
    }

     
} 

?>
