<?php

namespace gspanarin\irbis64;

class irbis64_provider { 
    
       
    public $ip;
    public $port;
    public $sock = null;
    public $login;
    public $pass;
    public $id = 0; 
    public $seq = 1;
    public $arm;

    
    public function __construct($ip = '127.0.0.1', $port = 6666, $login = '1', $pass = '1', $arm = 'C') {
        $this->id = rand(100000, 999999);
        
        $this->ip = $ip;
        $this->port = $port;
        $this->login = $login;
        $this->pass = $pass;
        $this->arm = $arm;    
    }
    
    public function __destruct() { 
        $this->logout(); 
    }

    





//Формат для запроса, возвращающего запись
   public $fields_pft = "&uf('+0')"; 
   //Реестр загруженных ресурсов (типа кеша)
   public $registry_input_file = array();
   //Выводить отладочную информацию по обмену данными с сервером
   public $print_message = false;
   private  $dict = array(
      'normalized_name'       => 'NN=',   //Нормированное имя автор
      'author'                => 'A=',    //Автор
      'title'                 => 'T=',    //Заглавие
      'publishing_house'      => 'O=',    //Издательство
      'place_of_publication'  => 'MI=',   //Место издания
      'year'                  => 'G=',    //Издано не ранее
      'isbn'                  => 'B=',    //ИСБН
      'keywords'              => 'K=',    //Ключевые слова
      'simple'                => 'DS=',   //Простой поиск
      'language'              => 'J=',    //Язык
      'guid'                  => 'GUID=', //GUID
      'inv'                   => 'IN=',   //Инвентарынй номер, штрих-код, RFID
      );
   public $lang = array();
   
   public $db = 'IBIS'; // 
   public $server_timeout = 30;
   public $server_ver     = '';
   public $error_code = 0;
   public $error_message = '';
   public $user_list;

   public $ini = array();


   


   
   public function debug(){
       $this->print_message = true;
   }

   function error($code = '') {
      if ($code == '') $code = $this->error_code;
      
      switch ($code) {
      case '0': return 'Ошибки нет';
      case '1': return 'Подключение к серверу не удалось';
      case '-2222': return 'Ошибка протокола. Возможно не соответствие версий сервера и клиента(WRONG_PROTOCOL)'; 
      case '-3333': return 'Пользователь не существует'; 
      case '-3337': return 'Пользователь уже зарегистрирован'; 
      case '-4444': return 'Пароль не подходит'; 
      case '-140':  return 'MFN за пределами базы'; 
      case '-5555': return 'База не существует'; 
      case '-400': 	return 'Ошибка при открытии файла mst или xrf'; 
      case '-603': 	return 'Запись логически удалена'; 
      case '-601': 	return 'Запись удалена'; 
      case '-202': 	return 'Термин не существует'; 
      case '-203': 	return 'TERM_LAST_IN_LIST'; 
      case '-204': 	return 'TERM_FIRST_IN_LIST'; 
      case '-608': 	return 'Не совпадает номер версии у сохраняемой записи'; 
      }
      return 'Неизвестная ошибка: ' . $code;
   }


   function connect() {
      $this->sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
      if ($this->sock === false){
         $this->error = socket_last_error();
         return false;
      } 
      return (@socket_connect($this->sock, $this->host, $this->port));
   }


