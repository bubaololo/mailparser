<?php
set_time_limit(0);
ignore_user_abort();


$resArray = [];
function convertToSimpleArray($array){
    global $resArray; 
    if(is_array($array)){
        foreach($array as $below){
            $res = convertToSimpleArray($below);
        }
    }else{
        $resArray[] = $array; 
    }
    return $resArray; 
}

$raw = (trim($_POST['one']));
// $raw = (trim(file_get_contents('urls_20.txt')));
$arrayRE = '|\s|';
$urlArray = preg_split($arrayRE,$raw);
$urls = array_filter($urlArray);
// $preUrls = array_filter($urlArray);
// $urls = [];

// foreach ($preUrls as $url) {

// if(filter_var($url, FILTER_VALIDATE_URL)) {
//     $urls[]=parse_url($url, PHP_URL_HOST);
// } else {continue;}
// }




$dir = dirname(__FILE__);
$config['cookie_file'] = $dir . '/cookies/' . md5(microtime()) . '.txt';


function curl_fetch_multi_2(array $urls_unique, int $max_connections = 100, array $additional_curlopts = null)
{

    // $urls_unique = array_unique($urls_unique);
    $ret = array();
    $mh = curl_multi_init();
    // $workers format: [(int)$ch]=url
    $workers = array();
    $max_connections = min($max_connections, count($urls_unique));
    $unemployed_workers = array();
    for ($i = 0; $i < $max_connections; ++ $i) {
        $unemployed_worker = curl_init();
        if (! $unemployed_worker) {
            throw new \RuntimeException("failed creating unemployed worker #" . $i);
        }
        $unemployed_workers[] = $unemployed_worker;
    }
    unset($i, $unemployed_worker);

    $work = function () use (&$workers, &$unemployed_workers, &$mh, &$ret): void {
        assert(count($workers) > 0, "work() called with 0 workers!!");
        $still_running = null;
        for (;;) {
            do {
                $err = curl_multi_exec($mh, $still_running);
            } while ($err === CURLM_CALL_MULTI_PERFORM);
            if ($err !== CURLM_OK) {
                $errinfo = [
                    "multi_exec_return" => $err,
                    "curl_multi_errno" => curl_multi_errno($mh),
                    "curl_multi_strerror" => curl_multi_strerror($err)
                ];
                $errstr = "curl_multi_exec error: " . str_replace([
                    "\r",
                    "\n"
                ], "", var_export($errinfo, true));
                throw new \RuntimeException($errstr);
            }
            if ($still_running < count($workers)) {
                // some workers has finished downloading, process them
                // echo "processing!";
                break;
            } else {
                // no workers finished yet, sleep-wait for workers to finish downloading.
                // echo "select()ing!";
                curl_multi_select($mh, 1);
                // sleep(1);
            }
        }
        while (false !== ($info = curl_multi_info_read($mh))) {
            if ($info['msg'] !== CURLMSG_DONE) {
                // no idea what this is, it's not the message we're looking for though, ignore it.
                continue;
            }


            $ch = $info['handle'];
            $ch_index = (int) $ch;
            $url = $workers[$ch_index];
            $ret[$url] = curl_multi_getcontent($ch);
            unset($workers[$ch_index]);
            curl_multi_remove_handle($mh, $ch);
            $unemployed_workers[] = $ch;
        }
    };

    $opts = array(
        CURLOPT_URL => '',
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_ENCODING => '',
        CURLOPT_FOLLOWLOCATION => 1,
        CURLOPT_SSL_VERIFYPEER => 0,
CURLOPT_SSL_VERIFYSTATUS => 0,
CURLOPT_SSL_VERIFYHOST => 0,
CURLOPT_USERAGENT => 'Safari: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0 Safari/605.1.15',
CURLOPT_REFERER => 'https://www.google.com/',
        

    );
    if (! empty($additional_curlopts)) {
        // i would have used array_merge(), but it does scary stuff with integer keys.. foreach() is easier to reason about

        foreach ($additional_curlopts as $key => $val) {
            $opts[$key] = $val;
        }
    }
    foreach ($urls_unique as $url) {
        while (empty($unemployed_workers)) {
            $work();
        }
        $new_worker = array_pop($unemployed_workers);
        $opts[CURLOPT_URL] = $url;
        if (! curl_setopt_array($new_worker, $opts)) {
            $errstr = "curl_setopt_array failed: " . curl_errno($new_worker) . ": " . curl_error($new_worker) . " " . var_export($opts, true);
            throw new RuntimeException($errstr);
        }
        $workers[(int) $new_worker] = $url;
        curl_multi_add_handle($mh, $new_worker);
    }
    while (count($workers) > 0) {
        $work();
    }
    foreach ($unemployed_workers as $unemployed_worker) {
        curl_close($unemployed_worker);
    }
    curl_multi_close($mh);
    return $ret;
}

$urlsContent = curl_fetch_multi_2($urls);

$results = [];
foreach($urlsContent as $url => $content) {


    $mailRE = '/\b\w+@\w+\.[^(jpg)(png)(webp)(jpeg)(gif)(avif)(svg)]\w{2,8}/';
    preg_match_all($mailRE,$content,$mailMatches);

    if (!empty($mailMatches[0])) {

        $matches = array_unique($mailMatches[0]);

        foreach($matches as &$match) {
            $match = $match.'|'.$url;
        }

    array_push($results,$matches);
    


    }
    else {
        $linksRE = '/(?:(menu-item.*?))(?:(\shref="))(?<link>[^"]+)/';
        preg_match_all($linksRE,$content,$linksMatches);
        $navLinks = array_unique($linksMatches['link']);

        do {
        $subLinkscontent = curl_fetch_multi_2($navLinks);

        foreach($subLinkscontent as $linkcontent) {
            preg_match_all($mailRE,$linkcontent,$linkMatches);
            
        foreach($linkMatches[0] as &$match1) {
            $match1 = $match1.'|'.$url;
        }

            array_push($results, $linkMatches);
            


        }
    } while (!empty($linkMatches[0]));


        
    }

}


convertToSimpleArray($results);
$ready = array_values(array_unique($resArray));




$output = [];
foreach($ready as $bindedString) {
    $strPartsArr = explode('|',$bindedString);
    $output[$strPartsArr[0]] = $strPartsArr[1];
}


file_put_contents('t.json',json_encode($output, JSON_UNESCAPED_UNICODE));

// echo "<pre>";
// var_dump($output);





?>