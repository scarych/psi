<?php
/* тут тоже будет хороший класс PSI_Mail из предыдущей версии. Нечего отказываться от хорошего класса :) */




class PSI_Mail extends PSI_Core {

    static public $config = array('from'=>'PSI MAILER <mail@psifunction.com>', 'groups'=>array(), 'encoding'=>'utf-8');

    private $_params = array(), $_html, $_headers, $_boundary, $_ = "\r\n";

    /**
     * @static
     * @return PSI_Mail
     */
    static public function create() {
        return new self();
    }

    public function __construct() {
        $this->_params = array(
            'to'=>array(),
            'bcc'=>array(),
            'cc'=>array(),
            'from'=>static::$config['from'],
            'subj'=>'',
            'text'=>'',
            'prior'=>1,
            'files'=>array(),
        );
        $this->_headers = array();
        $this->_html = null;
        $this->_boundary = null;
    }

    /**
     * @param $field
     * @param $to
     * @param $replace
     * @return array|PSI_Mail
     */
    private function _addr ($field = null, $to = null, $replace = false) {
        if (is_null($to)) {
            return $this->_params[$field];
        } else {
            $to = (is_array($to) ? $to : array($to));
            if ($replace) {
                $this->_params[$field] = $to;
            } else {
                $this->_params[$field] = array_merge($this->_params[$field], $to);
            }
            return $this;
        }
    }
    /**
     * Кому
     * @param string $to
     * @param bool $replace
     * @return PSI_Mail|string
     */
    public function to($to = null, $replace = false) {
        return $this->_addr('to', (isset(static::$config['groups'][$to]) ? static::$config['groups'][$to] : $to), $replace);
    }
    /**
     * Скрытая копия
     * @param string $to
     * @param bool $replace
     * @return PSI_Mail|string
     */
    public function bcc($to = null, $replace = false) {
        return $this->_addr('bcc', $to, $replace);
    }
    /**
     * Копия
     * @param string $to
     * @param bool $replace
     * @return PSI_Mail|string
     */
    public function cc($to = null, $replace = false) {
        return $this->_addr('cc', $to, $replace);
    }
    /**
     * @param $field
     * @param $text
     * @return PSI_Mail|string
     */
    private function _text($field, $text = null) {
        if (is_null($text)) {
            return $this->_params[$field];
        } else {
            $this->_params[$field] = $text;
            return $this;
        }
    }
    /**
     * От кого
     * @param string $from
     * @return PSI_Mail|string
     */
    public function from($from = null) {
        return $this->_text('from', $from);
    }
    /**
     * Тема письма
     * @param string $from
     * @return PSI_Mail|string
     */
    public function subj($subj = null) {
        return $this->_text('subj', $subj);
    }
    /**
     * Текст
     * @param string $from
     * @return PSI_Mail|string
     */
    public function text($text = null) {
        return $this->_text('text', $text);
    }
    /**
     * Приоритет
     * @param string $from
     * @return PSI_Mail|string
     */
    public function prior($prior = null) {
        return $this->_text('prior', $prior);
    }
    /**
     * приложить файлы
     * @return PSI_Mail
     */
    public function attach($files) {
        if (!is_array($files)) $files = array($files);
        foreach ($files as $k=>$v) {
            switch (true) {
                case (is_numeric($k)): //-- массив вида array('/path/to/file1', '/path/f2.txt', etc..)
                    if (file_exists($v)) $this->_params['file'][] = array('name'=>basename($v), 'path'=>$v);
                    break;
                case (isset($_FILES[$k])): //-- если подача из $_FILES
                    if (!is_array($v['name'])) { //-- если работаем с одиночным файлом
                        if (!$v['error'] && file_exists($v['tmp_name'])) $this->_params['file'][] = array('name'=>$v['name'], 'path'=>$v['tmp_name']);
                    } else {
                        for ($i=0;$i<count($v['name']);$i++) { //-- или перебираем массив
                            if (!$v['error'][$i] && file_exists($v['tmp_name'][$i])) $this->_params['file'][] = array('name'=>$v['name'][$i], 'path'=>$v['tmp_name'][$i]);
                        }
                    }
                    break;
                case (is_string($k)): //-- если подача по принципу array('filename'=>'filepath',)
                default :
                    if (file_exists($v)) {
                        $this->_params['file'][] = array('name'=>$k, 'path'=>$v);
                    }
                    elseif (file_exists($k)) {
                        $this->_params['file'][] = array('name'=>$v, 'path'=>$k);
                    }
                    break;
            }
        }
        return $this;
    }
    /**
     * отправлять в html или нет (null - автовыбор)
     * @param null $do
     * @return PSI_Mail
     */
    public function html($yes = false) {
        $this->_html = $yes;
        return $this;
    }

