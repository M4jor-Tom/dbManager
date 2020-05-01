<?php
if(isset($_POST['name'], $_POST['value'], $_POST['user']) AND in_array('UPDATE', $userGrants[$dbName.'.utilisateurs']['tableswide permissions']['utilisateurs']))
{
    //S'il contient 2 tirets dans son nom d'input, alors il doit avoir la forme que l'on recherche
    $postInfo = explode('-', $_POST['name']);
    //[0]:  Table
    //[1]:  Droit
    
    $userId = 'u' . (int)ltrim($_POST['user'], 'u');
    
    if($_POST['value'] === 'revoke')
    {
        sqlUser($sudo[$dbName], 'REVOKE', $userId, '', 'localhost', $postInfo[0], $postInfo[1]);
    }
    elseif($_POST['value'] === 'grant')
    {
        sqlUser($sudo[$dbName], 'GRANT', $userId, '', 'localhost', $postInfo[0], $postInfo[1]);
    }
}