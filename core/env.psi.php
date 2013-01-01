<?php
//-- PSI_Env
class PSI_Env extends PSI_Core {
    /**
     * @var PSI_Env
     */
    static protected
        $_cookies
    ,   $_session
    ,   $_cache
    ,   $_cloud
        ;
    //-
    protected
        $_type
    ,   $_time = array()
    ,   $_path = array()
    ,   $_cached = array()
    ;
    //--
    public function __construct($type = '_session') {
        $this->_type = $type;
    }
    //-- это срабатывает на деструкторе
    public function __destruct() {
        $this($this);
    }
    //--
    public function __invoke() {
        list($ident, $data) = args(func_get_args(), null, null);
        if ($ident) {
            if ($ident===$this) {
                switch ($this->_type) {
                    case '_cookies':
                        //-- здесь возможно будут какие-то параметры инициации и подключения, сейчас изучим
                        switch (true) {
                            case is_string($data): //-- тут попытка взять значение из источника, и присвоить его, захватив линк
                                if (isset($_COOKIE[$data])) {
                                    return $_COOKIE[$data];
                                } else {
                                    return null;
                                }
                                break;
//                            case is_array($data): //--- тут попытка сохранить? пока не вижу, сейчас буду писать, увижу
//                                break;
                            case is_null($data): //-- это уж точно сохранение всего набора значений :)
                            default:
                                foreach ($this() as $key=>$value) {
                                    setcookie($key, $value['_data'], $value['_time'], $value['_path']);
                                }
                                return $this;
                                break;
                        }
                        break;
                    case '_session':
                        //-- здесь возможно будут какие-то параметры инициации и подключения, сейчас изучим
                        switch (true) {
                            case is_string($data): //-- тут попытка взять значение из источника, и присвоить его, захватив линк
                                if (isset($_SESSION[$data])) {
                                    if (!isset($_SESSION[$data]['_time']) || $_SESSION[$data]['_time']>=now()) {
                                        return $_SESSION[$data]['_data'];
                                    } else {
                                        return null;
                                    }
                                } else {
                                    return null;
                                }
                                break;
//                            case is_array($data): //--- тут попытка сохранить? пока не вижу, сейчас буду писать, увижу
//                                break;
                            case is_null($data): //-- это уж точно сохранение всего набора значений :)
                            default:
                                foreach ($this() as $key=>$value) {
                                    $_SESSION[$key] = $value;
                                }
                                return $this;
                                break;
                        }
                        break;
                    case '_cache':
                    case '_cloud':
                        //-- тут уже будет вызов процессора, либо работа с временными файлами по умолчанию, еще не решил
                        switch (true) {
                            case is_string($data): //-- тут попытка взять значение из источника, и присвоить его, захватив линк
                                //-- и вот тут готовится обработка на изменение на основе полученных данных
                                if ($result = (is_callable(static::$_core->env) ? call_user_func_array(static::$_core->env, array($data, null, $this)) : null)) {
                                    list($value, $time) = array_values($result);
                                    if (strlen($value)) {
                                        //-- если данные получены, то уже фиксируем
                                        $this->_cached[$data] = 1;
                                        //-- и теперь тут проверка на то, чтобы время в текущем значении как-то коррелировало с полученным
                                        if (isset($this->_time[$data])) {
                                            if (now(($this->_type=='_cloud' ? $this->_time[$data] : 'now'))>$time) {
                                                unset($this->_cached[$data]);
                                                return null;
                                            } else {
                                                return $value;
                                            }
                                        } else {
                                            return $value;
                                        }
                                    } else {
                                        return null;
                                    }
                                } else {
                                    return null;
                                }
                                break;
                            case is_null($data): //-- это уж точно сохранение всего набора значений :)
                            default:
                                foreach ($this() as $key=>$value) {
                                    return (empty($this->_cached[$key]) && is_callable(static::$_core->env) ? call_user_func_array(static::$_core->env, array($key, $value, $this)) : null);
                                }
                                break;
                        }
                        break;
                    default:
                        return $this;
                }
                return $this;
            } else {
                $this->{$ident} = $data;
                return $this->{$ident};
            }
        } else {
            $return = array();
            foreach ($this->_quants as $key=>&$value) {
                $return[$key] = array('_data'=>&$value, '_time'=>(isset($this->_time[$key]) ? now($this->_time[$key]) : null), '_path'=>(isset($this->_path[$key]) ? now($this->_path[$key]) : null));
            }
            return $return; //-- вот тут нужно объединить с таймерами
        }
    }
    //--
    static protected function _init(PSI_Env $Env, $ident=null, &$value=null, $datetime=null, $path=null) {
        if (isset($ident)) {
            $Env($ident, array(&$value, $datetime, $path));
        }
        return $Env;
    }
    //--
    static public function cookies($ident=null, &$value=null, $datetime=null, $path=null) {
        if (!isset(static::$_cookies)) {
            static::$_cookies = new self('_cookies');
        }
        return static::_init(static::$_cookies, $ident, $value, $datetime, $path);
    }
    //--
    static public function session($ident=null, &$value=null, $datetime=null, $path=null) {
        if (!isset(static::$_session))  {
            session_start();
            static::$_session = new self('_session');
        }
        return static::_init(static::$_session, $ident, $value, $datetime, $path);
    }
    //--
    static public function cache($ident=null, &$value=null, $datetime=null, $path=null) {
        if (!isset(static::$_cache)) {
            static::$_cache = new self('_cache');
        }
        return static::_init(static::$_cache, $ident, $value, $datetime, $path);
    }
    //--
    static public function cloud($ident=null, &$value=null, $datetime=null, $path=null) {
        if (!isset(static::$_cloud)) {
            static::$_cloud = new self('_cloud');
        }
        return static::_init(static::$_cloud, $ident, $value, $datetime, $path);
    }
    //--
    public function __toString() {
        return $this->_type;
    }
    //--
    public function &__get($name) {
        if (isset($this->_quants[$name])) {
            return $this->_quants[$name];
        } else {
            //-- тут попытка взять ее значение по идентификатору $name и вернуть ее
            $this->_quants[$name] = $this($this, $name);
            //-- вот тут происходит саме важное
            return $this->_quants[$name];
        }
    }
    //--
    public function __set($name, $value) {
        if (is_array($value)) {
            $this->_quants = array_merge($this->_quants, array($name=>&$value[0]));
            if (isset($value[1])) { //-- зададим таймауты
                $this->_time = array_merge($this->_time, array($name=>$value[1]));
            }
            if (isset($value[2])) { //-- зададим локали
                $this->_path = array_merge($this->_path, array($name=>$value[2]));
            }
            return $this->_quants[$name];
        } else {
            return parent::__set($name, $value);
        }
    }
    //--
    public function __call($name, $arguments) {
        if (count($arguments)) {
            if (count($arguments)>1) {
                return call_user_func_array($this, array($name, $arguments));
            } else {
                return call_user_func_array($this, array($name, $arguments[0]));
            }
        } else {
            return
                PSI( $this->_quants[$name] )
                    ->time( isset($this->_time[$name]) ? $this->_time[$name] : false)
                    ->path( isset($this->_path[$name]) ? $this->_path[$name] : false)
                ;
        }
    }
}
/* конфигурация ядра */
return function($processor = null, PSI_Core $Core) {
    $Core->env = $processor;
    return $Core;
}
?>