    protected function _parse($input) {
        //-- следует распарсить строку вида NAME <mail@addr.com> на array(NAME, mail@addr.com);
        preg_match('/.*\<(.*)\>/', $input, $matches);
        return
            (count($matches)
                ? array('from'=>$matches[0], 'mail'=>$matches[1])
                : array('from'=>'', 'mail'=>$input)
            );

    }
    /**
     * сгенерировать заголовки
     * @return array
     */
    private function _headers() {
        $this->_headers[] = "MIME-Version: 1.0";
        $this->_headers[] = "X-Mailer: PSI::mail http://psifunction.com";

        if (!empty($this->_params['bcc'])) {
            $this->_headers[] = "BCC: " . implode (', ' , $this->_params['bcc']);
        }

        if (!empty($this->_params['cc'])) {
            $this->_headers[] = "CC: " . implode (', ' , $this->_params['cc']);
        }

        if (!empty($this->_params['from'])) {
            $this->_params['from'] = (is_array($this->_params['from']) ? $this->_params['from'] : $this->_parse($this->_params['from']));
            $this->_headers[] = "From: "
                . ($from = ($this->_params['from']['from']
                    ? '=?koi8-r?B?' . base64_encode(iconv(static::$config['encoding'], 'koi8-r', $this->_params['from']['from']))
                    : '') . '?='
                    . ($this->_params['from']['mail'] ? ' <' . $this->_params['from']['mail'] . '>' : '')
                );
            ;
            $this->_headers[] = "Reply-To: " . $from;
        }

        if (!empty($this->_params['file'])) {
            $this->_boundary = md5(uniqid(microtime(true)));
            $this->_headers[] = "Content-type:  multipart/mixed; boundary=" . $this->_boundary;
        }

        if ($this->_boundary) { $this->_headers[] = '--' . $this->_boundary; }
        $this->_headers[] = "Content-type:". ($this->_html ? 'text/html' : 'text/plain') ."; charset=". static::$config['encoding'] ;
        $this->_headers[] = "Content-Transfer-Encoding: base64" ;

        return $this->_headers;
    }
    /**
     * отправить почту
     * @return bool
     */

    protected function _compile() {
        $this->_html = (is_null($this->_html)
            ? (strlen(strip_tags($this->_params['text'])) != strlen($this->_params['text']))
            : $this->_html);

        $_ = $this->_;

        $headers = implode($_, $this->_headers());
        $text = '';
        $text .= chunk_split(base64_encode($this->_params['text']));
        if ($this->_boundary && count($this->_params['file'])>0) {
            foreach ($this->_params['file'] as $v) {
                $_type = function_exists('mime_content_type') ? mime_content_type($v['path']) : 'application/x-force-download';
                $text .= '--' . $this->_boundary.$_;
                $text .= "Content-type:".$_type."; name=\"".$v['name']."\"".$_;
                $text .= "Content-length:".filesize($v['path']).$_;
                $text .= "Content-Transfer-Encoding: base64" . $_;
                $text .= "Content-Disposition: attachment; filename=\"".$v['name']."\"" . $_;
                $text .= $_;
                $text .= chunk_split(base64_encode(file_get_contents($v['path'])));
                $text .= $_;
                $text .= $_;
            }
            $text .= '--' . $this->_boundary . '--';
        }

        $this->_params['subj'] = '=?' . static::$config['encoding'] .'?B?' . base64_encode($this->_params['subj']) . '?=';
        return array(implode(', ', $this->_params['to']), $this->_params['subj'], $text, $headers);
    }

    public function send () {
        return call_user_func_array('mail', $this->_compile()) ;
    }

    //-- toString добавлен специально, чтобы можно было сохранять отправляемое письмо в виде строки того или иного вида
    public function __toString() {
        return serialize($this->_compile());
    }


    public function queue ($queue = _NULL_) {

    }

}

return function () {
    list($Core,  $groups, $from, $encoding) =  tail(func_get_args(), null, null, null, null);
    if ($from) PSI_Mail::$config['from'] = $from;
    if ($groups) PSI_Mail::$config['groups'] = $groups;
    if ($encoding) PSI_Mail::$config['encoding'] = $encoding;
    return $Core;
}
    ;

?>