   // Регистрация на сервере Ирбиса
   function login() {
      /*if ($this->arm=''){
         $this->error = 'Не указан тип АРМа';
         return false;
      }
      if ($this->login=''){
         $this->error = 'Не указан логин';
         return false;
      }
      if ($this->pass=''){
         $this->error = 'Не указан пароль';
         return false;
      }*/
    //$this->id = $this->set_id(rand(100000, 999999));
      
      $packet = implode("\n", array('A', $this->arm, 'A', $this->id, $this->seq, '', '', '', '', '', $this->login, $this->pass));
      $packet = strlen($packet)."\n".$packet;
      $answer = $this->send($packet);
      
      if ($answer === false) {
         $this->error_code = 1;
         return false;
      }
      
      $this->error_code = $answer[10];
      if ($this->error_code != 0) return false;
      $this->server_timeout = $answer[11];
      $this->server_ver     = $answer[4];
      
      $this->ini = array();
      $section='';
      for ($i=12; $i<count($answer);$i++){
         if (substr(trim($answer[$i]),0,1)=='#'){}
         elseif (substr(trim($answer[$i]),0,1)=='['){
            $section = mb_strtolower(substr(trim($answer[$i]),1,strlen(trim($answer[$i]))-2));
         }
         else{
            $tmp = explode('=',trim($answer[$i])); 
            if (!array_key_exists(1,$tmp)) $tmp[1] = '';
            $this->ini[$section][mb_strtolower($tmp[0])] = $tmp[1];
         }
      }
      return true;
   }


   /** 
   * Разрегистрация
   **/
   function logout() {
      $packet = implode("\n", array('B', $this->arm, 'B', $this->id, $this->seq, $this->login, $this->pass, '', '', '', '', ''));
      $packet = strlen($packet) . "\n" . $packet;
      $answer = $this->send($packet);
      
      if ($answer === false) return false;
      $this->error_code = $answer[10];
      if ($this->error_code != 0) return false;
      return true;
   }


   /** 
   * Установка параметров в ини-файле
   **/
   function Set_ini_param($section, $parameter){
      $packet = implode("\n", array('8', $this->arm, '8', $this->id, $this->seq, $this->pass, $this->login, '', '', '', $section, $parameter));
      $packet = strlen($packet) . "\n" . $packet;
      $answer = $this->send($packet);
      
      if ($answer === false) return false;
      $this->error_code = $answer[10];
      if ($this->error_code != 0) return false;
      return true;
   }
   
   
   /**
   *    Получить максимальный MFN в базе
   **/
   function mfn_max($db_name) {
      $packet = implode("\n", array('O', $this->arm, 'O', $this->id, $this->seq, '', '', '', '', '', $db_name));
      $packet = strlen($packet) . "\n" . $packet;
      $answer = $this->send($packet);
      
      if ($answer === false) return false;
      $this->error_code = $answer[10];
      
      if ($this->error_code > 0) {
         $this->error_code = 0;
         return $answer[10];
      } else {
         return false;
      }
   }


    /**
    * получение терминов словаря (индексов)
    **/
    function terms_read($db_name, $key, $term, $num_terms = 10, $format = '') {
        if ($db_name=='') {
            $this->error_code = '-11111';
            $this->error_message = 'Чтение словаря не будет выполнено. Не передано название базы';
        }
      
        $packet = implode("\n", array('H', $this->arm, 'H', $this->id, $this->seq, '', '', '', '', '', $db_name, $key.$term, $num_terms, $format));
        $packet = strlen($packet) . "\n" . $packet;
        $answer = $this->send($packet);
         
        if ($answer === false) 
            return false;
        
        $this->error_code = $answer[10];
        
        if (($this->error_code == 0) or ($this->error_code == -202) or ($this->error_code == -203) or ($this->error_code == -204)) {
            // массив $terms
            $terms = [];
            $c = count($answer) - 1;
            for ($i = 11; $i < $c; $i++) {
                $tmp = explode('#',$answer[$i]);
                if (mb_substr($tmp[1], 0, strlen($key))==$key){
                    $tmp[1] = mb_substr($tmp[1],strlen($key));
                    $terms[] = ['term' => $tmp[1], 'count' => $tmp[0]];
                }
            }
            return $terms;
        } else {
            return false;
        }
   }



