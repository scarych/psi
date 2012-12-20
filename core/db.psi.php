<?php

class PSI_Shell extends PSI_Core {
    protected
        $_query = ''
        , $_params, $_all_params = array()
        , $_deep = 0

        /**
         * @var $_result PDOStatement
         */
        , $_result = null

        , $_slices = array()
        , $_status = array()
        , $_source = null
        , $_readonly = true
        , $_db = null
        , $_limit = array()
        , $_order = array()
        , $_fields = array()
        , $_data = array() //-- вот эта data и будет хранить изменения, и по ним будет идти ориентация (пока так)
        , $_cursor = 0 //-- пока так
        , $_count = 0 //-- пока так
        /**
         * @var PSI_Watch
         */
        , $_watch = null
        , $_procedures = array() //-- процедуры, которые будут фильтровать текущее значение, устанавливает правила обработки всех прочих значений
        , $_ = null
    ;

    public function __construct($source, PSI_DB $Db, $type = PSI_DB::_TYPE_SQL_, $Psi) {

        $this->_source = $source;
        $this->_db = &$Db;
        $this->_readonly = ($type===PSI_DB::_TYPE_SQL_); //-- закроем от записи то, что сделано запросом
        $this->_params = &$this->_all_params; //-- зациклим параметры
        $this->_psi = $Psi($Psi);
        //pr ($Psi($Psi));

        $this
            ->or(
            function() {
                list($shell, $args) = tail(func_get_args(), null);
                return $shell->deep('OR')->param($args)->deep();
            })
            ->and(
            function() {
                list($shell, $args) = tail(func_get_args(), null);
                return $shell->deep('AND')->param($args)->deep();
            })
            ->not(
            function() {
                list($shell, $args) = tail(func_get_args(), null, null);
                return $shell->deep('NOT')->param($args)->deep();
            })
            ->default(
            function() {
                list($Shell, $skip) = tail(func_get_args(), null, array());
                if (!is_array($skip)) $skip = array($skip); $skip = array_flip($skip);
                //-- вот тут я как-то должен подключить отображение дефолтных значений
                $return = array();
                foreach (psy($Shell->fields()) as $field=>$value) {
                    if (!isset($skip[$field]) && $value) {
                        $return[$field] = $value->default();
                    }
                }
                return $return;
            })
        ;
    }

    public function count() {
        return $this->_count;
    }

    protected  $_delimetr = 'AND';

    public function delimetr($delimetr = null) {
        if (isset($delimetr)) {
            $this->_delimetr = $delimetr;
            return $this;
        } else {
            return $this->_delimetr;
        }
    }

    public function extract($delimetr = 'AND') {
        //-- заполняет
        $this->_result = $this->delimetr($delimetr)->_db->db($this->_psi->query($this, null, null));
        $this->_count = $this->_result->rowCount();
        $this->_watch = new PSI_Watch($this->_result, $this);
        return $this;
    }

    static protected $_transaction = true, $_translevel = 0;

