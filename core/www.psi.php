<?php

class PSI_Www extends PSI_Core {
    static public $config = array('root'=>'/.');

    protected
        $_current = ''
    ,   $_process = false
    ,   $_url = array()
    ,   $_root = '/'
    ,   $_errors = array('404'=>'not found')
    ;

    private $__toString = false;
    public function __construct($url='/') {
        $this->_url = $url;
        return call_user_func_array(array($this, 'create'), func_get_args());
    }

    public function url($url=null) {
        static $_url; if (!$_url) { $_url = $url; } //-- вот это переделать потом
        if (!is_null($url)) {
            $this->_url = array_filter(explode('/', parse_url($url, PHP_URL_PATH)));
            /*

            self::$__url = array('real'=>array(), 'virt'=>array());
            self::$__virt = &self::$__url['virt']; self::$__real = &self::$__url['real'];

            $uri = urldecode($_SERVER['REQUEST_URI']);
            if (!empty(self::$_config['url_shift']) && strlen(self::$_config['url_shift']) > 1) { //-- если у нас определена константа сдвига,
                $this->dump($uri, strpos ($uri, self::$_config['url_shift']));
                if (strpos ($uri, self::$_config['url_shift'])!==false) { //-- и если она входит во фрагмент строки запроса
                    $uri = substr($uri, (strlen(self::$_config['url_shift'])+1));  //-- то отрежем фрагмент сдвигаемый
                }
            }
            $query_string = urldecode($_SERVER['QUERY_STRING']);
            foreach (($tmp = (!empty($query_string) ? explode("/", substr($uri, 0, strpos($uri, $query_string)-1)) : explode("/", $uri) )) as $k=>$v) if (!empty($v)) self::$__url['virt'][] = $tmp[$k];
            //----------------------------------------

            return $this;

            // $this->_url = $url;
            */
            return $this;
        } else {
            return $_url;
        }
    }

    protected $_real = array(), $_virt = array();


    protected function _deep($params = array()) {
        list($www, $default) = args($params, null, '');
        //-- тут делаем экстракт $www, тут же делаем разбор ссылки, тут же делаем красные и синии смещения
        //-- тут же можно делай choiser
        if ($www) {
            if (count($this->_url)) { //-- вообще-то здесь следует использовать $www от PSI_Watch, но пока подождем
                $url = array_shift($this->_url);
                $quants = $this->quants($www);
                if (isset($quants[$url])) {
                    return $www->$url();
                } else {
                    return (string) $this->_errors['404'];
                }
            } else {
                return $default;
            }
        } else {
            return $this->_errors['404'];
        }
    }


    public function redirect($addr = null, $code=301) {
        //-- тут редирект
    }

    protected $_www = null;
    public function __invoke() {

        //pr ($this->_psi);
        //-- технически этот самый psi мне и следует назначить, чтобы потом им оперировать
        //-- но пока еще рано, у меня нет наблюдаемых событий
        //-- и их следует прямо сейчас создать

        if (!$this->_www) {
            list($class, $args) = tail(func_get_args(), __CLASS__);
            if (is_string($class)) { //-- если происходит вызов в контексте создания объекта Class::www()
                $reflect = new ReflectionClass($class);
                return $this->_watch($reflect->newInstanceArgs($args)->www($this));
            } else { //-- или если в контексте готового объекта $Object->www()
                // pr ($class->_watch->ololo($class));
                //-- вообще тут надо возвращать что-то типа $this->process($class->_watch)
                if (count($args)) { //-- если пришли аргументы, то войдем в собственное погружение и и обработаем их
                    //-- вот этот deep должен повлиять на то, что вернется в следующем __toString для вызова
                    return $this->_deep($args); //-- $deep - это то, что вернется сейчас в __toString

                } else { //-- иначе просто вернем объект
                    if ($this->_process) { //-- если сейчас идет обработка состояния, то вернем его
                        return $this->_process;
                    } else { //-- или вернем запрашиваемый класс
                        return $class->_watch;
                    }
                }

            }
        }
        return $this->_www;
    }

    public function process ($do=null, $current = '') {
        if (is_null($do)) {
            return $this->_process;
        } else {
            $this->_process = $do;
            $this->_current = $current;
            return $this;
        }
    }

    protected function _watch($object) {

        //-- фактически для этого объекта следует как-то добавить возврат текущего watch, который привязан к ней
        //-- и с которым перепрелетны события

        return $object->_watch( new PSI_Watch ($object, $this) )->_watch;
        //return $object->_watch(new PSI(function () use ($object) { return $object; }) )->_watch;
    }

    public function error($error=404, $value = null) {
        if ($value) {
            $this->_errors[$error] = $value;
            return $this;
        } else {
            return $this->_errors[$error];
        }
    }

    //-- сюда можно будет цеплять всякие редиректы и возвраты
    public function code($code=200, $value = null) {
        return $this;
    }

    //-- составить путь на основе параметров,
    public function path() {
        $return = array();
        foreach (func_get_args() as $k=>$v) {
            if ($v) $return[] = $v;
        }
        return $this->_current . ($return ? ('/' . implode('/' . $return)) : '');
    }

    public function string() {
        return $this->__toString;
    }


    public function __toString() {
        $this->__toString = true;
        $return = array((string) $process = $this->_process);
            while ($process && $process = call_user_func($process)) {
                $return[] = (string) $process;
            }
        $this->__toString = false;
        return '/' . implode('/', array_reverse(array_filter($return)));
    }

    public function __1call($function, $args) {
        //-- первичный вызов аналогичен установке, вторичный же возвращает результат
    }
}


class PSI_Watch extends PSI {

    protected $_asString = false;
    protected $_current = '';
    protected $_process = false;
    protected $_watch = null;
    protected $_www = null;
    protected $_previous = false;


    public function __construct(&$object, PSI_Www $www) {
        $this->_watch = &$object;
        $this->_www = &$www;

        $this->_previous = $this->_watch->www->process();

        parent::__construct(function () use ($object) { return $object; });
        return $this;
    }

    public function __call($function, $arguments = array()) {
        $this->_process = $this->_watch->www->process();
        $this->_current = $function;
        $this->_watch->www->process($this, $this->_current);

        //-- вот тут следует еще немного допилить, но уже почти красиво получается :)
        $return =
            (isset($this->_quants[$function]) //-- если определено значение по этому имени
                ? (is_callable($this->_quants[$function]) //-- и значение можно вызвать
                    ? call_user_func_array($this->_quants[$function], array_merge($arguments, array($this->_watch))) //-- то вызовем его и вернем результат
                    : $this->_quants[$function] //-- иначе просто вернем его
                )
                : ($this->_quant($function, array_pop($arguments))) //-- внесем его
            )
            ;
        $this->_watch->www->process($this->_process);
        //$this->_current = '';
        return $return;
    }


    //-- в принципе через invoke я это могу делать, поскольку его самого я непосредственно нигде не вызываю

    public function __invoke() {
        list ($return) = args(func_get_args(), null);
        if ($return) {
            //-- тут следует сделать что-то с аргументами
            //-- вот тут можно проверить следующее
            $this->_psi = new PSI (function() use ($return) { return $return; });
            return $this;
        } else {
            if ($this->_www->string()) { //-- если текущий www обрабатывается как строка, то бежим по сборке родителей
                return $this->_previous;
            } else {
                return $this->_www;
            }
        }
    }

    public function __toString() {
        if ($this->_watch->www->process()) {
            return $this->_current;
        } else {
            return parent::__toString();
        }
    }

}

return function ($procedure) {
    return call_user_func_array($procedure, array(new PSI_Www('/')));
}

?>