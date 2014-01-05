<?php
class HTML{

    private $captchaError = false,
            $createdPacket = false;

    public function error($code){
        header('HTTP/1.0 ' . $code, true, $code);
    }

    public function ok(){
        echo $this->page();
    }

    public function setCaptchaError(){
        $this->captchaError = true;
    }

    public function setCreatedPacket(){
        $this->createdPacket = true;
    }

    private function page(){
        $content = '<html><head>'
            . '<meta http-equiv="Content-Type" content="application/xhtml+xml; charset=utf-8"/>'
            . '<title>Send a NPBS Packet!</title>'
            . '<style>'
            . 'form input[type="text"]{width: 100%}'
            . 'form button{width: 100%}'
            . '</style>'
            . '</head><body>'
        ;

        if($this->captchaError)
            $content .= '
                <font color="#FF0000">验证码错误，请重新输入。</font><br />
            ';

        $content .= '<form method="POST" action=".">'
            . '<input name="do" value="1" type="hidden" />'
            . '<table cellspacing="5" cellpadding="5" bgcolor="#EEEEEE">'
            . '<tr><td>输入8位标签（字母、数字组合）: </td><td><input name="label" size="10" maxlength="8" type="text"/></td></tr>'
            . '<tr><td colspan="2"><textarea name="data" cols="40" rows="5"></textarea></td></tr>'
            . '<tr><td>输入验证码：</td><td><input name="code" size="10" type="text" /></td></tr>'
            . '<tr><td colspan="2" bgcolor="#CCCCCC"><img src="captcha.php?_' . uniqid() . '"></td></tr>'
            . '<tr><td>发送：</td><td><button type="submit">Send</button></td></tr>'
            . '</table>'
            . '</form>'
        ;

        $content .= '</body></html>';
        return $content;
    }

};

