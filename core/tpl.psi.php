<?php

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
        return static::$renderer ? call_user_func_array(static::$renderer, array($this->_psi, $this)) : $this->_psi;
    }

}

return function($render = null, PSI_Core $Core) {
    if (!is_null($render)) {
        PSI_Tpl::$renderer = $render;
    }
    return $Core;
}

?>