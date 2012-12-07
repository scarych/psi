<?php

function psi($arguments, $callback) {
    return new PSI ( function () use ($arguments, $callback) {
        return call_user_func_array($callback, $arguments);
    });
}

class PSI_Core extends PSI {
    static protected $_binds = array();

    final public function quants($object = null) {
        return $this->_quants($object ? $object : $this);
    }

    //-- возвращает массив квантов
    protected function _quants($object) {
        $reflect = new ReflectionObject($object);
        $quants = $reflect->getProperty('_quants');
        $quants->setAccessible(true);
        return ($quants->getValue($object));
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
            PSI_Core::one(function() use ($_singletones){
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
            $_core = PSI::core($init);
        }
        return $_core;
    }
}

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

?>