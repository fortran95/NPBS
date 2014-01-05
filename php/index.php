<?php
// How long should a packet be stored.
define('PACKET_STORAGE_LIFE', 86400);

// When there are more than so many packets in cache, renew it.
define('CACHE_RENEW_COUNT', 100);

// If there is a write lock, wait up to so many seconds.
define('LOCK_WAIT', 15);

// Lock effective time
define('LOCK_LIFE', 20);

// Max packets per request
define('REQUEST_PACKETS', 10);

// How much contribute we want, from each browse.
define('CONTRIBUTE', 1);



///////////////////////// DEFINE NECESSARY FUNCTIONS /////////////////////////
require(dirname(__FILE__) . '/io.php');
require(dirname(__FILE__) . '/packet.php');

$nowtime = time();

$cacheFile = new IO(dirname(__FILE__) . '/packets.txt');
$taskFile = new IO(dirname(__FILE__) . '/tasks.txt');
$audienceFile = new IO(dirname(__FILE__) . '/audiences.txt');

$audiences = $audienceFile->lines();

function quit($code, $text=''){
    global $cacheFile;

    $cacheFile->unlock();

//    header('HTTP/1.0 ' . $code, true, $code);
    die('
<html>
    <head>
        <title>Send a NPBS Packet!</title>
    </head>
    <body>
        ' . $text . '
        <form method="POST" action="/">
            <input name="do" value="1" type="hidden" />
            Label: <input name="label" size="10" maxlength="8" type="text"/>
            <br />
            <textarea name="data"></textarea><br />
            <button type="submit">Send</button>
        </form>
    </body>
</html>');
    exit;
};

function forward($url){
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $ch);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);

    curl_exec($ch);
    curl_close($ch);
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
    print 'Create one packet.';
    $packet = $classPacket->createPacket(
        $_POST['label'],
        $_POST['data']
    );
    if($packet !== false)
        $packets[] = $packet;
    else
        quit(400);
};


/* break up if $packets is null */
if(!$packets){
    quit(200);
};




//////////////////////// CACHE FILE OPEN AND READ ////////////////////////////

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
    foreach($ary as $key=>$value){
        $content[] = "$key {$value['time']} {$value['data']}";
    };
    $content = implode("\n", $content);
    file_put_contents($cacheFilename, $content);
};

unlink($cacheLock);




///////////////// SAVE PACKETS AND GENERATE NETWORK TASKS ////////////////////

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
foreach($acceptedPackets as $checksum=>$packet){
    file_put_contents(
        $cacheFilename, 
        implode(' ', array(
            $packet['checksum'],
            $nowtime,
            $classPacket->stringify($packet),
        )) . "\n",
        FILE_APPEND | LOCK_EX
    );

    if(0 == $packet['ttl']) continue;

    $packet['ttl'] -= 1;
    $packetStr = $classPacket->stringify($packet);

    foreach($audiences as $audience){
        if(!$audience = trim($audience)) continue;

        $taskURL = $audience . $packetStr;
        $taskID = md5($taskURL);

        file_put_contents(
            $taskFilename,
            implode(' ', array(
                '+',
                $taskID,
                $taskURL,
            )) . "\n",
            FILE_APPEND | LOCK_EX
        );
    };
};



///////////////////// READ TASK FILE AND DO A FEW TASKS //////////////////////



quit(200, var_dump($packets));
