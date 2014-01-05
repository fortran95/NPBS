<?php
class HTML{

    private $captchaError = false,
            $packetCreationError = false,
            $createdPacket = false;

    private $wroteData = '', $wroteLabel = '';

    public function error($code){
        header('HTTP/1.0 ' . $code, true, $code);
    }

    public function ok(){
        echo $this->page();
    }

    public function setCaptchaError(){
        $this->captchaError = true;
    }

    public function setPacketCreationError(){
        $this->packetCreationError = true;
    }

    public function setCreatedPacket(){
        $this->createdPacket = true;
    }

    public function setAkashicData($label, $data){
        $this->wroteData = $data;
        $this->wroteLabel = $label;
    }

    private function writeError($e){
        return '<font color="#FF0000">' . $e . '</font><br />';
    }

    private function writeOK($e){
        return '<font color="#00AA00">' . $e . '</font><br />';
    }

    private function page(){
        $content = '<html><head>'
            . '<meta http-equiv="Content-Type" content="application/xhtml+xml; charset=utf-8"/>'
            . '<title>Send a NPBS Packet!</title>'
            . '<style>'
            . 'form input[type="text"]{width: 100%}'
            . 'form button{width: 100%}'
            . '</style>'
            . '</head><body><center>'
        ;

        if($this->captchaError)
            $content .= $this->writeError('验证码错误，请重新输入。');

        if($this->packetCreationError)
            $content .= $this->writeError('创建包失败。');

        if($this->createdPacket)
            $content .= $this->writeOK('包已经创建。');

        $content .= '<form method="POST" action=".">'
            . '<input name="do" value="1" type="hidden" />'
            . '<table cellspacing="5" cellpadding="5" bgcolor="#EEEEEE">'
            . '<tr><td>输入8位标签（字母、数字组合）: </td>'
            . '<td><input name="label" size="10" maxlength="8" type="text" value="' . $this->wroteLabel . '"/></td></tr>'
            . '<tr><td colspan="2"><textarea name="data" cols="40" rows="5">'
                . htmlspecialchars($this->wroteData)
            . '</textarea></td></tr>'
            . '<tr><td>输入验证码：</td><td><input name="code" size="10" type="text" /></td></tr>'
            . '<tr><td colspan="2" bgcolor="#CCCCCC"><img src="captcha.php?_' . uniqid() . '"></td></tr>'
            . '<tr><td>发送：</td><td><button type="submit">Send</button></td></tr>'
            . '</table>'
            . '</form>'
        ;

        $content .= '</center></body></html>';
        return $content;
    }

};

