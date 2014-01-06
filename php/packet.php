<?php
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

        $data = base64_encode($data);
        $dataM = str_replace(
            array('+', '/', '='),
            array('_', '-', '*'),
            $data
        );

        if(!$this->isData($dataM)) return false;

        return array(
            'base64'=>$data,
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
        return (0 != preg_match('/^[0-9a-zA-Z]{8}$/', $label));
    }

    private function isChecksum($checksum){
#       print 'Checksum';
        return (0 != preg_match('/^[0-9a-fA-F]{40}$/', $checksum));
    }

    private function isData($data){
#       print 'Data';
        return (0 != preg_match('/^[0-9a-zA-Z_\-\*]{4,1600}/', $data));
    }

};