    // Прочитать запись
    function record_read($db_name, $mfn, $lock = false) {
        if ($mfn=='') {
           $this->error_code = '-11111';
           $this->error_message = 'Чтение не будет выполнено. Не передан MFN записи';
        }
        if ($db_name=='') {
           $this->error_code = '-11111';
           $this->error_message = 'Чтение не будет выполнено. Не передано название базы';
        }
      
        $packet = implode("\n", array('C', $this->arm, 'C', $this->id, $this->seq, '', '', '', '', '', $db_name, $mfn, $lock ? 1 : 0));
        $packet = strlen($packet) . "\n" . $packet;
        $answer = $this->send($packet);

        if ($answer === false) return false;
        $this->error_code = $answer[10];
        if ($this->error_code != 0) return false;

        $mfn_status  = explode('#', $answer[11]);
        $rec_version = explode('#', $answer[12]);

        $record = new irbis64_record;

        $record->mfn = $mfn_status[0];
        $record->status = (isset($mfn_status[1]) && $mfn_status[1] != '') ? $mfn_status[1] : 0;
        $record->ver = isset($rec_version[1]) ? $rec_version[1] : 0;

        $c = count($answer) - 1;
        for ($i = 13; $i < $c; $i++) {
            
            preg_match("/(\d+?)#(.*?)/U", $answer[$i], $matches);
            $field_num = (int)$matches[1];
            $field_val = $matches[2];

            if ($field_num != '') {
                if (mb_substr($field_val,0,1) != '^'){
                    $field_val = '^'.chr(29).$field_val;
                } 
                $prefield = explode('^', $field_val);
                $prefields = [];
                foreach ($prefield as $val){
                    if (mb_substr($val,1)!=''){
                       $prefields[mb_substr($val,0,1)] = mb_substr($val,1);
                    }
                }
                $record->fields[$field_num][] = $prefields;
            }       
        }
        return $record;
    }












    // Сохранить запись
    function record_write($db_name, &$record, $lock = false, $if_update = true) { // $lock  = блокировать запись , $if_update = актуализировать запись
        
        if (count($record->fields) == 0) {
            $this->error_code = '-11111';
            $this->error_message = 'Сохранение не будет выполнено. Запись пустая';
        }
        if ($db_name == '') {
            $this->error_code = '-11111';
            $this->error_message = 'Сохранение не будет выполнено. Не передано название базы';
        }
        //Проверить, есть ли у нас БН-фалк в текущем ини 
        if (array_key_exists('entry', $this->ini)) { 
            /*Получить название файл ДБНФЛК*/
            if ($this->ini['entry']['dbnflc'] == '') {
                $this->ini['entry']['dbnflc'] = 'dbnflc';
            }

            //Проверить запись по формату ФЛК
            /*$flc = $record->virtual_recording_format($db_name, $this, '@'.$this->ini['entry']['dbnflc']) ;  
            $flc = explode("\n",$flc);

            //Обработать результаты ФЛК
            foreach($flc as $value){
                if (mb_substr($value,0,1) != '0'){
                    $this->error_code = '-11111';
                    $this->error_message = mb_substr($value,1);
                    return false;
                } 
            }*/
        }

        $records = "";
        $records .= $record->mfn.'#'.$record->status;
        $records .= chr(31).chr(30);
        $records .= '0#'.$record->ver;
        $records .= chr(31).chr(30);

        /*Массив по всем полям*/
        foreach ($record->fields as $tag => $fieldsvalue){
            foreach ($fieldsvalue as $field) {
                $records .= $tag . '#';
                foreach ($field as $prefieldkey => $prefildvalue) {
                    if ($prefildvalue != '') {
                        $records .= ($prefieldkey != chr(29)? '^'.$prefieldkey : '').$prefildvalue;
                    }
                }   
                $records .= chr(31).chr(30);
            }
        }

        $packet = implode("\n", array('D', $this->arm, 'D', $this->id, $this->seq, $this->login, $this->pass, '', '', '', $db_name, 0, 1, $records));
        $packet = strlen($packet)."\n".$packet;
        $answer = $this->send($packet);

        if ($answer === false) {
            return false;
        }
        
        $ret = explode('#',$answer[11]);
        
        if ($ret[0] > 0 ){
            $record = $this->record_read($db_name, $ret[0], 0);
            $this->error_code = '0';
        } else {
            if ($ret[0] == '-608'){
                $record = $this->record_read($db_name, $record->mfn, 0);
                $this->error_code = '0';
            }
            $this->error_code = $ret[0];
        }
        
        return $record; 
    }









   














   
   
