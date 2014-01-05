<?php
class IO{

    private $filename = false;

    public function __construct($filename){
        $this->filename = $filename;
        if(!file_exists($this->filename))
            file_put_contents($this->filename, '');
    }

    public function lock(){
        $lockname = $this->filename . '.lock';
        $nowtime = time();

        if(file_exists($lockname)){
            $lockTime = file_get_contents($lockname);
            if(
                is_numeric($lockTime) && 
                $lockTime <= $nowtime &&
                $nowtime - $locktime <= LOCK_LIFE
            ){
                for($i=0; $i < LOCK_WAIT; $i++){
                    if(!file_exists($lockname)) break;
                    sleep(1);
                };
                if(file_exists($lockname)) return false;
            } else {
                unlink($lockname);
            };
        };

        /* Lock up*/
        file_put_contents($lockname, $nowtime);
        return true;
    }

    public function unlock(){
        $lockname = $this->filename . '.lock';
        if(!file_exists($lockname)) return;
        unlink($lockname);
    }

    public function lines(){
        $lines = explode("\n", file_get_contents($this->filename));
        $ret = array();
        foreach($lines as $line){
            if('' == trim($line)) continue;
            $ret[] = $line;
        };
        return $ret;
    }

    public function explodedLines(){
        $lines = $this->lines();
        $ret = array();
        foreach($lines as $line){
            $ret[] = explode(' ', $line);
        };
        return $ret;
    }

    public function writeExplodedLines($write){
        $content = array();
        foreach($write as $parts){
            $content[] = implode(' ', $parts);
        };
        $content = implode("\n", $content);
        file_put_contents($this->filename, $content);
    }

    public function appendExploded($parts){
        file_put_contents(
            $this->filename,
            implode(' ', $parts) . "\n",
            LOCK_EX | FILE_APPEND
        );
    }

};
