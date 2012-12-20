<?php

class PSI_Www extends PSI_Core {
    static public $config = array('root'=>'/.');

    protected
        $_current = ''
    ,   $_process = false
    ,   $_stop = false
    ,   $_url = array()
    ,   $_root = '/'
    ,   $_atom = null
    ,   $_errors = array('404'=>'not found')
    ,   $_www = null
    ;

    private $__toString = false;
    public function __construct($url='/') {
        $this->_url = $url;
        return call_user_func_array(array($this, 'create'), func_get_args());
    }

    public function url($url=null) {
        static $_url; if (!$_url) { $_url = $url; } //-- вот это переделать потом
        if (!is_null($url)) {
            $this->_url = array_filter(explode('/', parse_url($url, PHP_URL_PATH)), 'strlen');
            return $this;
        } else {
            return $_url;
        }
    }

    public function tail() {
        return array_shift($this->_url);
    }
    protected $_real = array(), $_virt = array();


    protected function _deep($params = array()) {
        list($atom, $quants, $default) = args($params, null, null, '', null);
        //-- тут делаем экстракт $www, тут же делаем разбор ссылки, тут же делаем красные и синии смещения
        //-- тут же можно делай choiser
        if ($quants) {
            if (count($this->_url)) { //-- вообще-то здесь следует использовать $www от PSI_Atom, но пока подождем
                //-- вот это, конечно, хорошее место, но нужно как-то "реставрировать" ссылки после их сдвигов
                $url = array_shift($this->_url);
                if (isset($quants[$url])) {
                    $return = call_user_func_array($quants[$url], array($atom));
                        return
                            (!count($this->_url) && $this->_stop)
                                ? call_user_func_array($this->_stop, array($return))
                                : $return
                            ;
                } else {
                    return
                    ($this->_skip)
                        ? $this->_skip($url, $atom, $default)
                        : (string) new PSI($this->_errors['404']); //-- почему строка? Да, видимо, потому, что 404 может быть вызвана только из браузера :) А все остальное может быть дополнительно интерпретировано.
                }
            } else {
                return (is_closure($default) ? call_user_func_array($default, array($atom))  : $default);
            }
        } else {
            return $atom;
        }
    }

    protected function _skip($url, $atom, $default) {
        array_unshift($this->_url, $url);
        $this->skip(false); //-- сбросим его тут же (это будет очень локальная опция)
        return (is_closure($default) ? call_user_func_array($default, array($atom))  : $default);
    }

    protected $_skip = false;

    public function skip($skip=null) {
        if (isset($skip)) {
            $this->_skip = $skip;
            return $this;
        } else {
            return $this->_skip;
        }
    }

    public function redirect($addr = null, $code=301) {
        //-- тут редирект
    }

    public function __invoke() {
        list($class, $args) = tail(func_get_args(), __CLASS__);
        if (is_string($class)) { //-- если происходит вызов в контексте создания объекта Class::www()
                if ($class === __CLASS__) {
                    return $this->_url;
                } else {
                    $reflect = new ReflectionClass($class);                        //-- Считается, что аргументом является имя класса
                    return new PSI_Atom($reflect->newInstanceArgs($args), $this); //-- создаем его, атомизируем и возвращаем заключенный в атом (то есть возвращаем сам атом)
                }
        } else { //-- или если в контексте готового объекта $Object->www()
            //-- вообще тут надо возвращать что-то типа $this->process($class->_atom)
            if (count($args)>1) { //-- если пришли аргументы, то войдем в собственное погружение и и обработаем их
                //-- вот этот deep должен повлиять на то, что вернется в следующем __toString для вызова
                return $this->_deep($args, $class); //-- $deep - это то, что вернется сейчас в __toString
            } else { //-- иначе просто вернем объект
                if ($argument = array_pop($args)) {
                    if ($argument == $this->_process) {
                        //-- если текущий вызов находится в процессе исполнения, то сделать что-то
                    } else {
                        //-- иначе сделать что-то другое
                    }
                } else {
                    if ($this->_process) { //-- если сейчас идет обработка состояния, то вернем его
                        return $this->_process;
                    } else { //-- или вернем запрашиваемый класс
                        return $class;
                    }
                }
            }
        }
        return $this->_url;
    }

    public function process ($do=null) {
        if (is_null($do)) {
            return $this->_process;
        } else {
            $this->_process = $do;
            return $this;
        }
    }

    public function error($error=404, $value = null) {
        if ($value) {
            $this->_errors[$error] = $value;
            return $this;
        } else {
            return $this->_errors[$error];
        }
    }


    public function stop($criteria = false) {
        $this->_stop = $criteria;
        return $this;
    }

    public function listen($listener = null) {
        //-- в зависимости от того, что пришло
        return $this;
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

        $process = $this->_process; //-- тут хранится крайний элемент, теперь делаем разрядку
        $return = array();
        //-- крайний этот тот, с которого происходит вызов
        //-- и что тут у нас происходит?
        //-- этот элемент конечную цепочку тех, кого он вызывался, упорядоченный ли по структуре?
        //-- в данный момент, да
        while ($this->_process) {
            $return[] = call_user_func($this->_process);
        }
        call_user_func_array($process, array($process));
        $this->__toString = false;
        return '/' . implode('/', array_reverse((array_filter($return))));
    }

}


class PSI_Atom extends PSI {

    public
            $_current = null
            , $_previuos = null
            , $_thread = array()
            , $_atom = null
            , $_www = null
    ;