   function send($packet, $debug = false) {
      //Пишем в лог текущее действие
      //$this->log($packet);
      
      if ($this->sock === false) {
         //Не удалось создать сокет
         $this->error_code = socket_last_error();
         $this->error_message = 'Ошибка создания сокета';
         return false;
      }
      if (!$this->connect()) {
         //Сокет создан, но соединиться с удаленным компьютером не получилось
         $this->error_code = socket_last_error();
         $this->error_message = 'Ошибка сокета, не удалось создать соединение';
         return false;
      }
      $this->seq++;
      
      socket_write($this->sock, $packet, strlen($packet));
      $answer = '';
      while ($buf = @socket_read($this->sock, 2048, PHP_NORMAL_READ)) {
         $answer .= $buf;
      }
      socket_close($this->sock);
      
      /*
      $file = 'irbis.log';
      $current = file_get_contents($file);
      $current_time = date("c",time());
      $current .= $current_time."\n";
      $current .= "\====================================================================\n";
      $current .= "\Запрос к серверу: \n";
      $current .= "\====================================================================\n";
      $current .= $packet."\n";
      $current .= "\====================================================================\n";
      $current .= $answer."\n";
      $current .= "\====================================================================\n";
     
      file_put_contents($file, $current);
      */
      
      
      
      

      if (($this->print_message) or ($debug)) {
         print "\nЗапрос к серверу: \n";
         print_r($packet);
         print "\n====================================================================\n";
         print "\nОтвет сервера: \n";
         //if ($answer[0]=='A'){}
         //else{
            print_r($answer);   
         //}
         print "\n====================================================================\n";
      }
      
      
      return explode("\r\n", $answer);
   }


   function log($packet){
      
      //$packet = str_replace(chr(31).chr(30),'#',   str_replace(chr(30).chr(31),'#',$packet));
      $mas = explode("\n",$packet);
      
      $record = new irbis64_record;
      $record->mfn=0;
      $record->ver=0;
      $record->status=0;
      $record->fields[1][] = $this->login;
      $record->fields[2][] = $this->pass;
      $record->fields[3][] = date('omd H:i');
      $record->fields[4][] = $mas[1];
      $record->fields[6][] = $mas[4];
      $record->fields[7][] = $mas[5];
      
      for ($i=1;$i<count($mas);$i++){
         if ((mb_strpos($mas[$i],chr(31))<>0) or (mb_strpos($mas[$i],chr(30))<>0)){
            $mas[$i] = str_replace(chr(10),'###',$mas[$i]);
            $mas[$i] = str_replace(chr(13),'###',$mas[$i]);
            $mas[$i] = str_replace(chr(30),'###',$mas[$i]);
            $mas[$i] = str_replace(chr(31),'###',$mas[$i]);
         }
            
        
         $record->fields[5][] = $mas[$i];
         
            
      }
      
      
     
      $this->log_write($record);
      
   }
  

