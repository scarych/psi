<?php

class PSI_Cache extends PSI_Core {
    //-- тут будет особая функция __invoke и __toString.
    //-- сравнив состояние валидации
}

return function($cache=false, $time='5 minutes', $criteria=false) {
    PSI::cache(function() {
        //-- пока так, потом накрути внутрь проверки и извлечения
        list($class, $args) = tail(func_get_args(), 'PSI');
        $reflect = new ReflectionClass($class);
        return $reflect->newInstanceArgs(function () use ($args) { return new PSI_Cache($args); });

    });
}

?>