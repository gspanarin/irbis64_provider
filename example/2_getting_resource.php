<?php
namespace gspanarin\irbis64;

print '<pre>';

require '..\src\irbis64_provider.php';
use gspanarin\irbis64\irbis64_provider;


$irbis = new irbis64_provider('127.0.0.1', '6666', '1', '1', 'C');




if ($irbis->login()){
    $Apath = '1';
    $db_name = '';
    $filename = 'dbnam1.mnu';
    $code_page = 'windows-1251';
    $type = 'mnu';
    
    $resource = $irbis->getresourse($Apath, $db_name, $filename, $code_page, $type);
    print_r($resource);
    
}else{
    print 'Не удалось подключиться к TCP/IP серверу';
}