   function log_write($record, $lock = false, $if_update = true) { // $lock  = блокировать запись , $if_update = актуализировать запись
      
      $db_name = 'userlog';
      
      $records = "";
      $records .= $record->mfn.'#'.$record->status;
      $records .= chr(31).chr(30);
      $records .= '0#'.$record->ver;
      $records .= chr(31).chr(30);
      
      /*Массив по всем полям*/
      foreach ($record->fields as $fieldsnum => $fieldsvalue){
         /*массив по повторениям поля*/
         if (isset($fieldsvalue) && is_array($fieldsvalue))
         foreach ($fieldsvalue as $field){
            if (is_array($field)){
               /*массив по подполям*/
               $records .= $fieldsnum.'#';
               foreach ($field as $prefieldkey => $prefildvalue){
                  if ($prefildvalue!='')
                     $records .= '^'.$prefieldkey.$prefildvalue;
               }
            }
            else{
               if ($field!='')
                  $records .= $fieldsnum.'#'.$field;
            }
            $records .= chr(31).chr(30);
         }
      }

      $packet = implode("\n", array('D', $this->arm, 'D', $this->id, $this->seq, $this->login, $this->pass, '', '', '', $db_name, 0, 1, $records));
      $packet = strlen($packet) . "\n" . $packet;
      $answer = $this->send_log($packet);

      return 0; 
   }
   
   
   function send_log($packet) {
      if ($this->sock === false) {
         //Не удалось создать сокет
         $this->error_code = socket_last_error();
         $this->error_message = 'Ошибка создания сокета';
         return false;
      }
      if (!$this->connect()) {
         //Сокет создан, но соединиться с удаленным компьютером не получилось
         $this->error_code = socket_last_error();
         $this->error_message = 'Ошибка сокета, не удалось создать соединение';
         return false;
      }
      $this->seq++;
      
      socket_write($this->sock, $packet, strlen($packet));
      $answer = '';
      while ($buf = @socket_read($this->sock, 2048, PHP_NORMAL_READ)) {
         $answer .= $buf;
      }
      socket_close($this->sock);
     
     
      /*      
      print "\n=============================================\n";
      print "Запрос к серверу\n";
      print $packet."\n";
      
      
      print "\n=============================================\n";
      print "Ответ сервера\n";
      print $answer."\n";
      
      */
      return 0;
   }

   
   
   
   
   
   /*Прочитать список пользователей САБ ИРБИС64*/
   function read_irbis_user_list() {
      $packet = implode("\n", array('+9', $this->arm, '+9', $this->id, $this->seq, '', '', '', '', '', $this->login, $this->pass));
      $packet = strlen($packet)."\n".$packet;
      $answer = $this->send($packet);
      
      
      if ($answer === false) {
         $this->error_code = 1;
         return false;
      }
      
      $user_num = $answer[11];
      $user_len = $answer[12];
      
      for ($i = 0; $i<$user_num ; $i++) {    
         $user_list[] = new irbis64_user(
            $answer[14 +$i + $i*$user_len],
            $answer[14 +$i + $i*$user_len + 1],
            
            $answer[14 +$i + $i*$user_len + 2],
            $answer[14 +$i + $i*$user_len + 3],
            $answer[14 +$i + $i*$user_len + 4],
            $answer[14 +$i + $i*$user_len + 5],
            $answer[14 +$i + $i*$user_len + 6],
            $answer[14 +$i + $i*$user_len + 7]
         );
      }
      
      $this->error_code = $answer[10];
      if ($this->error_code != 0) return $this->error_code;
    
      return $user_list;//true;
   }



   /*Сохранить список пользователей САБ ИРБИС64*/
   function write_irbis_user_list($user_list) {
      $list = "";
      foreach ($user_list as $user){
         $list .= $user->login."\r\n";
         $list .= $user->pass."\r\n";
         
         if ($user->c!='irbisc.ini') {
            $list .= 'C='.$user->c.'; ';
         }
         if ($user->r!='irbisr.ini') {
            $list .= 'R='.$user->r.'; ';
         }
         if ($user->b!='irbisb.ini') {
            $list .= 'B='.$user->b.'; ';
         }
         if ($user->p!='irbisp.ini') {
            $list .= 'M='.$user->p.'; ';
         }
         if ($user->k!='irbisk.ini') {
            $list .= 'K='.$user->k.'; ';
         }
         if ($user->a!='irbisa.ini') {
            $list .= 'A='.$user->a.'; ';
         }
         
         $list .= "\r\n";
      }
      $list .= "*****\r\n";

      $packet = implode("\n", array('+7', $this->arm, '+7', $this->id, $this->seq, $this->login, $this->pass, '', '', '', $list));
      $packet = strlen($packet)."\n".$packet;
      $answer = $this->send($packet);
      
      
      if ($answer === false) {
         $this->error_code = 1;
         return false;
      }
      

      $this->error_code = $answer[10];
      if ($this->error_code != 0) return $this->error_code;
    
    
      return $answer[10];//true;
      
   }












