<?php


class PSI_FS extends PSI_Core {

    static public $config = array('root'=>'/.');

    static protected $_one;
    /**
     * @static
     * @return PSI_FS
     */

    static public function one(){
        if (!isset(static::$_one)) {
            static::$_one = new self();
        }
        return static::$_one;
    }

    static public function dir($path) {
        return self::one()->_dir(static::$_core->fs . $path);
    }

    static public function file($path) {
        return self::one()->_file(static::$_core->fs . $path);
    }

    static public function link($path) {
        return self::one()->_link(static::$_core->fs . $path);
    }

    protected $_resources, $_resource, $_ident, $_pattern, $_objective;

    public function __construct() {
        //-- просто создадим паттерн :) потом удалим его
        $this->_pattern = array(
            'resource'=>null,
            'type'=>null,
            'read'=>false,
            'write'=>false,
            'exist'=>false,
            'path'=>null,
            'chmod'=>null,
        );
        $this->_objective = false;
    }
    /**
     * освободить текущий дескриптор
     * @return PSI_FS
     */
    public function free($ident = null) {
        if (is_null($ident)) $ident = $this->_ident;
        if (isset($this->_resources[$ident])) {
            unset ($this->_resources[$ident]);
            $this->_resource = null;
            $this->_ident = null;
        }
        return $this;
    }
    /**
     * вернуть текущий путь
     * @param string $assign
     * @return string
     */
    public function path() {
        return $this->_resource['path'];
    }
    /**
     * вернуть или сменить/установить режим
     * @param $chmod
     * @return PSI_FS
     */
    public function chmod($chmod = null) {
        if (is_null($chmod)) {
            return $this->_resource['chmod'];
        } else {
            $this->_resource['chmod'] = $chmod;
            if ($this->_resource['exist'] && $this->_resource['write']) {
                chmod($this->_resource['path'], $this->_resource['chmod']);
            };
            return $this;
        }
    }
    /**
     * @param string $path
     * @return string|PSI_FS
     */
    private function _ident($path = NULL) {
        if (is_null($path)) {
            return $this->_ident;
        } else {
            if ($this->_ident != $path) {
                $this->_ident = $path;
                if (!isset($this->_resources[$this->_ident])) {
                    $this->_resources[$this->_ident] = $this->_pattern;
                }
                $this->_resource = &$this->_resources[$this->_ident];
            }
            return $this;
        }
    }
    /**
     * создать оболочку над файлом
     * @param $path
     * @return PSI_FS
     */
    public function _file($path) {
        $this->_ident($path);
        if (!$this->_resource['type']) {
            $this->_resource['type'] = 'file';
            $this->_resource['path'] = $this->_ident;
            switch (true) {
                case file_exists($this->_ident) && !is_dir($this->_ident):
                    $this->_resource['exist'] = true;
                    $this->_resource['read'] = is_readable($this->_ident);
                    $this->_resource['write'] = is_writable($this->_ident);
                    $this->_resource['chmod'] = 0666;
                    break;
                default:
                    $this->_resource['exist'] = false;
                    $this->_resource['read'] = false;
                    $this->_resource['write'] = $this->_writecheck($this->_ident); //-- проверим на возможность записаться в директорию верхнего уровня
                    $this->_resource['chmod'] = 0666;
                    break;
            }
        }
        return $this;
    }
    /**
     * создать оболочку над ссылкой
     * @param $path
     * @return PSI_FS
     */
    public function _link($path) {
        $this->_ident($path);
        if (!$this->_resource['type']) {
            $this->_resource['type'] = 'link';
            $this->_resource['path'] = $this->_ident;
            switch (true) {
                case file_exists($path) && is_link($path):
                    $this->_resource['exist'] = true;
                    $this->_resource['read'] = is_readable(readlink($this->_ident));
                    $this->_resource['write'] = is_writable(readlink($this->_ident));
                    $this->_resource['chmod'] = 0666;
                    break;
                default:
                    $this->_resource['exist'] = false;
                    $this->_resource['read'] = false;
                    $this->_resource['write'] = $this->_writecheck($this->_ident); //-- проверим на возможность записаться в директорию верхнего уровня
                    $this->_resource['chmod'] = 0666;
                    break;
            }
        }
        return $this;
    }
    /**
     * создать оболочку над каталогом
     * @param $path
     * @return PSI_FS
     */
    public function _dir($path) {
        $this->_ident($path);

        if (!$this->_resource['type']) {
            $this->_resource['type'] = 'dir';
            $this->_resource['path'] = $this->_ident;
            switch (true) {
                case file_exists($this->_ident) && is_dir($this->_ident):
                    $this->_resource['exist'] = true;
                    $this->_resource['read'] = is_readable($this->_ident);
                    $this->_resource['write'] = is_writable($this->_ident);
                    $this->_resource['chmod'] = 0777;
                    break;
                default:
                    $this->_resource['exist'] = false;
                    $this->_resource['read'] = false;
                    $this->_resource['write'] = $this->_writecheck($this->_ident); //-- проверим на возможность записаться в директорию верхнего уровня
                    $this->_resource['chmod'] = 0777;
                    break;
            }
        }
        return $this;
    }


