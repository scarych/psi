<?php

function PSI_Tpl ($param = null) {
    return ($param ? new PSI_Tpl($param) : PSI_Tpl::functions());
}

class PSI_Tpl extends PSI_Core {
    static public $renderer = null;
    static protected
       $_binds = array()
        ;

    static public function create($template=null) {
        return new self($template);
    }

    static public function functions() {
        return static::$_binds;
    }

    public function __toString() {
        return (string) (static::$renderer ? call_user_func_array(static::$renderer, array($this->_ego, $this)) : $this->_ego);
    }
}

return function($render = null, PSI_Core $Core) {
    if (!is_null($render)) {
        PSI_Tpl::$renderer = $render;
    }
    return $Core;
}

?>