   /**
   * Поиск записей по запросу в формате ИРБИСА
   **/
   function search($db_name, $search_exp, $num_records = 1, $first_record = 0, $format) {
      if ($db_name==''){
         $this->error_code = '-11111';
         $this->error_message = 'Поиск невозможен. Не передано название базы';
         return false;
      } 
      if ($search_exp==''){
         $this->error_code = '-11111';
         $this->error_message = 'Поиск невозможен. Не передано поисковое выражение';
         return false;
      }
      
      $packet = implode("\n", array('K', $this->arm, 'K', $this->id, $this->seq, '', '', '', '', '', $db_name, $search_exp, $num_records, $first_record, $format, '', '', ''));
      $packet = strlen($packet) . "\n" . $packet;
      $answer = $this->send($packet);
      
      
      if ($answer === false) return false;
      $this->error_code = $answer[10];
      if ($this->error_code != 0) return false;
      $ret['found'] = $answer[11]; // количество найденных записей
      $c = count($answer) - 1;
      for ($i = 12; $i < $c; $i++) {
         
          //$ret['records'][] = substr($answer[$i], strpos($answer[$i],'#') + 1);//irbis64_record::read_record(substr($answer[$i],strpos($answer[$i],'#')+3)); 
          $ret['records'][] = $this->MfnValueResult($answer[$i]);
          //$ret['records'][] = mb_convert_encoding($answer[$i], "utf-8", "windows-1251");
          //print $answer[$i];
          
      }
      
      return $ret;
   }


   
    private function MfnValueResult($str){
        if ($str != ''){
            $tmp = explode('#', $str, 2);
            return [
                'mfn' => (!empty($tmp[0]) ? $tmp[0] : 0 ) ,
                'string' => (!empty($tmp[1]) ? $tmp[1] : 0 )
            ];
        }else {
            return false;
        }
    }


   /**
   * Поиск по запросу в мнемоническом формате
   **/
   function organic_search($db_name, $search_exp, $num_records = 1, $first_record = 0) {
      if ($db_name==''){
         $this->error_code = '-11111';
         $this->error_message = 'Поиск невозможен. Не передано название базы';
         return false;
      } 
      if ($search_exp==''){
         $this->error_code = '-11111';
         $this->error_message = 'Поиск невозможен. Не передано поисковое выражение';
         return false;
      }

     
      //"(title=Сказки)&(author=Пушкин)&(year=2016)"

      foreach ($this->dict as $key => $value){
         $search_exp = str_replace ($key,$value,$search_exp);
      }
         
      $search_exp = str_replace ('(','("',$search_exp);
      $search_exp = str_replace (")",'")',$search_exp);
      $search_exp = str_replace ('&','*',$search_exp);
      $search_exp = str_replace ('||','+',$search_exp);
      $search_exp = str_replace ('!','^',$search_exp);
      
      if (substr($search_exp, 0, 1)=='^'){
         $search_exp = '("HD=$")'.$search_exp;
      }
         
         
      $packet = implode("\n", array('K', $this->arm, 'K', $this->id, $this->seq, '', '', '', '', '', $db_name, $search_exp, $num_records, $first_record, $this->fields_pft, '', '', ''));
      $packet = strlen($packet) . "\n" . $packet;
      $answer = $this->send($packet);
      
      
      if ($answer === false) return false;
      $this->error_code = $answer[10];
      if ($this->error_code != 0) return false;
      $ret['found'] = $answer[11]; // количество найденных записей
      $c = count($answer) - 1;
      for ($i = 12; $i < $c; $i++) {
         $ret['records'][] = irbis64_record::read_record(substr($answer[$i],strpos($answer[$i],'#')+3)); 
      }
       
      return $ret;

   }



   
   
   
   /**
   * Поиск по запросу в мнемоническом формате
   **/
   function org_search($search_exp, $num_records = 1, $first_record = 0) {
      /*
      сигла
      название
      инн
      адрес
      
      */

         
      $packet = implode("\n", array('K', $this->arm, 'K', $this->id, $this->seq, '', '', '', '', '', 'users', $search_exp, $num_records, $first_record, $this->fields_pft, '', '', ''));
      $packet = strlen($packet) . "\n" . $packet;
      $answer = $this->send($packet);
      
      
      if ($answer === false) return false;
      $this->error_code = $answer[10];
      if ($this->error_code != 0) return false;
      $ret['found'] = $answer[11]; // количество найденных записей
      $c = count($answer) - 1;
      for ($i = 12; $i < $c; $i++) {
         //$tmp = new irbis64_organization;
         $ret['records'][] = osl_organization::read_org(substr($answer[$i],strpos($answer[$i],'#')+3)); 
      }
       
      return $ret;

   }




















