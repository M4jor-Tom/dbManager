<?php 
if(isset($db, $_POST['table'], $_POST['queryType']) AND sqlIndb($db, $_POST['table']))
{
    //Si les post sont là, et que la table envoyée existe dans la base de données
    $dbTables = sqlGetTables($db);
    $table = securedContentPick($dbTables, $_POST['table']);

    if($_POST['queryType'] === 'insert')
    {
        //Récupérer les champs qui viennent de la saisie de l'utilisateur
        $presets = [];
        $executeQuery = true;
        foreach($_POST as $name => $value) 
            if(preg_match('/^-(\w+)-.-(\w+)-.-(\w+)-.-(\w*)-$/', $name, $matches) AND in_array($matches[3], sqlGetColumnsProperties($db, $table, 'Field')) AND $value)
            {
                //mactches[3] protégé des injections
                //$presetDb = securedContentPick(sqlGetDbs($db), $matches[1]);
                $presetTable = securedContentPick($dbTables, $matches[2]);
                $presets[$presetTable]['columns'][] = $matches[3];
                //var_dump($_POST, $matches[0], $presetTable);
                if($matches[4])
                {
                    $listTable = securedContentPick($dbTables, $matches[4]);
                    $listPk = sqlGetPrimaryKey($db, $listTable);
                    $displayedColumn = sqlGetShowKey($db, $listTable, 'comma');
                    $insertedValue = sqlSelect($db, "SELECT $listPk FROM $listTable WHERE $displayedColumn = ?", $value);
                    
                    if(isset($insertedValue[0]))
                        $insertedValue = implode(',', numericKeys($insertedValue[0], 'drop'));
                    else $executeQuery = false;
                }
                else $insertedValue = $value;
                $presets[$presetTable]['values'][] = $insertedValue;
            }

        //var_dump($db, $table, $presetColumns, $presetValues);
        //On ajoute en premier les données de la table séléctionnée
        if($executeQuery)
        {
            sqlInsert($db, $table, $presets[$table]['columns'], $presets[$table]['values']);

            //On ajoute ensuite les données qui sont pas dans la table séléctionnée, ou l'utilisateur a mit une valeur
            foreach($presets as $tableName => $preset)
                if($tableName != $table AND count($preset['columns']) === count($preset['values']))
                    //[shit-flag] la clé primaire manque car il n'y a pas d'auto incrément pour les tables de pivot
                    sqlInsert($db, $tableName, $preset['columns'], $preset['values']);
            
            //Correction des jointures (Création)
            sqlFillMissingJoins($sudo[$dbName]);
        }
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