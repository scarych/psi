<?php

class PSI {
    static protected $_binds = array();
    protected $_quants = array( ), $_psi;

    //-- эта функция будет вызываться в преобразователе
    protected function _psi($source = array()) {
        return is_callable($this->_psi)
            ? call_user_func_array($this->_psi, $source )
            : $this->_psi;
    }

    static public function __callStatic($name, $arguments = array()) {
        if (isset(static::$_binds[$name])) {
            if (is_callable(static::$_binds[$name])) {
                return call_user_func_array(static::$_binds[$name], array_merge($arguments, array(get_called_class())));
            } else {
                return static::$_binds[$name];
            }
        } else {
            static::$_binds[$name] = array_pop($arguments);
            return null;
        }
    }

    public function __construct() {
        $this->_psi = array_pop(func_get_args());
        return $this;
    }

    protected function _quant($name, $value) { //-- квантовое присвоение
        $this->_quants[$name] = $value;
        return $this;
    }

    public function __get($name) {
        //-- что должно происходить, если вызов идет из квантового состояния?
        return
            isset($this->_quants[$name])
                ? $this->_quants[$name]
                : null;
    }

    public function __set($name, $value) {
        //-- что должно происходить, если вызов идет не из квантового состояния?
        $this->_quants[$name] = $value;
    }

    public function __call($function, $arguments = array()) {
        return
            (isset($this->_quants[$function]) //-- если определено значение по этому имени
                ? (is_callable($this->_quants[$function]) //-- и значение можно вызвать
                    ? call_user_func_array($this->_quants[$function], array_merge($arguments, array($this))) //-- то вызовем его и вернем результат
                    : $this->_quants[$function] //-- иначе просто вернем его
                )
                : ($this->_quant($function, array_pop($arguments))) //-- внесем его
            )
            ;
    }

    public function __toString () {
        return (string) $this->_psi();
    }

    public function __invoke() {
        if ($arguments = func_get_args()) {
            return $this->_psi(func_get_args());
        } else {
            return $this->_quants;
        }
    }
}



/*
class PSI {
    static protected $_binds = array();
    protected $_quants = array( ), $_psi;


    //-- выбор среди умолчаний
    protected function _default($default, $source = array()) {
        return ($default === $this->_psi)
            ? //-- равенство вначале потому, что это будет чаще встречаемый случай
            ( is_callable($this->_psi) //-- и пытаемся вызвать текущий $this->_psi
                ? call_user_func_array($this->_psi, array_merge($source, array($this))) //-- вызовем его, прикрепив в конце $this
                : $this->_psi
            )
            :   //-- думаю, что стоит пояснить
            (is_callable($default) //-- если default вызывается
                ? call_user_func_array($default, array_merge($source, array($this))) //-- то вызываем его, подставляя в качестве последнего аргумента $this
                : (is_callable($this->_psi) //-- если не вызывается $default, но вызывается $this->_psi
                    ? call_user_func_array($default, array_merge($source, array($default))) //-- то вызываем его, подставляя на последний аргумент $default
                    : $default //-- или просто вернем то, что в дефолте
                )
            )
            ;
    }

    //-- магический преобразователь :-)
    protected function __magic($psi, $foo) {
        return function () use ($psi, $foo) {
            $arguments = func_get_args();
            return call_user_func_array(($arguments ? $foo : $psi), $arguments);
        };
    }


    //-- эта функция будет вызываться в преобразователе
    protected function _psi($source = null, $default = null) {
        if ($source && $default) { //-- если есть входящие параметры
            //-- вот еще важный момент, у нас в $source
            if (($last = array_pop($source)) && is_callable($last)) { //-- если при иъятии последнего объекта массив обнуляется, то это вызов на присваивание
                if ($source) {
                    return call_user_func_array($last, array_merge($source, array($this)));
                } else {
                    $reflect = new ReflectionFunction($last);
                    if ($args = $reflect->getParameters()) { //-- если функция пришла с аргументами
                        $this->_psi = $this->__magic($this->_psi, $last);
                    } else {
                        $this->_psi = $this->__magic($last, $this->_psi);
                    }
                    return call_user_func_array($last, array($this)); //- и вот тут загвоздка. Что будем возвращать? о_О
                }
            } else { //-- если нет, то расцениваем как простой вызов с аргументами
                return $this->_default($default, array_merge($source, array($last)));
            }
        } else { //-- если входящих параметров нет (вызывается как $this())
            if ($default) { //-- если задано значение по умолчанию, то работаем с ним
                return $this->_default($default, $source);
            } else { //-- иначе пытаемся работать с текущим $this->_psi
                return
                    ($this->_psi
                        ? (is_callable($this->_psi)
                            ? call_user_func_array($this->_psi, array($this))
                            : $this->_psi
                        )
                        : ( get_class($this) === __CLASS__
                            ? $this->_psi
                            : new PSI()
                        )
                    )
                    ;
            }
        }
    }

    static public function __callStatic($name, $arguments = array()) {
        if (isset(static::$_binds[$name])) {
            if (is_callable(static::$_binds[$name])) {
                return call_user_func_array(static::$_binds[$name], array_merge($arguments, array(get_called_class())));
            } else {
                return static::$_binds[$name];
            }
        } else {
            static::$_binds[$name] = array_pop($arguments);
            return null;
        }
    }

    public function __construct() {
        $this->_psi = array_pop(func_get_args());
        return $this;
    }

    protected function _quant($name, $value) { //-- квантовое присвоение
        $this->_quants[$name] = $value;
        return $this;
    }

    public function __get($name) {
        //-- что должно происходить, если вызов идет из квантового состояния?
        return
            isset($this->_quants[$name])
                ? $this->_quants[$name]
                : null;
    }

    public function __set($name, $value) {
        //-- что должно происходить, если вызов идет не из квантового состояния?
        $this->_quants[$name] = $value;
    }

    public function __call($function, $arguments = array()) {
        return
            (isset($this->_quants[$function]) //-- если определено значение по этому имени
                ? (is_callable($this->_quants[$function]) //-- и значение можно вызвать
                    ? call_user_func_array($this->_quants[$function], array_merge($arguments, array($this))) //-- то вызовем его и вернем результат
                    : $this->_quants[$function] //-- иначе просто вернем его
                )
                : ($this->_quant($function, array_pop($arguments))) //-- внесем его
            )
            ;
    }

    public function __toString () {
        return (string) $this->_psi();
    }

    public function __invoke() {
        if ($arguments = func_get_args()) {
            return $this->_psi(func_get_args(), $this->_psi);
        } else {
            return $this->_quants;
        }

    }
}

*/
?>