   // Создание новой библиографической базы данных
   function new_db($db_name, $db_title) {
      if ($db_name==''){
         $this->error_code = '-11111';
         $this->error_message = 'Создание базы невозможно. Не передано название базы';
         return false;
      }   
         
      $packet = implode("\n", array('T', $this->arm, 'T', $this->id, $this->seq, $this->login, $this->pass, '', '', '', $db_name, mb_convert_encoding($db_title, 'Windows-1251'), '1'));
      $packet = strlen($packet) . "\n" . $packet;
      $answer = $this->send($packet);
      
      
      if ($answer === false) return false;
      $this->error_code = $answer[10];
      
      return $answer[10];
   }




   //Удаление базы данных
   function delete_db($db_name) {
      
      if ($db_name!=''){
         $this->error_code = '-11111';
         $this->error_message = 'Удаление базы невозможно. Не передано название базы';
         return false;
      }      
         
         
      $packet = implode("\n", array('W', $this->arm, 'W', $this->id, $this->seq, $this->login, $this->pass, '', '', '', $db_name));
      $packet = strlen($packet) . "\n" . $packet;
      $answer = $this->send($packet);
      
      
      if ($answer === false){
         $this->error_code = $answer[10];
         $this->error_message = '';
         return false; 
      } 
      $this->error_code = $answer[10];

      return $answer[10];

   }




