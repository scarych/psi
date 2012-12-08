<?php

function debug() {
    //-- тут можно через debug backtrace() разобрать, что есть что и записать, будет нормальный такой лог
    if ($args = func_get_args()) {
        foreach ($args as $value)  {
            file_put_contents('/tmp/' . (basename($_SERVER['SERVER_NAME'])) .'.log', /*now('now', 'd.m.Y h:i:s ') . */ (print_r ($value, true) . "\r\n--\r\n"), FILE_APPEND );
        };
    }
}

/*
* текущий формат даты
*/
function now($date = null, $format = null, $strf = false)
{
    if (!$date) $date = time();
    $ret = (is_numeric($date) ? $date : strtotime($date));
    return (!$format ? $ret : ($strf ? strftime($format, $ret) : date($format, $ret)));
}


function psy($argument) { //-- а вот это очень интересная фукнция. Используется как раз для психоделических операций :-) Возвращает вызов от параметра. Может быть использована в сложных связях
    return $argument();
}

function is_closure($object) {
    return (is_object($object) && get_class($object)=='Closure');
}

function push() {
    list ($array, $tail) = args(func_get_args(), array());
    foreach ($tail as $v) $array[]=$v;
    return $array;
}

function tail()
{
    $args = func_get_args();
    $ret = array();
    list($src, $defaults) = array(array_reverse($args[0]), array_slice($args, 1));
    foreach ($defaults as $k => $v) {
        $ret[] = (!isset($src[$k]) ? $defaults[$k] : $src[$k]);
    }
    $ret[] = count($ret) < count($src) ? array_reverse(array_slice($src, count($ret))) : array();
    return $ret;
}

function args()
{
    $args = func_get_args();
    $ret = array();
    list($src, $defaults) = array($args[0], array_slice($args, 1));
    foreach ($defaults as $k => $v) {
        $ret[] = (!isset($src[$k]) ? $defaults[$k] : $src[$k]);
    }
    $ret[] = count($ret) < count($src) ? array_slice($src, count($ret)) : array();
    return $ret;
}


class PSI {
    static protected $_binds = array();
    protected $_quants = array( ), $_psi, $_proxy = null;

    //-- эта функция будет вызываться в преобразователе
    protected function _psi($source = array()) {
        /*
            делается так: если в качестве аргумента пришла функция без параметров, то она перетирает текущий вызов без параметров, замещая собой кванты, но делая доступными кванты возвращаемого значения
            То есть, я делаю так:
            $object(function () use ($atom) { return $atom ; })
            В этом случае начинается интересное: при вызове $object() я получаю доступ к $atom, пришедшему извне, в то же время при вызове $object->atom я могу к нему обратиться. Единственное, что я не смогу сделать, так это получить $object() со своими атомами, и мне их надо будет где-то отдельно пересохранять
            $object(function(CLASS $object) use ($atom) { return $atom; }) //-- и внутри уже нельзя будет обращаться к $object() иначе приведет к замыканию
        */
        if (count($source)==1 && !$this->_proxy && is_closure($proxy = array_pop($source))) { //-- это магическая приблуда, позволяет запутать квантово два атома, если аргумент функция, и еще не задано проксирование и прочие приблуды. Работает только с функцией на входе!
            $this->_proxy = $proxy;
            return $this;
        }  else {
            return is_callable($this->_psi)
                ? call_user_func_array($this->_psi, array_merge($source, array($this)))
                : $this->_psi;
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

    public function &__get($name) {
        //-- что должно происходить, если вызов идет из квантового состояния?
        if (! isset($this->_quants[$name])) {
            $this->_quants[$name] = null; //-- вот это очень важная фишечка, она позволяет делать реверсивные захваты при инициации
        }
        return $this->_quants[$name];
        /*
        return
            isset($this->_quants[$name])
                ? $this->_quants[$name]
                : null
            ;
        */
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
            return (
            $this->_proxy //-- если задано проксирование
                ? call_user_func_array($this->_proxy, array($this))
                : $this->_quants
                );
        }
    }
}


?>