    public function state() {
        return $this->_resource;
    }


    private function _writecheck($path) {
        $res = false;
        while ('/' !== ($path=dirname($path)) && !$res) {
            $res = is_writable($path);
        }
        return $res;
    }
    /**
     * превращать результат чтения директории в объекты PSI_FS
     * @param string $do
     * @return bool|PSI_FS
     */
    public function objective ($do = NULL) {
        if  (is_null($do)) {
            return $this->_objective;
        } else {
            $this->_objective = !empty($do);
            return $this;
        }
    }
    /**
     * прочитать содержимое директории или файла
     * @param string $assign
     * @return PSI_FS|string|array
     */
    public function read() {
        $result = array();
        if ($this->_resource['exist'] && $this->_resource['read']) {
            $result = '';
            switch ($this->_resource['type']) {
                case 'file':
                    $result = file_get_contents($this->_resource['path']);
                    break;
                case 'link':
                    $result = file_get_contents($this->_resource['path']);
                    break;
                case 'dir':
                    $dir = opendir ($this->_resource['path']);
                    $result = array();
                    while($file = readdir($dir)) {
                        $_current = $this->_ident;
                        if ($file!='.' && $file!='..') {
                            $_path = $this->_resource['path'] .'/'. $file;
                            $result[$_path] = ($this->_objective) ? ( is_dir($_path) ? $this->_dir($_path) : ( is_link($_path) ? $this->_link($_path) : $this->_file($_path) ) ) : $file;
                        }
                        $this->_ident($_current);
                    }
                    break;
            }
        }
        return $result;
    }


