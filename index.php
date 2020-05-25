<?php
$indexUrl = 'index.php';
$pathToProperties = '../../../home/tom/properties.ini';

require_once 'modele/functions.php';
$functionTime = microtime(true) - $startTime;

if($_POST)
{
    require_once 'post/loginPost.php';
}
//require_once 'controller/32fInterface.php';

//var_dump(ldap_bind(ldap_connect()));
if(sessionCheck($dbLogger, $dbManager) AND !isset($_GET['disconnect']))
{
    //Utilisateur authentifié
    $pwd = parse_ini_file($pathToProperties)[2];
    $mysqlCreds = sqlGetMysqlUserInfo($dbLogger, $_SESSION[$tokenKey], $pwd);
    $db = sqlConnect($host, $dbName, $mysqlCreds['User'], $pwd/*, 'silent'*/);
    /*
        $time = microtime(true);
        foreach(sqlGetJoinList($db, $_GET['selectedtable'], false, '', [], false, false) as $join)
            var_dump($join);
        $f = microtime(true) - $time;
        var_dump($f);
        foreach(sqlGetJoinList($db, $_GET['selectedtable'], true, '', [], false, false) as $join)
            var_dump($join);
        var_dump(microtime(true) - $f - $time);
    */
    //var_dump(sqlGetJoinList($db, $_GET['selectedtable'], true, '', [], true));
    //Aquisition des php dont on à besoin pour que tout fonctionne (sauf query.php qui affiche du json pour répondre)
    if($_POST)
    {
        require_once 'post/ajaxTablePost.php';
        require_once 'post/sqlInsertDelete.php';
    }

    if(isset($_GET['selectedtable']) OR isset($_GET['tables']))
    {
        //Si on veut visualiser la liste des tables, et/ou une table
        $userGrants = sqlShowGrants($dbManager, sqlGetMysqlUserInfo($dbManager, $_SESSION[$tokenKey], '', 'justToken')['User']);

        if((isset($_GET['uid']) AND $_POST) OR isset($_POST['uid']))
        {
            //Si l'utilisateur regarde la table utilisateurs
            require_once 'post/privilegesUpdate.php';
        }

        require_once 'vision/tableWatch.php';
    }
}
else
{
    //Utilisateur non authentifié
    $hideDisconnect = 1;
    $hideTables = 1;
    
    if(isset($_SESSION[$tokenKey]))
    {
        //Vidage du token dans la base de données
        sqlUpdate($dbManager, $usersTableName, $tokenKey, $_SESSION[$tokenKey], $tokenKey, '');

        //Dans la variable $_SESSION
        $_SESSION = [];
        
        //Fin de session
        session_destroy();
    }
    require_once 'vision/loginPage.php';
}

require_once 'vision/template.php';