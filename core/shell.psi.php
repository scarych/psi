<?php

class PSI_Shell extends PSI_Core {
    protected
        $_query = ''
        , $_params, $_all_params = array()
        , $_deep = 0
        , $_slices = array()
        , $_status = array()
        , $_source = null
        , $_readonly = true
        , $_db = null
        , $_limit = null
        , $_active = false
        , $_fields = '*'
        , $_cursor = 0 //-- пока так
        , $_count = 0 //-- пока так
        , $_complete = 0 // пока так
        , $_procedures = array() //-- процедуры, которые будут фильтровать текущее значение, устанавливает правила обработки всех прочих значений
        , $_ = null
    ;

    public function __construct($source, PSI_DB $Db, $type = PSI_DB::_TYPE_SQL_, PSI $Psi) {

        $this->_source = $source;
        $this->_db = &$Db;
        $this->_readonly = ($type===PSI_DB::_TYPE_SQL_); //-- закроем от записи то, что сделано запросом
        $this->_params = &$this->_all_params; //-- зациклим параметры
        $this->_psi = $Psi($Psi);

        $this
            ->or(
            function() {
                list($shell, $args) = tail(func_get_args(), null, null);
                return $shell->deep('OR')->param($args)->deep();
            })
            ->and(
            function() {
                list($shell, $args) = tail(func_get_args(), null, null);
                return $shell->deep('AND')->param($args)->deep();
            })
        ;
    }

