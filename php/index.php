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

///////////////////////// DEFINE NECESSARY FUNCTIONS /////////////////////////
$nowtime = time();

$cacheFilename = dirname(__FILE__) . '/packets.txt';
$cacheLock = dirname(__FILE__) . '/~.packets.txt.lock';
$taskFilename = dirname(__FILE__) . '/tasks.txt';
$audienceFilename = dirname(__FILE__) . '/audiences.txt';

$audiences = explode("\n", file_get_contents($audienceFilename));

function quit($code, $text=''){
    global $cacheLock;

    if(file_exists($cacheLock)) unlink($cacheLock);
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

class PACKET{

    public function isPacket($input){
        $input = trim($input);

        if(substr($input, 0, 4) != 'NPBS') return false;

        if(!(
            $this->isVersion( $version=substr($input, 4, 1) ) &&
            $this->isTTL( $ttl=hexdec(substr($input, 5, 2)) ) &&
            $this->isLabel( $label=strtolower(substr($input, 7, 8)) ) &&
            $this->isChecksum(
                $checksum=strtolower(substr($input, 15, 40))
            ) &&
            $this->isData( $data=substr($input, 55) )
        ))
            return false;

        $data = str_replace(
            array('_', '-', '*'), array('+', '/', '='), $data
        );

        return array(
            'base64'=>$data,
            'label'=>$label,
            'checksum'=>$checksum,
            'ttl'=>$ttl,
            'version'=>$version,
        );
    }

    public function createPacket($label, $data, $ttl=255, $version=1){
        if(!$this->isLabel($label)) return false;
        if(!$this->isTTL($ttl)) return false;

        $checksum = sha1($data);
        if(!$this->isData($data)) return false;

        return array(
            'base64'=>base64_encode($data),
            'label'=>$label,
            'checksum'=>$checksum,
            'ttl'=>$ttl,
            'version'=>$version,
        );
    }

    public function stringify($packetArray){
        $ttl = $packetArray['ttl'];
        if($ttl < 16)
            $ttl = '0' . dechex($ttl);
        else
            $ttl = dechex($ttl);

        $label = $packetArray['label'];
        $checksum = $packetArray['checksum'];
        $data = str_replace(
            array('+', '/', '='),
            array('_', '-', '*'),
            $packetArray['base64']
        );

        return 'NPBS1' . strtoupper($ttl . $label . $checksum) . $data;
    }

    private function isVersion($version){
#       print 'Version';
        return 1 == $version;
    }

    private function isTTL($ttl){
#       print 'TTL';
        return ($ttl >= 0 && $ttl <= 255);
    }

    private function isLabel($label){
#       print 'Label';
        return (0 != preg_match('/^[0-9a-z]{8}$/', $label));
    }

    private function isChecksum($checksum){
#       print 'Checksum';
        return (0 != preg_match('/^[0-9a-f]{40}$/', $checksum));
    }

    private function isData($data){
#       print 'Data';
        return (0 != preg_match('/^[0-9a-zA-Z_\-\*]{4,1600}/', $data));
    }

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
if(file_exists($cacheLock)){
    $lockTime = file_get_contents($cacheLock);
    if(
        is_numeric($lockTime) && 
        $lockTime <= $nowtime &&
        $nowtime - $locktime <= LOCK_LIFE
    ){
        for($i=0; $i < LOCK_WAIT; $i++){
            if(!file_exists($cacheLock)) break;
            sleep(1);
        };
        if(file_exists($cacheLock)) quit(503);
    } else {
        unlink($cacheLock);
    };
};

/* Lock up cache*/
file_put_contents($cacheLock, $nowtime);




if(!file_exists($cacheFilename)){
    file_put_contents($cacheFilename, '');
};

$cached = explode("\n", file_get_contents($cacheFilename));

$ary = array();

$cacheNeedRenew = CACHE_RENEW_COUNT;

/*

            Cache line:

        0        1       2
    CHECKSUM    TIME    DATA
*/

foreach($cached as $item){
    $itemParts = explode(' ', trim($item));
    
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
unset($cached);

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