    public function __invoke() {
        if (count($args = func_get_args())) {
            $argument = array_shift($args);
            switch (true) {
                case (is_array($argument)): //-- это массив, а значит импорт в оболочку
                    //-- тут проверяется, есть ли в текущем положении какое-либо значение, и если есть, то обновляет его значения
                    //-- если нет, то вставляет новые
                    //-- идет генерация insert или update запроса
                    /*
                    Итак, приходит массив. В нем уложены штабелями новые данные.
                    Эти данные помешаются по следующему принципу:

                    */


                    //-- вот тут мне следует начинать транзакцию, и начинать ее на глобальном уровне.
                    //-- то есть ... то есть на самом деле что?

                    //--
                    if (self::$_translevel) {
                        $this->_db->db()->exec("SAVEPOINT LEVEL". self::$_translevel ." ;");
                    } else {
                        $this->_db->db()->beginTransaction();
                    }

                    self::$_translevel++;
                    $current = (string) $this(); //-- текущая позиция в массиве
                    //-- как узнать, что у меня тут массив массивов? либо все делать через импорт и смещение?



                    /*
                    При обновлении следует сделать один важный момент проверки:
                    Если (исходный массив + входящий массив) после обработки полностью пересекается с исходным массивом после обработки, то сохранения не происходит.
                    Так я экономлю "флопсы" на базе данных
                    */

                    if (isset($this->_data[$current])) { //-- тут update или delete с подстановкой ключа
                        $query = $this->_psi->query($this, $argument, ($this->_key
                            ? (is_array($this->_key)
                                ?  call_user_func_array(function($keys, $values) { $return = array(); foreach ($keys as $key) $return[$key]=$values[$key]; return $return; }, array($this->_key, $this->_data[$current]))
                                : array($this->_key=>$this->_data[$current][$this->_key]))
                            : $this->_data[$current]));
                    } else { //-- тут insert
                        if (count($argument)) {
                            $query = $this->_psi->query($this, array_merge($this->default($this->_key), $argument), null);
                        } else {
                            $query = $this->_psi->query($this, $argument, $this->param(), null); //-- иначе удаление по ключу
                        }
                    }
                    debug($query);
                    $result = $this->_db->db($query);
                    if (self::$_transaction = $result->rowCount()) {
                        if (!isset($this->_data[$current])) {
                            if ($this->_key && is_scalar($this->_key) && empty($argument[$this->_key])) {
                                $argument[$this->_key] = $this->_db->db()->lastInsertId();
                            }
                            $this->_data[$current] = $argument; //-- вставим строку
                            $this->_count += self::$_transaction;
                        } else {
                            $this->_data[$current] = array_merge($this->_data[$current], $argument);
                        }
                    }

                    self::$_translevel--;

                    if (self::$_translevel) { //-- если транзакции еще идут
                        if (self::$_transaction) {
                            $this->_db->db()->exec("RELEASE SAVEPOINT LEVEL" . self::$_translevel . ";");
                        } else {
                            $this->_db->db()->exec("ROLLBACK TO SAVEPOINT LEVEL" . self::$_translevel . ";");
                        }
                    } else { //-- если транзакции закончились
                        if (self::$_transaction) {
                            $this->_db->db()->commit();
                        } else {
                            $this->_db->db()->rollBack();
                        }
                    }

                    //$data = array_filter(array_map('strval', $this->_data[(string) $current]));

                    //pr ($data);

                    //-- здесь следует возвращать атом, который только что обновили
                    //-- ок, пробуем

                    return $this;
                    break;
                case (is_integer($argument)): //-- это позиция курсора
                    $this->_cursor = abs($argument);
                    return $this;
                    break;
                case (is_string($argument)): //-- это поиск по оболочке
                    //-- тут надо будет какой-нибудь поиск написать
                    break;
                case (is_closure($argument)): //-- это поиск по оболочке
                    //-- тут надо будет какой-нибудь поиск написать
                    $return  = parent::__invoke($argument);
                    return $return;
                    break;
                default:
                    return $this;
            }
        } else {
            if ( !isset($this->_result) ) {
                $this->extract(); //-- вот тут происходит первичное извлечение
            }
            //pr ($this->_watch);
            //-- если у меня есть прокси, то я могу сделать следующее
            return call_user_func_array($this->_watch, array($this));
            // return $this->_psi;
        }
        //return 'invoke ok';
        //-- __invoke для $Shell дает две возможности
        //-- обратиться непосредственно к PSI в том состоянии, в которое он себя хранит
        //-- (то есть будет всего один атом PSI_Watch, который будет перемещаться по матрице индексов
        //-- обратиться к мета-статусу, в котором находится
        //-- мета-статус - это формируемые в самом начале вызовы struct, fields и так далее
        //-- выбор между тем, что вызывать даст следующее

        //-- доступ к атомам будет даваться через
        //-- ->forward(), ->backward()
        //-- если будет параметр (5), то это считается индексом для перехода, если там ставится строка, то считается параметром для ключа
        //-- да, вызов с параметром отправляется к мета-статусу, а с параметром - пытается найти индекс
        //-- запрос хранится в атоме, другое дело, что этот атом можно сохранить и использовать по назначению
        //-- по большому счету каждый индекс сдвига и позволяет перемещаться по нему
        //-- ща ща решим все



        //-- такс, что мы имеем с гуся?

        //-- а с гуся мы имеем такие примерно варианты использования:
        /*
        PSI::mylsq()->table(
            function (PSI_Shell $shell) {
                $shell
                    ->param('is_blocked', 0)
                    ->limit(10)
                ;
                return $shell(intval($_GET['shift'])); //-- вот что вернется тут? и как с этим можно будет жить?
                //-- а тут вернется сдвинутый на нужную позицию атом, который будет доступен для перебора
                //-- таким образом получаем следующий алгоритм
                {while $atom = $Shell->forward()}
                    //-- таким образом тут получается доступ к атому
                    {$atom->data()}
                    //-- все заебок, двигаемся дальше
                {/while}
            }
        )

        */

        //-- И ВОТ ТУТ Я ПРИДУМАЛ ТРАНЗАКЦИИ!!!1

        /*
         Все складывается!
            Шелл пишется так.
            Есть бегающий по индикации курсор.
            Есть окошко в этот курсор с обвешанными функциями.
            Вызовы определяются последовательностью их включения
            То есть технически они способны работать таким образом. На примере NDAB, например

            PSI::mysql()->realty(function (PSI_Shell $Shell) {
                $Shell()->object_owner(function($value) {
                    $Owners = Root::one()->lots()->owners(); (а там возвращается Owners, которые при вызове его в строке дает целую таблицу внутри, но мы можем его использовать
                    return $Owners->source();
                })
            }



            Это все будет делаться затем, чтобы в шаблоне написать
            $Owners
         */

        /*
        В зависимости от того, что будет поставляться $complete или $active будет идти обращение к вызову или к операции


       К примеру: Нужно в качестве параметров получить список полей, тогда $Deals()->fields() вернет состояния для этой таблицы, в этом случае оболочка будет активна

       Если оболочка будет закрыта, то обращение $Deals() вернет что?
       И как этим следует будет обращаться?

       Например:
       Tpl()->Data(DB()->sometable());

       Теперь при вызове $Data() будет происходить сдвиг вперед, либо назад и возвращаться идентификатор
       То есть можно будет делать строку
       {while $Data->forward()}

           {$Data()->field()} //-- вот так мне и надо действовать
           {$Data(id)->field()}


           //-- в этом случае я оставляю право использования

       {/while}

        */

        /*
         При вызове через () собираются параметры те, что в $shell()->fields() + те, что $shell->params() и возвращается $Shell, который потом можно использовать как ->forward()
         При вызове через (ID) собирается те, что в params(), и возвращается PSI_Watch
         При вызове через (array(...)) пытается собрать данные в кучу для записи, и возвращает PSI_Watch с подготовленными значениями, для ограничения выборки использует param(), для ограничения записи использует $shell()->fields()
         При вызове через (null) или прочие пустые значения, пытается собрать данные в кучу, используется их эквивалент как ключ для запуска, и возвращется DBAtom
         */


        /*
        Теперь, как будет работать DBAtom $Atom при его обращении
        1. __call запускается, передавая текущее значение
        2. __set и __get работают аналогично

        3. $Atom() должен возвращать к шеллу. Только нахуя? А чтобы иметь возможность поменять функцию или выбрать рекурсивные значения
           Например, $Atom(array('gallery_pid'=>$Atom->id())) создаст новую оболочку с рекурсивным вызовом для текущий
           В принципе, пригодится для обкатки страниц. По большому счету склонирует текущий Shell и вернет его $Shell, который можно будет пробежать, который можно будет как-то применить.
           То есть даже тут можно будет вызвать $Shell($_POST['data'])
           Тоже самое для прочих текстовых параметров.

           Для цифровых параметров, только поиск будет среди индексов текущего блока, и будет возвращен DBAtom и его $atom() и возвратом на текущее значение (без фиксации и привязки, просто по номеру), который я смогу применить как прочий атом, потому что его поля уже будут подготовлены для запуска
           Просто $Atom() должен вернуть, например, массив своих значений. Даже не массив, а то, что я могу интерпретировать в массив. Ну да, массив из интепретированных квантов. Это будет крутотенюшка!

           А __toString - то, что считается у него ключем.
           Вот так будет то, что надо!
        */

        /*
        Итак, еще раз. Как извлекать данные из оболочек?
        tpl()->Data(Mysql()->table(5))
        Возвращается Atom
        И теперь в шаблоне к нему можно обращаться как $Data->value()
        Непосредственно $Data() - будет возвращать родительский шелл.

        $Data(array()) будет пытаться сохранить данные в текущей позиции, и переставить его значения
        (string) $Data вернет текущий ID записи
        $Data(string) или $Data(int) делать что? Пытаться извлечь новый атом по ключу? Подумать. Скорее всего, да.


        tpl()->Data(Mysql()->table()),
        Возвращается Shell
        И теперь в шаблоне к нему можно будет обращаться как $atom = $Data->forward() и читать $atom
        $Data->fields() можно будет использовать как ограничитель структуры
        $Data() получает доступ к текущей структуре. Всегда!

        $Data(int) возвращает атом по заданной позиции
        $Data(string) ищет атом по заданному ключу
        $Data(array()) делает дополнительную выборку среди имеющейся, в которой ищет значения и возвращает Shell.

        По сути должно быть можно использовать
        //-- при этом в существующем параметрическом облаке все должно быть четко и не вызывать повторов и разрывов.
        //-- это я сделаю в процессе написания кода
        {while $atom2 = $Data(array('is_blocked'=>0))->forward()}

            $atom2;

        {/while}


        Что будет давать обращение через функцию?

        */

    }

