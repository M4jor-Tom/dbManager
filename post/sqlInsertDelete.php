<?php 
if(isset($db, $_POST['table'], $_POST['queryType']) AND sqlIndb($db, $_POST['table']))
{
    //Si les post sont là, et que la table envoyée existe dans la base de données
    $dbTables = sqlGetTables($db);
    $table = securedContentPick($dbTables, $_POST['table']);

    if($_POST['queryType'] === 'insert')
    {
        //Récupérer les champs qui viennent de la saisie de l'utilisateur
        $presetColumns = [];
        $presetValues = [];
        foreach($_POST as $name => $value) if(preg_match('/field-(\w+)/', $name, $matches) AND in_array($matches[1], sqlGetColumnsProperties($db, $table, 'Field')) AND $value)
        {
            $presetColumns[] = $matches[1];
            $presetValues[] = $value;
        }

        //var_dump($db, $table, $presetColumns, $presetValues);
        sqlInsert($db, $table, $presetColumns, $presetValues);

        //Correction des jointures (Création)
        sqlFillMissingJoins($sudo[$dbName]);
    }
    elseif($_POST['queryType'] === 'delete' AND isset($_POST['primaryKey'], $_POST['primaryValue']) AND sqlIntable($db, $table, $primaryKey = explode(',', $_POST['primaryKey'])))
    {
        $primaryValues = explode(',', $_POST['primaryValue']);
        $tableKeys = sqlGetColumnsProperties($db, $table, 'Field');
        $primaryKey = securedContentPickArray($tableKeys, $primaryKey);
        
        //var_dump($db, $table, $primaryKey, $primaryValues);
        sqlDelete($db, $table, $primaryKey, $primaryValues);
    }
    else var_dump($_POST);
}