    public function search() {

    }
    /**
     * добавить данные к файлам
     * @param $data
     * @return PSI_FS
     */
    public function append ($data) {
        return $this->_write($data, true);
    }
    /**
     * записать данные в файл
     * @param $data
     * @param bool $append
     * @return PSI_FS
     */
    public function write($data) {
        return $this->_write($data);
    }
    /**
     * записать данные в файл
     * @param $data
     * @param bool $append
     * @return PSI_FS
     */
    private function _write($data, $append=false) {
        if ($this->_resource['write']) {
            $_current = $this->_ident;
            switch ($this->_resource['type']) {
                case 'file':
                    $_file = $this->_resource['path'];
                    if ($this->_resource['exist']) {
                        $f = fopen($_file, $append ? 'a+' : 'w'); fputs($f, $data); fclose($f);
                    } else {
                        $this->_dir(dirname($_file))->write(array(basename($_file)=>$data))->free();
                    }
                    break;
                case 'link':
                    file_put_contents(readlink($this->_resource['path']), $data);
                    break;
                case 'dir':
                    $_dir = $this->_resource['path'];
                    if (!$this->_resource['exist']) { //-- создадим директорию, если ее не было
                        $this->touch();
                    }
                    foreach ($data as $filename=>$content) {
                        $path = $_dir .'/'. $filename;
                        if (!is_array($content)) { //-- если значение - не массив, то считаем, что это файл и записываем его
                            $this->_file($path)->touch()->write($content, true)->free();
                        } else { //-- иначе рекурсивно идем внутрь
                            $this->_dir($path)->write($content);
                        }
                    }
                    break;
            }
            $this->_ident($_current); //-- вернем указатель в исходную позицию
        }
        return $this;
    }
    /**
     * "коснуться" и обновить информацию о файле
     * @return PSI_FS
     */
    public function touch() {
        switch ($this->_resource['type']) {
            case 'file':
                $path = $this->_resource['path'];
                PSI_FS::dir(dirname($path))->touch()->_file($path);
                $this->_resource['exist'] =  touch($this->_resource['path']);
                break;
            case 'dir':
                if (!$this->_resource['exist']) $this->_resource['exist'] = mkdir($this->_resource['path'], $this->_resource['chmod'], true);
                break;
        }

        return $this;
    }
    /**
     * удалить файл или директорию
     * @return PSI_FS
     */
    public function delete() {
        if ($this->_resource['exist'] && $this->_resource['write']) {
            if ($this->_resource['type']=='file' || $this->_resource['type']=='link') {
                unlink($this->_resource['path']);
                $this->free();
            }
            elseif ($this->_resource['type']=='dir') {
                $this->clean(); //-- сначала очистим директорию
                rmdir($this->_resource['path']); //-- и затем ее же и удалим
            }
        }
        return $this;
    }
    /**
     * очистить файл или директорию
     * @return PSI_FS
     */
    public function clean() {
        if ($this->_resource['exist'] && $this->_resource['write']) {
            if ($this->_resource['type']=='file') {
                file_put_contents($this->_resource['path'], '');
            }
            elseif ($this->_resource['type']=='dir') {
                $dir = opendir ($this->_resource['path']); $result = array();
                while($file = readdir($dir)) {
                    $_path = $this->_resource['path'] .'/'. $file;
                    $_current = $this->_ident;
                    if ($file!='.' && $file!='..')  {
                        if (is_dir($_path)) { $this->_dir($_path)->delete(); } //-- рекурсивно удалим внутренний каталог
                        else { unlink($_path); }
                    }
                    $this->_ident($_current);
                }
            }
        }
        return $this;
    }
    /**
     * скопировать
     * @param $dest
     * @param bool $switch
     * @return PSI_FS
     */
    public function copy($dest, $switch = false) {
        if (is_dir($dest) && is_writable($dest)) {
            $destination = $dest .'/'. basename($this->_resource['path']);
            copy ($this->_resource['path'], $destination);
            if ($switch) {  $type = $this->_resource['type']; self::$type($destination); }
        } elseif ($this->_resource['type']=='dir' && is_writable(dirname($dest))) {
            //-- вот здесь по идее нужно рекурсивное копирование, но мы его пока оставим в покое
        } elseif ($this->_resource['type']=='file' && is_writable(dirname($dest))) {
            copy ($this->_resource['path'], $dest);
            if ($switch) {  $type = $this->_resource['type']; self::$type($dest); }
            $this->_resource['path'] = $dest;
        }
        return $this;
    }
    /**
     * переместить файл или директорию
     * @param $dest
     * @return PSI_FS
     */
    public function move($dest) {
        if (is_dir($dest) && is_writable($dest)) {
            $destination = $dest .'/'. basename($this->_resource['path']);
            rename ($this->_resource['path'], $destination);
            $this->_resource['path'] = $destination;
        } elseif ($this->_resource['type']=='file' && is_writable(dirname($dest))) {
            rename ($this->_resource['path'], $dest);
            $this->_resource['path'] = $dest;
        }
        return $this;
    }
    /**
     * переименовать файл или директорию
     * @param $newname
     * @return PSI_FS
     */
    public function rename($newname) {
        if ($this->_resource['exist']) {
            rename ($this->_resource['path'], dirname($this->_resource['path']) . '/' . $newname);
        }
        return $this;
    }

