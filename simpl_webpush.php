<?php

class simpl_webpush {

    public $options = array(
        'TTL' => 60, 
        'urgency' => 'normal',
        'topic' => 'news',
        'openssl_bin' => '/usr/bin/openssl'
    );

    public $auth_vapid = array(
        'subject' => 'mailto:example@domen.ru', 
        'publicKey' => "abc/+BpW60w111+LTNq5vnMft222/pkfKk3SMb3lzzzl7gDeV1cbq+xA1GU1L333RO1/oMyfGK555wNYsJp444Q=",
        'privateKey' => "123nMwSHJ/sp+456d6Sz8rpeaaaFVr24NBCRxmg7894=", 
        'privateKey.pem' => '-----BEGIN EC PRIVATE KEY-----
ABCCAQEEIJJ+5z111yf7Kft8gXeks/K6XlRHB000QQkcZoFGI+oAoGCCqGSM49
AwEHoUQDQgAEmz/DEFbrTB+HH4tM2rm+cx+3X0r+mR8qTdIxveUie333AN5XVxur
aaa23TUtA0JE7X+gzJ8YrPXDA1iwmmVcpA==
-----END EC PRIVATE KEY-----  '
    );

    function __construct() { 
    }

    function get_headers($endpoint) 
    {
        
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
        
        $http_headers=array(
            'User-Agent'=>'php simpl_webpush',
            'Content-Length'=>0,
            'TTL'=>$this->options['TTL'],
            'Urgency'=>$this->options['urgency'],
            'Topic'=>$this->options['topic'],
            'Authorization' => 'WebPush '.$Authorization,
            'Crypto-Key' => 'p256ecdsa='.$this->base64url_encode(base64_decode($this->auth_vapid['publicKey'])),
        );        
        
        
        return $http_headers;
    }
    
    function get_curl_cmd($endpoint) {
        $http_headers=$this->get_headers($endpoint);
        
        $curl= 'curl -v -X POST ';
        foreach($http_headers as $hk=>$hv) {
            $curl.=' -H "'.$hk.': '.$hv.'"';
        }
        $curl.=' "'.$endpoint.'" ';
        return $curl;
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
 
