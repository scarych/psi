<?php
/*
А также следует понять, как сам PSI_Tpl будет выглядеть, чтобы иметь использовать такие возможности,
и чтобы к нему можно было обращаться таким образом
*/

class PSI_Tpl extends PSI_Core {
    protected
        $_template = ''
        ;
    static protected
       $_binds = array()
        ;

    static public function create() {
        return call_user_func_array(parent::$_binds['create'], array_merge(func_get_args(), array(__CLASS__)));
    }

    public function variables() {
        return $this->_quants;
        return array_map (function (&$value) { //-- развернем кванты так, чтобы функции выполнялись при обращении к ним как к строке
            if (is_closure($value)) {
                return new PSI(function () use ($value) {
                    return call_user_func_array($value, array());
                });
            } else {
                return $value;
            }
        }, $this->_quants);
    }

    public function functions() {
        return static::$_binds;
    }

    public function template($template = null) {
        if ($template) {
            $this->_psi = $template;
            return $this;
        } else {
            return $this->_psi;
        }
    }

    public function __toString() {
        static $render;
        if (!$render) {
            $render = call_user_func_array(parent::$_binds['tpl'], array());
        }
        return (call_user_func_array($render, array($this)));
    }
}


return function ($render=null) {
    //-- теперь нужно понять, как будет подключать рендерер, и как будут подключаться шаблонки в код
    static $renderer = null;
    if (!is_null($render)) {
        $renderer = $render;
        return null;
    } else {
        return $renderer ? $renderer : function () { return null; };
    }
}

?>