    /**
     * @param $mask
     * @return PSI_FS
     */
    public function upload($mask = NULL, $assign = NULL) {
        $result = array();
        if ($this->_resource['write']) {
            $this->touch();
            switch($this->_resource['type']) {
                case 'file': //-- для файла просто выборка файла по маске
                    if (!is_null($mask) && is_string($mask) && isset($_FILES[$mask]) && !$_FILES[$mask]['error']) {
                        $res = move_uploaded_file($_FILES[$mask]['tmp_name'], $this->_resource['path']);
                        if ($res) $result[] = $this->_resource['path'];
                    } elseif (is_null($mask) && !empty($_FILES) && ($uploadFile = array_shift($_FILES)) && !$uploadFile['error']) {
                        $res = move_uploaded_file($uploadFile['tmp_name'], $this->_resource['path']);
                        if ($res) $result[] = $this->_resource['path'];
                    }
                    break;
                case 'dir':
                    if (!empty($_FILES)) {
                        foreach ($_FILES as $index=>$props) {
                            if (!is_null($mask)) { //-- обработка согласно маске
                                if (is_array($mask) && isset($mask[$index]) && !$props['error']) {
                                    $ext1 = (substr($props['name'], strrpos($props['name'], '.'))); $ext2 = (substr($mask[$index], strrpos($mask[$index], '.')));
                                    $res = move_uploaded_file($props['tmp_name'], ($resultFile = $this->_resource['path'] . '/' . $mask[$index] . ($ext1!=$ext2 ? $ext1 : '')));
                                    if ($res) $result[] = $resultFile;
                                } elseif (isset($_FILES[$mask])) {
                                    $data = $_FILES[$mask];
                                    if (!is_array($data['name'])) {
                                        $res = move_uploaded_file($data['tmp_name'], ($resultFile = $this->_resource['path'] . '/' . $data['name']));
                                        if ($res) $result[] = $resultFile;
                                    } else {
                                        foreach ($data['name'] as $i=>$v) {
//                                        for ($i=0;$i<count($data['name']);$i++) {
                                            if (!$data['error'][$i]) {
                                                $res = move_uploaded_file($data['tmp_name'][$i], ($resultFile = $this->_resource['path'] . '/' . $data['name'][$i]));
                                                if ($res) $result[$i] = $resultFile;
                                            }
                                        }
                                    }
                                }
                            } elseif (is_null($mask)) { //-- загрузка в потоке
                                if (!is_array($props['name'])) {
                                    $res = move_uploaded_file($props['tmp_name'], ($resultFile = $this->_resource['path'] . '/' . $props['name']));
                                    if ($res) $result[] = $resultFile;
                                } else {
                                    for ($i=0;$i<count($props['name']);$i++) {
                                        if (!$props['error'][$i]) {
                                            $res = move_uploaded_file($props['tmp_name'][$i], ($resultFile = $this->_resource['path'] . '/' . $props['name'][$i]));
                                            if ($res) $result[] = $resultFile;
                                        }
                                    }
                                }
                            }
                        }
                    }
                    break;
                default:
                    break;
            }
        }
        return $result;
    }
}
/*

Тут будет копия класса PSI_FS, потому что он получился удачным очень уж :)
Только следует будет обновить формулу upload, чтобы она была действительно удачной.

 */

/* а тут пускалка */
return function($root=null, PSI_Core $Core) {
    $Core->fs = $root;
    return $Core;
}

?>