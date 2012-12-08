<?php

function psi($arguments, $callback) {
    return new PSI ( function () use ($arguments, $callback) {
        return call_user_func_array($callback, $arguments);
    });
}

class PSI_Core extends PSI {
    static protected $_binds = array();

    private $_core = false;

    public function __construct() {
        $this->_core = (get_called_class() === __CLASS__) ; //--
        parent::__construct(array_pop(func_get_args()));
    }


    public function __call($function, $arguments=array()) {
        if ($this->_core) {                                         //-- если мы находимся в ядре
            if (!count($this->_quants)) {                           //-- то проверим, что у нас с состоянием квантов
                if ($handle = opendir( $core = $this . '/core')) {  //-- и определим их
                    while ($file = readdir($handle)) {              //-- как вызов функций, возвращаемых файлами с передачей аргументов
                        if (strstr($file, 'psi')) {
                            $name = array_shift(explode('.', $file));
                            $this->$name = function() use ($core, $file) {
                                static $_module; if (!$_module) $_module = include $core . '/' . $file;
                                call_user_func_array($_module, func_get_args());
                            };
                        }
                    }
                }
            }
            if (isset($this->_quants[$function])) {                 //-- если определен вызов
                parent::__call($function, $arguments);              //-- то исполним его
            } else {
                                                                    //-- иначе возврат ошибки
            }
            return $this;                                           //-- и вернем ядро для дальнейших процедур
        } else {                                                    //-- иначе возвращаем как есть
            return parent::__call($function, $arguments);
        }
    }



    //-- синглтон для цельного ядра (включая стандартные функции, возможно следует его назвать standart)
    static public function init ($init=null) {
        static $_core, $_singletones = array();
        if (!$_core) {
            //-- фактори
            PSI_Core::create(function() {
                list($class, $args) = tail(func_get_args(), __CLASS__);
                $reflect = new ReflectionClass($class);
                return $reflect->newInstanceArgs($args);
            });
            //-- синглтон
            PSI_Core::one(function() use (&$_singletones){
                list($class, $args) = tail(func_get_args(), __CLASS__);
                if (!isset($_singletones[$class])) {
                    $reflect = new ReflectionClass($class);
                    $_singletones[$class] = $reflect->newInstanceArgs($args);
                }
                return $_singletones[$class];
            });

            //-- отладочный класс
            PSI_Core::debug(function() {
                list($class, $args) = tail(func_get_args(), __CLASS__);

                return
                    call_user_func_array(array($class, 'create'), $args)
                        ->pr(function() {
                        $args = func_get_args();
                        pr ($args);
                        list($obj, $foo) = array(array_pop($args), array_pop($args)) ;
                        call_user_func_array('pr', count($args) ? $args : array($obj));
                        return $obj;
                    })
                        ->dpr(function() {
                        $args = func_get_args();
                        list($obj, $foo) = array(array_pop($args), array_pop($args)) ;
                        call_user_func_array('dpr', count($args) ? $args : array($obj));
                        return $obj;
                    })
                    ;
            });
            $_core = new PSI(__DIR__);
        }
        return call_user_func_array($init, array($_core));
    }
}

return function($init = null) {
    return call_user_func_array($init, array(new PSI_Core(__DIR__)));
};

/*
PSI::core(function() {
    static $_defined = false;
    list($class, $args) = tail(func_get_args(), 'PSI_Core');

    if (!$_defined) {
        if ($handle = opendir(__DIR__ . '/core')) {
            while ($file = readdir($handle)) {
                if (strstr($file, 'psi')) {
                    $function = array_shift(explode('.', $file));
                    PSI_Core::$function(
                        function () use ($file, $function) {
                            list($class, $args) = tail(func_get_args(), 'PSI_Core');
                            //-- вот тут надо как-то узнать, как подключать классы
                            static $_core;
                            if (!$_core) {
                                $_core = include_once __DIR__ . '/core/'. $file;
                            }
                            return call_user_func_array($_core, $args);
                        }
                    );
                }
            }
        }
        $_defined = true;
    }

    $reflect = new ReflectionClass('PSI_Core');
    return $reflect->newInstanceArgs($args);
});
*/
?>