    protected $_key = null;

    public function insert($data = array()) {
        call_user_func($this(), $this->_count); //-- сместим в самый конец курсор и добавим входящее значение
        return $this($data);
    }

    public function key($key = null) {
        if (is_null($key)) {
            return $this->_key;
        } else {
            $this->_key = $key;
            return $this;
        }
    }

    public function blank() {
        $last = $this();
        $current = 1 + (string) $last;
        $this->_data[$current] = $this->default();
        $last($current);
        return $this();
    }

    public function current($default = true) {
        $current = (string) $this();
        if (isset($this->_data[$current])) {
            return $this();
        } else {
            if ($default) {
                $this->_data[$current] = $this->default();
                return $this();
            } else {
                return false;
            }
        }
    }

    public function autokey() {
        $this->_key = array_shift(array_keys(psy($this->fields()))); //-- извлечем первый столбец
        return $this;
    }

    protected function _slice(&$params) {
        $slices = &$this->_slices;
        $slices[] = &$params;
        return $this;
    }

    public function deep($open=0) {
        $this->_deep += ($open ? 1 : 0); //-- сменим "глубину"
        if ($open) {
            $bkt_key = ($open=='OR' ? 'OR' : 'AND') .  '-' . $this->_deep;
            $this->_slice($this->_params);
            $this->_params[$bkt_key] = array();
            $this->_params = &$this->_params[$bkt_key];
        } else {
            $this->_params = &$this->_slices[$key = end(array_keys($this->_slices))]; unset ($this->_slices[$key]);
        }
        return $this;
    }

    public function readonly($value=null) { //-- при необходимости блокирует таблицу для записи
        if (is_null($value)) {
            return $this->_readonly;
        } else {
            $this->_readonly = (boolean) $value;
            return $this;
        }
    }

    //-- возможно тут следует сделать рефлексию.. А для чего я хотел делать рефлексию? Хороший вопрос.

    /*
    Параметры сделать так же, как в старой версии?
    Или попробовать их оптимизировать?
    */



    /**
     * @return PSI_Shell|mixed
     */
    public function param($fields = null, $value=null) {
        if (is_null($fields) && is_null($value)) {
            return $this->_params;
        } elseif (is_null($value)) {
            if (is_string($fields) && $this->fields()->{$fields}) {
                //-- возврат аттрибутов
                return $this->_attrs[$fields];
            } else {
                if (is_callable($fields)) { //-- установка через замыкание
                    call_user_func_array($fields, array($this)); //-- там внутри все распишется
                } else {
                    if (is_array($fields)) { //-- установка из массива
                        if (count($fields)>1) {
                            foreach ($fields as $k=>$v) {
                                if (is_numeric($k)) {
                                    $this->param($fields[0], $fields[1]);
                                    break;
                                } else {
                                    $this->param($k, $v);
                                }
                            }
                        } else {
                            foreach ($fields as $k=>$v) {
                                if (is_numeric($k)) {
                                    $this->param($v);
                                } else {
                                    $this->param($k, $v);
                                }
                            }
                        }
                    } else { //-- установка по ключу
                        //-- тут поиск ключа и установка
                        //- @todo сюда я еще доберусь
                        if ($this->_key) {
                            $this->param($this->_key, $fields);
                        } else {
                            $this->param(array_shift(array_keys(psy($this->fields()))), $fields);
                        }
                    }
                }
                return $this;
            }
        } else {
            //-- установка параметра по полю
            $this->_param($fields, $value);
        }
        //-- параметризация будет построена аналогично предыдущим шеллам.
        //-- только собираться параметры будут локальными функциями сборки внутри БД
        return $this;
    }

