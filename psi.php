<?php

function PSI($argument = null) { return new PSI($argument); }

function ego($Object) { return $Object($Object); }

function dual($Object) { return $Object(); }


function is_closure($object) {
    return (is_object($object) && get_class($object)=='Closure');
}

class PSI {
    static protected $_binds = array();
    protected $_ego = null, $_dual = null, $_quants = array();

    //-------------
    protected function _quant($name, $value) {
        $this->_quants[$name] = $value;
        return $this;
    }
    //-------------
    protected function _ego($source = array()) {
        //debug($source);
        if (count($source)==1 && !$this->_dual && is_closure($proxy = $source[0])) {
            $this->_dual = $proxy;
            return $this;
        }  else {
            //debug($source, array_merge($source, array($this)));
            return is_callable($this->_ego)
                ? call_user_func_array($this->_ego, array_merge($source, array($this)))
                : $this->_ego;
        }
    }
    //-------------
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
    //-------------
    public function __construct() {
        $this->_ego = array_pop(func_get_args());
        return $this;
    }
    //-------------
    public function &__get($name) {
        if (!isset($this->_quants[$name])) $this->_quants[$name] = null;
        return $this->_quants[$name];
    }
    //-------------
    public function __set($name, $value) {
        $this->_quants[$name] = $value;
    }
    //-------------
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
    //-------------
    public function __toString () {
        return (string) $this->_ego();
    }
    //-------------
    public function __invoke() {
        return
            ( $arguments = func_get_args()
                ? ($this->_ego(func_get_args()))
                : ($this->_dual ? call_user_func_array($this->_dual, array($this)) : $this->_quants)
            )
            ;
    }
}


?>