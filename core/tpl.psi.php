<?php
//--PSI_Tpl
class PSI_Tpl extends PSI_Core {
    //-- перегрузка Связей
    static public $_binds = array();
    //-- перегрузка строки
    public function __toString() {
        return call_user_func_array(static::$_core->tpl, array($this->_ego, $this));
    }
    //-- перегрузка вызова функции
    public function __invoke() {
        return
             ($arguments = func_get_args())
                ? $this->_ego($arguments)
                : $this->_quants
            ;
    }
}
//-- функция быстрого вызова
function PSI_Tpl ($param = null) {
    return ($param ? new PSI_Tpl($param) : PSI_Tpl::$_binds);
}
//-- конфигуратор PSI_Tpl в Ядре
return function($renderer = null, PSI_Core $Core) {
    $Core->tpl = $renderer;
    return $Core;
}
;
?>