    //-- задать интервал
    public function between($field, $from, $to) {
        //dpr ($this->fields()->{$field});
        if ($Field = $this->fields()->{$field}) {
            $this->_params[$field] = array_merge_recursive(
                (isset($this->_params[$field]) ? $this->_params[$field] : array()),
                array('-between'=>array($this->{$Field->type()}(array($from, $to), false)))
            );
        }
        return $this;
    }

    protected function _param($params, $values) {
        if (!is_array($params)) list ($params, $values) = array(array($params), array($values));
        foreach ($params as $index=>$param) {
            //-- value должно представлять из себя массив, который подключается по принципу
            if ($field = $this->fields()->{$param}) {
                $value = $this->{$field->type()}($values[$index], true);

                if (!isset($this->_params[$param])) {
                    $this->_params[$param] = $value;
                } else {
                    $this->_params[$param] = array_merge_recursive($this->_params[$param], $value);
                }
            }
        }
        return $this;
    }

    //-- функции приведения типов
    //-- приведение к валидному целому
    public function integer($values, $sign=false) {
        if (!is_array($values)) $values = array($values);
        $return = array();
        array_map(function($value) use ($sign, &$return) {
            $parse = ($value && is_string($value)
                ? ( $sign
                    ? (
                    ($value[0]=='>' || $value[0]=='<' || $value[0]=='!' || $value[0]=='&')
                        ? ( ( $value[1]=='=' || $value[1]=='>'  || $value[1]=='<' )
                        ? array(substr($value,0,2)=>preg_replace('/\D/', '', $value))
                        : array(substr($value,0,1)=>preg_replace('/\D/', '', $value))
                    )
                        : array('-enum'=>intval(preg_replace('/\D/', '', $value)))
                    )
                    : ( is_null($value) ? 'NULL' : intval(preg_replace('/\D/', '', $value)) )
                )
                : ($sign ? array('-enum'=>intval((string) $value)) : (is_null($value) ? 'NULL' : intval( (string) $value)) )
            )
            ;
            ($sign ? $return = array_merge_recursive($return, $parse) : $return[] = $parse);
        }, $values );
        return $return;
    }
    //-- приведение к валидной строке
    public function string($values, $sign=false, $quotes=true) {
        if (!is_array($values)) $values = array($values);
        $return = array();
        array_map(function($value) use ($sign, &$return) {
            $value = (string) $value;
            $parse =
                ($sign
                    ?   (
                    (@$value[0]=='~')
                        ? array(substr($value,0,1)=>"'%" . (addslashes(substr($value, 1))) . "%'")
                        : array('-enum'=>"'" . (addslashes($value)) . "'")
                    )
                    : ( is_null($value) ? 'NULL' : "'" . (addslashes($value)) . "'" )
                )
            ;
            ($sign ? $return = array_merge_recursive($return, $parse) : $return[] = $parse);
        }, $values );
        return $return;
    }
    //-- приведение к валидному тексту
    public function ___text($values, $sign=false, $quotes=true) {
        return self::string($values, $sign, $quotes);
    }
    //-- приведение к валидному тексту
    public function boolean($values, $sign=false, $quotes=true) {
        return $this->integer($values, $sign, $quotes);
        if (!is_array($values)) $values = array($values);
        $return = array();
        array_map(function($value) use ($sign, &$return) {
            $parse = ($value && is_string($value)
                ? ( $sign
                    ? ( array('-enum'=>(empty($value)?0:1)))
                    : ( is_null($value) ? 'NULL' : (empty($value)?0:1) )
                )
                : ($sign ? array('-enum'=>intval((string) $value)) : (is_null($value) ? 'NULL' : (empty($value)?0:1)) )
            )
            ;
            ($sign ? $return = array_merge_recursive($return, $parse) : $return[] = $parse);
        }, $values );
        return $return;
    }
    //-- приведение к валидному тексту
    public function timestamp($values, $sign=false, $quotes=true) {
        return $this->datetime($values, $sign, $quotes);
    }
    //-- приведение к валидному дробному
    public function  float($values, $sign=false) {
        if (!is_array($values)) $values = array($values);
        $return = array();
        array_map(function($value) use ($sign, &$return) {
            $parse = (is_string($value)
                ? ($sign
                    ?   (
                    ($value[0]=='>' || $value[0]=='<' || $value[0]=='!')
                        ? ( ( $value[1]=='=' || $value[1]=='>' )
                        ? array(substr($value,0,2)=>floatval(str_replace(',', '.', substr($value, 2))))
                        : array(substr($value,0,1)=>floatval(str_replace(',', '.', substr($value, 1))))
                    )
                        : array('-enum'=>floatval(str_replace(',', '.', $value)))
                    )
                    : ( is_null($value) ? 'NULL' : floatval(str_replace(',', '.', $value)) )
                )
                : ($sign ? array('-enum'=>(string) $value) : (is_null($value) ? 'NULL' : (string) $value ) )
            )
            ;
            ($sign ? $return = array_merge_recursive($return, $parse) : $return[] = $parse);
        }, $values );
        return $return;
    }
    //-- приведение к валидной дате
    public function datetime($values, $sign=false, $quotes = true) {
        if (!is_array($values)) $values = array($values);
        $return = array();
        array_map(function($value) use ($sign, &$return) {
            $parse = $sign ? (
            ($value[0]=='>' || $value[0]=='<' || $value[0]=='!')
                ? ( ( $value[1]=='=' || $value[1]=='>' )
                ? array(substr($value,0,2)=>"'" . (addslashes(now(substr($value, 2), 'c'))) . "'")
                : array(substr($value,0,1)=>"'" . (addslashes(now(substr($value, 1), 'c'))) . "'")
            )
                : array('-enum'=>"'" . (addslashes(now($value, 'c'))) . "'")
            )
                : ( is_null($value) || $value=='NULL' ? 'NULL' : "'" . (addslashes(now($value, 'c'))) . "'" )
            ;
            ($sign ? $return = array_merge_recursive($return, $parse) : $return[] = $parse);
        }, $values );
        return $return;
    }

