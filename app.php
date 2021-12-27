<?php
set_time_limit(6000);
// function findmail($n) {
//     $ch = curl_init($n);
//     $dir = dirname(__FILE__);
// $config['cookie_file'] = $dir . '/cookies/' . md5($_SERVER['REMOTE_ADDR']) . '.txt';
//     curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//     curl_setopt($ch, CURLOPT_HEADER, true);
//     curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
//     curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
//     curl_setopt($ch, CURLOPT_SSL_VERIFYSTATUS, false);
//     curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
//     curl_setopt($ch, CURLOPT_TIMEOUT, 4);
//     curl_setopt($ch, CURLOPT_USERAGENT, 'Safari: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0 Safari/605.1.15');
//     curl_setopt($ch, CURLOPT_REFERER, 'https://www.google.com/');
//     curl_setopt($ch, CURLOPT_COOKIEFILE, $config['cookie_file']);
//     curl_setopt($ch, CURLOPT_COOKIEJAR, $config['cookie_file']);
//     $urlcontent = curl_exec($ch);
//     curl_close($ch);







//     $mailRE = '/\b\w+@\w+\.[^(jpg)(png)(webp)(jpeg)(gif)(avif)(svg)]\w{2,8}/';
//     $result = preg_match_all($mailRE,$urlcontent,$mailMatches);
//     $matches = array_unique($mailMatches[0]);
//     return ($matches);
// }
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
$arrayRE = '|\s|';
$urlArray = preg_split($arrayRE,$raw);
$urls = array_filter($urlArray);

$dir = dirname(__FILE__);
$config['cookie_file'] = $dir . '/cookies/' . md5($_SERVER['REMOTE_ADDR']) . '.txt';


function curl_fetch_multi_2(array $urls_unique, int $max_connections = 30, array $additional_curlopts = null)
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
            // if ($info['result'] !== CURLM_OK) {
            //     $errinfo = [
            //         "effective_url" => curl_getinfo($info['handle'], CURLINFO_EFFECTIVE_URL),
            //         "curl_errno" => curl_errno($info['handle']),
            //         "curl_error" => curl_error($info['handle']),
            //         "curl_multi_errno" => curl_multi_errno($mh),
            //         "curl_multi_strerror" => curl_multi_strerror(curl_multi_errno($mh))
            //     ];
            //     $errstr = "curl_multi worker error: " . str_replace([
            //         "\r",
            //         "\n"
            //     ], "", var_export($errinfo, true));
            //     throw new \RuntimeException($errstr);
            // }

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



// $additional_curlopts = array(

// "CURLOPT_HEADER" => 1,
// "CURLOPT_FOLLOWLOCATION" => 1,
// "CURLOPT_RETURNTRANSFER" => 1,
// "CURLOPT_ENCODING" => 'gzip,deflate',
// "CURLOPT_SSL_VERIFYPEER" => 0,
// "CURLOPT_SSL_VERIFYSTATUS" => 0,
// "CURLOPT_SSL_VERIFYHOST" => 0,
// "CURLOPT_TIMEOUT" => 4,
// "CURLOPT_USERAGENT" => 'Safari: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0 Safari/605.1.15',
// "CURLOPT_REFERER" => 'https://www.google.com/',
// "CURLOPT_COOKIEFILE" => $config['cookie_file'],
// "CURLOPT_COOKIEJAR" => $config['cookie_file'],

// );


$urlsContent = curl_fetch_multi_2($urls);

$results = [];
foreach($urlsContent as $content) {


    $mailRE = '/\b\w+@\w+\.[^(jpg)(png)(webp)(jpeg)(gif)(avif)(svg)]\w{2,8}/';
    preg_match_all($mailRE,$content,$mailMatches);

    if (!empty($mailMatches[0])) {

        $matches = array_unique($mailMatches[0]);
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
            array_push($results, $linkMatches);
        }
    } while (!empty($linkMatches[0]));


        
    }


    
}



convertToSimpleArray($results);

echo "<pre>";
print_r(array_unique($resArray));



// $mails = array_map('findmail',$urlArray);


// С ЮТУБА____________________________________

// $multi = curl_multi_init();
// $handles = [];


// foreach ($urls as $url) {

//     $ch = curl_init( $url );

