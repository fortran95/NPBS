<?php
// How long should a packet be stored.
define('PACKET_STORAGE_LIFE', 86400);

// When there are more than so many packets in cache, renew it.
define('CACHE_RENEW_COUNT', 100);

// If there is a write lock, wait up to so many seconds.
define('LOCK_WAIT', 15);


//////////////////////////////////////////////////////////////////////////////
$nowtime = time();
$cacheFilename = dirname(__FILE__) . '/packets.txt';
$cacheLock = dirname(__FILE__) . '/~.packets.txt.lock';

function quit($code){
    header('HTTP/1.0 ' . $code, true, $code);
    unlink($cacheLock);
    exit;
};

if(file_exists($cacheLock)){
    for($i=0; $i < LOCK_WAIT; $i++){
        if(!file_exists($cacheLock)) break;
        sleep(1);
    };
    if(file_exists($cacheLock)) quit(503);
};

file_put_contents($cacheLock, $nowtime);

if(!file_exists($cacheFilename)){
    file_put_contents($cacheFilename, '');
};

$cached = explode('\n', file_get_contents($cacheFilename));

$ary = array();
$cacheNeedRenew = CACHE_RENEW_COUNT;

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

    $ary[$itemParts[0]] = $itemParts[2];
};
unset($cached);

print var_dump($ary);

unlink($cacheLock);