    public function order($field=null, $direct = null) {
        if (is_null($field)) {
            return $this->_order;
        } else {
            if (is_null($direct)) list($field, $direct) = array_merge(explode(' ', $field), array('DESC'));
            $this->_order[] = ($field . ' ' . $direct );
            return $this;
        }
    }

    /*
   save действует по следующему принципу:
   На вход подается массив.
   В значение по текущему курсору подставляются аргументы
   Если в текущем курсоре нет значения, то готовится запрос на вставку
   Если в текущем курсоре существует значение, то готовим вызов update на основе входящих параметров
   Теперь бы еще понять, как красиво извлечь идентификатор записи под курсором, и будет совсем зашибись.
    */
    public function save($data = array()) {
        //-- что тут следует возвращать? результат запроса в текущей позиции? наверное так
    }
    //-- удалить данные в текущем курсоре
    public function delete() {
        //-- что тут следует возвращать? результат запроса в текущей позиции? наверное так
    }

    /*
    public function status($status = null) {
        if (is_null($status)) {
            $this->_status = $status;
            return $this;
        } else {
            return $this->_status;
        }
    }
    */
    protected function _magic() {
        //--
    }

    public function all(){
        return $this->_data;
    }
    protected $_complete;
    public function data($watch = null) {
        if ($watch) {
            //static $return = array (), $data = array();
            //dpr ($this->_result->fetchColumn(  ));
            $this->_data = $this->_result->fetchAll(PDO::FETCH_ASSOC);
            $data = &$this->_data;

            //-- все данные, полученные из сета
            foreach (psy($this->fields()) as $field=>$attrs) {
                $this->_complete[$field] = new PSI(function () use ($field, &$data, $attrs, $watch) {
                    $index = intval((string)$watch);
                    if (isset($data[$index])) {
                        if ($filter = $attrs->filter) {
                            return call_user_func_array($filter, array($data[$index][$field], $watch));
                        } else {
                            return $data[$index][$field];
                        }
                    } else {
                        return false;
                    }
                })
                ;
            }

            return $this->_complete;
        } else {
            return $this->_data;
        }
    }


    public function from($cursor = 0) {
        $this->_cursor = $cursor;
        return $this;
    }

    public function limit($from_or_limit=null, $to = null) {
        if (is_null($from_or_limit)) {
            return ($this->_limit ? array($this->_cursor, $this->_limit) : array());
        } else {
            if (is_null($to)) {
                $this->_limit = $from_or_limit;
            } else {
                $this->_cursor = $from_or_limit;
                $this->_limit = $to;
            }
            return $this;
        }
    }

    //-- надо понять, начиная с какого места я генерирую запрос
    public function reset($cursor = 0) {
        $this->_cursor = $cursor;
        //$this->forward(0);
        return $this;
        //-- возвращает указатель на начало
    }

    public function cursor($cursor = null) {
        if (is_null($cursor)) {
            return $this->_cursor;
        } else {
            $this->_cursor = ($cursor>0 && $cursor<=$this->_count ? $cursor : $this->_cursor); //-- присваиваем новое значение, или остаемся на месте
            return $this;
        }
    }

    public function forward($limit = -1) { //-- -1 eq all
        static $active=false, $iteration=0;
        if (!$active && !$iteration) { //-- если не заданы активность и итерации, то задаем количество шагов
            $iteration = $limit;
            $active = true;
        }
        if ($iteration && ($this->_cursor < $this->_count)) {
            $iteration-- ; //-- уменьшим итерацию на единицу
            return call_user_func_array($this(), array($this->_cursor++));
        } else {
            $iteration = 0;
            $active = false;
            return $active;
        }
        //-- если лимита нет, то считается по внутреннему


    }


    public function backward($limit = null) {
        //-- если лимита нет, то считается по внутреннему
    }

    public function source() {
        return $this->_source;
    }

    public function fields($filter = null) {
        //-- если тут стоят фильтры, то перегрузим ими текущие вызовы
        if (!$this->_fields) { $this->_fields =  $this->_psi->fields($this->_source, $this); }
        if ($filter) {
            switch (true) {
                case is_callable($filter):
                    call_user_func_array($filter, array($this->_fields));
                    break;
                case is_string($filter):

                    //call_user_func_array($filter, array($this->_psi->fields($this->_source, $this)));
                    break;
            }
            return $this;
        } else{
            return $this->_fields;
        }
    }

    public function status() {
        return $this->_psi->status($this->_source, $this);
    }

    //-- возвращает запрос, который был/есть будет сгенерирован для требуемого состояния оболочки (по умолчанию: выборка)
    public function query() {
        return $this->_psi->query($this, null, null);
    }



    /* __call будет работать по следующему принципу:
    Если он не задан, то возвращает текущее значение имени (если таковое есть в аттрибутах)
    Если он задан, то выполняет код, в который подставляется текущее значение и текущая оболочка (для доступа к другим атомам)
    */
    public function __call($name, $args) {
       return parent::__call($name, $args);
    }
    /*
    __get будет работать по следующему принципу
    Если задана функция по текущему параметру, то возвращется она
    */
    public function &__get($name) {
            return parent::__get($name);
    }
    /*
    __set будет работать по принципу присваивания PSI от аргумента для текущего фильтра
     */
    public function __set($name, $value) {
            parent::__set($name, $value);
    }

    //-- текущий PDO для строки, можно извлечь значение в нужном формате, например ->pdo()->fetch()
    /**
     * @return PSI_DB
     */
    public function db($query = null) {
        return $this->_db->db($query);
    }