   function record_delete($db_name, &$record){
      if (get_class($record) != 'irbis64_record'){
         $irbis->error_code = '-11111';
         $irbis->error_message = 'Удаление не возможно: не передан объект irbis64_record';
         return false;
      }
      if (trim($db_name )== ''){
         $irbis->error_code = '-11111';
         $irbis->error_message = 'Удаление не возможно: не указано имя базы данных';
         return false;
      }
      if ($record->mfn<1){
         $irbis->error_code = '-11111';
         $irbis->error_message = 'Удаление не возможно: запись является виртуальной и не была ранее записана в базу данных (MFN=0)';
         return false;
      }
      
      $record->status = '1';
      $ret = $this->record_write($db_name, $record);
      
      return $ret;
   }

   
   
   
   function print_form_info($db_name, $printformname){
      $form_info['name'] = $printformname;
      /*
         Проверка на ошибки:
         1. передано ли название базы
         2. передано ли название печатной формы
      */
      
      $packet = implode("\n", array('L', $this->arm, 'L', $this->id, $this->seq, '', '', '', '', '', '10.'.$db_name.'.'.$printformname.'.hdr'));
      $packet = strlen($packet) . "\n" . $packet;
      
      $answer = $this->send($packet);
      if ($answer === false) return false;
      $this->error_code = $answer[10];
      
      $answer[10] = iconv('windows-1251', 'UTF-8', $answer[10]);
      $ret = explode(chr(31).chr(30), trim($answer[10]));
      
      $wss = '';
      $header = '';
      if (strpos($ret[0],'.wss') > 0) {
         $wss = substr($ret[0], strpos($ret[0],'@') + 1, strpos($ret[0],'.wss') + 3);
      }
      if (strpos($ret[0],'.wss') > 0) {
         $header = substr($ret[0], strpos($ret[0],'.wss') + 5, strpos($ret[0],'.pft') - strpos($ret[0],'.wss') - 1);
      }
      else
      {
         $header = substr($ret[0], strpos($ret[0],'!') + 1, strpos($ret[0],'.pft') - strpos($ret[0],'!') + 3);
      }
      
      $form_info['header'] = $header;
      
      $form_info['wss'] = new irbis64_subfield();
      $tmp = new irbis64_resurce();      
      $form_info['wss'] = $tmp->read_wss($db_name, $this, $wss);
      
      return $form_info;
   }
   
   
   function print_record($db_name, $printformname, $virtual_field, $search_exp, $documentstype, $listMFN=array(0,0) ){
      if (substr($printformname,1,1)!='@')
      $printformname = '@'.$printformname;
       
      foreach ($this->dict as $key => $value){
         $search_exp = str_replace ($key,$value,$search_exp);
      }
         
      $search_exp = str_replace ('(','("',$search_exp);
      $search_exp = str_replace (")",'")',$search_exp);
      $search_exp = str_replace ('&','*',$search_exp);
      $search_exp = str_replace ('||','+',$search_exp);
      $search_exp = str_replace ('!','^',$search_exp);
      
      if (substr($search_exp, 0, 1)=='^'){
         $search_exp = '("HD=$")'.$search_exp;
      }
      
      $listMFNstr = implode("\n", $listMFN);

      $packet = implode("\n", array( 
         '7', 
         $this->arm, 
         '7', 
         $this->id, 
         $this->seq, 
         $this->login, 
         $this->pass, 
         '', 
         '', 
         '', 
         $db_name, 
         $printformname, 
         '',
         $virtual_field,
         $search_exp,
         '0',
         '0',
         '',
         $documentstype,
         $listMFNstr,
      ));
      $packet = strlen($packet) . "\n" . $packet;
      $answer = $this->send($packet);

      if ($answer === false) return false;
         $this->error_code = $answer[10];

      if (substr($answer[10],1,3)=='</>')
      {
         $answer[10] = '<html>'.$answer[10].'</html>';
      }
      else
      {
         $answer[10] = '{\rtf '.$answer[10].' }';
      }   


      return $answer[10];
   }

   
   
    /* Функция чтения текстового ресурса (файла), расположенного на сервере ИРБИС64. (L)
     * Αpath – коды путей принимающие следующие значения:
     * 0 – общесистемный путь  0
     * 1 – путь размещения сведений о базах данных сервера ИРБИС64.
     * 2 – путь на мастер-файл базы данных.
     * 3 – путь на словарь базы данных.
     * 10 – путь на параметрию базы данных.
    */  
    public function getresourse($Apath, $db_name, $filename, $code_page = '', $type){
        $packet = implode("\n", array('L', $this->arm, 'L', $this->id, $this->seq, '', '', '', '', '', $Apath.'.'.$db_name.'.'.$filename));
        $packet = strlen($packet) . "\n" . $packet;
        $answer = $this->send($packet);

        $this->error_code = $answer[10];

        if ($code_page != '')
            $answer[10] = iconv($code_page, 'UTF-8', $answer[10]);
        
        $result = explode(chr(31).chr(30), trim($answer[10]));

        switch ($type){
            case 'mnu':
                $result = $this->asMNU($result);
                break;
            default:
                
                break;
        }
        return $result;
    }   
    
    private function asMNU($resource){
        $tmp = [];
        
        for($i = 0; $i < count($resource); $i = $i + 2){
            if ($resource[$i] == '*****')
                break;
            $tmp[$resource[$i]] = $resource[$i + 1];
        }
            
        return $tmp;
    }
    
} 