<?php
//-- класс-наблюдатель. Позволяет заглянуть внутрь функции и извлечь фрагменты ее вызова
class PSI_Watch extends PSI_Core {

    static public function create($class, $args) {

        $reflect = new ReflectionClass($class);
        return $reflect->newInstanceArgs($args);
    }

    public function __call ($function, $args) {
        //-- вот тут следует при вызове функции использовать дополнительную переменную, которая будет располагаться внутри
        parent::__call($function, $args);
    }

    static  public function __callStatic ($function, $args) {
        //-- вот тут следует при вызове функции использовать дополнительную переменную, которая будет располагаться внутри
    }
}


return function ($procedure) {

}
?>