    public function __toString() {
        //-- возврашает то, что считается названием оболочки (идентификатор)
        return $this->_proxy ? (string) call_user_func_array($this->_proxy, array($this)) : $this->_source;
    }



    public function sql($query=null) {
        if ($query) {
            return null;
        } else {
            return $this->_query;
        }
    }
}
//-- класс PSI_Watch делается по аналогии с PSI или PSI_Watch, только для своей обработки он использует
//-- возможность обратиться к параметрам оболочки при вызове собственных перезрузок
class PSI_Watch extends PSI {
    protected
        $_shell
    ,   $_result
    ,   $_cursor = 0
    ;

    public function __construct(PDOStatement $Result, PSI_Shell $Shell) {
        /*
        Вот тут на самом деле надо сделать очень хитрый ход.
        Нужно создать кванты на основе того, какие поля есть в шелле. Доступ к каждому кванту будет определен возвратом по текущей оболочке
        */
        $this->_result = $Result;
        $this->_shell = $Shell;
        $this->_quants = $this->_shell->data($this);
    }



    public function __invoke() {
        //-- собственный вызов с аргументами будет
        //-- 2. Сохранять значения в атоме
        //-- 3. Принять коллбек на саму себя, обработать его и вернуть результат
        //-- без аргументов: вернуть собственное значение как массив (по сути срезать кванты) (или все же вернуть шелл!!?)
        if (count($arguments = func_get_args())>0) {
            $data = array_shift($arguments);
            switch(true) {
                case $data===$this: //--
                    return $this->_shell;
                case is_array($data): //-- внести новые данные в "окно"
                    //-- тут что-то типа импорта
                    break;
                case is_object($data): //-- сюда якобы можно добавить данных? Или вызвать какое-то наследие?
                    return $this;
                    break;
                case (is_numeric($data) && $data>=0) : //-- тогда можно вернуть значение по индексу (с переходом или возвратом в это значение?)
                    $this->_cursor = $data;
                    return $this;
                    break;
            }
            return $this;
        } else {
            return parent::__invoke(); //-- тут возврат квантов
        }
    }

    public function __toString() {
        //-- возврашает то, что считается позицией текущего индекса (курсор находится внутри watch)
        return (string) $this->_cursor;

    }


//    public function __call($name, $args) {
//        //return $this->_q
//    }
//
//    public function __set($name, $value) {
//        //-- работает по старому принципу и заполняет значение в текущем курсоре
//    }
//
//    public function __get($name) {
//        //-- работает по старому принципу и возвращает непосредственное значение в текущем курсоре
//        //-- и да, там можно подставить атом и он выполнится :)
//    }
}
class PSI_DBAttrs extends PSI_Core {

    //protected $_attrs = array();

//    protected $_name, $_type, $_default, $_size, $_read, $_write;

    public function __call($function, $arguments) {
        if (!isset($this->_quants[$function])) {
            $this->{$function} = new PSI(function () use ($function) {
                list($psi, $args) = tail(func_get_args(), null);
                list($attrs, $arguments) = tail($args, null, null);
                if ($arguments && is_callable($arguments)) {
                    $psi->filter = $arguments;
                    return $attrs;
                }
                return $psi;
            });

            list($type, $size, $default, $comment) = args($arguments, PSI_DB::_PARAM_STR_, 0, '', '');
            $this->{$function}
                ->type($type)
                ->size($size)
                ->default($default)
                ->comment($comment)
            ;
//
//            function() use ($arguments) {
//                static $attrs;
//                list($psi, $params) = tail(func_get_args(), null, null);
//                if (!isset($attrs)) {
//                    $attrs = null;
//                }
//            };
//            $this->{$function} = function() use ($arguments) {
//                static $attrs;
//                list($psi, $params) = tail(func_get_args(), null, null);
//                if (!isset($attrs)) {
//                    $attrs = new PSI(function (PSI $Psi) { return $Psi->_quants; });
//                    list($type, $size, $default, $comment) = args($arguments, PSI_DB::_PARAM_STR_, 0, '', '');
//                    $attrs
//                        ->type($type)
//                        ->size($size)
//                        ->default($default)
//                        ->comment($comment)
//                        ;
//                }
//                /*
//                if (isset($params)) {
//                    is_callable()
//                } else {
//                    return $attrs;
//                }
//                */
//            };

            return $this;
        } else {
            return parent::__call($function, $arguments);
            //return call_user_func_array($this->{$function}, array_merge($arguments, array($this)));
        }
    }

}

/*
 Query по идее должен собираться по хитрому. Это будет что-то вроде
 $query = new PSI (function($prefix, PSI $Psi) {
    pr ($_quants
    return 'SELECT * FROM ' . $prefix;

})
 */

class PSI_DB extends PSI_Core {
    const _TYPE_SQL_ = 1;
    const _TYPE_TABLE_ = 2;

//    const _PARAM_INT_ = PDO::PARAM_INT;
//    const _PARAM_STR_ = PDO::PARAM_STR;
//    const _PARAM_BOOL_ = PDO::PARAM_BOOL;
//    const _PARAM_DATETIME_ = 'datetime';
//    const _PARAM_FLOAT_ = 'float';
    const _PARAM_INT_ = 'integer';
    const _PARAM_STR_ = 'string';
    const _PARAM_BOOL_ = 'boolean';
    const _PARAM_DATETIME_ = 'datetime';
    const _PARAM_FLOAT_ = 'float';
    /**
     * @var PDO
     */
    protected $_pdo, $_tables = array();

    /**
     * Выполнить запрос или вернуть указатель на текущий PDO
     * @param null|string $query
     * @return PDO|PDOStatement
     */
    public function db($query = null, $pdo = false) {
        if (!is_null($query)) {
            $result = $this->_pdo->prepare($query, array(PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL));
            $result->execute();
            return $pdo ? $this : $result;
        } else {
            return $this->_pdo;
        }
    }

