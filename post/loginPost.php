<?php
if(isset($_POST['firstnamevalue'], $_POST['namevalue'], $_POST['passwordvalue']))
{
    //Si tous les champs sont rentrés
    //Récupération des colonnes existantes dans la table
    $userKeys = sqlGetColumnsProperties($dbManager, 'utilisateurs', 'Field');
    
    //Récupération sécurisée des clés d'identification
    $userPrimaryKey = securedContentPick($userKeys, $userPrimaryKey);
    $userNameKey = securedContentPick($userKeys, $userNameKey);
    $userFirstNameKey = securedContentPick($userKeys, $userFirstNameKey);
    $userPasswordKey = securedContentPick($userKeys, $userPasswordKey);
    $tokenKey = securedContentPick($userKeys, $tokenKey);

    if(sqlDataExists($dbLogger, $usersTableName, [$userNameKey, $userFirstNameKey], [$_POST['namevalue'], $_POST['firstnamevalue']]))
    {
        //L'utilisateur existe
        //Récupérer la valeur primaire de l'utilisateur
        $userInfo = sqlSelect($dbLogger, "SELECT $userPrimaryKey FROM $usersTableName WHERE $userNameKey = ? AND $userFirstNameKey = ?", array($_POST['namevalue'], $_POST['firstnamevalue']));
        $tokenValue = sessionTokenize($dbLogger,  $usersTableName, //db & table
                                                $userInfo[0][$userPrimaryKey], //uid
                                                $_POST['passwordvalue'], //pwd
                                                $error);
        if($tokenValue)
        {
            //Si le login est bon ($tokenValue != 0) alors on l'update dans la base de données et on le met en $_SESSION
            sqlUpdate($dbLogger, $usersTableName, $userPrimaryKey, $userInfo[0][$userPrimaryKey], $tokenKey, $tokenValue);

            //Enregistrement dans la session du token
            $_SESSION[$tokenKey] = $tokenValue;
        }
    }
}