//     $dir = dirname(__FILE__);
// $config['cookie_file'] = $dir . '/cookies/' . md5($_SERVER['REMOTE_ADDR']) . '.txt';
//     curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//     curl_setopt($ch, CURLOPT_HEADER, true);
//     curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
//     curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');
//     curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
//     curl_setopt($ch, CURLOPT_SSL_VERIFYSTATUS, false);
//     curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
//     curl_setopt($ch, CURLOPT_TIMEOUT, 4);
//     curl_setopt($ch, CURLOPT_USERAGENT, 'Safari: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0 Safari/605.1.15');
//     curl_setopt($ch, CURLOPT_REFERER, 'https://www.google.com/');
//     curl_setopt($ch, CURLOPT_COOKIEFILE, $config['cookie_file']);
//     curl_setopt($ch, CURLOPT_COOKIEJAR, $config['cookie_file']);

//     curl_multi_add_handle( $multi, $ch );
//     $handles [ $url ] = $ch;
// }
 
// echo "<pre>";
// var_dump($handles);



// do {

// $mrc = curl_multi_exec( $multi, $active );
// $info = curl_multi_info_read($multi);

// } while ( $mrc == CURLM_CALL_MULTI_PERFORM );

// while ( $active && $mrc = CURLM_OK ) {

//     if ( curl_multi_select( $multi ) == -1 ) {

// usleep( 100 );

//     }

//     do {

//         $mrc = curl_multi_exec( $multi, $active );

//     } while ( $mrc = CURLM_CALL_MULTI_PERFORM );

// }

// foreach ( $handles as $channel ) {

// $html = curl_multi_getcontent( $channel );

// var_dump ( $html );



// }


// CURLOPTS FOR ARRAY____________________________


// CURLOPT_HEADER => 1,
// CURLOPT_FOLLOWLOCATION => 1,
// CURLOPT_RETURNTRANSFER => 1,
// CURLOPT_ENCODING => 'gzip,deflate',
// CURLOPT_SSL_VERIFYPEER => 0,
// CURLOPT_SSL_VERIFYSTATUS => 0,
// CURLOPT_SSL_VERIFYHOST => 0,
// CURLOPT_TIMEOUT => 4,
// CURLOPT_USERAGENT => 'Safari: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0 Safari/605.1.15',
// CURLOPT_REFERER => 'https://www.google.com/',
// CURLOPT_COOKIEFILE => $config['cookie_file'],
// CURLOPT_COOKIEJAR => $config['cookie_file'],


// ____________________________________________________











// $resArray = []; 
// convertToSimpleArray($mails);
// echo "<pre>";
// print_r($urls);
// print_r($results);












// ini_set('memory_limit', '100000M');
// function findmail($n) {
//     $urlcontent = file_get_contents($n); 
//     $mailRE = '/\b\w+@\w+\.[^(jpg)(png)(webp)(jpeg)(gif)(avif)]\w{2,8}/';
//     $result = preg_match_all($mailRE,$urlcontent,$mailMatches );
//     $matches = array_unique($mailMatches[0]);
//     return $matches;
// }
// function convertToSimpleArray($array){
//     global $resArray; 
//     if(is_array($array)){
//         foreach($array as $below){
//             $res = convertToSimpleArray($below); 

//         }
//     }else{
//         $resArray[] = $array; 
//     }
//     return $resArray; 
// }

// if (trim($_POST['one']) !='') {
//     $formdata = preg_replace('/\s/', '%20', (trim($_POST['one'])));
//     $searchURL = 'https://www.googleapis.com/customsearch/v1?key=AIzaSyARRcNevgwiQcB0cFEAdrIzcyQF2Y2mkIA&cx=acfd41e8274e5b238&q='.$formdata;
//     $urlcontent = file_get_contents($searchURL);    
//     $linksRE = '/(?<=("link": "))[^"]+/';
//     $links = preg_match_all($linksRE,$urlcontent,$linkMatches);
//     $links = ($linkMatches[0]);
//     $mails = array_map('findmail',$links);
//     $mails = array_filter($mails);
//     $resArray = []; 
//     convertToSimpleArray($mails);
//     echo "<pre>";
//     var_dump($resArray);
// }
// else {
//     echo "Введите валидный URL";
// }

?>