    public function shell($query = '') {
        //-- тут будем создавать шелл на основе запроса, и это будет ахуеть как круто!
    }
    /**
     * @param string $db
     * @param string $server
     * @param string $user
     * @param string $pass
     * @return PSI_DB
     */
    public function mysql($db = 'mysql', $server='localhost:3306', $user='mysql', $pass='mysql' ) {
        try {
            $this->_pdo = new PDO('mysql:dbname=' . $db  . ';server=' . $server .';', $user, $pass);
        } catch( PDOException $Exception ) {
            die ($Exception->getMessage());
        }

        if ($this->_pdo) {
            /*
            что мне нужно сделать сейчас?
            1. Нужно подготовить структуру, и сделать доступным методы для каждой таблицы, для этого выгребаем составные из БД
            */

            //-- на самом деле мне следует сделать что-то типа ремакроса, который при первичном вызове одной из функций подвесит для нее

//            $this->or(function() {  pr('oooor'); });
//            $this->and(function() {  pr('aaaannnd!'); });
//            $this->or();
//            $this->and();

            foreach ($this->_pdo->query('SHOW TABLES')->fetchAll() as $k=>$v) {
                //-- на самом деле следует добавлять их чуть позже, ну да ладно
                $this->_tables[array_pop($v)] = array('_filters'=>array(
                    'status'=>function($table, PSI_Shell $Shell) {
                        static $status = null;
                        if (!$status) {
                            $res = $Shell->db('SHOW TABLE STATUS LIKE \'' . $table .'\';')->fetchObject();
                            $status = new PSI();
                            $status
                                ->count($res->Rows)
                                ->comment($res->Comment)
                                ->create($res->Create_time)
                                ->update($res->Update_time)
                                ;
                        }
                        return $status;
                    },
                    'fields'=>function($table, PSI_Shell $Shell) {
                        static $_gettype;
                        if (!$_gettype) { //-- инициализируем функцию для определения типов
                            $_gettype = function ($type, $length=0) {
                                switch ($type) {
                                    case 'int': case 'smallint': case 'mediumint': case 'bigint': return PSI_DB::_PARAM_INT_;
                                    case 'float': case 'double': case 'real': return PSI_DB::_PARAM_FLOAT_;
                                    case 'tinyint': return $length>1 ? self::_PARAM_INT_ : PSI_DB::_PARAM_BOOL_;
                                    case 'varbinary': case 'varchar': case 'char': return PSI_DB::_PARAM_STR_;
                                    case 'text':case 'tinytext': case 'longtext': case 'mediumtext': return PSI_DB::_PARAM_STR_;
                                    case 'datetime': case 'date': case 'time': case 'year': return PSI_DB::_PARAM_DATETIME_;
                                }
                                return $type; } ;
                        }
                        //--- сохраняем структуру
                        //if (!isset($fields[$table])) {
                            $fields = new PSI_DBAttrs(function(PSI_DBAttrs $Attrs) {return $Attrs;});

                            foreach ($Shell->db('SHOW FULL COLUMNS FROM `' . $table .'`;')->fetchAll(PDO::FETCH_ASSOC) as $value) {
                                //-- вот это магическая строка, я ее не рискую даже разбирать :-) Пусть останется такой
                                list ($type, $length) = array(strtolower(substr($value['Type'], 0, ($tmp_pos = strpos( $value['Type'], '(')) ? $tmp_pos : strlen($value['Type']))), ($tmp_pos ? intval(substr($value['Type'], $tmp_pos+1)) : null));

                                $fields->{$value['Field']}(
                                    $_type = call_user_func_array($_gettype, array($type, $length)),
                                    (($_type!='datetime')
                                        ? $length
                                        : (($type=='date')
                                            ? 10
                                            : ($type=='year' ? 4
                                                : ( $type=='time' ? 8 : 32 ))
                                        )
                                    ),
                                    $value['Default'],
                                    $value['Comment']
                                );
                            }
                        //}
                        return $fields;
                        //return $Shell->db();
                        //return new PSI_Shell($db->db('SHOW TABLE STATUS LIKE \'' . $table .'\';'), $db);
                    },
                    'query' => function (PSI_Shell $Shell, $new = array(), $current = array()) {
                        //-- будет генерировать запрос в контексте правильности порядка полей: фильтрация, экранирование и все-такие прочее :-)
                        //-- по большому счету просто перенесу функции из предыдущего шелла

                        //-- в зависимости от того, что пришло в $src
                        //-- nul


                        $generator = function($params = array(), $fields, $foo,  $prefix = '', $empty = true, $delimetr = 'AND') use ($Shell) {
                            $return = array(); if (!is_array($fields)) $fields = $fields();
                            if (!empty($params) && is_array($params)) {
                                foreach ($params as $field=>$values) {
                                    if (isset($fields[$field]) && $props = $fields[$field]) {
                                        $field = '`' . $field . '`';
                                        //-- тут погружение в окружение
                                        if (is_array($values)) {
                                            foreach ($values as $key=>$value) {
                                                switch ($key) {
                                                    case '-enum': //-- список или значение
                                                        //-- если тут стоит конкретное значение, то возьмем его
                                                        $return[] =
                                                            $field .
                                                                ( is_array($value)
                                                                    ? ' IN ('. (implode(', ', $value)) . ')'
                                                                    : ' = ' . $value )
                                                        ;
                                                        break;
                                                    case '>': case '<': case '>=': case '=<': case '<>': case '!=': //-- символы
                                                    if (is_array($value)) { //-- если на входе массив, то проставим каждую пендюрку
                                                        foreach ($value as $v) {
                                                            $return[] = ($field . $key . $v);
                                                        }
                                                    } else {
                                                        $return[] = ($field . $key . $value);
                                                    }
                                                    break;
                                                    case '-between': //-- интервалы
                                                        foreach ($value as $v) {
                                                            $return[] = '( ' . ($field . ' BETWEEN ' . min($v) . ' AND ' . max($v)) . ' )';
                                                        }
                                                        break;
                                                    case '~':   //-- обработка для LIKE
                                                        if (is_array($value)) { //-- если на входе массив, то проставим каждую пендюрку
                                                            foreach ($value as $v) {
                                                                $return[] = '( ' . ($field . ' LIKE ' . $v ) . ' )';
                                                            }
                                                        } else {
                                                            $return[] = '( ' . ($field . ' LIKE ' . $value ) . ' )';
                                                        }
                                                        break;
                                                }
                                            }
                                        } else {
                                            $return[] = ($field . '=' . array_shift($Shell->{$props->type()}($values)));
                                        }

                                    } else {
                                        list($slice) = explode('-', $field);
                                        if ($slice=='AND' || $slice=='OR') {
                                            $return[] = '( ' . ($slice =='AND' ? ' TRUE ' : ' FALSE ') . ($foo($values, $fields, $foo, $slice)) . ' ) ';
                                        } else {
                                            //-- ничего не делать
                                        }

                                    }
                                }
                            }
                            return ($return ? $prefix . ($empty ? ' ( ' : '') . implode(' '. $delimetr . ' ', $return ) . ($empty ? ' ) ' : '') : (intval(!$empty)));
                        };
                        switch (true) {
                            case (empty($new) && empty($current)): //-- запрос на выборку
                                $where = call_user_func_array($generator, array($Shell->param(), $Shell->fields(), $generator,  '', true, $Shell->delimetr()));
                                return
                                    ('SELECT * FROM '
                                    . '`'. $Shell->source() . '`'
                                    . ($where ? ' WHERE ' . $where : '')
                                    . (($order = $Shell->order()) ? ' ORDER BY ' . (implode(', ', $order)) : '')
                                    . (($limit = $Shell->limit()) ? ' LIMIT ' . (implode(', ', $limit)) : '')
                                );
                                break;
                            case !empty($new) && empty($current): //-- запрос на вставку
                                $new = call_user_func_array($generator, array($new, $Shell->fields(), $generator, '', false, ', '));
                                return
                                    'INSERT INTO ' . '`'. $Shell->source() . '`'
                                    . ' SET ' . $new
                                    ;
                                break;
                            case !empty($new) && !empty($current): //-- запрос на обновление
                                $where = call_user_func_array($generator, array($current, $Shell->fields(), $generator));
                                $new = call_user_func_array($generator, array($new, $Shell->fields(), $generator, '', false, ', '));
                                return
                                    'UPDATE ' . '`'. $Shell->source() . '`'
                                    . ' SET ' . $new
                                    . ' WHERE ' . $where;
                                break;
                            case empty($new) && !empty($current): //-- запрос на удаление
                                $where = call_user_func_array($generator, array($current, $Shell->fields(), $generator));
                                return
                                    'DELETE FROM  ' . '`'. $Shell->source() . '`'
                                    . ' WHERE ' . $where;
                                break;
                            default:

                                break;
                        }
                        return null;
                    }
                ), '__call'=>null);
            }
        } else {
            die ('cannot connect to database');
        }
        return $this;
    }


