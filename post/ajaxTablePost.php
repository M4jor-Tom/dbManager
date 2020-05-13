<?php //var_dump($_POST);
if(
    isset($_POST['edittable'], $_POST['primarykey'], $_POST['primaryvalue'], $_POST['editkey'], $_POST['value']) AND 
    !(
        is_string($_POST['value']) AND inString($_POST['value'], '#')
    )
)
{
    //Si l'opérateur veut modifier une donnée
    if(sqlIndb($db, $_POST['edittable']))
    {
        //Si $edittable existe dans la db
        //Alors récupération sécurisée de $editTable
        $dbTables = sqlGetTables($db);
        $editTable = securedContentPick($dbTables, $_POST['edittable']);
        $securedPrimaryKey = [];
        
        if(sqlIntable($db, $editTable, explode(',', $_POST['primarykey'])) AND 
           sqlIntable($db, $editTable, $_POST['editkey']))
        {
            //Si les colonnes font partie de la table
            //Alors récupération sécurisée de $primaryKey et $editKey
            $editTableKeys = sqlGetColumnsProperties($db, $editTable, 'Field');
            $primaryKey = explode(',', $_POST['primarykey']);
            foreach($primaryKey as $primaryColumn)
            {
                $securedPrimaryKey[] = securedContentPick($editTableKeys, $primaryColumn);
            }
            $editKey = securedContentPick($editTableKeys, $_POST['editkey']);
        }
        else
        {
            $sqlInjectionAttempt = 1;
        }
    }
    else
    {
        $sqlInjectionAttempt = 1;
    }
    
    if(isset($_POST['displaykey'], $_POST['list']) AND $_POST['list'] != 'undefined')
    {
        //Si l'on à affaire à une [html:datalist] [sql:join]
        if(sqlIndb($db, $_POST['list']))
        {
            $displayTable = securedContentPick($dbTables, $_POST['list']);
            if(sqlIntable($db, $displayTable, $_POST['displaykey']))
            {
                $displayTableKeys = sqlGetColumnsProperties($db, $displayTable, 'Field');
                $displayKey = securedContentPick($displayTableKeys, $_POST['displaykey']);
            }
            else
            {
                $sqlInjectionAttempt = 1;
            }
        }
        else
        {
            $sqlInjectionAttempt = 1;
        }

        $listPrimaryKey = (int)isset($_POST['listprimarykey']) ? $_POST['listprimarykey'] : $editKey;
        
        $editValue = sqlSelect($db, "SELECT $listPrimaryKey FROM $displayTable WHERE $displayKey = ?", $_POST['value'])[0][0];
    }
    else
    {
        //S'il s'agit d'input normal
        $editValue = strip_tags($_POST['value']);
    }

    $primaryValues = explode(',', $_POST['primaryvalue']);

    if(!isset($sqlInjectionAttempt))
    {
        //var_dump($db, $editTable, $securedPrimaryKey, $primaryValues, $editKey, $editValue);
        sqlUpdate($db, $editTable, $securedPrimaryKey, $primaryValues, $editKey, $editValue);
        //         db   table       condition_colonnes  condition_valeurs      clé_modif valeur_modif
    }
    else
    {
        //Attaque
        
    }
}
else
{
    //Pas (assez) de post pour lancer un update
}