    public function __invoke() {
        pr ($this->_psi);
        dpr ($this->_active);
        if (count($args = func_get_args())) {
            return $this->_psi;
        } else {

        }
        return 'invoke ok';
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
         При вызове через (ID) собирается те, что в params(), и возвращается PSI_Atom
         При вызове через (array(...)) пытается собрать данные в кучу для записи, и возвращает PSI_Atom с подготовленными значениями, для ограничения выборки использует param(), для ограничения записи использует $shell()->fields()
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

    protected function _slice(&$params) {
        $slices = &$this->_slices;
        $slices[] = &$params;
        return $this;
    }

    public function deep($open=0) {
        $this->_deep += ($open ? 1 : 0); //-- сменим "глубину"
        if ($open) {
            $bkt_key = '-' . ($open=='OR' ? 'OR' : 'AND') .  '-' . $this->_deep;
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
                if (is_closure($fields)) { //-- установка через замыкание
                    //-- просто выполнить ее, применив туда текущую оболочку
                    call_user_func_array($fields, array($this)); //-- там внутри все распишется
                } else {
                    if (is_array($fields)) { //-- установка из массива
                        foreach ($fields as $k=>$v) {
                            if (is_numeric($k)) {
                                $this->param($v);
                            } else {
                                $this->param($k, $v);
                            }
                        }
                    } else { //-- установка по ключу
                        //-- тут поиск ключа и установка
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
/*
     protected function _param ($params, $values) {
        if (!is_array($params)) list ($params, $values) = array(array($params), array($values));
        foreach ($params as $index=>$param) {
            if (isset($this->_attrs['struct'][$param])) {
                $type = $this->_attrs['struct'][$param]['type'];
                $value = self::$type(is_array($val = $values[$index]) ? $val : array($val), true);
                if (!isset($this->_params[$param])) {
                    $this->_params[$param] = $value;
                } else {
                    $this->_params[$param] = array_merge_recursive($this->_params[$param], $value);
                }
            }
        }
        return $this;
    }
 */
    protected function _param($field, $value) {
        pr ($field, $value);
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

    //-- удалить данные в текущем курсоре
    public function complete($do = 1) {
        $this->_complete = $do;
        return $this;
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

    public function each($function) {
        return $this;
    }

    public function from($start = 0) {

    }

    //-- надо понять, начиная с какого места я генерирую запрос

    public function reset() {
        //-- возвращает указатель на начало
    }

    public function forward($limit = null) {
        //-- если лимита нет, то считается по внутреннему

    }

    public function backward($limit = null) {
        //-- если лимита нет, то считается по внутреннему
    }


    public function fields($filter = null) {
        //-- если тут стоят фильтры, то перегрузим ими текущие вызовы
        if ($filter) {
            return $this;
        } else{
            return $this->_psi->fields($this->_source, $this);
        }
    }

    public function status() {
        return $this->_psi->status($this->_source, $this);
    }

    public function query() {
        return $this->_psi->query($this->_source, $this);
    }


    public function limit($limit = null) {
        if (empty($limit)) {
            return $this->_limit;
        } else {
            $this->_limit = intval($limit);
            return $this;
        }
    }

    /* __call будет работать по следующему принципу:
    Если он не задан, то возвращает текущее значение имени (если таковое есть в аттрибутах)
    Если он задан, то выполняет код, в который подставляется текущее значение и текущая оболочка (для доступа к другим атомам)
    */
    public function __call($name, $args) {
        if (!$this->_complete) {
            return parent::__call($name, $args);
        } else {
            return $this;
        }
    }
    /*
    __get будет работать по следующему принципу
    Если задана функция по текущему параметру, то возвращется она
    */
    public function __get($name) {
        if (!$this->_complete) {
            return parent::__get($name);
        } else {
            return null;
        }
    }
    /*
    __set будет работать по принципу присваивания PSI от аргумента для текущего фильтра
     */
    public function __set($name, $value) {
        if (!$this->_complete) {
            parent::__set($name, $value);
        } else {

        }
    }


    public function active() {
        $this->_active = true;
        return $this;
    }

    //-- текущий PDO для строки, можно извлечь значение в нужном формате, например ->pdo()->fetch()
    /**
     * @return PSI_DB
     */
    public function db($query = null) {
        return $this->_db->db($query);
    }

    public function sql($query=null) {
        if ($query) {
            return null;
        } else {
            return $this->_query;
        }
    }
}
//-- класс PSI_Atom делается по аналогии с PSI или PSI_Watch, только для своей обработки он использует
//-- возможность обратиться к параметрам оболочки при вызове собственных перезрузок
class PSI_Atom extends PSI {
    protected $_shell;

    public function __invoke() {
        //-- собственный вызов с аргументами будет
        //-- 2. Сохранять значения в атоме
        //-- 3. Принять коллбек на саму себя, обработать его и вернуть результат
        //-- без аргументов: вернуть собственное значение как массив (по сути срезать кванты) (или все же вернуть шелл!!?)

    }


    public function __toString() {
        //-- возврашает то, что считается ключом для
    }

    public function __call($name, $args) {
        //-- отличие __call состоит в том, что через родительский шелл можно забрать
    }

    public function __set($name, $value) {
        //-- работает по старому принципу и заполняет значение в текущем курсоре
    }

    public function __get($name) {
        //-- работает по старому принципу и возвращает непосредственное значение в текущем курсоре
        //-- и да, там можно подставить атом и он выполнится :)
    }
}
class PSI_DBAttrs extends PSI_Core {

    //protected $_attrs = array();

//    protected $_name, $_type, $_default, $_size, $_read, $_write;

    public function __call($function, $arguments) {
        if (!isset($this->_quants[$function])) {
            $this->{$function} = function() use ($arguments) {
                static $attrs;
                if (!isset($attrs)) {
                    $attrs = new PSI(function (PSI $Psi) { return $Psi->_quants; });
                    list($type, $size, $default, $comment)=args($arguments, PSI_DB::_PARAM_STR_, 0, '', '');
                    $attrs
                        ->type($type)
                        ->size($size)
                        ->default($default)
                        ->comment($comment)
                        ;
                }
                return $attrs;
            };
            return $this;
        } else {
            return call_user_func_array($this->{$function}, array_merge($arguments, array($this)));
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

    const _PARAM_INT_ = PDO::PARAM_INT;
    const _PARAM_STR_ = PDO::PARAM_STR;
    const _PARAM_BOOL_ = PDO::PARAM_BOOL;
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
            $result = $this->_pdo->query($query);
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
        $this->_pdo = new PDO('mysql:dbname=' . $db  . ';server=' . $server .';', $user, $pass);

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
                        static $fields, $_gettype;
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
                        if (!isset($fields)) {
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
                        }
                        return $fields;
                        //return $Shell->db();
                        //return new PSI_Shell($db->db('SHOW TABLE STATUS LIKE \'' . $table .'\';'), $db);
                    },
                    'query' => function ($type=null, PSI_Shell $Shell) {
                        //-- будет генерировать запрос в контексте правильности порядка полей: фильтрация, экранирование и все-такие прочее :-)
                        //-- по большому счету просто перенесу функции из предыдущего шелла
                        switch ($type) {
                            case 'select':
                                break;
                            case 'insert':
                                break;
                            case 'update':
                                break;
                            case 'delete':
                                break;
                            default:
                                break;
                        }
                        return null;
                    }
                ), '__call'=>null);
            }
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
                    return !empty($args[0]) && is_callable($args[0])
                        ? call_user_func_array($args[0], array($shell->active()))
                        : call_user_func_array($shell->complete(), $args)
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
    return call_user_func_array($procedure, array());
}

?>