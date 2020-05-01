<?php
$pathToProperties = '../../../../home/tom/properties.ini';

require_once('../modele/functions.php');
header('Content-type: application/json');

if(isset($_SESSION[$tokenKey], $_POST['table'], $_POST['conditionkey'], $_POST['conditionvalue'], $_POST['orderby']))
{
    //Connexion à la base de données
    $pwd = parse_ini_file($pathToProperties)['2'];
    $db = sqlConnect($host, $dbName, sqlGetMysqlUserInfo($dbLogger, $_SESSION[$tokenKey], $pwd)['User'], $pwd);
    
    $table = securedContentPick(sqlGetTables($db), $_POST['table']);

    $tableKeys = sqlGetColumnsProperties($db, $table, 'Field');

    $conditionKey = securedContentPick($tableKeys, $_POST['conditionkey']);
    $orderby = securedContentPick($tableKeys, $_POST['orderby']);

    echo json_encode(sqlSelect($db, "SELECT * FROM $table WHERE $conditionKey = ? ORDER BY $orderby ASC", (int)$_POST['conditionvalue'], 'drop'));
}