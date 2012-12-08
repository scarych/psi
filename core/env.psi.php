<?php

class PSI_Env extends PSI_Core {

    /*
    Особенностью этого класса будет то, что
    1. К его перегруженным методам можно будет цеплять значения, реверсируя их по ссылке.

    То есть, как это должно будет выглядеть?

    PSI_Env::cookies()->date(_Root::one()->ololo, );
    */


    /*
    Как будет работать тут?
    Будет 3 синглтона (может быть 2, и от кеша придется отказаться)



    Данные функции могут вызываться со следующими наборами команд

    PSI_Env::cookies($Object, $params) //- в этом случае происходит (что?), и возвращается (что?!)

    PSI_Env::cookies()-> //-- просто создается и возвращается синглтон

    PSI_Env::cookies()->value //-- пытается вернуть из соответсвующего окружения

     */

    static public
        $processor;

    /**
     * @var PSI_Env
     */
    static protected
            $_cookies
        ,   $_session
        ,   $_cache
        ;

    protected
        $_type
    ,   $_time = array()
    ;

    //-- сохраненные фигульки будут храниться в квантах, и этим все сказано :)

    public function __construct($type = '_session') {
        $this->_type = $type;
    }

    //-- это срабатывает на деструкторе
    public function __destruct() {
        $this($this);



    }

    public function __invoke() {
        list($ident, $value, $locale) = args(func_get_args(), null, null, null);
        if ($ident) {
            if ($ident===$this) {
                $options = &$value;
                switch ($this->_type) {
                    case '_cookies':
                        //-- здесь возможно будут какие-то параметры инициации и подключения, сейчас изучим
                        switch (true) {
                            case is_string($options): //-- тут попытка взять значение из источника, и присвоить его, захватив линк
                                if (isset($_COOKIE[$options])) {
                                    return $_COOKIE[$options];
                                } else {
                                    return null;
                                }
                                break;
                            case is_array($options): //--- тут попытка сохранить? пока не вижу, сейчас буду писать, увижу
                                break;
                            case is_null($options): //-- это уж точно сохранение всего набора значений :)
                            default:
                                foreach ($this() as $key=>$value) {
                                    setcookie($key, $value['_data'], $value['_time'], $locale);
                                }
                                break;
                        }
                        break;
                    case '_session':
                        //-- здесь возможно будут какие-то параметры инициации и подключения, сейчас изучим
                        switch (true) {
                            case is_string($options): //-- тут попытка взять значение из источника, и присвоить его, захватив линк
                                if (isset($_SESSION[$options])) {
                                    if (!isset($_SESSION[$options]['_time']) || $_SESSION[$options]['_time']>=now()) {
                                        return $_SESSION[$options]['_data'];
                                    } else {
                                        return null;
                                    }
                                }
                                break;
                            case is_array($options): //--- тут попытка сохранить? пока не вижу, сейчас буду писать, увижу
                                break;
                            case is_null($options): //-- это уж точно сохранение всего набора значений :)
                            default:
                                foreach ($this() as $key=>$value) {
                                    $_SESSION[$key] = $value;
                                }
                                break;
                        }
                        break;

                        break;
                    case '_cache':
                        pr ('CAAAAAAAACHE SET!');
                        //-- тут уже будет вызов процессора, либо работа с временными файлами по умолчанию, еще не решил

                        break;
                }
            } else {
                $this->{$ident} = $value;
                return $this->{$ident};
            }
            ;
        } else {
            $return = array();
            foreach ($this->_quants as $key=>&$value) {
                $return[$key] = array('_data'=>&$value, '_time'=>(isset($this->_time[$key]) ? now($this->_time[$key]) : null));
            }
            return $return; //-- вот тут нужно объединить с таймерами
        }
    }



    //-- вполне очевидные решения, возможно стоит их перенести на конфиг, пока так


    static protected function _init(PSI_Env $Env, $ident=null, &$value=null, $datetime=null) {
        if (isset($ident)) {
            $Env($ident, array(&$value, $datetime));
        }
        return $Env;
    }

    static public function cookies($ident=null, &$value=null, $datetime=null) {
        if (!isset(static::$_cookies)) {
            static::$_cookies = new self('_cookies');
        }
        return static::_init(static::$_cookies, $ident, $value, $datetime);
    }

    static public function session($ident=null, &$value=null, $datetime=null) {
        if (!isset(static::$_session))  {
            session_start();
            static::$_session = new self('_session');
        }
        return static::_init(static::$_session, $ident, $value, $datetime);
    }

    static public function cache($ident=null, &$value=null, $datetime=null) {
        if (!isset(static::$_cache)) {
            static::$_cache = new self('_cache');
        }
        return static::_init(static::$_session, $ident, $value, $datetime);
    }


    public function __toString() {
        return $this->_type;
    }



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

    public function __set($name, $value) {
        if (is_array($value)) {
            $this->_quants = array_merge($this->_quants, array($name=>&$value[0]));
            if (isset($value[1])) { //-- зададим таймауты
                $this->_time = array_merge($this->_time, array($name=>$value[1]));
            }
        } else {
            parent::__set($name, $value);
        }
    }

    /*
    квант для текущей фигулины представляет собой массив, в первом элементе которого хранится то, что присваивается
    во-втором хранится то, стыкуется
    */

    public function __call($name, $arguments) {

        /*
         __call вызывается с двумя возможными вариантами, одним из которых оказывается присваиваемое значение, вторым - набор параметров

        то есть
        PSI_Env::session()->some_ident($Psi->value)->some_ololo($Psi->value2)
        либо же
        PSI_Env::cookies()->some_var($Psi->value)->some_var2($Psi->value2, '+2 hours');

        //-- вторично стоит вопрос об использовании ссылок, но это мы сейчас разберемся

        //-- что делает повторный вызов? сейчас разберемся, сначала сделаем первый

         */
        //pr ($arguments);
//        pr (debug_backtrace()) ;


        return call_user_func_array($this, array($name, $arguments[0]))//->{$name}
        ;



        //dpr ($args);
        //$value = &$args[0];
        //$value = 12;
        //pr ($trace);
        //$value = &$args[1];


        //return parent::__call($name, $arguments);
    }




    /*
    Я только что придумал, как будет выглядеть кеширование

    //-- если ничего под условие не попадает, значит выполняем ее и возвращаем чистый результат
    //-- если попадает, значит берем старое значение
    //-- старым значением будет являться строковое выражение внутреннего параметра


    return PSI_Env::cache($options, PSI::www(function(PSI $Psi) {})->data()->other());
    */
}


/* а тут пускалка */
return function($processor = null, PSI_Core $Core) {
    if (!is_null($processor)) {
        PSI_Env::$processor = $processor;
    }
    return $Core;
}

?>