    public function __set($name, $value) {

    }

    //-- генератор оболочки, вынес в отдельный метод, может пригодиться
    protected function _shell($name, $arguments) {
        if (empty($this->_tables[$name]['__call'])) { //-- и для еще него не определен вызов
            $filters = $this->_tables[$name]['_filters'];
            $this->_tables[$name]['__call'] = //-- вызов в текущей месте - это функция
                function () use ($name, $filters) {
                    //pr (func_get_args());
                    $shell = new PSI_Shell($name, array_pop(func_get_args()), PSI_DB::_TYPE_TABLE_,
                        new PSI( function (PSI $Psi) use ($filters) {
                            static $action = false;
                            if (!$action) {
                                $action = true;
                                foreach ($filters as $filterName=>$filterFunction) { //-- обвесим ее фильтрами
                                    // call_user_func_array($filterFunction, array($name, $shell));
                                    $Psi->{$filterName} = $filterFunction;
                                }
                            }
                            return $Psi;
                        })
                    ); //-- создадим собственную оболочку по таблице
                    //$shell->complete();
                    //-- пожалуй тут следует добавлять для шелла собственный вызов status, attrs и иже с ними
                    //-- однозначно да
                    $args = func_get_args();
                    return
                        !empty($args[0]) && is_callable($args[0])
                        ? call_user_func_array($args[0], array($shell))
                        : call_user_func_array($shell, $args)
                        ;
                };
        }
        return call_user_func_array($this->_tables[$name]['__call'], push($arguments, $this));
    }

    //-- изменим функционал _call для PSI_DB, ограничив его работой только с имеющимися таблицами
    public function __call($name, $arguments=array(null)) {
        if (isset($this->_tables[$name])) { //-- если имеющийся запрос есть в списке таблиц
            return $this->_shell($name, $arguments);
        } else {
            return parent::__call($name, $arguments);
            //return null; //-- + ошибка, что нельзя выполнить запрос (или же перевести его в разряд квантуемых?)
        }
    }
}

/*
 Теперь немного о том, как будут выглядеть вызовы классов для оболочек.

 PSI::mysql()->table(function (PSI_Shell $shell) {
        return $shell
                ->param('id', 5)
                ->defaults('name', 'ololo')
        ;
    })->id;

 PSI::mysql()->table(function (PSI_Shell $shell) {
        return $shell
                ->param('id', 5)
                ->defaults('name', 'ololo')
        ;
    })->import(  )->save( function (PSI_Shell $shell ) {
        $shell->id(); $shell->value();
        $shell()->table();
    }  );

*/

return function ($procedure) {
    return call_user_func_array($procedure, array(new PSI_DB()));
}

?>