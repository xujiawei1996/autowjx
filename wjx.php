#!usr/bin/php
<?php

function request_by_curl($remote_server, $post_string, $wjxId){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $remote_server);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt ($ch, CURLOPT_REFERER, $remote_server);
    $header = set_header($post_string);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}

function request_by_dingding($remote_server, $post_string){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $remote_server);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array ('Content-Type: application/json;charset=utf-8'));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // 线下环境不用开启curl证书验证, 未调通情况可尝试添加该代码
    // curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0);
    // curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, 0);
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}

function set_header($data)
{
    $ip = mt_rand(1,255).".".mt_rand(1,255).".".mt_rand(1,255).".".mt_rand(1,255);
    $header = [
        'X-Forwarded-For' => $ip,
        'Content-Type' => 'application/x-www-form-urlencoded;charset=utf-8',
        'Content-Length' => strlen($data),
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/66.0.3359.181 Safari/537.36',
    ];
    return $header;
}

function dataenc($a,$ktime)
{
    $c = "";
    for ($d = 0;$d < strlen($a);$d++){
        $b = $ktime % 10;
        if ($b == 0) $b = 1;
        //转换unicode
        $e = ord($a[$d]) ^ $b;
        //unicode转为字符
        $c = $c . utf8_encode(chr($e));
    }
    return $c;
}

function getMillisecond() {
    list($t1, $t2) = explode(' ', microtime());
    return (float)sprintf('%.0f', (floatval($t1) + floatval($t2)) * 1000);
}


function getDinnerInfo($wjxId)
{
    $microTime = getMillisecond() + mt_rand(100,300);
    $bookHtml = "https://www.wjx.cn/jq/".$wjxId.".aspx";
    $s = request_by_curl($bookHtml, "",$bookHtml);
    preg_match_all('/starttime=\"(.*)\"/',$s,$startTimes);
    preg_match_all('/jqnonce=\"(.*)\"/',$s,$jqnonces);
    preg_match_all('/rndnum=\"(.*)\"/',$s,$rndnum);
    $startTime = urlencode($startTimes[1][0]);
    $jqnonce = $jqnonces[1][0];
    $rn = $rndnum[1][0];
    $ktimes = mt_rand(10,50);
    $jqsign = urlencode(dataenc($jqnonce,$ktimes));
    $param = "submittype=5&curID=".$wjxId."&t=".$microTime."&starttime=".$startTime.
        "&ktimes=".$ktimes."&rn=".$rn."&hlv=1&jqnonce=".$jqnonce."&jqsign=".$jqsign."&jpm=21";
    $dinnerURL = "https://www.wjx.cn/joinnew/processjq.ashx?".$param;
    return $dinnerURL;
}

function run($webhook,$wjxId){
    $names = [
        '测试张三' => '110',
    ];
    foreach ($names as $name => $tel){
        $dinnerURL = getDinnerInfo($wjxId);
        $dinnerData = "submitdata=1$".$name."}2$9";
        $dinner = request_by_curl($dinnerURL, $dinnerData, "");
        $pos = strpos($dinner,"/");
        $dinner = substr($dinner,$pos);
        if(strpos($dinner,'JoinID')){
            $dinnerRes = date("Y-m-d")."\n订餐成功\n结果页:https://www.wjx.cn".$dinner;
        }else{
            $dinnerRes = date("Y-m-d")."\n订餐失败-原因".$dinner."\n"."请手动订餐";
        }
        $data = array('msgtype' => 'text', 'text' => array('content' => $dinnerRes), 'at'=>["atMobiles"=>[$tel]]);
        $data_string = json_encode($data);
        request_by_dingding($webhook, $data_string);
        sleep(5);
    }
}

$webhook = "dingdingwebhook";
$wjxId = "7484266011111111";
run($webhook,$wjxId);