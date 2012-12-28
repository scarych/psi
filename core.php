<?php



class PSI_Core extends PSI {
    static protected $_binds = array();
    private $_core = false;

    final static public function debug($debug='/tmp/debug.psi.log', $path= __DIR__ ) { //-- запускает ядро в режиме отладки, устанавливает константу, на которую будут опираться
        if (!defined('_PSI_CORE_DEBUG_')) {
            define('_PSI_CORE_DEBUG_', $debug);
            debug('PSI_Core debug mode on');
        }
        return new self($path);
    }

    public function __construct($path = __DIR__) {
        if (!defined('_PSI_CORE_DEBUG_')) define('_PSI_CORE_DEBUG_', 0);
        $this->_core = (get_called_class() === __CLASS__);
        parent::__construct($path);
    }

    final protected function __debug($argument) {
        debug($argument);
        return $argument;
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

            $_core = new PSI(__DIR__);
        }
        return call_user_func_array($init, array($_core));
    }
}

return function($init = null) {
    return call_user_func_array($init, array(new PSI_Core(__DIR__)));
};




/* FUNCTIONS  */
//-- Date and Time control in light mode :)
function now($date = null, $format = null, $strf = false) {
    if (!$date) $date = time();
    $ret = (is_numeric($date) ? $date : strtotime($date));
    return (!$format ? $ret : ($strf ? strftime($format, $ret) : date($format, $ret)));
}
//--
function tail() {
    $args = func_get_args();
    $ret = array();
    list($src, $defaults) = array(array_reverse($args[0]), array_slice($args, 1));
    foreach ($defaults as $k => $v) {
        $ret[] = (!isset($src[$k]) ? $defaults[$k] : $src[$k]);
    }
    $ret[] = count($ret) < count($src) ? array_reverse(array_slice($src, count($ret))) : array();
    return $ret;
}

function args() {
    $args = func_get_args();
    $ret = array();
    list($src, $defaults) = array($args[0], array_slice($args, 1));
    foreach ($defaults as $k => $v) {
        $ret[] = (!isset($src[$k]) ? $defaults[$k] : $src[$k]);
    }
    $ret[] = count($ret) < count($src) ? array_slice($src, count($ret)) : array();
    return $ret;
}

function _psi($arguments, $callback) {
    return new PSI ( function () use ($arguments, $callback) {
        return call_user_func_array($callback, $arguments);
    });
}

function psy($PSI, $arguments = array()) {
    return call_user_func_array($PSI, $arguments);
}

function debug() {
    if (_PSI_CORE_DEBUG_) {
        if ($args = func_get_args()) {
            foreach ($args as $value)  {
                file_put_contents(_PSI_CORE_DEBUG_, now('now', 'd.m.Y h:i:s ') . "--\r\n" . (print_r ($value, true) ) . "\r\n", FILE_APPEND );
            };
        }
    }
}


function push() {
    list ($array, $tail) = args(func_get_args(), array());
    foreach ($tail as $v) $array[]=$v;
    return $array;
}


?>