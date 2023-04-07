<?php
namespace gspanarin\irbis64;

print '<pre>';

require '..\src\irbis64_provider.php';
use gspanarin\irbis64\irbis64_provider;


$irbis = new irbis64_provider('127.0.0.1', '6666', '1', '1', 'C');




if ($irbis->login()){
    print 'Yes!!!';
}else{
    print 'Не удалось подключиться к TCP/IP серверу';
}

print_r($irbis);