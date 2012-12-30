<?php
//-- проверка на Замыкание
function is_closure($object) {
    return (is_object($object) && get_class($object)=='Closure');
}
//-- PSI
class PSI {
    //-- Связи
    static protected $_binds = array();
    //-- создание Связей
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
    //-- Эго, Дуал, Кванты
    protected $_ego = null, $_dual = null, $_quants = array();
    //-- создание Эго
    public function __construct() {
        $this->_ego = array_pop(func_get_args());
        return $this;
    }
    //-- вызов Эго строкой
    public function __toString () {
        return (string) $this->_ego();
    }
    //-- вызов Эго/Дуала функцией или создание Дуала
    public function __invoke() {
        return
            ( ($arguments = func_get_args())
                ? ($this->_ego($arguments))
                : ($this->_dual ? call_user_func_array($this->_dual, array($this)) : $this->_quants)
            )
            ;
    }
    //-- вызов Эго
    protected function _ego($source = array()) {
        if (count($source)==1 && !$this->_dual && is_closure($dual = $source[0])) {
            $this->_dual = $dual;
            return $this;
        }  else {
            //debug($source, array_merge($source, array($this)));
            return is_callable($this->_ego)
                ? call_user_func_array($this->_ego, array_merge($source, array($this)))
                : $this->_ego;
        }
    }
    //-- вызов/создание Кванта функцией
    public function __call($function, $arguments = array()) {
        return
            (isset($this->_quants[$function])
                ? (is_callable($this->_quants[$function])
                    ? call_user_func_array($this->_quants[$function], array_merge($arguments, array($this)))
                    : $this->_quants[$function]
                )
                : ($this->_quant($function, array_pop($arguments)))
            )
            ;
    }
    //-- создание Кванта присвоением
    public function __set($name, $value) {
        $this->_quants[$name] = $value;
    }
    //-- возврат Кванта
    public function &__get($name) {
        if (!isset($this->_quants[$name])) { $this->_quants[$name] = null; }
        return $this->_quants[$name];
    }
    //-- установка Кванта
    protected function _quant($name, $value) {
        $this->_quants[$name] = $value;
        return $this;
    }
}
//-- Магия :)
function PSI($argument = null) { return new PSI($argument); }
function this ($Object) { return $Object; }
function ego  ($Object) { return $Object($Object); }
function dual ($Object) { return $Object(); }
?>