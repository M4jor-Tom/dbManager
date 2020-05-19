<?php
//Récupération des tables existantes
$dbTables = sqlGetTables($db);

$indexUrl = $GLOBALS['indexUrl'];
$indexUrlWithGets = $indexUrl . rebuildGetString();

//Récupération des droits de l'utilsiateur actuel
foreach($dbTables as $dbTableKey => $dbTable)
{
    //Pour chaque table
    foreach($userGrants as $dbPointTable => $grant) if(inString($dbPointTable, "$dbName.$dbTable"))
    {
        //Pour chaque $grant où la table est appelée
        if(!isset($userGrants["$dbName.$dbTable"]['privileges']['SELECT']))
        {
            unset($dbTables[$dbTableKey]);
        }
    }
}

$menu = htmlMenu($dbTables, "$indexUrl?selectedtable=$contentString", ['nav' => 'id = tableList'], NULL, NULL, NULL, NULL, '', 'selectedtable');

if(isset($tablesProperties, $_GET['selectedtable']) AND in_array($_GET['selectedtable'], $dbTables) AND !in_array($_GET['selectedtable'], $hiddenTables))
{
    //Table trouvée, tables altérables listées
    $selectedTable = securedContentPick($dbTables, $_GET['selectedtable']);
    $contentTitle = ucwords(str_replace('_', ' ', $selectedTable));

    //Écriture en html de la table
    $table = $infoWidgets = $graph = '';

    //$selectedTable désigne quelque chose d'existant, il l'affiche
    $directQueryGlobal = $_GET;
    unset($directQueryGlobal['selectedtable']);

    //$selectedTable désigne une table particulière
    switch($selectedTable)
    {
        case $usersTableName:
            if(isset($_GET['uid']))
            {
                $infoWidgets = htmlFormatMysqlUserPriv($sudo[$dbName], (int)$_GET['uid'], $indexUrlWithGets);
            }

        default:
            $table = getRelationnalTable($sudo[$dbName], $selectedTable, $userGrants, $directQueryGlobal);
    }

    ob_start();
    //Ce qui est affiché en dessous du tableau...
    ?>
    <div class = chart-container>
        <canvas id="graph"></canvas>
    </div>
    <?php

    //...ne sera un chart que si demandé
    $graph = (int)(isset($tablesProperties[$selectedTable]['Has_graph']) AND $tablesProperties[$selectedTable]['Has_graph']) 
        ?   ob_get_clean() 
        :   '';
    ob_clean();

    
    $contentBody = $menu . $table . $graph . $infoWidgets;
}
elseif(isset($_GET['tables']))
{
    //Si aucune table n'est séléctionnée
    $contentBody = $menu;
}
else
{
    //Table non trouvée
    $contentTitle = 'Table inconnue';
}