    public function __construct(&$object, PSI_Www &$www) {
        //-- все объекты, которые помещаются в атомы, сразу теряют доступ к своим собственным квантам, но получают доступ к атому
        $this->_atom = $this->_atomize($object, $this);
        $this->_www = &$www;
        $this->_previuos = $this->_www->process();  //-- На первой итерации сюда придет false, и это будет зашибись, таким образом в тред войдет начало.
        $this->_www->process($this); //-- поместим в текущий процесс
        parent::__construct(function (PSI_Atom $atom) use ($object) {
            //-- вот эта вся байда отрабатывает в одном месте - __toString, значит в ней мне следует завершить текущее действие с атомом, ибо он сейчас находится в каком-то из состояний
            return $atom($atom);
        }); //-- перекнем класс так, чтобы он смотрел внутрь входящего объекта
        return $this;
    }

    protected function _atomize(&$object, $atom) {
        return  $object(function() use ($atom) { return $atom; });
    }

    public function __call($function, $arguments = array()) {
        if (isset($this->_quants[$function])) {
            //-- все хорошо за одним только исключением, само определение этой функции должно включать в себя самоидентификацию при вызове, то есть ее непосредственный вызов определяет погружение и сообщает об изменениях
            //-- ну так сделаем это!
            //-- здесь все кванты будут вызываемые
            list($psi, $args)  = tail($arguments, null);
            $return = (is_callable($this->_quants[$function]) //-- и значение можно вызвать
                //? call_user_func_array($this->_quants[$function], array($this->_atom)) //-- то вызовем его и вернем результат
                ? call_user_func_array($this->_quants[$function], array_merge($args, array($psi ? $psi : $this->_atom))) //-- то вызовем его и вернем результат
                : $this->_quants[$function] ) //-- иначе просто вернем его
            ;
            //-- вот тут на самом деле надо возвращать что-то типа триггера, определяющий правило: когда возвращается результат, тогда и восстанавливается процесс на предыдущий
            ; //-- восстановим предыдущий фрагмент

            return $return;
        } else {
            $this->_quant($function, function () use ($arguments, $function) {
                //-- $class() дает доступ к атому
                //-- теперь надо сделать так, чтобы
                //-- окей, смотрим что есть тут
                /*
                 * $object - вызываемый объект, идущий сквозь тернии к звездам, блеать :)
                 * $atom = $object() - его непосредственный атом
                 * $www = $atom() - непосредственный $www в каком-то из состояний
                 * $www() в разных вариациях дающий разный ответ
                 *
                 * $function - вызываемая функция
                 * $arguments[0] - то, что будет вызвано
                 *
                 * Непосредственным вызовом этой функции следует зафиксировать в текущем состоянии листенера то, что просходит внутри этого вызова, вызвать непосредственно фунцию, снять флаги о выполнении себя и вернуть функцию
                 *
                 */
                list($psi) = tail($args = func_get_args(), null);
                $atom = $psi();
                call_user_func_array($atom, array($function)); //-- а тут стек вызовов надо наоборот нагрузить
                $return = is_closure($result = $arguments[0]) ? call_user_func_array($result, $args) : $result; //-- это то, что будет возвращаться
                call_user_func_array($atom, array($atom)); //-- да, тут следует вызвать так, чтобы разгрузить стек вызовов
                return $return;
            });
            return $this;
        }
    }


    //-- в принципе через invoke я это могу делать, поскольку его самого я непосредственно нигде не вызываю


    /*
    Надо расписать варианты вызовов разных вариантов и их цепочек. Пока не вижу.
    */

    public function __invoke() { //-- аргумент тут всегда или один, или ни одного
        if ($argument = array_pop(func_get_args())) { //-- если есть входящие аргументы, то считаем, что вызывается функция на обработку погружения, и рассматриваем ее
            if ($argument===$this) {
                if ($this->_www->string()) { //-- если текущий вызов сейчас находится в запросе строки, то вернем на место
                    $this->_www->process($this); //-- поместим на место текущего процесса себя :)
                    return $this;
                } else {
                    if ($this->_thread) array_pop($this->_thread);
                    $return  = $this->_atom; //-- вот тут надо проверять, что возвращать, но это решим еще
                    return $return;
                }
            } else {
                if (is_string($argument) && isset($this->_quants[$argument])) {         //-- если текущий вызов присутствует в списке квантов
                    $this->_thread []= ($this->_current = $argument);                   //-- расширить текущую связь из потока
                    //$this->_thread []= ($this->_current = $argument);                   //-- расширить текущую связь из потока
                    return $this;
                    //-- это происходит в момент инициации
                } elseif (is_bool($argument)) {
                    $this->_www->skip($argument);
                    return $this;
                } else {
                    //-- вот тут подключается вызов _deep в _www
                    return call_user_func_array($this->_www, array($this->_atom, $this->_quants, $argument, $this));
                }
            }
        } else {
            if ($this->_www->string()) { //-- если текущее облако обрабатывается как строка (а это означает только попытку посмотреть текущий вызов), то бежим по сборке родителей.
                $this->_www->process($this->_previuos); //-- и сместим индетификатор назад
                if ($this->_current) {
                    $return = $this->_current;
                } else {
                    $return = reset($this->_thread);
                }
                return $return ;
            } else {
                return $this->_www;
            }
        }
    }

    /*
    Что у нас происходит на самом деле?
    Идет печать $print, в этот момент вызывается внутренний PSI, возвращая связанный объект.

    В данный момент печать идет следующим образом:
    1. Генерится

     */


    public function __toString() {
        if ($this->_thread) { //-- если текущее действие на print уже установлено, то есть идет печать, то возвращаем текущий вызов
            return reset($this->_thread);
        } else { //-- иначе начинаем процесс печати, захватив с собой в стартовую точку предыдущее состояние на "облаке" Www. В самом начале это будет null
            $return = parent::__toString();
            $this->_www->process($this->_previuos);
            return $return;
        }
    }
}

return function ($procedure) {
    return call_user_func_array($procedure, array(new PSI_Www('/')));
}

?>