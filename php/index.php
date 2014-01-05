<?php
// How long should a packet be stored.
define('PACKET_STORAGE_LIFE', 864000);

// When there are more than so many packets in cache, renew it.
define('CACHE_RENEW_COUNT', 20);

// If there is a write lock, wait up to so many seconds.
define('LOCK_WAIT', 15);

// Lock effective time
define('LOCK_LIFE', 20);

// Max packets per request
define('REQUEST_PACKETS', 10);

// How much contribute we want, from each browse.
define('CONTRIBUTE', 2);

// CAPTCHA
define('CAPTCHA', false);



///////////////////////// DEFINE NECESSARY FUNCTIONS /////////////////////////
require(dirname(__FILE__) . '/html.php');
require(dirname(__FILE__) . '/io.php');
require(dirname(__FILE__) . '/packet.php');
require(dirname(__FILE__) . '/securimage/securimage.php');

$nowtime = time();

$cacheFile = new IO(dirname(__FILE__) . '/save/packets.txt');
$taskFile = new IO(dirname(__FILE__) . '/save/tasks.txt');
$audienceFile = new IO(dirname(__FILE__) . '/save/audiences.txt');

$html = new HTML();

function quit($error=false){
    global $cacheFile, $taskFile, $audienceFile, $html;

    $cacheFile->unlock();
    $taskFile->unlock();
    $audienceFile->unlock();

    if($error)
        $html->error($error);
    else
        $html->ok();
    exit;
};

function forward($url){
    return file_get_contents($url);
    /*
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $ch);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);

    $ret = curl_exec($ch);
    print htmlspecialchars($ret);
    curl_close($ch);

    return $ret;
    */
};

$classPacket = new PACKET();



///////////////////////////// READ IN PACKETS ////////////////////////////////

/* 
    Get if there is a packet, good formated. Only one packet will be proceeded. 
*/
$packets = array();
foreach($_GET as $key=>$value){
    $packet = $classPacket->isPacket($value);
    if($packet !== false) $packets[] = $packet;
};
if(isset($_POST['do'])){
    $html->setAkashicData($_POST['label'], $_POST['data']);

    if(CAPTCHA){
        $captcha = new Securimage();
        if(false == $captcha->check($_POST['code'])){
            $html->setCaptchaError();
            quit();
        };
    };

    $packet = $classPacket->createPacket($_POST['label'], $_POST['data']);

    if($packet !== false){
        $html->setCreatedPacket();
        $packets[] = $packet;
    } else {
        $html->setPacketCreationError();
        quit();
    };
};


if($packets){

    //////////////////////// CACHE FILE OPEN AND READ ////////////////////

    /* Politely wait for a lock, if it is valid. Otherwise remove it. */
    if(!$cacheFile->lock())
        quit(503);


    $ary = array();
    $cached = $cacheFile->explodedLines();

    $cacheNeedRenew = CACHE_RENEW_COUNT;
    /*
                Cache line:
            0        1       2
        CHECKSUM    TIME    DATA
    */
    foreach($cached as $itemParts){
        if(count($itemParts) < 3) continue;
        if(strlen($itemParts[0]) != 40) continue;
        if(!is_numeric($itemParts[1])) continue;
        if(
            $itemParts[1] > $nowtime ||
            $nowtime - $itemParts[1] > PACKET_STORAGE_LIFE
        ){
            $cacheNeedRenew -= 1;
            continue;
        };

        $ary[$itemParts[0]] = array(
            'time'=>$itemParts[1],
            'data'=>$itemParts[2],
        );
    };

    if($cacheNeedRenew <= 0){
        $content = array();
        foreach($ary as $key=>$value)
            $content[] = array($key, $value['time'], $value['data']);
        $cacheFile->writeExplodedLines($content);
    };

    $cacheFile->unlock();

    ///////////////// SAVE PACKETS AND GENERATE NETWORK TASKS ////////////

    $acceptedPackets = array();

    # filter out packets existed or have bad checksums.
    foreach($packets as $packet){
        if(array_key_exists($packet['checksum'], $ary)) continue;

        try{
            if($packet['checksum'] != sha1(base64_decode($packet['base64'])))
                continue;
        } catch(Exception $e){
            continue;
        };

        if(count($acceptedPackets) < REQUEST_PACKETS)
            $acceptedPackets[$packet['checksum']] = $packet;
    };

    # get tasks and append to cache file
    $audiences = $audienceFile->lines();
    foreach($acceptedPackets as $checksum=>$packet){
        $cacheFile->appendExploded(array(
            $packet['checksum'],
            $nowtime,
            $classPacket->stringify($packet),
        ));

        if(0 == $packet['ttl']) continue;

        $packet['ttl'] -= 1;
        $packetStr = $classPacket->stringify($packet);

        foreach($audiences as $audience){
            $taskURL = $audience . $packetStr;
            $taskID = md5($taskURL);

            $taskFile->appendExploded(array(
                '+',
                $taskID,
                $taskURL,
            ));
        };
    };

    //////////////////////////////// DONE ////////////////////////////////

};



///////////////////// READ TASK FILE AND DO A FEW TASKS //////////////////////
$tasks = array();
$cacheNeedRenew = CACHE_RENEW_COUNT;

# read in all tasks
$taskFile->lock();

foreach($taskFile->explodedLines() as $parts){
    if(count($parts) < 2) continue;

    if($parts[0] == '+') $tasks[$parts[1]] = $parts[2];
    if($parts[0] == '-'){
        $tasks[$parts[1]] = false;
        $cacheNeedRenew -= 1;
    };
};
$tasks = array_filter($tasks);
if($cacheNeedRenew <= 0){
    $content = array();
    foreach($tasks as $key=>$value)
        $content[] = array('+', $key, $value);
    $taskFile->writeExplodedLines($content);
};

$taskFile->unlock();

# pick up some task
$selected = array();
foreach($tasks as $key=>$value){
    if(count($selected) > CONTRIBUTE) break;
    $selected[$key] = $value;
};


# perform task
foreach($selected as $key=>$url){
    forward($url);
    print '<a href="' . $url . '" target="_blank">[CLICK HERE TO MANUALLY FORWARD]</a><br />';
    $taskFile->appendExploded(array('-', $key));
};


quit();
