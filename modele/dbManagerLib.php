<?php
/*
Développeur: Tom VAUTRAY

Contraintes de fonctionnement: 
    -Le DBMS doit être MySQL
    -Les tables doivent être créées avec InnoDB
    -Aucune base de données, table ou colonne ne doit contenir de point(s) (.) dans son nom
    -Les views doivent être appelées de la manière suivante: 'origin_table.view_name' (oui, avec un point)
    -Les contraintes de clés étrangères doivent être appliquées sur chaque table
    -Les tables de jointures doivent nommer leurs clés étrangères (FOREIGN KEY) de la même manière que ces clés sont appelées dans leurs tables d'origines
        -> Les tables jointes entre elles sans table de jointures peuvent avoir des noms de clés différentes
*/

/*require 'utilisateurs.php';
foreach($utilisateurs as $user)
{
    echo "INSERT INTO utilisateurs(ID_utilisateur,Name,Prenom,Mot_de_passe,Token) VALUES($user[ID_utilisateur],'$user[Name]','$user[Prenom]','$user[Mot_de_passe]','$user[Token]');<br>";
}*/

//Variables globales
    $startTime = microtime(true);
    $nextTimeUseDisconnect = 0;

    $rootPwd = parse_ini_file($pathToProperties)[4];

    try
    {
        $sudo[$dbName] = new PDO("mysql:host=$host;dbname=$dbName", 'root', $rootPwd, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }
    catch(Exception $e)
    {
        die('Connection error: ' . $e -> getMessage());
    }

    $host = isset($host)
        ?   $host
        :   'localhost';

    $showKeyColumnName = 'Name';
    $tempSuffix = '_temp';

    $idString = '__ID__';
    $contentString = '__CONTENT__';
    $allString = '__ALL__';
    $valueString = '__VALUE__'; //Doit matcher avec functions.js/changeAttribute
    $thisString = '__THIS__';   //Doit matcher avec functions.js/changeAttribute

    $tableConfigTableName = 'config_tables';
    $columnsConfigTableName = 'config_columns';
    $joiningDataTableName = 'config_joining_data';
    $pivotDatasTableName = 'config_pivot_data';
    
    $usersTableName = 'utilisateurs';
        $userPrimaryKey = 'ID_utilisateur';
        $userNameKey = 'Name';
        $userFirstNameKey = 'Prenom';
        $userPasswordKey = 'Mot_de_passe';
        $tokenKey = 'Token';

    $regexHintHref = 'https://regexr.com/';
    
    //(White)lists
        //Tables à afficher autrement qu'en utilisant getTable
        $tablesProperties = isset($GLOBALS['tablesProperties']) 
                                ? array_merge($GLOBALS['tablesProperties'], sqlDbManageConfig($sudo[$dbName])) 
                                : sqlDbManageConfig($sudo[$dbName]);

        //Opérations sur urilisateurs MySQL
        $userOperations = ['CREATE', 'GRANT', 'REVOKE', 'DROP'];
        $userPrivileges =
            [ 
                'ALL PRIVILEGES' => ['global' => 1, 'database' => 1, 'table' => 1, 'column' => 1, 'stored routine' => 1, 'proxy' => 1, 'edit' => 0],
                'CREATE' => ['global' => 1, 'database' => 1, 'table' => 1, 'column' => 1, 'stored routine' => 1, 'proxy' => 1, 'edit' => 0],
                'CREATE TEMPORARY TABLES' => ['global' => 1, 'database' => 1, 'table' => 1, 'column' => 0, 'stored routine' => 0, 'proxy' => 0, 'edit' => 0],
                'DROP' => ['global' => 1, 'database' => 1, 'table' => 1, 'column' => 0, 'stored routine' => 0, 'proxy' => 0, 'edit' => 0],
                'INDEX' => ['global' => 1, 'database' => 1, 'table' => 1, 'column' => 0, 'stored routine' => 0, 'proxy' => 0, 'edit' => 0],
                'SELECT' => ['global' => 1, 'database' => 1, 'table' => 1, 'column' => 1, 'stored routine' => 0, 'proxy' => 0, 'edit' => 1, 'showWord' => 'Consulter'],
                'INSERT' => ['global' => 1, 'database' => 1, 'table' => 1, 'column' => 1, 'stored routine' => 0, 'proxy' => 0, 'edit' => 1, 'showWord' => 'Créer'],
                'UPDATE' => ['global' => 1, 'database' => 1, 'table' => 1, 'column' => 1, 'stored routine' => 0, 'proxy' => 0, 'edit' => 1, 'showWord' => 'Modifier'],
                'DELETE' => ['global' => 1, 'database' => 1, 'table' => 1, 'column' => 0, 'stored routine' => 0, 'proxy' => 0, 'edit' => 1, 'showWord' => 'Supprimer'],
                'EXECUTE' => ['global' => 1, 'database' => 1, 'table' => 1, 'column' => 0, 'stored routine' => 0, 'proxy' => 0, 'edit' => 0]
            ];

        //Opérations d'altérations MySQL
        $alterationOperations = ['ADD', 'MODIFY', 'DROP', 'CHANGE'];

        //Types de variables MySQL
        $typesList = array('TINYINT' => 11, 'INT' => 11, 'VARCHAR' => 255, 'FLOAT' => 'x', 'TEXT' => 'x');
        
    //Blacklists
        //Tables cachées que les utilisateurs ne doivent pas pouvoir consulter dans la page Tables (TOUS les utilisateurs)
        if(isset($GLOBALS['hiddenTables']))
        {
            $hiddenTables = $GLOBALS['hiddenTables'];
        }
        elseif(isset($GLOBALS['tablesProperties']))
        {
            $hiddenTables = [];
            foreach($tablesProperties as $tableName => $tableProperties)
            {
                if(isset($tableProperties['Hidden']) AND $tableProperties['Hidden'])
                {
                    $hiddenTables[] = $tableName;
                }
            }
        }
        else
        {
            $hiddenTables = [];
        }
        
//String functions
function inString($haystack, $needle)
{
    return (int)(strstr($haystack, $needle) != '');
}

function str_replaces($searchesNReplaces, $string)
{
    foreach($searchesNReplaces as $search => $replace)
    {
        $string = str_replace($search, $replace, $string);
    }
    return $string;
}

//Array functions
function array_return($array, $keys)
{
    $return = [];
    foreach($keys as $key)
        if(isset($array[$key]))
        {
            $return[$key] = $array[$key];
        }
    
    return $return;
}

function array_combinations($input, $i = 0) 
{
    if(!isset($input[$i])) 
    {
        return [];
    }

    if($i == count($input) - 1) 
    {
        return $input[$i];
    }

    // get combinations from subsequent arrays
    $tmp = array_combinations($input, $i + 1);

    $result = [];

    // concat each array from tmp with each element from $input[$i]
    foreach ($input[$i] as $v) 
    {
        foreach ($tmp as $t)
        {
            $result[] = array_merge((array)$v, (array)$t);
        }
    }

    return $result;
}

function array_funnel($input, $keysTransfert)
{
    foreach($input as $key => $value) if(isset($keysTransfert[$key]))
    {
        //Pour chaque élément en entrée où il existe une clé de départ ($key), et une clé d'arrivée ($keysTransfert[$key])
        //Copier la valeur de l'élément depuis sa clé d'origine jusqu'à la clé qu'on veut qu'il aie
        $input[$keysTransfert[$key]] = $input[$key];
    }
    return $input;
}

//Inverse de array_replace
function array_give($receiver, $giver, $forceTake = [])
{
    foreach($giver as $giverKey => $giverValue) if(!isset($receiver[$giverKey]) OR isset($forceTake[$giverKey]))
    {
        //Si le receiver n'avait pas déja cette info ou si on le force à la prendre
        $receiver[$giverKey] = $giverValue;
    }
    return $receiver;
}

function array_offset($input, $offsets)
{
    $return = [];
    foreach($offsets as $offset)
    {
        $return[] = $input[$offset];
    }
    return $return;
}

function securedContentPick($haystack, $needle)
{
    if(in_array($needle, $haystack)) return $haystack[array_search($needle, $haystack)];
    else return 0;
}

function securedKeyPick($haystack, $key)
{
    if(isset($haystack[$key])) return $haystack[$key];
    else return 0;
}

function securedKeyReturn($haystack, $key)
{
    if(isset($haystack[$key])) return $key;
    else return 0;
}

function array_search_all($needle, $haystack)
{
    $return = [];
    while(in_array($needle, $haystack))
    {
        //Tant qu'il existe une occurence recherchée
        //On la stocke
        $key = $return[] = array_search($needle, $haystack);

        //Puis on la supprime pour trouver celle d'après
        unset($haystack[$key]);
    }
    return $return;
}

function ins_array($needles, $haystack, $each = true)
{//each: true => Toutes les occurences doivent apparaître / false => Au moins une
    $fullMatch = true;
    foreach($haystack as $occurence) 
        if(in_array($occurence, $needles))
        {
            //Pour chaque occurence du haystack apparaissant dans les valeurs à chercher
            if(!$each) return true;
        }
        else
        {
            $fullMatch = false;
        }
    return $fullMatch;
}

function array_column_keep_key($input, $key)
{
    $output = [];
    foreach($input as $keptKey => $value)
    {
        $output[$keptKey] = $value[$key];
    }
    return $output;
}

function array_columns($input, $keys)
{
    $keys = (array)$keys;
    $output = [];
    foreach($keys as $arrayKey => $key)
    {
        $output[$key] = array_column($input, $key);
    }
    return $output;
}

function array_cross($inputs)
{
    $outputs = [];
    foreach($inputs as $subOutputKey => $input)
    {
        //Pour chaque array à 2 dimensions
        $input = array_values($input);
        foreach($input as $outputKey => $subInput)
        {
            //Pour chaque contenu
            $outputs[$outputKey][$subOutputKey] = $subInput;
        }
    }
    return $outputs;
}

function array_unpack($subArray, $unpackCount = 1)
{
    if($unpackCount)
    {
        //Si une valeur numérique non nulle est donnée en dépaquetage ($unpackCount)
        for($i = 0; $i < $unpackCount; $i++) if(is_array($subArray) AND count($subArray) == 1)
        {
            //Et qu'il ne contient qu'un seul élément
            $subArray = $subArray[key($subArray)];
        }
    }
    else
    {
        //Si 0 ou NULL sont rentrés en $unpackCount
        while(is_array($subArray)) if(count($subArray) == 1)
        {
            //Si le array ne contient qu'un seul élément
            $subArray = $subArray[key($subArray)];
        }
    }
    return $subArray;
}

function securedContentPickArray($haystack, $needles)
{
    $returnArray = [];
    $needles = (array)$needles;
    $haystack = (array)$haystack;
    foreach($needles as $needle)
    {
        if(isset($haystack[array_search($needle, $haystack)])) 
        {
            $returnArray[] = $haystack[array_search($needle, $haystack)];
        }
    }
    return $returnArray;
}

function securedKeyPickArray($haystack, $keys)
{
    $returnArray = [];
    $keys = (array)$keys;
    $haystack = (array)$haystack;
    foreach($keys as $key)
    {
        if(isset($haystack[$key])) 
        {
            $returnArray[] = $haystack[$key];
        }
    }
    return $returnArray;
}

function securedKeyReturnArray($haystack, $keys)
{
    $returnArray = [];
    $keys = (array)$keys;
    $haystack = (array)$haystack;
    foreach($keys as $key)
    {
        if(isset($haystack[$key])) 
        {
            $returnArray[] = $key;
        }
    }
    return $returnArray;
}

function numericKeys($array, $action = 'keep')
{
    $result = [];
    foreach($array as $key => $value)
    {
        if(($action == 'drop' AND !is_numeric($key)) OR 
           ($action == 'select' AND is_numeric($key)) OR 
            $action == 'keep')
        {
            $result = array_merge($result, [$key => $value]);
        }
    }
    return $result;
}

function array_inject($input, $injection, $cutKey)
{
    if(isset($input[$cutKey]))
    {
        //Si les arguments sont valides
        $cutIndex = 0;
        foreach($input as $key => $value)
        {
            //Pour chaque ligne de $input
            if(key($input) != $cutKey)
            {
                //Si la clé courrante est différente de la $cutKey
                //Cette clé est située au moins une ligne après
                $cutIndex++;
            }
            next($input);
        }
        reset($input);
        $leftPart = array_slice($input, 0, $cutIndex);
        $rightPart = array_slice($input, $cutIndex);
        return array_merge($leftPart, $injection, $rightPart);
    }
    else
    {
        return 0;
    }
}

function array_orderByKey($model, $shuffled, $countControl = false)
{
    if(count($model) === count($shuffled) OR !$countControl)
    {
        //Le bon compte, ou on compte pas
        $ordered = [];
        foreach($model as $key => $value)
        {
            if(isset($shuffled[$key]))
            {
                $ordered[] = $shuffled[$key];
            }
            else
            {
                var_dump('Error: array_orderByKey: !isset($shuffled[$key])', $shuffled, $key);
            }
        }
        return $ordered;
    }
    else
    {
        var_dump('Error: array_orderByKey: count($model) == ' . count($model) . ' != count($shuffled) == ' . count($shuffled));
    }
}

function array_minus($positiveArray, $negativeArray, $identification = 'value')
{
    foreach($positiveArray as $key => $value)
    {
        if(
            ($identification === 'value' AND array_search($value, $negativeArray)) OR
            ($identification === 'key' AND isset($negativeArray[$key])) OR
            ($identification === 'both' AND array_search($value, $negativeArray) AND isset($negativeArray[$key]))
        )
        {
            unset($positiveArray[$key]);
        }
    }
    return $positiveArray;
}

function array_spliceByKeys($input, $keys, $replacement = NULL)
{
    $keys = (array)$keys;
    
    foreach($input as $key => $value)
    {
        if(in_array($key, $keys))
        {
            //Cette clé doit se faire splicer
            array_splice($input, array_search($key, $keys) + 1, 1, $replacement);
        }
    }
    return $input;
}

function array_spliceByValues($input, $values, $replacement = NULL)
{
    $values = (array)$values;
    foreach($input as $value)
    {
        if(in_array($value, $values))
        {
            //Cette clé doit se faire splicer
            array_splice($input, array_search($value, $values) + 1, 1, $replacement);
        }
    }
    return $input;
}

function parseArray($inputString, $spaces = true)
{
    $result = [];
    $inputArray = $spaces ? explode(', ', $inputString) : explode(',', $inputString);
    foreach($inputArray as $keyValueString)
    {
        if(!(inString($keyValueString, '=>')))
        {
            //S'il n'y a pas les flèches comme il faut
            return 0;
        }
        $keyValueArray = $spaces ? explode(' => ', $keyValueString) : explode('=>', $keyValueString);
        $result = array_merge($result, [$keyValueArray[0] => $keyValueArray[1]]);
    }
    return $result;
}

/*
Fonctions url générales
*/

function rebuildGetString($forHtml = false)
{
    $getString = '';
    $separator = '?';
    foreach($_GET as $getKey => $getValue)
    {
        $brackets = '';
        if(is_array($getValue))
        {
            $brackets = '[]';
        }
        foreach((array)$getValue as $getSubValue)
        {
            $getString .= "$separator$getKey$brackets=$getSubValue";
            $separator = $forHtml ? '&amp;' : '&';
        }
    }
    return $getString;
}

function htmlBuildHiddens($globalType = 'GET')
{
    return htmlInputs($globalType === 'GET' ? $_GET : $_POST);
}

/*
  ______                   _    _                       _____   ____   _                 __           __              _            
 |  ____|                 | |  (_)                     / ____| / __ \ | |               /_/          /_/             | |           
 | |__  ___   _ __    ___ | |_  _   ___   _ __   ___  | (___  | |  | || |        __ _   ___  _ __    ___  _ __  __ _ | |  ___  ___ 
 |  __|/ _ \ | '_ \  / __|| __|| | / _ \ | '_ \ / __|  \___ \ | |  | || |       / _` | / _ \| '_ \  / _ \| '__|/ _` || | / _ \/ __|
 | |  | (_) || | | || (__ | |_ | || (_) || | | |\__ \  ____) || |__| || |____  | (_| ||  __/| | | ||  __/| |  | (_| || ||  __/\__ \
 |_|   \___/ |_| |_| \___| \__||_| \___/ |_| |_||___/ |_____/  \___\_\|______|  \__, | \___||_| |_| \___||_|   \__,_||_| \___||___/
                                                                                 __/ |                                             
                                                                                |___/                                              
*/

function sqlConnect($host, $dbname, $user, $pwd, $errmode = 'exception')
{
    try
    {
        $inputErrmode = $errmode === 'exception' ? PDO::ERRMODE_EXCEPTION : PDO::ERRMODE_SILENT;
        return new PDO("mysql:host=$host;dbname=$dbname", $user, $pwd, [PDO::ATTR_ERRMODE => $inputErrmode]);
    }
    catch(Exception $e)
    {
        die('Connection error: ' . $e -> getMessage());
    }
}

function sqlDbManageConfig($db)
{
    global $dbName, 
    $pathToProperties,
    $tableConfigTableName, 
    $columnsConfigTableName, 
    $columnsConfigTableName, 
    $joiningDataTableName, 
    $pivotDatasTableName, 
    $showKeyColumnName, 
    $tempSuffix,
    $usersTableName,
    $userPrimaryKey,
    $userNameKey,
    $userFirstNameKey,
    $userPasswordKey,
    $tokenKey;
    
    //Créer les tables de configuration si elles n'existent pas
    sqlQuery($db, 'FLUSH PRIVILEGES; CREATE USER \'dbLogger\'@localhost IDENTIFIED BY \'' . parse_ini_file($pathToProperties)[2] . '\';', false);
    sqlQuery($db, 'FLUSH PRIVILEGES; CREATE USER \'dbManager\'@localhost IDENTIFIED BY \'' . parse_ini_file($pathToProperties)[3] . '\';', false);
    
    if(!sqlInDb($db, 'check_table_exists', 'procedure', $dbName))
    {
        sqlQuery(
            $db, 
            "CREATE DEFINER=`root`@`localhost` PROCEDURE `check_table_exists`(IN `table_name` VARCHAR(100))
            BEGIN
               DECLARE CONTINUE HANDLER FOR SQLSTATE '42S02' SET @err = 1;
               SET @err = 0;
               SET @table_name = table_name;
               SET @sql_query = CONCAT('SELECT 1 FROM ',@table_name);
               PREPARE stmt1 FROM @sql_query;
               IF (@err = 1) THEN
                   SET @table_exists = 0;
               ELSE
                   SET @table_exists = 1;
                   DEALLOCATE PREPARE stmt1;
               END IF;
            END", 
            false
        );
    }
    
    sqlQuery($db, "
        DROP FUNCTION IF EXISTS SPLIT_STRING;
        CREATE FUNCTION SPLIT_STRING(str VARCHAR(255), delim VARCHAR(12), pos INT)
        RETURNS VARCHAR(255)
        RETURN REPLACE(SUBSTRING(SUBSTRING_INDEX(str, delim, pos),
               LENGTH(SUBSTRING_INDEX(str, delim, pos-1)) + 1),
               delim, '');
        CREATE TABLE IF NOT EXISTS $tableConfigTableName(
            ID_table INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY,
            ID_graph_abscissa INTEGER,
            ID_graph_ordinate INTEGER,
            $showKeyColumnName VARCHAR(255) NOT NULL,
            Configuration TINYINT(1) NOT NULL DEFAULT 0,
            Has_graph TINYINT(1) NOT NULL DEFAULT 0,
            Hidden TINYINT(1) NOT NULL DEFAULT 1,
            Grants_hidden TINYINT(1) NOT NULL DEFAULT 0,
            Is_full_pivot_join_table TINYINT(1) NOT NULL DEFAULT 0,
            Is_inter_join_table TINYINT(1) NOT NULL DEFAULT 0
        )ENGINE = INNODB;
        CREATE TABLE IF NOT EXISTS $columnsConfigTableName(
            ID_column INTEGER NOT NULL AUTO_INCREMENT,
            ID_table INTEGER NOT NULL DEFAULT 0 COMMENT 'showKey',
            $showKeyColumnName VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'showKey',
            Hidden TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY(ID_column)
        )ENGINE = INNODB;
        CREATE TABLE IF NOT EXISTS $joiningDataTableName(
            constraintName VARCHAR(255) NOT NULL COMMENT 'showKey',
            columnAlias VARCHAR(255) DEFAULT NULL,
            PRIMARY KEY (constraintName)
           )ENGINE = InnoDB;
        CREATE TABLE IF NOT EXISTS $pivotDatasTableName(
            ID_table INTEGER NOT NULL COMMENT 'showKey',
            ID_joining_table INTEGER COMMENT 'showKey',
            ID_extra_table INTEGER COMMENT 'showKey',
            extraRequest VARCHAR(255) DEFAULT '',
            PRIMARY KEY(ID_table, ID_joining_table, ID_extra_table)
        )ENGINE = INNODB;
        CREATE TABLE IF NOT EXISTS $usersTableName(
            $userPrimaryKey INTEGER PRIMARY KEY NOT NULL AUTO_INCREMENT,
            $userNameKey VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'showKey',
            $userFirstNameKey VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'showKey',
            $userPasswordKey VARCHAR(255) NOT NULL DEFAULT '',
            $tokenKey VARCHAR(255) NOT NULL DEFAULT ''
        )ENGINE = INNODB;",
        false);

    if(!sqlGetJoinList($db, $tableConfigTableName) AND !sqlGetJoinList($db, $columnsConfigTableName) AND !sqlGetJoinList($db, $pivotDatasTableName))
    //[shit-flag]
    {
        sqlQuery($db, "
            ALTER TABLE $tableConfigTableName 
                ADD UNIQUE KEY `UNIQUE_TABLE_NAMES`(Name),
                ADD KEY `FOREIGN_graph_abscissa`(ID_graph_abscissa),
                ADD KEY `FOREIGN_graph_ordinate`(ID_graph_ordinate),
                ADD CONSTRAINT FOREIGN KEY (`ID_graph_abscissa`) REFERENCES `$columnsConfigTableName` (`ID_column`),
                ADD CONSTRAINT FOREIGN KEY (`ID_graph_ordinate`) REFERENCES `$columnsConfigTableName` (`ID_column`);
    
            ALTER TABLE $columnsConfigTableName
                ADD UNIQUE KEY `UNIQUE_COLUMN_PER_TABLE` (ID_table, Name),
                ADD CONSTRAINT FOREIGN KEY (ID_table) REFERENCES `$tableConfigTableName` (ID_table) ON DELETE CASCADE;
    
            ALTER TABLE $pivotDatasTableName
                ADD KEY `FOREIGN_table`(ID_table),
                ADD KEY `FOREIGN_joining_table`(ID_joining_table),
                ADD KEY `FOREIGN_extra_table`(ID_extra_table),
                ADD CONSTRAINT FOREIGN KEY (ID_table) REFERENCES `$tableConfigTableName` (ID_table) ON DELETE CASCADE,
                ADD CONSTRAINT FOREIGN KEY (ID_joining_table) REFERENCES `$tableConfigTableName` (ID_table) ON DELETE CASCADE,
                ADD CONSTRAINT FOREIGN KEY (ID_extra_table) REFERENCES `$tableConfigTableName` (ID_table) ON DELETE CASCADE;", 
                false
        );
    }

    sqlQuery($db, "
        GRANT CREATE USER ON *.* TO 'dbLogger'@localhost;
        GRANT SELECT, UPDATE, INSERT ON $dbName.$usersTableName TO 'dbLogger'@localhost;
        GRANT SELECT, UPDATE ON mysql.user TO 'dbLogger'@localhost;
        GRANT SELECT, INSERT ON $dbName.$usersTableName TO 'dbManager'@localhost;
        GRANT SELECT ON mysql.* TO 'dbManager'@localhost;
        GRANT SELECT, UPDATE, INSERT, DELETE, CREATE TEMPORARY TABLES ON $dbName.* TO 'dbManager'@localhost WITH GRANT OPTION;
        GRANT EXECUTE ON PROCEDURE $dbName.check_table_exists TO 'dbManager'@localhost;",
        false
    );
        
    foreach(
    [
        $tableConfigTableName => ['Label' => $showKeyColumnName, 'primaryKey' => 'ID_table', 'nullTuple' => 'No table'], 
        $columnsConfigTableName => ['Label' => $showKeyColumnName, 'primaryKey' => 'ID_column', 'nullTuple' => 'Name']
    ] as $configTable => $info)
        if(!sqlDataExists($db, $configTable, $showKeyColumnName, $info['nullTuple']))
        {
            sqlInsert($db, $configTable, $info['Label'], $info['nullTuple']);
            sqlUpdate($db, $configTable, $info['Label'], $info['nullTuple'], $info['primaryKey'], 0);
        }

    //Insérer des occurences dans la table de config pour chaque table trouvée dans la base de données
    $dbTables = sqlGetTables($db);
    foreach($dbTables as $dbTable) 
    {
        //Pour chaque table
        if(!sqlDataExists($db, $tableConfigTableName, $showKeyColumnName, $dbTable))
        {
            //Créer une image de cette table dans la table de config des tables
            sqlInsert($db, $tableConfigTableName, $showKeyColumnName, $dbTable);
        }

        //Obtenir des informations sur ses contraintes de clés étrangères
        $joiningInfos = sqlGetJoinList($db, $dbTable, true);//sqlGetTableJoiningInfo($db, $dbTable, true);
        //var_dump($joiningInfos);
        $ID_table = isset($joiningInfos[0]['ID_root_table'])
                        ?   $joiningInfos[0]['ID_root_table'] 
                        :   sqlSelect($db, "SELECT ID_table FROM $tableConfigTableName WHERE Name = ?", $dbTable, 'drop')[0]['ID_table'];
                        
        foreach($joiningInfos as $constraintName => $joiningInfo) 
            if(
                !sqlDataExists($db, $joiningDataTableName, ['constraintName'], $constraintName) AND
                !in_array($dbTable, sqlGetJoinTables($db, 'pivot'))
            )
            {   
                //Pour chaque info récoltée sur les jointures où l'information dans la table de config ne figure pas
                sqlInsert(
                    $db, 
                    $joiningDataTableName, 
                    [
                        'constraintName'
                    ], 
                    [
                        $constraintName
                    ]
                );
            }

        $tableColumnsNames = sqlGetColumnsProperties($db, $dbTable, 'Field');
        $tableColumns = 
                sqlSelect($db, 
                    "SELECT ID_column, $showKeyColumnName
                    FROM $columnsConfigTableName 
                    WHERE $columnsConfigTableName.ID_table = ?", $ID_table, 'drop'
                );
        
        //var_dump($dbTable, $tableColumns);

        foreach($tableColumns as $tableColumn)
            if(!in_array($tableColumn[$showKeyColumnName], sqlGetColumnsProperties($db, $dbTable, 'Field')))
            {
                sqlDelete($db, $columnsConfigTableName, 'ID_column', $tableColumn['ID_column']);
            }
        
        foreach($tableColumnsNames as $tableColumnName) if(!sqlDataExists($db, $columnsConfigTableName, ['ID_table', $showKeyColumnName], [$ID_table, $tableColumnName]))
        {
            //Pour chaque colonne de la table non répertoriée dans la table de config des colonnes
            if
            (
                array_intersect(
                    (array)sqlGetShowKey($db, $tableColumnsNames), 
                    (array)sqlGetColumnsProperties($db, $tableColumnName, ['Field'])
                )
            )
            {
                //Si la colonne à insérer fait partie de la clé primaire de la table
                $hidden = 1;
            }
            else
            {
                $hidden = 0;
            }

            //Créer les colonnes dans la table de config correspondante 
            sqlInsert($db, $columnsConfigTableName, 
                ['ID_table', $showKeyColumnName, 'Hidden'], 
                [sqlSelect($db, "SELECT ID_table FROM $tableConfigTableName WHERE Name = ?", $dbTable, 'drop')[0]['ID_table'], $tableColumnName, $hidden]
            );
        }

        if(isset($_GET['restructure'])) sqlManagePivotForeignKeysDatas($db, $dbTable, true);
    }
    $configuratedTables = sqlSelect($db, "SELECT *, CONCAT($tableConfigTableName.$showKeyColumnName, '$tempSuffix') AS Temp_name FROM $tableConfigTableName WHERE ID_table != 0", NULL, 'drop');
    $configuratedTablesNames = array_column($configuratedTables, $showKeyColumnName);

    //Supprimer des occurences dans la table config pour chaque table non trouvée dans la base de données
    foreach($configuratedTablesNames as $configuratedTableName)
        if(!in_array($configuratedTableName, $dbTables) AND $configuratedTableName != $showKeyColumnName)
        {
            sqlDelete($db, $tableConfigTableName, $showKeyColumnName, $configuratedTableName);
        }

    return array_combine($configuratedTablesNames, $configuratedTables);
}

function sqlManagePivotForeignKeysDatas($db, $tableName, $updatePivotTable = false, $userGrants = [], $columnsAttributes = [], &$pivotPrimaryValues = [], &$selectArray = [], &$showKeyJoinings = [], $tableRename = '')
{
    global $startTime, $dbName, $columnsConfigTableName, $pivotDatasTableName, $tableConfigTableName, $showKeyColumnName;
    $tableRename = (bool)$tableRename ? $tableRename : $tableName;
    $pivotSelectArray = [];
    
    //var_dump("sqlManagePivotForeignKeysDatas() begins for $tableName: " . (string)(microtime(true) - $startTime));
    
    //Obtention des informations de pivots générales sur toute la base de données depuis la table de config
    $dbPivotDatas = sqlSelect($db,
    "SELECT 
        originalTable_.$showKeyColumnName AS rootTable,
        pivotJoinTable_.$showKeyColumnName AS pivotJoinTable,
        extraTable_.$showKeyColumnName AS extraTable,
        extraRequest
    FROM $pivotDatasTableName
    JOIN $tableConfigTableName originalTable_
        ON originalTable_.ID_table = $pivotDatasTableName.ID_table
    JOIN $tableConfigTableName pivotJoinTable_
        ON pivotJoinTable_.ID_table = $pivotDatasTableName.ID_joining_table
    JOIN $tableConfigTableName extraTable_
        ON extraTable_.ID_table = $pivotDatasTableName.ID_extra_table",
    NULL, 'drop');
    
    //Obtention des informations de pivots spécifiques à $tableName depuis les contraintes
    $showKeyJoinings = $pivotDatas = [];
    foreach(sqlGetJoinTables($db, 'pivot') as $pivotJoinTableName)
    {
        //Pour chaque table de jointures devant avoir un tuple par assemblage possible
        foreach(sqlGetJoinList($db, $pivotJoinTableName) as $constraintName => $pivotJoinTools)
        {
            //Ici, la table root est la table de pivot
            $pivotDatasKey = $pivotJoinTools['rootTable']['Name']/* . '-' . $pivotJoinTools['extraTable']['Name']*/;
            $tmp_pivotDatas[$pivotDatasKey]['pivotTable']['primaryKey'] = $pivotJoinTools['rootTable']['primaryKey'];
            $tmp_pivotDatas[$pivotDatasKey]['pivotTable']['Name'] = $pivotJoinTools['rootTable']['Name'];
            $tmp_pivotDatas[$pivotDatasKey]['pivotTable']['showKey'] = $pivotJoinTools['rootTable']['showKey'];
            
            if($tableName === $pivotJoinTools['extraTable']['Name'])
            {
                //Si le nom de la table extra avant concaténation est celui de la table séléctionnée
                //Alors la table extra avant concaténation est la table root après concaténation
                //pivotRootJoinKey vaut ici les colonnes de jointure:
                //  -A la table root 
                //  -DE la table pivot
                $tmp_pivotDatas[$pivotDatasKey]['rootTable']['pivotJoinKey'] = $pivotJoinTools['rootTable']['joinKey'];
                $tmp_pivotDatas[$pivotDatasKey]['rootTable']['joinKey'] = $pivotJoinTools['extraTable']['joinKey'];
                $tmp_pivotDatas[$pivotDatasKey]['rootTable']['Name'] = $tableName;    //($tableName === $pivotJoinTools['extraTable']['Name'])
            }
            else
            {
                //Si le nom de la table extra avant concaténation n'est pas celui de la table séléctionnée
                //Alors la table extra avant concaténation est la table extra après concaténation
                //pivotExtraJoinKey vaut ici les colonnes de jointure:
                //  -A la table extra 
                //  -DE la table pivot
                $tmp_pivotDatas[$pivotDatasKey]['extraTable']['pivotJoinKey'] = $pivotJoinTools['rootTable']['joinKey'];
                $tmp_pivotDatas[$pivotDatasKey]['extraTable']['joinKey'] = $pivotJoinTools['extraTable']['joinKey'];
                $tmp_pivotDatas[$pivotDatasKey]['extraTable']['Name'] = $pivotJoinTools['extraTable']['Name'];
            }
            if(array_minus($pivotJoinTools['rootTable']['joinKey'], $pivotJoinTools['rootTable']['showKey']))
                //Si la clé de jointure de la table root avant concaténation n'est pas entièrement contenue dans sa showKey
                $pivotDatas[$pivotDatasKey] = 
                    array_replace(
                        isset($pivotDatas[$pivotDatasKey])
                            ?   $pivotDatas[$pivotDatasKey]
                            :   [],
                        $tmp_pivotDatas[$pivotDatasKey]
                    );
            else 
                $showKeyJoinings[$pivotDatasKey] = 
                    array_replace(
                        isset($showKeyJoinings[$pivotDatasKey])
                            ?   $showKeyJoinings[$pivotDatasKey]
                            :   [],
                        $tmp_pivotDatas[$pivotDatasKey]
                    );
            //var_dump($pivotJoinTools, $pivotDatasKey);
        }
    }
    

    //$pivotDatas a pour clés les tables de pivot et pour données :
    //---leur clé sql les liant à $tableName (rootKey)
    //---le nom de la table extra (extraTable)
    //---leur clé sql les liant à extraTable (extraKey)

    //$dbPivotDatas sait tout des informations de pivot
    //Il à pour chaque sous-valeur:
    //---Le nom d'une table racine (rootTable)
    //---Le nom d'une table extra (extraTable)
    //---Le nom de la table de pivot les reliant (pivotJoinTable)
    //---Une requête pour filtrer les résultats sur la table extra (extraRequest)
    
    //var_dump($tableName, $pivotDatas, $showKeyJoinings, $dbPivotDatas);

    //Récupération des valeurs primaires des tables
    $tablePrimaryValues = [];
    foreach(sqlGetTables($db) as $table)
    {
        //Pour chaqune des trois table (root, join, extra), récupération des valeurs primaires
        $result = sqlSelect($db,
        [
            'SELECT' => 
            [
                [
                    'Database' => $dbName, 
                    'Table' => $tableConfigTableName,
                    'Name' => 'ID_table'
                ]
            ],
            'FROM' =>
            [
                [
                    'Database' => $dbName, 
                    'Table' => $tableConfigTableName
                ]
            ],
            'WHERE' =>
            [
                [
                    'Database' => $dbName, 
                    'Table' => $tableConfigTableName,
                    'Key' => $showKeyColumnName,
                    'Operator' => '=',
                    'Value' => $table
                ]
            ]
        ]);
        $tablePrimaryValues[$table] = isset($result[0]['ID_table'])
            ?   $result[0]['ID_table']
            :   [];
    }
    
    foreach($pivotDatas as $pivotData)
    if(
        isset($pivotData['rootTable']['Name']) AND 
        $tableName === $pivotData['rootTable']['Name'] AND
        array_minus($pivotData['extraTable']['pivotJoinKey'], $pivotData['pivotTable']['showKey'])
    )
    {
        //Pour chaque donnée de pivot concernant la table
        //Où la pivotExtraJoinKey n'est pas entièrement contenue dans la pivotShowKey

        //Mise à jour de la table de config des pivots si besoins / demandé
        $pivotJoinTable = $pivotData['pivotTable']['Name'];
        if(
            (
                !in_array($pivotJoinTable, array_column($dbPivotDatas, 'pivotJoinTable')) OR
                !in_array($tableName, array_column($dbPivotDatas, 'rootTable')) OR
                !in_array($pivotData['extraTable']['Name'], array_column($dbPivotDatas, 'extraTable'))
            ) AND 
            $updatePivotTable
        )
        {
            //Pour chaque donnée de pivot ne figurant pas dans la table des informations de pivot
            //  ->  Mise à jour depuis les contraintes vers la table $pivotDatasTableName
           
            //Insertion des infomations de pivot dans la table de config correspondante
            //var_dump("$tableName -> $pivotJoinTable -> $pivotData[extraTableName]");
            sqlInsert($db, $pivotDatasTableName, 
            [
                'ID_table', 
                'ID_joining_table', 
                'ID_extra_table'
            ], 
            [
                $tablePrimaryValues[$tableName], 
                $tablePrimaryValues[$pivotJoinTable], 
                $tablePrimaryValues[$pivotData['extraTable']['Name']]
            ]);
        }
        
        //Combinaison des données de pivot spécifiques depuis les contraintes aux données de pivot générales depuis la table de config
        if( 
            $userGrants AND
            isset($userGrants[$dbName . '.' . $pivotData['extraTable']['Name']], $userGrants["$dbName.$pivotJoinTable"]) AND
            in_array('SELECT', $userGrants[$dbName . '.' . $pivotData['extraTable']['Name']]['tableswide permissions'][$pivotData['extraTable']['Name']]) AND
            in_array('SELECT', $userGrants["$dbName.$pivotJoinTable"]['tableswide permissions'][$pivotJoinTable])
        )
        {
            //Si l'utilisateur à les droits nécessaires pour visualiser la pivot

            $pivotColumns = [];
            foreach($dbPivotDatas as $dbPivotData)
            {
                //Pour chaque jointure de pivot existant dans la table de config
                if($dbPivotData['pivotJoinTable'] === $pivotJoinTable AND
                    $dbPivotData['rootTable'] === $tableName AND
                    $dbPivotData['extraTable'] === $pivotData['extraTable']['Name'])
                {
                    //Si les données générales qui savent tout mais qui savent aussi ce qui sert à rien correspondent aux données qui savent rien mais qui parlent quand même de ce qu'on veut
                    //Alors on y ajoute ce qu'il nous manque
                    $pivotData['extraRequest'] = $dbPivotData['extraRequest'];
                    $pivotData['pivotShowKey'] = array_column(sqlGetShowKey($db, $pivotJoinTable), 'Name');
                    
                    //Donner un nom aux colonnes de pivot
                    foreach($pivotData['pivotShowKey'] as $nIndex => $pivotJoiningShowColumn)
                    {
                        //Pour chaque colonne de pivot
                        $pivotData['rootColumnPreviousTexts'][$nIndex] = $pivotData['extraTable']['Name'] . ' - ' . $pivotJoiningShowColumn . ' - ';
                    }
                }
            }

            //Remise en forme de $pivotColumns: $pivotColumns[0] devient les noms de colonnes de jointures à afficher, et 
            //                                  $pivotColumns[1] devient les textes d'accompagnement
            $pivotColumns = array_column(array_cross($pivotColumns), key($pivotColumns));
            if(array_intersect(sqlGetColumnsProperties($db, $pivotJoinTable, 'Field'), (array)$pivotData['pivotTable']['showKey']) AND !isset($_GET['puretable']))
            {
                //Si la table de jointure ciblée possède bien les colonnes à afficher
                $pivotPrimaryValues[] = 
                sqlPivot(
                    $db,
                    $tableName, //Table originale
                    $tableRename, //Table temporaire (copiée de la racine)
                    $pivotData,   //Donnée de pivot
                    $columnsAttributes,
                    $selectArray,
                    ''  //...Qui s'ajoutent après cette colonne de $originTable
                );
            }
        }
    }
    //var_dump("sqlManagePivotForeignKeysDatas() ends for $tableName: " . (string)(microtime(true) - $startTime));
    return $columnsAttributes;
}

//Cette fonction crée un token pour un utilisateur s'il s'identifie correctement
function sessionTokenize($db, $usersTableName, $userPrimaryValue, $plainTextPasswordValue, &$error = NULL)
{
    global $nextTimeUseDisconnect, $userPrimaryKey, $userNameKey, $userFirstNameKey, $userPasswordKey, $tokenKey;
    if(sqlDataExists($db, $usersTableName, $userPrimaryKey, $userPrimaryValue))
    {
        //Si l'utilisateur prétendu existe
        $userInfo = sqlSelect($db, "SELECT $userPrimaryKey, $userPasswordKey, $tokenKey FROM $usersTableName WHERE $userPrimaryKey = ?", $userPrimaryValue)[0];
        $nextTimeUseDisconnect = $userInfo[$tokenKey] ? 1 : 0;
        if(password_verify($plainTextPasswordValue, $userInfo[$userPasswordKey]))
        {
            //Si l'utilisateur valide son mot de passe
            //On crée $tokenValue, une chaîne de charactères de longueur fixe et complètement aléatoire
            $tokenValue;
            do
            {
                //Tant qu'un autre utilisateur à le même token ou au moins une fois,
                //En créer un autre
                $tokenValue = str_shuffle(password_hash(time(), PASSWORD_DEFAULT));
            }while(sqlDataExists($db, $usersTableName, $tokenKey, $tokenValue));

            //On insère cette valeur dans un champ de l'utilisateur. C'est elle qui sera contôlée
            sqlUpdate($db, $usersTableName, $userPrimaryKey, $userPrimaryValue, $tokenKey, $tokenValue);

            //On indique si demandé qu'il n'y a pas d'erreur
            if(isset($error))
            {
                $error = 0;
            }

            //On return le token, rien d'autre ne sera utilisé afin de limiter la casse en cas de hack de session
            return $tokenValue;
        }
        else
        {
            if(isset($error))
            {
                $error = 'password-error';
            }
            return 0;
        }
    }
    else
    {
        if(isset($error))
        {
            $error = 'user-error';
        }
        return 0;
    }
}

function sessionCheck($dbLogger, $dbManager)
{
    global $dbName, $usersTableName, $userPrimaryKey, $tokenKey;
    if(isset($_SESSION[$tokenKey]) AND sqlDataExists($dbLogger, $usersTableName, $tokenKey, $_SESSION[$tokenKey]))
    {
        $userInfo = sqlSelect($dbLogger, "SELECT $userPrimaryKey FROM $usersTableName WHERE $tokenKey = ?", $_SESSION[$tokenKey])[0];
        sqlCreateAccount($dbLogger, $dbManager, $userInfo[$userPrimaryKey]);
        return 1;
    }
    else
    {
        return 0;
    }
}

function sqlGetMysqlUserInfo($db, $identificator = '', $password = '', $by = 'Token')
{
    global $dbName;
    if($by === 'Token')
    {
        //Mot de passe renseigné
        $whereClause = "$dbName.utilisateurs.token = ? AND mysql.user.authentication_string = PASSWORD(?)";
        $execute = [$identificator, $password];
    }
    elseif($by === 'justToken')
    {
        //Aucun mot de passe renseigné
        $whereClause = "$dbName.utilisateurs.token = ?";
        $execute = $identificator;
    }
    elseif($by === 'User')
    {
        $whereClause = "mysql.user.User = '?'";
        $execute = $identificator;
    }
    $return = sqlSelect($db, "SELECT mysql.user.Host, mysql.user.User
                            FROM $dbName.utilisateurs 
                                JOIN mysql.user 
                                    ON CONCAT('u', utilisateurs.ID_utilisateur) = mysql.user.User
                                    WHERE $whereClause", $execute, 'drop');
    return (int)(count($return) == 1) ? $return[0] : $return;
}

function sqlCreateAccount($dbLogger, $dbManager, $userPrimaryValue)
{
    global $usersTableName, $tokenKey, $userPrimaryKey, $userNameKey, $userFirstNameKey, $userPasswordKey, $host, $pathToProperties, 
    $tableConfigTableName, $columnsConfigTableName , $joiningDataTableName, $pivotDatasTableName;

    //Obtenir les informations de l'utilisateur
    $userInfo = sqlSelect($dbLogger, "SELECT $userPrimaryKey, $userPasswordKey FROM $usersTableName WHERE $userPrimaryKey = ?", $userPrimaryValue)[0];

    if(!sqlUserExists($dbLogger, "u$userInfo[$userPrimaryKey]"))
    {
        //Aucun compte mysql trouvé, il faut en créer un
        sqlUser($dbLogger, 'CREATE', "u$userInfo[$userPrimaryKey]", parse_ini_file($pathToProperties)[2], $host);
        foreach(sqlGetTables($dbLogger) as $table) 
        if(!in_array($table, [$tableConfigTableName, $columnsConfigTableName , $joiningDataTableName, $pivotDatasTableName, $usersTableName]))
        {
            sqlUser($dbLogger, 'GRANT', "u$userInfo[$userPrimaryKey]", parse_ini_file($pathToProperties)[2], $host, $table, ['SELECT', 'UPDATE', 'DELETE', 'INSERT']);
        }
        sqlUser($dbManager, 'GRANT', "u$userInfo[$userPrimaryKey]", parse_ini_file($pathToProperties)[2], $host, '*', 'CREATE TEMPORARY TABLES');
        sqlUser($dbManager, 'GRANT', "u$userInfo[$userPrimaryKey]", parse_ini_file($pathToProperties)[2], $host, 'check_table_exists', ['EXECUTE']);
    }
}

function sqlArrayToClause(  $clauseType,    //Commande MySQL comportant une clause
                            $elementsProperties,    //array([Name => '', Rename => ''])
                            $includeKeyWord = true,    //Inclure le mot clé de la clause
                            $guessDbName = false)
{
    global $dbName;
    $result = '';

    if($elementsProperties)
    {//var_dump($elementsProperties);
        //Si l'élément n'est pas vide
        $firstLoop = true;
        $keyWord = $includeKeyWord ? "$clauseType " : ' ';
        
        foreach($elementsProperties as $elementProperties) if($elementProperties)
        {
            //Pour chaque élément
            //Préparation de variables
            //if(!isset($elementProperties['Database'])) var_dump('Missing db name:', $clauseType, $elementProperties);
            $fieldQuote = (isset($elementProperties['Ignore']) AND in_array('Field Quotes', $elementProperties['Ignore']))
                                ?   ''
                                :   '`';
            $databaseName = (isset($elementProperties['Database']))
                                ? "`$elementProperties[Database]`"
                                : ($guessDbName
                                    ? $dbName
                                    : NULL);

            //if(!isset(array_funnel($elementProperties, ['Table' => 'dest', 'rootTableName' => 'dest'])['dest'])) var_dump('Missing table name:', $clauseType, $elementProperties);

            $tableName = 
            $fieldQuote . 
            (
                isset($elementProperties['Table'])
                    ?   $elementProperties['Table']
                    :   (
                            isset($elementProperties['rootTable']['Name'])
                                ?   $elementProperties['rootTable']['Name']
                                :   ''
                        )
            )
            . $fieldQuote;
            $tableId = implode('.', [$databaseName, $tableName]);
            
            if(!in_array($clauseType, ['FROM']) AND !inString($clauseType, 'JOIN')) 
            {
                $columnName = $fieldQuote . array_funnel($elementProperties, ['Name' => 'dest', 'Key' => 'dest'])['dest'] . $fieldQuote;
                $columnId = !(isset($elementProperties['Ignore']) AND in_array('Table', $elementProperties['Ignore'])) 
                    ?   implode('.', [$tableId, $columnName])
                    :   $columnName;
            }
            
            //Selon la clause MySQL
            switch($clauseType)
            {
                case 'SELECT DISTINCT':
                case 'SELECT':
                    //Pas la première fois
                    $result .= ($firstLoop === true ? $keyWord : ', ');
    
                    //Si l'élément à un nom
                    if(isset($elementProperties['Rename']) AND $elementProperties['Rename'] != '')
                    {
                        //Si l'on doit renomer la colonne
                        $result .= " $columnId AS `$elementProperties[Rename]`";
                    }
                    else
                    {
                        //Si le nom de la colonne convient
                        $result .= " $columnId";
                    }
                    break;
    
                case 'FROM':
                    $result = $keyWord . $tableId;
                    break;
    
                case 'WHERE':
                    //WHERE ou operateur(AND, OR)
                    $result .= ($firstLoop ? $keyWord : " $elementProperties[Condition] ");
    
                    //Valeur de type string ?
                    $realValue = isset($elementProperties['Execute'])
                        ?   $elementProperties['Execute']
                        :   $elementProperties['Value'];
                        
                    $quote = (is_numeric($realValue) OR $realValue === NULL OR $elementProperties['Operator'] === 'REGEXP')
                        ? ''
                        : '\'';

                    $realValue = is_numeric($realValue)
                        ?   (int)$realValue
                        :   $realValue;

                    //if(ob_get_contents()) var_dump($elementProperties, $quote, is_string($elementProperties['Value']));
                    $value = $elementProperties['Value'] === NULL
                                ? 'NULL'
                                : $elementProperties['Value'];
    
                    //Condition
                    $result .= " $columnId $elementProperties[Operator] $quote$value$quote";
                    break;
    
                case 'JOIN':
                case 'INNER JOIN':
                case 'LEFT JOIN':
                case 'RIGHT JOIN':
                case 'NATURAL JOIN':
                    $extraTableNotation = $extraTableName = '';
                    if(isset($elementProperties['extraTable']['Rename']) AND $elementProperties['extraTable']['Rename'] != '')
                    {
                        //En renommant la table jointe (['extraTableRename'] spécifié ?)
                        $extraTableNotation = $elementProperties['extraTable']['Name'] . ' ' . $elementProperties['extraTable']['Rename'];
                        $extraTableName = $elementProperties['extraTable']['Rename'];
                    }
                    else
                    {
                        //Sans renommer la table jointe
                        $extraTableNotation = $elementProperties['extraTable']['Name'];
                        $extraTableName = $elementProperties['extraTable']['Name'];
                    }

                    $result .= $keyWord;
                    $firstJoinColumnsPassed = false;
                    foreach(array_values((array)$elementProperties['rootTable']['joinKey']) as $nIndex => $rootJoinColumn)
                    {
                        //Pour chaque colonne faisant partie de la rootJoinKey
                        $elementProperties['extraTable']['joinKey'] = (array)$elementProperties['extraTable']['joinKey'];
                        $result .= $firstJoinColumnsPassed
                            ?   ' AND '
                            :   $extraTableNotation . ' ON ';
                        $result .= $elementProperties['rootTable']['Name'] . ".`$rootJoinColumn` = $extraTableName." . ((array)$elementProperties['extraTable']['joinKey'])[$nIndex] . ' ';
                        $firstJoinColumnsPassed = true;
                    }
                    break;
    
                case 'ORDER BY':
                    $mean = $elementProperties['Mean'] === 'ASC' ? 'ASC' : 'DESC';
                case 'GROUP BY':
                    $mean = $clauseType === 'ORDER BY'
                        ?   $mean
                        :   '';
                    $result .= ($firstLoop ? $keyWord : ', ') . "$columnId $mean";
                    break;

                default:
                    $result = 0;
            }
            $firstLoop = false;
        }
    }
    
    return $result;
}

function sqlControlElements($db, $elements, $tablesDesignations, $keysDesignations, $whiteList = [])
//                                          -'Table'             -'Name'
//                                          -'extraTableName'    -'extraJoinKey'
//                                          -'rootTableName'     -'rootJoinKey'
{
    $killQuery = false;
    foreach($elements as $element)
    {
        //Pour chaque element (table, clé)
        foreach($tablesDesignations as $tablesDesignation) if(isset($element[$tablesDesignation]) AND $element[$tablesDesignation])
        {
            //Pour chaque élément désigné comme une table
            if(tableExists($db, $element[$tablesDesignation]))
            {
                foreach($keysDesignations as $keysDesignation)
                {
                    if(isset($element[$keysDesignation])) 
                    {
                        foreach((array)$element[$keysDesignation] as $subElement)
                            if(
                                isset($element[$keysDesignation]) AND 
                                inString($subElement, ';') /*OR 
                                isset($element[$keysDesignation]) AND 
                                !sqlIntable($db, $element[$tablesDesignation], $element[$keysDesignation])*/
                            )
                            {
                                //Pour chaque élément désigné comme une clé dans cette table
                                //var_dump($elements, $keysDesignation);
                                $killQuery = true;
                            }
                    }
                }
            }
            /*elseif(!sqlIndb($db, $element[$tablesDesignation], 'table', $element['Database']))
            {
                var_dump($element, $tablesDesignation);
                $killQuery = true;
            }*/
        }
    }
    return !$killQuery;
}

function sqlQuery($db, $query, $fetch = true, $numericKeys = 'keep')
{
    $results = [];
    $killQuery = false;
    if(is_array($query))
    {
        //Si la requête est envoyée sous forme de tableau
        $strQuery = '';
        foreach($query as $clause => $elements)
        {
            //Pour chaque clause
            $killQuery = false;//!sqlControlElements($db, $elements, ['Table', 'Name', 'Rename'], ['Name', 'joinKey', 'Key']);
            $strQuery .= ' ' . sqlArrayToClause($clause, $elements);
        }
        $query = $strQuery;
    }
    
    if(is_string($query) AND $query AND !$killQuery)
    {
        $request = $db -> query($query) or die(var_dump($db -> errorInfo()));
        if($fetch)
        {
            while($data = $request -> fetch())
            {
                $results[] = numericKeys($data, $numericKeys);
            }
        }
        $request -> closecursor();
    }
    return $results;
}

function sqlSelect($db, $query, $execute = NULL, $numericKeys = 'keep', $byPass = [], $seeQuery = false)
{
    global $tempSuffix;
    $results = [];
    $killQuery = false;
    if(is_array($query))
    {
        //Si la requête est envoyée sous forme de tableau
        //var_dump($query);
        $strQuery = '';
        foreach($query as $clause => $elements)
        {
            //Pour chaque clause
            /*$killQuery = 
            !(
                sqlControlElements($db, $elements, ['Table', 'rootTableName', 'extraTableRename'], ['Name', 'rootJoinKey', 'extraJoinKey', 'Key']) OR 
                in_array($clause, (array)$byPass) OR
                $clause === $byPass
            );*/
            $strQuery .= ' ' . sqlArrayToClause($clause, $elements);
        }
        $query = $strQuery;
        if($seeQuery)
        {
            echo str_replaces(
                [
                    $tempSuffix . '0' => '',
                    $tempSuffix . '1' => $tempSuffix . '0',
                    ',' => ',<br>',
                    'SELECT' => '<br><br>SELECT',
                    'FROM' => '<br>FROM',
                    'LEFT' => '<br>LEFT',
                    'ON ' => '<br>ON ',
                    'WHERE' => '<br>WHERE',
                    'AND' => '<br>AND',
                    'ORDER BY' => '<br>ORDER BY',
                    'GROUP BY' => '<br>GROUP BY'
                ], $query);
        }
    }
    
    if(is_string($query) AND $query AND !$killQuery)
    {
        if(!isset($execute))
        {
            $request = $db -> query($query);
        }
        else
        {
            $request = $db -> prepare($query) or die(var_dump($db -> errorInfo()));
            $request -> execute((array)$execute);
        }
        
        while($data = $request -> fetch())
        {
            $results[] = numericKeys($data, $numericKeys);
        }
        $request -> closecursor();
    }
    //if(ob_get_contents()) var_dump($killQuery, $results);
    return $killQuery ? [] : $results;
}

function sqlInsert($db, $table, $fields = [], $values = [])
{
    $success = 0;
    $abort = false;
    $fields = (array)$fields;
    $values = (array)$values;
    
    if(count($fields) != count($values))
    {
        //Contrôle de validité des arguments
        return $success;
    }

    //Construction des champs à entrer en chaîne de caractères pour la requête sql
    $str_qmarks = '';
    for($key = 0; $key < count($values); $key++)
    {
        //Pour chaque valeur
        if($fields[$key] === NULL OR $values[$key] === NULL) 
        {
            $abort = true;
        }
        else $str_qmarks .= '?, ';
    }
    $str_fields = implode(', ', $fields);
    $str_qmarks = rtrim($str_qmarks, ', ');
    
    //Requête
    if(!$abort)
    {
        $request = $db -> prepare("INSERT INTO $table($str_fields) VALUES($str_qmarks)") or die(var_dump($db -> errorInfo()));
        $success = $request -> execute($values);
        $request -> closecursor();
    }
    return $success;
}

function sqlUpdate($db, $table, $field_condition, $value_condition, $fields, $values, $condition = "AND", $operator = "=")
{
    $success = 0;
    $field_condition = (array)$field_condition;
    $value_condition = (array)$value_condition;
    if(count($field_condition) != count($value_condition))
    {
        //Pas autant de champs que de valeurs => return 0;
        return $success;
    }
    //Initialisations
    $setClause = "";
    $i = 0;
    $fields = array_values((array)$fields);
    $values = array_values((array)$values);
    while(isset($fields[$i], $values[$i]))
    {
        //Tant qu'il y a un champ
        $setClause = "$setClause`$fields[$i]` = ?";
        if(isset($fields[$i + 1], $values[$i + 1]))
        {
            //S'il qu'il y a un champ après
            $setClause = "$setClause, ";
        }
        $i++;
    }
    $execute = array_merge($values, $value_condition);
    $whereClause = "";
    if(count($field_condition) AND count($value_condition))
    {
        //S'il y a des conditions
        $whereClause = " WHERE ";
        if(count($field_condition) > 1 AND count($value_condition) > 1)
        {
            //S'il y a plus d'une condition
            for($i = 0; $i < count($field_condition); $i++)
            {
                //Pour chaque condition
                $whereClause = "$whereClause$field_condition[$i] = ?";
                if(isset($field_condition[$i + 1]))
                {
                    //S'il y a une condition après
                    $whereClause = "$whereClause $condition ";
                }
            }
        }
        else
        {
            //S'il n'y a qu'une seule condition
            $whereClause = "$whereClause`$field_condition[0]` = ?";
        }
    }

    //Requête
    //var_dump("UPDATE $table SET $setClause$whereClause", $execute);
    $request = $db -> prepare("UPDATE $table SET $setClause$whereClause") or die(var_dump($db -> errorInfo()));
    $success = $request -> execute($execute);
    $request -> closecursor();
    return $success;
}

function sqlDelete($db, $table, $fields, $values, $condition = "AND", $operator = "=")
{
    $success = 0;
    $fields = (array)$fields;
    $values = (array)$values;
    if(count($fields) != count($values))
    {
        //Pas autant de champs que de valeurs => return 0;
        return $success;
    }
    //Initialisations
    $whereClause = "";
    $i = 0;
    while(isset($fields[$i], $values[$i]))
    {
        //Tant qu'il y a une condition
        $whereClause = "$whereClause$fields[$i] $operator ?";
        if(isset($fields[$i + 1], $values[$i + 1]))
        {
            //S'il qu'il y a une condition après
            $whereClause = "$whereClause $condition ";
        }
        $i++;
    }
    $request = $db -> prepare("DELETE FROM $table WHERE $whereClause") or die(var_dump($db -> errorInfo()));
    $success = $request -> execute($values);
    $request -> closecursor();
    return $success;
}

function sqlIndb($db, $object, $type = 'table', $databases = '', $eachOrOne = true)
{
    $exists = false;
    if($databases === '*')
    {
        //Note: $eachOrOne == true: La fonction retournera (boolean)true si l'objet existe dans UNE base de données et non toutes.
        //Note: $eachOrOne == false: La fonction retournera (boolean)true si l'objet existe dans TOUTES les bases de données.
        //On récupère tous les noms de bases de données
        $databases_ = sqlGetDbs($db);
        
        foreach($databases_ as $database)
        {
            //Pour chaque base de données récupérée
            if($database != '*')
            {
                //  -!!! STACK OVERFLOW ALERT !!!-
                $exists = (int)$eachOrOne ? $exists && sqlIndb($db, $object, $type, $database) : $exists || sqlIndb($db, $object, $type, $database);
            }
        }
    }
    else
    {
        //On veut checker dans une ou plusieurs db
        $databases = (array)$databases;
        $object = (array)$object;
        foreach($databases as $database)
        {
            switch($type)
            {
                case 'table':
                    $exists = $exists || (bool)array_intersect($object, sqlGetTables($db, $database));
                    break;
        
                case 'procedure':
                    $exists = $exists ||  (bool)array_intersect($object, sqlGetProcedures($db, $database, 'Name'));
                    break;
        
                case 'both':
                    $exists = $exists ||  (bool)array_intersect($object, sqlGetTables($db, $database)) || in_array($object, sqlGetProcedures($db, $database));
                    break;
        
                default;
                    return 0;
                    break;
            }
        }
    }

    return $exists;
}

function sqlIntable($db, $tableName, $columns)
{
    if(sqlIndb($db, $tableName) OR tableExists($db, $tableName))
    {
        //Si la table est bien dans la db
        $columns = (array)$columns;
        $result = true;
        foreach($columns as $column)
            $result = in_array($column, sqlGetColumnsProperties($db, $tableName, 'Field')) ? $result : false;

        return $result;
    }
    else
    {
        return 0;
    }
}

function sqlDataExists($db, $tableName, $keys, $values, $condition = "AND")
{
    $keys = (array)$keys;
    $values = (array)$values;

    $query = "SELECT * FROM $tableName WHERE";
    foreach($keys as $key)
    {
        //Pour chaque colonne contrôlée
        if($query != "SELECT * FROM $tableName WHERE")
        {
            //Si la requête comporte au moins une condition d'égalité (cette condition sera vraie à chaque fois après la première)
            //Ajouter un séparateur ($condition) entre deux conditions d'égalités. Celles d'avant, et celle qui va être écrite
            $query = "$query $condition ";
        }

        //Ajouter une condition d'égalité
        $query = "$query $key = ?";
    }
    
    //Effectuer la requête et interpréter son résultat
    $data = sqlSelect($db, $query, $values);
    
    if($data != []) return true;
    else return false;
}

function sqlGetObjectType($db, $object)
{
    //Les différents types d'objets d'une base de données
    $types = ['table', 'procedure'];
    foreach($types as $type)
    {
        //Pour chaque type
        if(sqlIndb($db, $object, $type))
        {
            //S'il correspond au type de l'objet
            return $type;
        }
    }

    //Si aucun type ne correspondait
    return 0;
}

function sqlGetEnumAlphabet($db, $table, $column)
{
    //On sécurise les valeurs en entrée
    $securedTable = securedContentPick(sqlGetTables($db), $table);
    $securedColumn = securedContentPick(sqlGetColumnsProperties($db, $table, 'Field')['Field'], $column);

    //On récupère la caractéristique 'Type' de la seule occurence retour
    $type = sqlSelect($db, "SHOW COLUMNS FROM $securedTable LIKE '$securedColumn'")[0]['Type'];

    //On regarde avec une regex les valeurs possibles
    preg_match('/enum\((.*)\)$/', $type, $matches);

    if(inString($type, 'enum'))
    {
        //On les sépare de leur chaîne de charactères pour un array
        $quotedReturns = explode(',', $matches[1]);
    
        $returns = [];
        foreach($quotedReturns as $quotedReturn)
        {
            //Retrait des guillemets en trop
            $returns[] = trim($quotedReturn, "'");
        }
        return $returns;
    }
    else
    {
        return [];
    }
}

function htmlDatalistFromSqlEnum($db, $joinTableName, $enumColumn, $datalistId)
{
    return htmlDatalist(sqlGetEnumAlphabet($db, $joinTableName, $enumColumn), NULL, NULL, ['datalist' => "id = '$datalistId'"]);
}

function sqlUserExists($db, $userValue)
{
    return sqlDataExists($db, 'mysql.user', 'User', $userValue);
}

function sqlDropUser($db, $username, $host)
{
    if(sqlDataExists($db, 'mysql.user', ['User', 'Host'], [$username, $host]))
    {
        sqlQuery($db, "DROP USER '$username'@'$host'", false);
        sqlQuery($db, "FLUSH PRIVILEGES", false);
        return 1;
    }
    return 0;
}

function sqlUser($db, $action, $username, $password = '', $host = '', $object = NULL, $privileges = NULL, $keys = NULL)
{
    if($username != 'root' AND $username != 'mysql.sys' AND $username != 'mysql.session' AND $username != 'debian-sys-maint')
    {
        //Si l'on ne touche pas à root
        //Récupération sécurisée des privilèges et formulation de la clause
        global $userPrivileges, $dbName;
        $privilegeClause = '';
        $killQuery = true;

        if(isset($privileges))
        {
            $securedPrivileges = securedKeyReturnArray($userPrivileges, $privileges);
            if(isset($keys))
            {
                //Si l'on ne veut affecter les droits qu'à certaines clés
                foreach($securedPrivileges as $securedPrivilegeKey => $securedPrivilege)
                {
                    if($userPrivileges[$securedPrivilege]['column'] == 1)
                    {
                        $securedPrivileges[$securedPrivilegeKey] = $securedPrivilege . ' (' . implode(', ', (array)$keys) . ')';
                    }
                }
            }
            $privilegeClause = implode(', ', $securedPrivileges);
        }
        
        if(sqlIndb($db, $object, 'both', $dbName)) $killQuery = false;
        if(sqlIndb($db, $object, 'procedure', $dbName)) $procedure = ' PROCEDURE';
        else $procedure = '';

        //Création et rassemblement d'informations sur l'utilisateur concerné
        $completeUsername = "'$username'@'$host'";

        if(!$killQuery);
        switch($action)
        {
            case 'CREATE':
                //Suppression de l'utilisateur avant sa création au cas où il existe
                sqlDropUser($db, $username, $host);

                //Création de l'utilisateur
                sqlQuery($db, "CREATE USER $completeUsername IDENTIFIED BY '$password'", false);
                break;

            case 'GRANT':
                if(isset($object))
                {
                    sqlQuery($db, "GRANT $privilegeClause ON$procedure $dbName.$object TO $completeUsername", false);
                }
                break;

            case 'REVOKE':
                if(isset($object))
                {
                    $remainingPrivileges = [];
                    $revokedPrivileges = [];
                    foreach($userPrivileges as $userPrivilege => $privilegeInfo) if($privilegeInfo['edit'])
                    {
                        //Pour chaque droit dit éditables par les adiminstrateurs...
                        $inString = false;
                        foreach($securedPrivileges as $securedPrivilege) if(inString($securedPrivilege, $userPrivilege))
                        {
                            $inString = true;
                        }

                        //...qui n'apparaît dans aucun droit cité précédemment
                        if(!$inString)
                        {
                            $remainingPrivileges[] = $userPrivilege;
                        }
                    }
                    $privilegeClause = implode(', ', $securedPrivileges);
                    $remainingPrivilegesClause = implode(', ', $remainingPrivileges);
                    sqlQuery($db, "REVOKE $privilegeClause ON $dbName.$object FROM $completeUsername;", false);
                }
                break;
    
            case 'DROP':
                sqlDropUser($db, $username, $host);
                break;

            default:
                break;
        }
        return true;
    }
    else
    {
        return false;
    }
}


//CETTE FONCTION UTILISE UNE REQUÊTE SQL AFFICHANT UN RESULTAT FAUX (bug#61846, plus d'infos [exemple]: http://bug.mysql.com/bug.php?id=61846)
//Cette fonction -devrait- être fiable à partir de MySQL 8.x.x

function sqlGetMysqlUsersPriv($db, $usersInfo, $explodeOutput = true)
{
    //Aquisition toutes les données structurelles des bases de données
    $databases = sqlGetDbs($db);
    $fullSqlKeysListInTablesInDbsByUser = [];

    foreach($usersInfo as $userInfo)
    {
        //Pour chaque utilisateur
        //Connexion
        $pdo = sqlConnect($userInfo['Host'], $userInfo['db'], $userInfo['User'], $userInfo['password']);

        //RÉCUPERATION DES DONNÉES
        $fullSqlTableswithDb = [];
        foreach($databases as $database)
        {
            //Pour chaque base de données
            $fullSqlTableswithDb[$database] = sqlGetTables($db, $database, NULL, true);

            foreach($fullSqlTableswithDb[$database] as $table)
            {
                //Pour chaque table de base de donnée
                //Voir les propriétés 'Field' et 'Privileges' de chaque clé de chaque table de chaque base de donnée
                $fullSqlKeysListInTablesInDbsByUser[$userInfo['User']][$database][$table] = sqlGetColumnsProperties($pdo, $table, ['Field', 'Privileges']);
            }
        }

        //MISE EN FORME DES DONNÉES
        foreach($fullSqlKeysListInTablesInDbsByUser[$userInfo['User']] as $database => $fullSqlKeysListInTables)
        {
            //Pour l' utilisateur en connexion de test
            foreach($fullSqlKeysListInTablesInDbsByUser[$userInfo['User']][$database] as $table => $details)
            {
                //Pour chaque $table => $details ($details[0] == $sqlKey : array(); $details[1] == $privileges : array())
                $fullSqlKeysListInTablesInDbsByUser[$userInfo['User']][$database][$table] = [$table => array_cross($details)];
                foreach($fullSqlKeysListInTablesInDbsByUser[$userInfo['User']][$database][$table] as $tableIndex => $sqlTable)
                {
                    //Pour chaque table sql
                    foreach($sqlTable as $index => $sqlKeyInfo)
                    {
                        //Pour chaque clé sql
                        if(isset($sqlKeyInfo['Field'], $sqlKeyInfo['Privileges']))
                        {
                            //On souhaitera observer la valeur de 'Privilege' en fontcion de 'Field'
                            $outputContent = strtoupper($sqlKeyInfo['Privileges']);
                            $output = (int)($explodeOutput) ? explode(',', $outputContent) : $outputContent;
                            $fullSqlKeysListInTablesInDbsByUser[$userInfo['User']][$database][$table][$sqlKeyInfo['Field']] = $output;
                        }
                    }
                    if(isset($fullSqlKeysListInTablesInDbsByUser[$userInfo['User']][$database][$table][$tableIndex]))
                    {
                        //L'ancien format du array ne nous intéresse plus
                        unset($fullSqlKeysListInTablesInDbsByUser[$userInfo['User']][$database][$table][$tableIndex]);
                    }
                }
                if($fullSqlKeysListInTablesInDbsByUser[$userInfo['User']][$database][$table] === [])
                {
                    //Une table sur laquelle n'est appliquée aucun droit, on supprime le tableau
                    unset($fullSqlKeysListInTablesInDbsByUser[$userInfo['User']][$database][$table]);
                }
            }
            if($fullSqlKeysListInTablesInDbsByUser[$userInfo['User']][$database] === [])
            {
                unset($fullSqlKeysListInTablesInDbsByUser[$userInfo['User']][$database]);
            }
        }
    }

    return $fullSqlKeysListInTablesInDbsByUser;
}

function sqlShowGrants($db, $user, $ghostsBusting = true)
{
    global $dbName, $allString;
    $host = sqlSelect($db, "SELECT host FROM mysql.user WHERE mysql.user.User = ?", $user);
    
    if(sqlUserExists($db, $user))
    {
        $hostName = array_funnel(
            $host[0],
            [
                'host' => 'dest',
                'Host' => 'dest'
            ]
        )['dest'];
        $username = "'$user'@'$hostName'";
        $userFullPrivileges = [];
        $grants = sqlSelect($db, "SHOW GRANTS FOR $username", NULL, 'select');

        //Obtenir tous les noms de bases de données, de tables et de colonnes
        $databases = sqlGetDbs($db);
        $fullSqlTablesList = sqlGetTables($db, $dbName);

        $fullSqlKeysListInTables = sqlGetColumnsProperties($db, $fullSqlTablesList, 'Field');
        
        $fullSqlKeysList = [];
        foreach($fullSqlKeysListInTables as $SqlKeysListInTable)
        {
            //Pour chaque listes de clés de tables
            foreach($SqlKeysListInTable as $key)
            {
                //Pour chaque clés de table
                $fullSqlKeysList[] = $key;
            }
        }
        
        foreach($grants as $key => $grant)
        {
            $sqlPermissions = [];
            preg_match('/^GRANT ([\w, ()]+) ON ?(PROCEDURE)? [`\']?([\w*]*)[`\']?[.@][`\']?([\w*]*)[`\']? TO \'(\w+)\'@\'(\w+)\' ?(WITH GRANT OPTION)? ?(IDENTIFIED BY PASSWORD \'[*\w]*\')?$/', $grant[0], $matches);
                //[1] => PRIVILEGE (clé, clé,...),...
                //[2] => PROCEDURE
                //[3] => db
                //[4] => table
                //[5] => user
                //[6] => host
                //[7] => WITH GRANT OPTION
                //[8] => IDENTIFIED BY PASSWORD 'xxx'
                
            $grantDatabases = $matches[3];
            $grantTables = $matches[4];
            
            /*if($ghostsBusting AND $grantTables != '*' AND !in_array($grantTables, sqlGetTables($db)) AND $matches[2] != 'PROCEDURE')
            {
                //Si on veut chasser les grants fantômes appartenant à des tables détruites
                foreach(array_column(sqlSelect($db, "SELECT mysql.user.User FROM mysql.user WHERE mysql.user.User LIKE 'u%'"), 'User') as $tempUserName)
                {
                    //On retire les droits à chaque utilisateur
                    sqlUser($db, 'REVOKE', $tempUserName, '', 'localhost', $grantTables, ['SELECT', 'UPDATE', 'INSERT', 'DELETE']);
                }
            }*/
            
            //var_dump($grant[0], $matches);

            //Découpage des privilèges par virgules, mais problème: le nom des clés a explosé aussi
            $wildPrivilegings = explode(', ', $matches[1]);

            //Détéction de parenthèse dans le array
            $containsParenthesis = false;
            foreach($wildPrivilegings as $wildPrivileging)
            {
                if(inString($wildPrivileging, '('))
                {
                    //Si au moins un membre de $wildPrivilegings contient '('
                    $containsParenthesis = true;
                }
            }

            //Sera de la forme [DROIT => [clé, clé, clé], DROIT => '$allString']
            $privilegings = [];

            if($containsParenthesis)
            {
                //Si le array séléctionné contient une parenthèse dans un de ses membres
                $i = 0;
                while($i + 1 < count($wildPrivilegings))
                {
                    //Tant qu'on a pas scanné chaque fragment
                    while(!inString($wildPrivilegings[$i], '('))
                    {
                        //On passe de fragment en fragment jusqu'à trouver celui où commencent les parenthèses
                        if(!inString($wildPrivilegings[$i], ')'))
                        {

                            $privilegings[$wildPrivilegings[$i]] = $allString;
                        }
                        $i++;
                    }
                    
                    //La première clé sur laquelle un privilège est appliquée est contenue dans une chaîne de charactères séparée du nom du privilège par un '('
                    $privilegingsFirstKey = explode('(', $wildPrivilegings[$i]);

                    //Nom du privilège
                    $partialPrivilegeIndex = trim($privilegingsFirstKey[0]);

                    //Début du array des clés du privilège partiel
                    $privilegingsFirstKey = trim($privilegingsFirstKey[1], ')');

                    $privilegings[$partialPrivilegeIndex] = [$privilegingsFirstKey];
                    $parenthesisIndex = $i;
                    while(isset($wildPrivilegings[$i]) AND !inString($wildPrivilegings[$i], ')'))
                    {
                        //Construction de la chaîne des clés
                        $i++;
                        $privilegings[$partialPrivilegeIndex][] = trim($wildPrivilegings[$i], ')');
                    }
                }

                
            }
            else
            {
                //$wildPrivilegings contient ses informations en valeurs de array, mais elles doivent être stockées en clés de array de $privilegings
                $privilegings = array_combine($wildPrivilegings, array_fill(0, count($wildPrivilegings), $allString));
            }

            if($grantDatabases === $dbName)
            {
                if($grantTables === '*')
                {
                    //Si toutes les tables sont ciblées par le GRANT
                    $grantTables = sqlGetTables($db, $dbName);
                    
                    foreach($grantTables as $grantTable)
                    {
                        //Pour chaque table sur laquelle on relève des grants, on liste chaque clé
                        $sqlPermissions[$grantTable] = array_flip(sqlGetColumnsProperties($db, $grantTable, 'Field'));
                    }
                }
                elseif($matches[2] != 'PROCEDURE')
                {
                    //Si seulement une table est ciblée par le grant
                    //Permission niveau - clés
                    $grantTable = $grantTables;
                    $sqlPermissions[$grantTable] = array_flip(sqlGetColumnsProperties($db, $grantTables, 'Field'));

                    //Permission niveau - tables
                    $sqlTablesWidePermissions = [];
                    foreach($privilegings as $privilege => $table) if($table === $allString)
                    {
                        //Pour chaque privilège appliqué à chaque clé de la table
                        $sqlTablesWidePermissions[$grantTable][] = $privilege;
                    }
                }
            }
            
            $userFullPrivileges[$matches[3] . '.' . $matches[4]] = 
            [
                'db' => $matches[3],
                'object' => $matches[4],
                'procedure' => (isset($matches[2]) AND $matches[2] === 'PROCEDURE') ? 1 : 0, 
                'user' => $matches[5],
                'host' => $matches[6],
                'grant option' => (isset($matches[7]) AND $matches[7] === 'WITH GRANT OPTION') ? 1 : 0,
                'privileges' => $privilegings,
                'tableswide permissions' => (int)isset($sqlTablesWidePermissions) ? $sqlTablesWidePermissions : []
            ];
        }
        return $userFullPrivileges;
    }
    else
    {
        return [];
    }
}

function htmlFormatMysqlUserPriv($db, $userPrimaryValue, $indexUrl = 'index.php')
{
    global $dbName, $pathToProperties, $contentString, $thisString, $userPrivileges, $tablesProperties, $showKeyColumnName,
        $tableConfigTableName, $joiningDataTableName, $pivotDatasTableName, $columnsConfigTableName;
    $tableString = '__TABLE__';
    $userRightString = '__USERRIGHT__';

    //Obtenir l'identifiant MySQL
    $data = sqlSelect($db, "SELECT mysql.user.User, $dbName.utilisateurs.Prenom, $dbName.utilisateurs.$showKeyColumnName
                            FROM $dbName.utilisateurs 
                                JOIN mysql.user 
                                    ON CONCAT('u', $dbName.utilisateurs.ID_utilisateur) = mysql.user.User
                            WHERE $dbName.utilisateurs.ID_utilisateur = ?", $userPrimaryValue, 'drop')[0];
    
    //Mettre en forme les informations
    $usersInfo =
    [
        [
            'User' => $data['User'],
            'password' => parse_ini_file($pathToProperties)[2],
            'db' => $dbName,
            'Host' => 'localhost'
        ]
    ];

    $revokeOnclick = "UpdatePrivileges('$indexUrl');ChangeAttribute('$thisString','class','replace','grant-button');ChangeAttribute('$thisString','value','replace','grant');";
    $grantOnclick = "UpdatePrivileges('$indexUrl');ChangeAttribute('$thisString','class','replace','revoke-button');ChangeAttribute('$thisString','value','replace','revoke');";

    $changeOnclickGrant = "ChangeAttribute('$thisString','onclick','replace',RevokeOnclick+ChangeOnclickRevoke)";
    $changeOnclickRevoke = "ChangeAttribute('$thisString','onclick','replace',GrantOnclick+ChangeOnclickGrant)";

    $onclicks = 
    "<script>
        RevokeOnclick = \"$revokeOnclick\";
        GrantOnclick = \"$grantOnclick\";
        ChangeOnclickGrant = \"$changeOnclickGrant\";
        ChangeOnclickRevoke = \"$changeOnclickRevoke\";
    </script>";

    //Obtention des droits de l'utilisateur
    $returns = '';

    $grants = sqlShowGrants($db, $data['User']);

    $revokeButton =
    "<button 
        class = 'revoke-button' 
        onclick = $revokeOnclick$changeOnclickRevoke
        user = '$data[User]'
        name = '$tableString-$userRightString'
        value = 'revoke'>
    </button>";

    $grantButton = 
    "<button 
        class = 'grant-button' 
        onclick = $grantOnclick$changeOnclickGrant
        user = '$data[User]'
        name = '$tableString-$userRightString'
        value = 'grant'>
    </button>";
    
    //CORRECTION: Ajout des tables sans grant et retrait des tables cachées
    foreach($tablesProperties as $tableName => $tableProperties)
    {
        //Pour chaque table 
        $columnId = $dbName . '.' . $tableName;
        if(!isset($grants[$columnId]))
        {
            //N'apparaissant pas dans les grants
            foreach($userPrivileges as $userRight => $rightInfo) if($rightInfo['edit'])
            {
                //Pour chaque droit applicable par un admin
                $grants[$columnId]['object'] = $tableName;
                $grants[$columnId]['procedure'] = false;
                $grants[$columnId]['tableswide permissions'][$tableName][] = $userRight . str_replaces([$tableString => $tableName, $userRightString => $userRight], $grantButton);
            }
        }

        if(isset($grants[$columnId], $tablesProperties[$tableName]['Grants_hidden']) AND $tablesProperties[$tableName]['Grants_hidden'])
        {
            //Apparaissant dans les grants devant être cachée
            //Retrait de la table de l'affichage des droits
            unset($grants[$columnId]);
        }
    }
    ksort($grants);

    //AFFICHAGE: Signets d'ajout et de retraits des droits
    $pivotTables = sqlGetJoinTables($db, 'pivot');
    foreach($grants as $grant) if(!inString($grant['object'], '*') AND !$grant['procedure'])        //Pour chaque table
        foreach($grant['tableswide permissions'] as $tableName => $tablesWidePermissions)               //Pour chaque table => permissions dessus
            foreach($userPrivileges as $userRight => $rightInfo) if($rightInfo['edit'])                     //Pour chaque privilège editable par un admin
                if(
                    !(
                        ($userRight === 'INSERT' OR $userRight === 'DELETE')
                        AND
                        (
                            $tableName === $tableConfigTableName
                            OR
                            $tableName === $joiningDataTableName
                            OR
                            $tableName === $pivotDatasTableName
                            OR
                            $tableName === $columnsConfigTableName
                            OR
                            in_array($tableName, $pivotTables)
                        )
                    )
                )
            {
                if(in_array($userRight, $tablesWidePermissions))
                {
                    //Si le droit d' utilisateur éditable séléctionné existe dans les droits de l'utilisateur séléctionné
                    $button = $revokeButton;
                }
                else
                {
                    $button = $grantButton;
                }
                $userPrivilegesLists[$tableName][$userRight] = "$rightInfo[showWord]<span class = tab>" . str_replaces([$tableString => $tableName, $userRightString => $userRight], $button) . "</span>";
            }

    foreach($userPrivilegesLists as $tableName => $userPrivilegesList)
    {
        //Représentation des privileges d'une table
        $returns .= "   <div class = table-priv-info>
                            <h4 class = table-name>" . str_replace('_', ' ', ucwords($tableName)) . '</h4><br />' . 
                                htmlMenu($userPrivilegesList, NULL, ['nav' => "class = 'priv-list' id = $tableName-priv"]) . 
                        '</div></div>';
    }// action = 'index.php" . rebuildGetString() . "' method = POST
    return "$onclicks<h3 id = user-priv-title>Droits de " . ucwords($data['Prenom']) . ' ' . strtoupper($data[$showKeyColumnName]) . "</h3>$returns";
}

function sqlGenerateColumnsTools($db, $tableName, $tablePrimaryKey = NULL, &$columnsAttributes, &$selectArray, $database = NULL, $temporaryName = NULL, $omitRootJoinKey = false)
{
    global $dbName;

    $tableColumns = sqlGetColumnsProperties($db, $tableName, 'Field');
    
    $rootJoinKey = array_column(sqlGetJoinList($db, $tableName, false, '', ['Table' => 'rootTable', 'Key' => 'joinKey']), 0);

    foreach($tableColumns as $tableColumn)
    {
        if(
            !(
                in_array($tableColumn, $rootJoinKey) AND 
                $omitRootJoinKey
            ) //OR 1
        )
        {
            //Si cette colonne ne sera pas appelée par une jointure (n'est pas rootJoinColumn)
            $selectArray[] =
            [
                'Name' => $tableColumn,
                'originTableName' => $tableName,
                'Table' => isset($temporaryName) ? $temporaryName : $tableName,
                'Database' => isset($database) ? $database : $dbName
            ];
        }
        
        $columnsAttributes[$tableColumn] = 
        [
            'edittable' => $tableName,
            'editkey' => $tableColumn,
            'primarykey' => isset($tablePrimaryKey) ? $tablePrimaryKey : sqlGetPrimaryKey($db, $tableName)
        ];
    }
}

function sqlCopyTemp($db, $originalTableName, $primaryKey = '', $dbName = '')
{
    global $tempSuffix;
    $tempTableIndex = 0;

    do
    {
        $newTableName = $originalTableName . $tempSuffix . $tempTableIndex;
        $tempTableIndex++;
    }while(tableExists($db, $newTableName, $dbName));
    
    if(!tableExists($db, $newTableName, $dbName))
    {
        //Si la table originale existe
        //Obtenir la clé primaire de la table
        if($primaryKey === '') 
        {
            //primaryKey non rensignée
            $primaryKey = sqlGetPrimaryKey($db, $originalTableName);
        }
    
        //Créer la table temporaire
        $primaryColumns = explode(',', $primaryKey);
        $tableFieldsNTypes = array_cross(sqlGetColumnsProperties($db, $originalTableName, ['Field', 'Type']));

        $primaryColumnsFieldsNTypes = [];
        $primaryKeyStrings = [];
        foreach($primaryColumns as $primaryColumn)
            foreach($tableFieldsNTypes as $nIndex => $fieldNType)
                if($fieldNType['Field'] === $primaryColumn)
        {
            //Construction d'un array contenant:
            //-En clé:      Le nom des colonnes primaires 
            //-En valeur:   Le type (MySQL) de données
            $primaryColumnsFieldsNTypes[$primaryColumn] = strtoupper($tableFieldsNTypes[$nIndex]['Type']);

            //Construction de la chaîne de charactères à entrer en requête à partir du array précédent
            $primaryKeyStrings[] = $primaryColumn . ' ' . $primaryColumnsFieldsNTypes[$primaryColumn];
        }
        
        sqlQuery($db, "CREATE TEMPORARY TABLE $newTableName
        (
            " . implode(', ', $primaryKeyStrings) . ",
            PRIMARY KEY($primaryKey)
        )
        SELECT * FROM $originalTableName WHERE " . str_replace(',', ' != \'0\' AND ', $primaryKey) . " != '0'", false);
        
        return $newTableName;
    }
    return 0;
}

function sqlTranslate($db, $table, $translations)
{
    if(tableExists($db, $table))
    {
        //La table existe
        foreach($translations as $key => $translation)
        {
            //Pour chaque colonne qui doit voire ses valeurs changer pour un enum sur la table temporaire
            //La colonne existe
            foreach($translation as $oldValue => $newValue)
            {
                sqlUpdate($db, $table, $key, $oldValue, $key, $newValue);
            }
        }  
    }
    
}

function sqlCopy($db, $from)
{
    //Whitelist des tables qu'on a le droit de d'altérer
    global $tablesProperties;
    if($tablesProperties[$from]['Temp_name'] != 0)
    {
        //La copie à un nom autorisé et n'a pas celui de l'originale
        $table = $tablesProperties[$from];
        $request = $db -> query("CREATE TABLE $table[Temp_name] SELECT * FROM $from") or die(var_dump($db -> errorInfo()));
        return $tablesProperties[$from]['Temp_name'];
    }
    else
    {
        return 0;
    }
}

function sqlDrop($db, $table)
{
    global $tablesProperties;
    //Noms des tables temporaires
    $tempTableNames = array_column($tablesProperties, 'Temp_name');

    if(in_array($table, $tempTableNames))
    {
        //Si la table est temporaire
        //On supprime
        $request = $db -> query("DROP TABLE $table");
        return 1;
    }
    else
    {
        return 0;
    }
}

function sqlAlter($db, $table, $column, $operation, $type, $size, $after = NULL, $comment = NULL, $newName = NULL)
{
    //Whitelists
    global $tablesProperties;
    global $alterationOperations;
    global $typesList;

    if(!isset($newName))
    {
        $newName = '';
        if($operation === 'CHANGE')
        {
            return 0;
        }
    }

    if(isset($operation, $alterationOperations))
    {
        //altersTypes de la bonne forme, on procède à l'altèreation
        if(!sqlIntable($db, $table, $column) AND                    //Si la colonne à ajouter n'existe pas déjà
            in_array($operation, $alterationOperations) AND                   //Si l'opération à effectuer existe est whitelistée
            in_array(rtrim($table, '0123456789'), array_column($tablesProperties, 'Temp_name')) AND  //Si le nom de la table à altérer auquel on retire les chiffres au début et a la fin est whitelisté
            isset($typesList[$type]) AND                                //Si le type est whitelisté
        ($typesList[$type] >= $size) OR $typesList[$type] == 'x')   //Si la taille demandée est inférieure ou égale à la taille possible pour cette variable
        {
            //$operation:   opération sur colonne   [ADD, MODIFY, DROP, ...]    (doit être dans $alterationOperations)
            //$type:        [int, varchar, float, ...]  (doit être dans $typesList)
            //$size:        [11, 255, x, ...]           (doit être inférieur à la valeur correspondante à $type dans $typesList,
            //                                                                  ou alors, cette même valeur dans $typesList doit valoir 'x')
            if($size != NULL)
            {
                $size = "($size)";
            }
            if($after != NULL)
            {
                $after = " AFTER $after";
            }
            if($comment != NULL)
            {
                $comment = " COMMENT '$comment'";
            }
            $request = $db -> query("ALTER TABLE $table $operation `$column` $newName $type$size NOT NULL$comment$after") or die(var_dump($db -> errorInfo()));
            $request -> closecursor();
        }
        return  1;
    }
    else
    {
        return 0;
    }
}

function sqlGetDbs($db)
{
    return array_column(sqlQuery($db, "SHOW DATABASES"), 0);
}

function sqlGetTables($db, $from = '', $blacklist = NULL, $partitionnate = false, $includeViews = false)
{
    $query = '';
    $partitionnedTablesList = $entireTablesList = [];
    $viewWhereClause = $includeViews ? '' : ' WHERE Table_Type != \'VIEW\'';
    if($from != '')
    {
        //Si on cible une ou les base(s) de données
        $databases = sqlGetDbs($db);
        if($from === '*')
        {
            //Toutes les bases de données sont ciblées
            foreach($databases as $database)
            {
                //Pour chaque base de données
                $partitionnedTablesList[$database][] = $entireTablesList[] = sqlQuery($db, "SHOW FULL TABLES FROM $database$viewWhereClause");
            }
            if($partitionnate)
            {
                $entireTablesList = $partitionnedTablesList;
            }
        }
        else
        {
            //Une seule base de données est ciblée
            $choosenDb = securedContentPick($databases, $from);
            $entireTablesList[] = sqlQuery($db, "SHOW FULL TABLES FROM $choosenDb$viewWhereClause");
        }
    }
    else
    {
        $entireTablesList[] = sqlQuery($db, "SHOW FULL TABLES$viewWhereClause");
    }

    $tables = [];
    foreach($entireTablesList as $database)
    {
        foreach($database as $key => $table)
        {
            $tables[] = $table[0];
        }
    }

    if(isset($blacklist))
    {
        $tables = array_spliceByValues($tables, $blacklist);
    }
    
    return $tables;
}

function sqlGetProcedures($db, $dbName, $property = 'Name')
{
    $unformatedProcedures = sqlQuery($db, "SHOW PROCEDURE STATUS WHERE db = '$dbName'");
    $procedures = [];

    if(isset($unformatedProcedures[0][$property]))
    {
        foreach($unformatedProcedures as $unformatedProcedure)
        {
            $procedures[] = $unformatedProcedure[$property];
        }
    }

    return $procedures;
}

function sqlGetJoinTables($db, $mode, $relatedTableName = '', $side = 'rootTable', $properties = ['Name'])
{
    global $dbName, $tableConfigTableName, $showKeyColumnName;
    if($mode === 'pivot')
    {
        $tableConfigColumnName = 'Is_full_pivot_join_table';
    }
    elseif($mode === 'inter')
    {
        $tableConfigColumnName = 'Is_inter_join_table';
    }
    else
    {
        var_dump("Error: $mode is not a correct mode");
        return [];
    }


    $possibleTables =
        array_column(
            sqlSelect(
                $db, 
                "SELECT
                    `$dbName`.`$tableConfigTableName`.`$showKeyColumnName`
                FROM
                    `$dbName`.`$tableConfigTableName`
                WHERE
                    `$dbName`.`$tableConfigTableName`.`$tableConfigColumnName` = 1;"
            ), 
            $showKeyColumnName
        );
    $return = $possibleTables;
    if($relatedTableName != '')
        foreach($possibleTables as $possibleTable)
        {
            foreach(
                array_column_keep_key(
                    sqlGetJoinList($db, $relatedTableName, true, '', [], true),
                    $side
                ) as $constraintName => $sidedJoin)
            {
                $return[$constraintName] = array_return($sidedJoin, $properties);
            }
        }
    else
    {
        return $possibleTables;
    }

    return $return;
}

function sqlGetFullPivotJoinKey($db, $joinTable)
{
    global $tablesProperties;
    $foreignKey = [];
    if($tablesProperties[$joinTable]['Is_full_pivot_join_table'])
    {
        foreach(sqlGetJoinList($db, $joinTable) as $join)
        {
            $foreignKey =
                array_merge(
                    $foreignKey,
                    $join['rootTable']['joinKey']
                );
        }
        return $foreignKey;
    }
    else
    {
        var_dump("Error: $joinTable is not a pivot table");
        return 0;
    }
}

//Fonction servant uniquement à sqlFillMissingJoins et sqlDeleteOddJoins
//Entrée: Une table
//Sortie: Array
//  -clés:      Tables jointes où les colonnes de la rootJoinKey sont toutes comprises dans la rootPrimaryKey
//  -valeurs:   extraJoinKey
function sqlGetExtraTablesAndJoinKeys($db, $table)
{
    $extraTablesAndJoinKeys = [];
    foreach(sqlGetJoinList($db, $table) as $constraintName => $joinData)
        if(array_intersect($joinData['rootTable']['joinKey'], $joinData['rootTable']['primaryKey']) === (array)$joinData['rootTable']['joinKey'])
        {
            //Pour chaque contrainte concernant cette table, où les colonnes de la rootJoinKey sont toutes comprises dans la rootPrimaryKey
            //Les colonnes ici sont donc des rootJoinColumns qui DOIVENT IMPERATIVEMENT présenter en terme de données toutes les combinaisons possibles
            $extraTablesAndJoinKeys[$joinData['extraTable']['Name']] = $joinData['extraTable']['joinKey'];
        }

    return $extraTablesAndJoinKeys;
}

function sqlGetSupposedJoins($db)
{
    //Récupération des tables de joinutre de la base de données
    $combinations = $supposedJoins = [];
    $joinTables = sqlGetJoinTables($db, 'pivot');
    foreach($joinTables as $table)
    {
        //Pour chaque table
        $foreignPrimaryKeys = $extraTablesDatas = [];
        $extraTablesAndJoinKeys = sqlGetExtraTablesAndJoinKeys($db, $table);
        
        
        foreach($extraTablesAndJoinKeys as $extraTableName => $extraTablePrimaryKey)
        {
            //Pour chaque tableau en tant que nom => clé primaire
            //Répertorier les données déjà existantes
            $extraTablesDatas[] = sqlQuery($db, "SELECT " . implode(',', $extraTablePrimaryKey) . " FROM $extraTableName");
        }

        //Récupération de chaque clé composant une variable de combinaison
        foreach($extraTablesDatas as $extraTablesIndex => $extraTablesData)
        {
            foreach($extraTablesData as $extraTupleIndex => $extraTupleData)
            {
                $extraTablesDatas[$extraTablesIndex][$extraTupleIndex] = numericKeys($extraTupleData, 'drop');
            }
        }

        //Récupérations de toutes les combinaisons qui devraient être dans la base de données
        $combinations[$table] = array_combinations($extraTablesDatas);
    }
    return $combinations;
}

function sqlFillMissingJoins($db)
{
    $supposedJoins = sqlGetSupposedJoins($db);
    foreach($supposedJoins as $table => $combinations)
        foreach($combinations as $nIndex => $combination) if(!sqlDataExists($db, $table, array_keys($combination), array_values($combination)))
        {
            //Insertion d'un tuple manquant dans la table de pivot
            sqlInsert($db, $table, array_keys($combination), array_values($combination));
        }
}

function sqlJoin($db,   $rootTable,     //Table principale à laquelle on souhaite en joindre d'autres
                        &$joinArray,    //Arrays 'json' décrivant le Nom ('Name'), la clé de la donnée affichée ('showKey'), 
                                            //et la clé de la table principale sujette à la jointure ('rootKey'), eux même concaténés dans un Array 2D (Njoin * 3)
                        &$columnsAttributes,    //RETOUR: Tableau des attributs html à entrer dans htmlTable()
                        $selectArray,   //Afin de connaître les originColumns à mettre en clé de columnsAttibutes
                        &$htmlDatalists,    //RETOUR (vidé): Datalists à ajouter dans la page pour permettre la saisie automatique
                        $tempTable = NULL,  //Table temporaire correspondant à la table principale
                        $userGrants,
                        $datalistQuery = [])    
{
    global $dbName, $showKeyColumnName;
    //$tempTable = (int)isset($tempTable) ? $tempTable : $rootTable;
    //$rootPrimaryKey = sqlGetPrimaryKey($db, $rootTable);
    $htmlDatalists = '';

    $originColumns = [];
    foreach($selectArray as $selected) if(isset($selected['originColumn']))
    {
        $columnAppelation = 
            isset($selected['Rename'])
                ? $selected['Rename']
                : $selected['Name'];
        $originColumns[$columnAppelation] = $selected['originColumn'];
    }

    
    foreach($joinArray as $joinElement) /*if(isset($joinElement['Table'], $joinElement['rootJoinKey'], $joinElement['showKey']))*/
    {
        //Pour chaque table extra
        //Obtenir sa clé primaire, si elle n'est pas spécifiée par l'utilisateur, sinon la récupérer
        
        //Enrichissement des attributs html des cases de tableau
        $rootJoinKey = implode(',', $joinElement['rootTable']['joinKey']);
        $columnsAttributes[$rootJoinKey] = 
        [
            'edittable' => $joinElement['rootTable']['originTableName'], //$rootTable
            'primarykey' => $joinElement['rootTable']['primaryKey'],
            'editkey' => 
                isset($originColumns[$rootJoinKey])
                    ?   $originColumns[$rootJoinKey]
                    :   $rootJoinKey,
            'listPrimaryKey' => implode(',', (array)$joinElement['extraTable']['primaryKey']),
            'list' => $joinElement['extraTable']['Name'],
            'extraTableQueryRename' => 
                isset($joinElement['extraTable']['Rename'])
                    ?   $joinElement['extraTable']['Rename']
                    :   '',
            'displaykey' => implode(',', $joinElement['extraTable']['showKey'])
        ];
        
        sqlCheckAttributesToMakeColumnEditable($columnsAttributes[$rootJoinKey]);
        
        //Enrichissement du contenu html (datalists)
        $htmlDatalists .= getRelationnalTable($db, 
                                                $joinElement['extraTable']['Name'],
                                                $userGrants,
                                                $datalistQuery,
                                                'index.php',
                                                'htmlDatalist');
    }

    return $htmlDatalists;
}

function sqlGetJoinList($db, $tableName, $full = false, $tableRename = '', $returnFilters = [], $tableNameIsReferenced = false)
{
    //foreach Join/constraint:
    //  -columnAlias
    //  -rootTable:
    //      -Name (string)
    //      -primaryKey (array)
    //      -joinKey (array)
    //      -showKey (array)
    //  -extraTable:
    //      -Name (string)
    //      -primaryKey (array)
    //      -joinKey (array)
    //      -showKey (array)
    global $dbName, $joiningDataTableName, $tableConfigTableName, $columnsConfigTableName, $showKeyColumnName;
    $tableNameColumn = $tableNameIsReferenced
        ?   'REFERENCED_TABLE_NAME'
        :   'TABLE_NAME';
        
    $originTableSide = $tableNameIsReferenced
        ?   'extraTable'
        :   'rootTable';
        
    $otherTableSide = $tableNameIsReferenced
        ?   'rootTable'
        :   'extraTable';

    $joinDatas = sqlSelect($db, 
    [
        'SELECT' => 
        [
            [
                'Database' => 'information_schema',
                'Table' => 'KEY_COLUMN_USAGE',
                'Name' => 'CONSTRAINT_NAME',
                'Rename' => 'constraintName'
            ],
            [
                'Database' => 'information_schema',
                'Table' => 'KEY_COLUMN_USAGE',
                'Name' => 'TABLE_NAME',
                'Rename' => 'rootTableName'
            ],
            [
                'Database' => 'information_schema',
                'Table' => 'KEY_COLUMN_USAGE',
                'Name' => 'TABLE_SCHEMA',
                'Rename' => 'Database'
            ], 
            [
                'Database' => 'information_schema',
                'Table' => 'KEY_COLUMN_USAGE',
                'Name' => 'COLUMN_NAME',
                'Rename' => 'rootJoinKey'
            ], 
            [
                'Database' => 'information_schema',
                'Table' => 'KEY_COLUMN_USAGE',
                'Name' => 'REFERENCED_TABLE_NAME',
                'Rename' => 'extraTableName'
            ], 
            [
                'Database' => 'information_schema',
                'Table' => 'KEY_COLUMN_USAGE',
                'Name' => 'REFERENCED_COLUMN_NAME',
                'Rename' => 'extraJoinKey'
            ],
            [
                'Database' => 'information_schema',
                'Table' => 'KEY_COLUMN_USAGE',
                'Name' => 'POSITION_IN_UNIQUE_CONSTRAINT'
            ],
            (
                $full
                ?   [
                        'Database' => $dbName,
                        'Table' => $joiningDataTableName,
                        'Name' => 'columnAlias'
                    ]
                :   []
            ),
            (
                $full
                ?   [
                        'Database' => $dbName,
                        'Table' => $tableConfigTableName,
                        'Name' => 'ID_table',
                        'Rename' => 'ID_root_table'
                    ]
                :   [] 
            )
        ],
        'FROM' => 
        [
            [
                'Database' => 'information_schema',
                'Table' => 'KEY_COLUMN_USAGE'
            ]
        ],
        'LEFT JOIN' => 
        (
            $full
            ? [
                [
                    'Database' => 'information_schema',
                    'rootTable' => 
                    [
                        'Name' => 'KEY_COLUMN_USAGE',
                        'joinKey' => 'CONSTRAINT_NAME'
                    ],
                    'extraTable' => 
                    [
                        'Name' => $joiningDataTableName,
                        'joinKey' => 'constraintName'
                    ],
                ],
                [
                    'Database' => 'information_schema',
                    'rootTable' =>
                    [
                        'Name' => 'KEY_COLUMN_USAGE',
                        'joinKey' => 'TABLE_NAME'
                    ],
                    'extraTable' =>
                    [
                        'Name' => $tableConfigTableName,
                        'joinKey' => 'ID_table'
                    ]
                ]
            ]
            : []
        ),
        'WHERE' =>
        [
            [
                'Database' => 'information_schema',
                'Table' => 'KEY_COLUMN_USAGE',
                'Key' => 'TABLE_SCHEMA',
                'Column' => 'TABLE_SCHEMA',
                'Value' => $dbName,
                'Condition' => 'AND',
                'Operator' => '='
            ],
            [
                'Database' => 'information_schema',
                'Table' => 'KEY_COLUMN_USAGE',
                'Key' => $tableNameColumn,
                'Column' => $tableNameColumn,
                'Value' => $tableName,
                'Condition' => 'AND',
                'Operator' => '='
            ],
            [
                'Database' => 'information_schema',
                'Table' => 'KEY_COLUMN_USAGE',
                'Key' => 'REFERENCED_COLUMN_NAME',
                'Column' => 'REFERENCED_COLUMN_NAME',
                'Value' => NULL,
                'Condition' => 'AND',
                'Operator' => 'IS NOT'
            ],
            (
                $full
                ?   [
                        'Database' => $dbName,
                        'Table' => $tableConfigTableName,
                        'Key' => 'Is_full_pivot_join_table',
                        'Column' => 'Is_full_pivot_join_table',
                        'Value' => 0,
                        'Condition' => 'AND',
                        'Operator' => '='
                    ]
                :   []
            )
        ],
        'ORDER BY' =>
        [
            [
                'Database' => 'information_schema',
                'Table' => 'KEY_COLUMN_USAGE',
                'Name' => 'CONSTRAINT_NAME',
                'Column' => 'CONSTRAINT_NAME',
                'Mean' => 'ASC'
            ],
            [
                'Database' => 'information_schema',
                'Table' => 'KEY_COLUMN_USAGE',
                'Name' => 'POSITION_IN_UNIQUE_CONSTRAINT',
                'Column' => 'CONSTRAINT_NAME',
                'Mean' => 'ASC'
            ]
        ]
    ], NULL, 'drop', ['SELECT DISTINCT', 'LEFT JOIN']);

    //Pour chaque (sous)donnée de jointure (une même contrainte va réapparaitre autant de fois qu'elle est en condition de join)
    foreach($joinDatas as $nIndex => $joinData)
    {
        $joinDatas[$joinData['constraintName']]['Database'] = $joinData['Database'];
        if($full)
        {
            $joinDatas[$joinData['constraintName']]['columnAlias'] = $joinData['columnAlias'];
        }

        $joinDatas[$joinData['constraintName']]['rootTable']['originTableName'] = $tableName;

        $joinDatas[$joinData['constraintName']][$originTableSide]['Name'] = 
            $tableRename === ''
                ?   $joinData['rootTableName']
                :   $tableRename;
                
        $joinDatas[$joinData['constraintName']]['rootTable']['primaryKey'] = (array)explode(',', sqlGetPrimaryKey($db, $joinData['rootTableName']));
        $joinDatas[$joinData['constraintName']]['rootTable']['joinKey'][$joinData['POSITION_IN_UNIQUE_CONSTRAINT'] - 1] = $joinData['rootJoinKey'];
        $joinDatas[$joinData['constraintName']]['rootTable']['showKey'] = array_column(sqlGetShowKey($db, $joinData['rootTableName']), 'Name');

        $joinDatas[$joinData['constraintName']][$otherTableSide]['Name'] = $joinData['extraTableName'];

        $joinDatas[$joinData['constraintName']]['extraTable']['primaryKey'] = (array)explode(',', sqlGetPrimaryKey($db, $joinData['extraTableName']));
        $joinDatas[$joinData['constraintName']]['extraTable']['joinKey'][$joinData['POSITION_IN_UNIQUE_CONSTRAINT'] - 1] = $joinData['extraJoinKey'];
        $joinDatas[$joinData['constraintName']]['extraTable']['showKey'] = array_column(sqlGetShowKey($db, $joinData['extraTableName']), 'Name');

        //La donnée a prit la forme voulue, on peut détruire son ancienne forme
        unset($joinDatas[$nIndex]);
    }
    
    $return = isset($returnFilters['Table'], $returnFilters['Key'])
        ?   array_column(
                array_column(
                    $joinDatas, 
                    $returnFilters['Table']
                ),
                $returnFilters['Key']
            )
        :   $joinDatas;
        
    return $return;
}

//A partir du nom de la table et des informations de configuration de la base de données,
//Cette fonction va ajouter des select correspondants aux nombres de jointures existants
//dans les tables d'interJoin qu'elle trouvera
//[ATTENTION] Pour utiliser cette fonction, un GROUP BY rootTable.primaryKey est obligatoire
function sqlGetInterJoinSelects($db, $rootTableName)
{
    if($joinTables = sqlGetJoinTables($db, 'inter', $rootTableName))
    {
        $countDistinctSelects = [];
        var_dump($joinTables);
        foreach($joinTables as $nIndex => $joinTable)
        {
            //Pour chaque interJoin
    
        }
    
        return $countDistinctSelects;
    }
    else return [];
}

function sqlGetJoinTools($db, $tableName, &$selectArray = [], &$joinArray = [], $tableRename = '')
{
    global $dbName;

    $selectArray = isset($selectArray)
        ?   $selectArray
        :   [];
    $joinArray = isset($joinArray)
        ?   $joinArray
        :   [];

    $tableAppelation = (bool)$tableRename
        ?   $tableRename
        :   $tableName;

    //Pas seulement la table de travail est à joindre, mais aussi les tables qui lui sont en pivot, lesquelles verront leurs showColumns jointes
    $joinsList = sqlGetJoinList($db, $tableName, true, $tableRename);
    
    foreach(sqlGetJoinTables($db, 'pivot') as $pivotTable)
        foreach(sqlGetJoinList($db, $pivotTable) as $constraintName => $join)
            if
            (
                $pivotJoinedShowKey = array_intersect($join['rootTable']['joinKey'], $join['rootTable']['showKey']) AND
                in_array(
                    $tableName,
                    array_column(
                        array_column(
                            sqlGetJoinList($db, $join['rootTable']['Name']),
                            'extraTable'
                        ), 
                        'Name'
                    )
                )
            )
                //Pour chaque jointure sur une showColumn sur une table de pivot où la table courante est une des extraTables
                foreach($selectArray as $select)
                    if(isset($select['originPivotTable']) AND inString(sqlGetColumnProperty($db, $tableAppelation, $select['Name'], 'Comment'), 'showKey'))//[shit-flag] Quelle originPivotTable ?
                        //Pour chaque élément select provenant d'une table de pivot [shit-flag] On peut mettre une seul show/join column par key
                        foreach($join['rootTable']['showKey'] as $joinShowColumn)
                            if(in_array($joinShowColumn, $pivotJoinedShowKey) AND $joinShowColumn === $select['originColumn'])
                            {
                                //Pour chaque showColumn de rootTable étant jointe à une autre table
                                //Ajout du join créé parmis ceux existants
                                $newJoin = $join;
                                
                                //Les join/showKey vont changer au nom de ceux qu'on mettra dans la vue
                                $newJoin['rootTable']['showKey'] = [];
                                $newJoin['rootTable']['joinKey'] = [];

                                $newJoin['rootTable']['Name'] = $tableAppelation;
                                $newJoin['rootTable']['originTableName'] = $select['originPivotTable'];

                                $newJoin['rootTable']['joinKey'][$joinShowColumn] = $select['Name'];
                                $newJoin['rootTable']['showKey'][$joinShowColumn] = $select['Name'];

                                $joinsList[/*"$constraintName-" . implode(',', $newJoin['rootTable']['joinKey']) . '-' . implode(',', $newJoin['extraTable']['joinKey'])*/] = $newJoin;
                            }

    //Enrichissement du selectArray et du joinArray
    $mainSelectArray = [];
    $joinSelectArray = [];
    $joinedPivotSelectArray = [];
    $elseSelectArray = [];
    foreach($joinsList as $constraintName => $join)
    {
        //Préparation du selectArray
        $i = 0;
        foreach($join['extraTable']['showKey'] as $nIndex => $extraTableShowColumn)
        {
            //Pour chaque extraShowColumn de la table
            
            $selectArray['j-' . $constraintName . '-' . $i] =
            [
                'Database' => $join['Database'],
                'originTable' => $join['extraTable']['Name'],
                'Table' => 'tempjoin_' . $constraintName,
                'Name' => $extraTableShowColumn
            ];
            $i++;
        }

        $i = 0;
        foreach($join['rootTable']['showKey'] as $nIndex => $rootTableShowColumn)
        {
            //Obtenir le nom d'origine de la colonne qui est clé de la colonne renommée que l'on a en main
            //On voit qu'elle est nième, alors on prend la clé de la nième colonne
            $originColumn = is_string($nIndex)
                ?   $nIndex
                :   array_search(array_values($join['rootTable']['showKey'])[$nIndex], $join['rootTable']['showKey']);

            if(is_string($originColumn))
                $selectArray['j-' . $constraintName . '-' . $i++]['originColumn'] = $originColumn;
        }

        $i = 0;
        foreach($join['rootTable']['joinKey'] as $rootTablejoinColumn)
        {
            //Pour chaque colonne de jointure côté root
            //Si une colonne portant le nom qu'on a donné en alias/rename à une colonne de jointure existe déjà, on la jarte
            $alias = isset($join['columnAlias'])
                ?   $join['columnAlias']
                :   $rootTablejoinColumn;

            
            if
            (
                $key = array_search(
                    $alias,
                    array_column_keep_key($selectArray, 'Name')
                )
            )
            {
                unset($selectArray[$key]);
            }

            $selectArray['j-' . $constraintName . '-' . $i++]['Rename'] = $alias;
        }

        //Préparation du joinArray
        $joinArray[$constraintName] = $join;
        $joinArray[$constraintName]['extraTable']['Rename'] = 'tempjoin_' . $constraintName;
    }

    $interJoinSelects = sqlGetInterJoinSelects($db, $tableName);

    //$selectArray sorting
    //ksort($selectArray);
    foreach($selectArray as $key => $select)
        if(is_numeric($key))
        {
            $mainSelectArray[$key] = $select;
        }
        elseif(preg_match('/^j-[0-9]+-[0-9]+$/', $key))
        {
            $joinedPivotSelectArray[$key] = $select;
        }
        elseif(preg_match('/^j-[0-9\w_]+-[0-9]+$/', $key))
        {
            $joinSelectArray[$key] = $select;
        }
        else
        {
            $elseSelectArray[$key] = $select;
        }

    ksort($mainSelectArray);
    ksort($joinSelectArray);
    ksort($joinedPivotSelectArray);
    rsort($joinedPivotSelectArray);
    ksort($elseSelectArray);

    $selectArray = [];
    $selectArray = 
        array_merge(
            $joinSelectArray,
            $mainSelectArray,
            $interJoinSelects,
            $joinedPivotSelectArray,
            $elseSelectArray
        );
        
    return $joinsList;
}

function sqlGetJoinColumns($db, $tableName)
{
    global $dbName;
    return array_column(
        sqlSelect($db, "SELECT DISTINCT `information_schema`.`KEY_COLUMN_USAGE`.`COLUMN_NAME` FROM `information_schema`.`KEY_COLUMN_USAGE` WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?", [$dbName, $tableName]),
        'COLUMN_NAME'
    );
}

function sqlPivot(
    $db,  
    $originalTableName, //Nom de la table racine qui va être copiée pour l'affichage final
    $tempTableName,     //Nom de la table temporaire copiée sur la racine
    $pivotData,    //Donnée de pivot
    &$columnsAttributes,
    &$selectArray,
    $addedAfterColumn   //Après cette colonne s'ajouteront les nouvelles colonnes
)
{
    global $dbName;
    $addedKeysMiscs = array_combine($pivotData['pivotShowKey'], (array)$pivotData['rootColumnPreviousTexts']);
    $pivotSelectArray = [];
    $values = [];
    
    //CRÉATION DES COLONNES
    $extraWhereClause = (bool)(isset($extraWhereClause) AND $extraWhereClause AND !preg_match('["\'`;]', $extraWhereClause)) ? "AND $extraWhereClause" : '1';
    //$extraWhereClause IJSQL
    
    $addedData = sqlSelect($db, "SELECT * FROM " . $pivotData['extraTable']['Name'] . " WHERE $extraWhereClause GROUP BY " . implode(',', $pivotData['extraTable']['joinKey']) . " DESC");
    foreach($pivotData['pivotShowKey'] as $joinShowColumn)
    {
        //Pour chaque groupement de colonnes à l'image d'une donnée dans la table extra et la table de jointures
        $addedColumnsGroup[$joinShowColumn] = [];
    }
    //$totaltime = 0.0;
    foreach($addedData as $extraTuple)
    {
        //Pour chaque ligne du tableau extra en temps que colonne
        //ATTENTION: Les colonnes apparaissent dans l'ordre inverse entre le tableau temporaire 'table_temp' et le array $addedColumns
        if(
            array_values(
                array_intersect(
                    array_keys($extraTuple), 
                    (array)array_column(sqlGetShowKey($db, $pivotData['extraTable']['Name']), 'Name'))
                ) 
            === array_values(array_column(sqlGetShowKey($db, $pivotData['extraTable']['Name']), 'Name'))
        )
        {
            //Si la clé que l'on veut ajouter existe complètement dans la table extra
            //if(count(numericKeys($addedKeysMiscs, 'drop')) > 1)
            {
                //$startTime = microtime(true);
                //Si l'on ajoute plus d'une clé
                foreach($pivotData['pivotShowKey'] as $joinShowColumn)
                {
                    //Pour chaque donnée de la jointure considérée comme nouvelle colonne
                    $showColumnsNames = 
                        array_column(
                            sqlGetShowKey(
                                $db,
                                $pivotData['extraTable']['Name']),
                                'Name'
                            );
                    $showColumnsValues = 
                        implode(
                            ',', 
                            array_return(
                                $extraTuple,
                                $showColumnsNames
                            )
                        );
                    $newColumnName = "$addedKeysMiscs[$joinShowColumn]$showColumnsValues";
                    
                    //La nouvelle colonne prend ses attributs
                    $columnsAttributes[$newColumnName] = 
                        isset($columnsAttributes[$newColumnName])
                            ?   array_replace_recursive(
                                    $columnsAttributes[$newColumnName],
                                    [
                                        'Database' => $dbName,
                                        'edittable' => $pivotData['pivotTable']['Name'],
                                        'primaryKey' => $pivotData['pivotPrimaryKey'],
                                        'editKey' => $joinShowColumn
                                    ]
                                )
                            :   [
                                    'Database' => $dbName,
                                    'edittable' => $pivotData['pivotTable']['Name'],
                                    'primaryKey' => $pivotData['pivotTable']['primaryKey'],
                                    'editKey' => $joinShowColumn
                                ];
                                
                    //La nouvelle colonne est ajoutée au selectArray
                    $pivotSelectArray[] =
                    [
                        'Database' => $dbName,
                        'Table' => $tempTableName,
                        'originTableName' => $originalTableName,
                        'originPivotTable' => $pivotData['pivotTable']['Name'], //$pivotData['extraTable']['Name']
                        'originColumn' => $joinShowColumn,
                        'Name' => $newColumnName
                    ];

                    //Mettre le bon type de valeur pour la colonne qui va être crée
                    $joinShowColumnTypes = [];
                    foreach(array_cross(sqlGetColumnsProperties($db, $pivotData['pivotTable']['Name'], ['Field', 'Type'])) as $fieldNType)
                    {
                        preg_match('#(\w+)(\(([0-9]+)\))?#', $fieldNType['Type'], $matches);
                        $joinShowColumnTypes[$fieldNType['Field']] = ['Type' => strtoupper($matches[1]), 'Size' => isset($matches[3]) ? $matches[3] : ''];
                    }
                    
                    //Ajout dans la table temporaire
                    sqlAlter(
                        $db,
                        $tempTableName,
                        $newColumnName,
                        'ADD',
                        $joinShowColumnTypes[$joinShowColumn]['Type'],
                        $joinShowColumnTypes[$joinShowColumn]['Size'],
                        $addedAfterColumn,
                        in_array($joinShowColumn, $pivotData['pivotShowKey'])
                            ?   sqlGetColumnProperty($db, $pivotData['pivotTable']['Name'], $joinShowColumn, 'Comment')
                            :   NULL
                    );

                    $addedColumnsGroup[$joinShowColumn] = 
                        array_merge(
                            $addedColumnsGroup[$joinShowColumn], 
                            [
                                '#' . implode(',', array_return($extraTuple, $pivotData['extraTable']['joinKey'])) => $newColumnName
                            ]
                        );
                }
                //$totaltime = $totaltime + microtime(true) - $startTime;
                //var_dump($pivotData['pivotTableName'], microtime(true) - $startTime, $totaltime);
            }
        }
    }

    //Récupération des données temporaires
    $tempDatas = sqlSelect($db, "SELECT * FROM $tempTableName");
    $addedValuesGroup = [];
    foreach($pivotData['pivotTable']['showKey'] as $joinShowColumn)
    {
        //Pour chaque groupement de colonnes à l'image d'une donnée dans la table extra et la table de jointures
        $addedValuesGroup[$joinShowColumn] = [];
    }

    $addedColumnsGroup = numericKeys($addedColumnsGroup, 'drop');

    foreach($tempDatas as $tempData)
    {
        //Pour chaque tuple de la table temporaire
        //var_dump($tempData, $rootPrimaryKey);

        //Récupérer les valeurs primaires de la table racine
        $rootPrimaryValues = [];
        foreach($pivotData['rootTable']['joinKey'] as $rootPrimaryColumn)
        {
            $rootPrimaryValues[/*$rootPrimaryColumn*/] = $tempData[$rootPrimaryColumn];
        }

        //Séléctionner les données de la table
        $joins = sqlSelect($db, "SELECT * FROM " . $pivotData['pivotTable']['Name'] . " WHERE " . implode(' = ? AND ', $pivotData['rootTable']['joinKey']) . " = ?", $rootPrimaryValues);
        
        //Modifications à apporter à cette ligne conformément aux informations sur les joins récupérés
        $addedValues = [];
        foreach($joins as $join)
            foreach($pivotData['pivotShowKey'] as $joinShowColumn)
            {
                //Pour chaque join dépendant en une part de la $tempData en question
                //La valeur primaire devient une chaîne de charactères de chaque valeurs séparées par des virgules
                $joinPrimaryValue = implode(',', array_return($join, $pivotData['pivotTable']['primaryKey']));
                
                //$extraPrimaryValue,$extraPrimaryValue,extraPrimaryValue...
                $extraJoinValues = implode(',', array_return($join, $pivotData['extraTable']['pivotJoinKey']));
                
                //Ajout de la donnée dans les colonnes de la table temporaire principale
                $addedValuesGroup[$joinShowColumn] = 
                    array_merge(
                        $addedValuesGroup[$joinShowColumn], 
                        [
                            "#$extraJoinValues" => 
                                [
                                    'value' => $join[$joinShowColumn],
                                    'primaryValue' => $joinPrimaryValue
                                ]
                        ]
                    );
            }
        
        //Requête sql: modification des données ajoutées
        $addedValuesGroup = numericKeys($addedValuesGroup, 'drop');
        foreach($pivotData['pivotTable']['showKey'] as $joinShowColumn)
        {
            if(isset($addedColumnsGroup[$joinShowColumn], $addedValuesGroup[$joinShowColumn]) AND $addedColumnsGroup[$joinShowColumn] != [] AND $addedValuesGroup[$joinShowColumn] != [])
            {
                //Si les données correspondant à la joinShowColumn sont trouvées et existantes dans les colonnes/valeurs ajoutées
                $primaryValuesListsGroups[$joinShowColumn][] = 
                    array_combine(
                        array_values($addedColumnsGroup[$joinShowColumn]), 
                        array_column(
                            array_orderByKey(
                                $addedColumnsGroup[$joinShowColumn], 
                                $addedValuesGroup[$joinShowColumn]
                            ), 
                            'primaryValue'
                        )
                    );

                //Mise a jour de la grille générée pour l'affichage
                sqlUpdate(
                    $db, 
                    $tempTableName, 
                    $pivotData['rootTable']['joinKey'], 
                    array_values(array_return($tempData, $pivotData['rootTable']['joinKey'])), 
                    array_values($addedColumnsGroup[$joinShowColumn]), 
                    array_column(
                        array_orderByKey(
                            $addedColumnsGroup[$joinShowColumn], 
                            $addedValuesGroup[$joinShowColumn]
                        ), 
                        'value'
                    )
                );
            }
        }
    }
    
    //Merge de tous les sub-array de primaryValuesListsGroups dans primaryValues
    if(isset($primaryValuesListsGroups))
        foreach(array_cross($primaryValuesListsGroups) as $nIndex => $primaryValuesLists)
        {
            $primaryValues[$nIndex] = [];
            foreach($primaryValuesLists as $primaryValuesList)
            {
                $primaryValues[$nIndex] = array_merge($primaryValues[$nIndex], $primaryValuesList);
            }
        }
        
    $selectArray = (bool)$selectArray
        ?   array_merge($selectArray, $pivotSelectArray)
        :   $pivotSelectArray;
        
    return isset($primaryValues) ? $primaryValues : [];
}

//Identique à sqlIndb(), mais requiert un procédure SQL et peut détécter les tables temporaires
function tableExists($db, $table, $dbName = '')
{
    global $dbName;

    if(sqlIndb($db, 'check_table_exists', 'procedure', $dbName))
    {
        //Appel de procédure
        $request = $db -> prepare("CALL check_table_exists(?)");
        $request -> execute([$table]);

        //Séléction de valeur de retour
        $data = sqlSelect($db, 'SELECT @table_exists');
        if(isset($data[0]['@table_exists']))
        {
            //La valeur de retour existe
            return (int)$data[0]['@table_exists'];
        }
        else
        {
            return 0;
        }
    }
    else
    {
        return -1;
    }
}

function sqlGetPrimaryKey($db, $tableName, $array = false)
{
    //'colonne1,colonne2,colonne3,...'
    $return = array_column(sqlQuery($db, "SHOW INDEX FROM $tableName WHERE Key_name = 'PRIMARY'", true), 'Column_name');
    return $array
        ?   $return
        :   implode(',', $return);
}

function sqlGetShowKey($db, $tableName, $returnType = 'selectArray', $tableRename = NULL, $defaultShowKey = '', $returnBlankIfIsDefaultShowKey = false)
{
    global $dbName, $showKeyColumnName;
    $showKey = [];

    //Nom de la table dans la requête
    $queryTableName = isset($tableRename) ? $tableRename : $tableName;

    //Propriétés des colonnes de la table
    $columnsProperties = array_cross(sqlGetColumnsProperties($db, $tableName, ['Field', 'Comment']));
    
    $showKeyByComment = in_array('showKey', array_column($columnsProperties, 'Comment'));
    $showKeyByField = in_array($showKeyColumnName, array_column($columnsProperties, 'Field'));

    if($showKeyByComment)
    {
        //Si au moins une showKey exprimée par commentaire se trouve dans la table
        foreach($columnsProperties as $properties) if(inString($properties['Comment'], 'showKey'))
        {
            //Pour chaque colonne comprise dans la showKey (il faut que le mot 'showKey' soit comprit dans son commentaire)
            
                $showKey[] = 
                [
                    'Database' => $dbName,
                    'Table' => $queryTableName,
                    'Name' => $properties['Field']
                ];
        }
    }
    elseif($showKeyByField)
    {
        foreach($columnsProperties as $properties) if($properties['Field'] === $showKeyColumnName)
        {
            //Pour chaque colonne comprise dans la showKey (il faut que le mot 'showKey' soit comprit dans son commentaire)
            
            $showKey[] = 
            [
                'Database' => $dbName,
                'Table' => $queryTableName,
                'Name' => $properties['Field']
            ];
        }
    }
    else
    {
        //Si aucune showKey n'est indiquée dans les commentaires de colonnes
        $defaultColumn = ($defaultShowKey === '')
                            ?   $showKeyColumnName
                            :   $defaultShowKey;
        
        $showKey[] = 
        [
            'Database' => $dbName,
            'Table' => $queryTableName,
            'Name' => $defaultColumn
        ];
    }
    
    if($returnType === 'concat' AND count($showKey) > 1)
    {
        //Si l'on veut directement la fonction MySQL 'CONCAT()' à insérer dans l'attribut 'Name' de la clause SELECT
        if($showKeyByComment)
        {
            foreach($showKey as $key => $showColumn)
            {
                $showKey[$key] = "$showColumn[Database].$showColumn[Table].$showColumn[Name]";
            }
            $showKey = 'CONCAT(' . implode(',\',\',', $showKey) . ')';
        }
        else
        {
            $showKey = $showKey['Name'];
        }
    }
    elseif(!$showKeyByComment AND $returnBlankIfIsDefaultShowKey)
    {
        //Si l'on demande un CONCAT() mais qu'une seule colonne n'est dans la showKey, c'est que l'affichage de cette colonne est déjà prévu autre part
        //Cela s'explique par le fait que le CONCAT() représente une valeur additionnelle, en read-only, déduite d'autres champs affichés.
        //Cependant, si une valeur déduite d'autres champs affichés n'est qu'un champ, c'est qu'elle est déjà affichée. Dans ce cas, on ne l'affiche pas.
        if($returnType === 'selectArray')
        {
            $showKey = [];
        }
        else
        {
            $showKey = '';
        }
    }//var_dump($showKey);

    return $showKey;
}

//Restituer les colonnes d'une table dans un array
function sqlGetColumns($db, $tableName, $full = true)
{
    if($full){$full = ' FULL';}
    else {$full = '';}
    if(tableExists($db, $tableName))
    {
        $return = sqlQuery($db, "SHOW$full COLUMNS FROM $tableName");
        if($return == [])
        {
            $showCreate = sqlQuery($db, "SHOW CREATE TABLE $tableName");
            preg_match('#^CREATE TEMPORARY TABLE `(\w*)` \(#', $showCreate[0]['Create Table'], $matches);
        }
    }
    else
    {
        $return = [];
    }
    return $return;
}

//Restituer un attribut donné de chaque colonne d'une table
function sqlGetColumnsProperties($db, $tableNames, $properties, $full = true, $blackList = [])
{
    $tableNames = (array)$tableNames;
    $columnsProperties = [];

    foreach($tableNames as $tableName) if(!in_array($tableName, $blackList))
    {
        //obtention des propriétés d'une table
        $columnsProperties[$tableName] = array_columns(sqlGetColumns($db, $tableName, $full), $properties);
    }

    if(count($columnsProperties) === 1)
    {
        //Si un seul champ est demandé
        $columnsProperties = array_unpack($columnsProperties, 2);
    }
    return $columnsProperties;
}

//Restituer un attribut d'une colonne
function sqlGetColumnProperty($db, $tableName, $columnName, $property)
{
    $columnsList = sqlGetColumns($db, $tableName);

    if(isset($columnsList[0][$property]))
    {
        //Si l'attribut existe pour les colonnes SQL
        foreach($columnsList as $column)
        {
            //Pour chaque colonne
            if($columnName == $column['Field'])
            {
                //Ne séléctionner qu'une seule colonne (la demandée)
                if(isset($column[$property]))
                {
                    //Retrour de la propriété demandée
                    return $column[$property];
                }
            }
        }
    }
    else
    {
        //Attribut de colonne inexistant
        return "Undefined property : $property";
    }
}

//Fonction vérifiant simplement la présence de tous les paramètres nécessaires pour la mise à jour des données
function sqlCheckAttributesToMakeColumnEditable($columnAttributes, $var_dump = true)
{
    $missing = [];
    foreach(
    [
        'edittable',
        'primarykey',
        'editkey',
        'listPrimaryKey',
        'list',
        'displaykey',
        'extraTableQueryRename'
    ] as $attribute)
        if(!in_array($attribute, array_keys($columnAttributes)))
        {
            $missing[] = $attribute;
        }
    
    if($missing === [])
    {
        return true;
    }
    else
    {
        if($var_dump)
        {
            var_dump('Missing in $columnAttributes to make column editable:', $missing);
        }

        return false;
    }
}

//Restituer les valeurs séparées d'un caractère stockées en commentaire de chaque colonne d'une table 
function sqlGetColumnsAttributes($db, $table, $checkEnum = false, $separator = ':')
{
    $columnsAttributes = [];
    $columnsList = sqlGetColumns($db, $table, true);
    foreach($columnsList as $column)
    {
        //Pour chaque colonne
        //On sépare le nom de table d'origine du nom de clé primaire
        $columnInfo = explode($separator, $column['Comment']);    //$columnInfo[0] = table d'origine    $columnInfo[1] = nom de clé primaire   $columnInfo[2] = nom de la donnée à modifier (affichée)
        if($checkEnum AND inString(sqlGetColumnProperty($db, $columnInfo[0], $column['Field'], 'Type'), 'enum'))
        {
            //Si la colonne à afficher est de type enum et qu'on choisit de le vérifier
            $type = sqlGetColumnProperty($db, $columnInfo[0], $column['Field'], 'Type');
        }
        //On remplit le tableau $columnsAttributes des tables d'origines de chaque colonne stockée en 'comment'
        $columnsAttributes[$column['Field']] =
        [
            'edittable' => $columnInfo[0], 
            'primarykey' => $columnInfo[1], 
            'editkey' => $columnInfo[2]
        ];
    }
    
    return $columnsAttributes;
}

function globalToArray($input, $clauseType, $table_, &$execute = [], $selectArray = [], $columnsAttributes = [], $joinArray = [])
{//                                                                  $selectArray sert à connaître les noms d'origine des variables
    //                                                                                  $columnsAttributes sert à connaître les noms de tables
    //                                                                                                           $joinArray sert à renommer les tables si besoins
    global $dbName;
    //var_dump($joinArray, $selectArray);

    $result = [];
    
    switch($clauseType)
    {
        case 'WHERE':
            if(isset($input) AND $input != [])
            {
                //Le but est de construire une requête à partir des informations en URL,
                //  et ensuite se servir du $selectArray pour retrouver des informations qui manqueraient
                foreach($input as $key => $inputValue) if(preg_match('#f([0-9]+)_(\w+)#', $key, $matches))
                {
                    //Concaténer l'input dans $result
                    //Mettre en forme pour une requête préparée (prepare/execute)
                    if($matches[2] === 'Value')
                    {
                        $result['f' . (int)$matches[1]]['Value'] = '?';
                        $result['f' . (int)$matches[1]]['Execute'] = $execute[] = $inputValue;
                    }
                    //Concaténer l'input dans $result
                    else $result['f' . (int)$matches[1]][$matches[2]] = $inputValue;

                    foreach($selectArray as $constraintName => $selected)
                    {
                        //Pour chaque selected

                        //Fouiller dans l'input
                        foreach($result as $filterKey => $filter)
                        {
                            //Pour chaque filtre
                            //On complète les paramètres attendus pour faire la requête
                            $result[$filterKey]['Condition'] = 'AND';
                            $result[$filterKey]['Database'] = $dbName;

                            //Ensuite, on déduit du nom de chaque champ de la requête du nom d'origine + nom de table à insérer dans la requête
                            foreach($filter as $parameter => $value)
                            {
                                //Pour chaque paramètre du filtre
                                //Ajouter le paramètre 'Table' et corriger 'Key' si besoins
                                if($matches[2] === 'Key' AND !isset($result[$filterKey]['Table']))
                                {
                                    //Si on séléctionne un paramètre de l'input portant sur la colonne
                                    if(isset($selected['Rename']) AND strtolower($value) === strtolower($selected['Rename']))
                                    {
                                        $result[$filterKey]['Table'] = $selected['Table'];
                                        $result[$filterKey]['Key'] = $selected['Name'];
                                    }
                                    elseif($value === $selected['Name'] AND isset($joinArray) AND $joinArray)
                                    {
                                        //[shit-flag]
                                        $result[$filterKey]['Table'] = $joinArray[key($joinArray)]['rootTable']['TableName'];
                                    }
                                }
                            }
                        }
                    }
                }
            }
            break;

        case 'ORDER BY':
            if(isset($input) AND $input != []) 
                foreach($input as $name => $inputValue) 
                    foreach((array)$inputValue as $inputSubValue)
                {
                    if(preg_match('#([\w+ \-]+)///(ASC|DESC)#', $inputSubValue, $matches))
                    {
                        $result[] = 
                        [
                            'Ignore' => ['Table'],
                            'Database' => $dbName, 
                            'Table' => '', 
                            'Name' => $matches[1], 
                            'Mean' => $matches[2]
                        ];
                    }
                }
            break;
    }
    //var_dump($clauseType, $result);
    return $result;
}

function sqlGetTableHiddenColumns($db, $tableName)
{
    global $columnsConfigTableName, $showKeyColumnName, $tableConfigTableName;
    return array_column(
                sqlSelect($db,
                    "SELECT $columnsConfigTableName.$showKeyColumnName
                    FROM $columnsConfigTableName
                    JOIN $tableConfigTableName
                        ON $tableConfigTableName.ID_table = $columnsConfigTableName.ID_table
                    WHERE $tableConfigTableName.$showKeyColumnName = '$tableName'
                        AND $columnsConfigTableName.Hidden = 1"
                ), 
                $showKeyColumnName
            );
}

function getRelationnalTable(
    $db,
    $originTable,
    $userGrants,
    $directQueryGlobal = NULL,
    $indexUrl = 'index.php',
    $returnType = 'htmlTable',
    &$keys = []
)
{
    global $dbName, $tableConfigTableName, $columnsConfigTableName, $usersTableName, $showKeyColumnName, $idString, $contentString;
    
    //Obtention de la clé primaire de la table
    $tablePrimaryKey = sqlGetPrimaryKey($db, $originTable);

    //Création d'une table temporaire équivalente avec des commentaires sur les colonnes pour identifier les valeurs
    $workTable = ($returnType === 'htmlTable')
        ?   sqlCopyTemp($db, $originTable, $tablePrimaryKey)
        :   $originTable;

    //Récupération des attributs de chaque colonne: 
    //---table d'origine/à éditer (edittable) = $originTable, 
    //---colonne d'origine/à éditer (editkey) = la colonne elle même, 
    //---clé primaire (primarykey) = $tablePrimaryKey
    sqlGenerateColumnsTools($db, $originTable, $tablePrimaryKey, $columnsAttributes, $selectArray, NULL, $workTable, $returnType === 'htmlTable');

    //Pivots (sqlAlter)
    $htmlDatalists = '';
    $joinArray = $showKeyArray = [];
    $showKeyArray = (array)array_column(sqlGetShowKey($db, $originTable), 'Name');
    if($returnType === 'htmlTable')
    {
        $columnsAttributes = 
            array_replace_recursive(
                $columnsAttributes, 
                sqlManagePivotForeignKeysDatas($db, 
                    $originTable, 
                    false, 
                    $userGrants, 
                    $columnsAttributes, 
                    $pivotPrimaryValues, 
                    $selectArray,
                    $pivotsShowKeyJoinings,
                    $workTable
                )
            );
            
        //Les tables de pivot et la table visée vont toutes les deux subire des jointures directes afin d'afficher les bons noms de colonnes/valeurs
        sqlGetJoinTools($db, $originTable, $selectArray, $joinArray, $workTable);

        sqlJoin($db, $originTable, $joinArray, $columnsAttributes, $selectArray, $htmlDatalists, $workTable, $userGrants/*, $datalistQuery*/);
        
        //var_dump($originTable, $returnType, '$selectArray', $selectArray, '$columnsAttributes', $columnsAttributes);
        
        $hiddenColumnsNames = sqlGetTableHiddenColumns($db, $originTable);
    }
    elseif($returnType === 'htmlDatalist')
    {
        //Insertion de la showKey dans le selectArray
        $showKeyConcat = sqlGetShowKey($db, $originTable, 'concat', $workTable, NULL);
        //var_dump($originTable, $returnType, $showKeyConcat, $showKeyArray);
        
        if(is_string($showKeyConcat) AND preg_match('/CONCAT\([\w, ]+\)/', $showKeyConcat))
        array_unshift(
            $selectArray,
            [
                'Ignore' => ['Database', 'Table', 'Field Quotes'],
                'Database' => $dbName,
                'Table' => $originTable,
                'Name' => $showKeyConcat,
                'Rename' => $showKeyColumnName
            ]
        );
    }
    
    $primaryColumns = [];
    foreach(sqlGetPrimaryKey($db, $workTable, true) as $primaryColumn)
    {
        $primaryColumns[] =
        [
            'Database' => $dbName,
            'Table' => $workTable,
            'Name' => $primaryColumn
        ];
    }
    
    $datas = sqlSelect($db,
    [
        'SELECT' => $selectArray,
        'FROM' => 
        [
            [
                'Database' => $dbName,
                'Table' => $workTable
            ]
        ],
        'LEFT JOIN' => $joinArray,
        'WHERE' => globalToArray($directQueryGlobal, 'WHERE', $workTable, $execute, $selectArray, $columnsAttributes, $joinArray),
        'GROUP BY' => $primaryColumns,
        'ORDER BY' => globalToArray($directQueryGlobal, 'ORDER BY', $workTable)
    ], $execute, 'drop', ['SELECT', 'WHERE'], false);
    
    if($returnType === 'htmlDatalist')
    {
        $return = htmlDatalist(
            $datas,
            [$tablePrimaryKey => 'listprimaryvalue'],
            $showKeyArray,
            ['datalist' => "id = '$originTable'"]
        );
    }
    elseif($returnType === 'htmlTable')
    {
        //Si la table en question est la table d'utilisateurs
        $indexUrlWithGets = $indexUrl . rebuildGetString(true);
        $cellMisc = $originTable === $usersTableName
            ?   [array_column(sqlGetShowKey($db, $originTable), 'Name')[0] => "<a href = '$indexUrlWithGets&amp;uid=$idString'>$contentString</a>"] //[shit-flag] Première showColumn seulement
            :   NULL;
        
        $renames = [];
        foreach($showKeyArray as $nIndex => $showColumn)
        {
            $showKeyArray[$nIndex] = ucwords($showColumn);
        }
        
        $htmlTable = htmlTable(
            $datas,
            $selectArray,
            $joinArray,
            $tablePrimaryKey,
            array_merge(
                array_fill_keys($hiddenColumnsNames, ''),
                $renames
            ),
            ['td' => 'class = \'dynamic-td\''],    //$tableElementsEditionRequested
            $columnsAttributes,   //columnsAttributes
            $cellMisc,   //$cellMisc
            $indexUrl,
            $indexUrlWithGets,
            $originTable,
            NULL,
            $showKeyArray,
            $pivotPrimaryValues,
            in_array('DELETE', $userGrants["$dbName.$originTable"]['tableswide permissions'][$originTable])
        );
                    
        $joinTables = sqlGetJoinList($db, $originTable);
        $lists = [];
        foreach($joinTables as $constraintName => $join)
            $lists[$constraintName] = $join['extraTable']['Name'];
        
        $return = 
            htmlFilterBox($_GET, array_keys($columnsAttributes), $indexUrl, $selectArray) . 
            $htmlTable .
            htmlCreateInputs($db, $originTable, array_keys($columnsAttributes), $userGrants, $indexUrl . rebuildGetString());
    }

    //Affichage html
    return $return . $htmlDatalists;
}

/*
  _    _ _             _    __                  _   _                 
 | |  | | |           | |  / _|                | | (_)                
 | |__| | |_ _ __ ___ | | | |_ _   _ _ __   ___| |_ _  ___  _ __  ___ 
 |  __  | __| '_ ` _ \| | |  _| | | | '_ \ / __| __| |/ _ \| '_ \/ __|
 | |  | | |_| | | | | | | | | | |_| | | | | (__| |_| | (_) | | | \__ \
 |_|  |_|\__|_| |_| |_|_| |_|  \__,_|_| |_|\___|\__|_|\___/|_| |_|___/
*/

function htmlTable( 
    $datas, //Données sous forme de array en deux dimensions [array(array('string' => 'string', ...)]
    $selectArray = NULL,
    $joinArray = NULL,
    $primaryKey = NULL, //Nom de la clé primaire dans la base de données [forme: 'ID_element'] 'string'
    $replaceColumns = NULL, //array assossiatif [forme: array('ancienne clé' => 'nouvelle clé', ...)](array('string' => 'string', ...))
    $tableElementsEditionRequested = NULL,  //Contient un array pour chaque élément à modifier [array('string' => "'attribute1' = 'value1' 'attribute2' = 'value2' [...]" , ...)]
    $columnsAttributes = NULL, //Ajout dans cellules correspondantes aux colonnes des attributs [array($key => array($attribute => $value, ...), ...)]
    $cellMisc = NULL, //Ajout dans cellules de contenu [forme: 'avant contenu ' + '__ID__' + 'après contenu']'string'
    $location = NULL, //Attribut 'action' du formulaire contenant le tableau entier
    $indexUrl = false,   //Ajout d'une case 'supprimer' à chaque occurence
    $rootTable = NULL, //Table originellement affichée (renseigner pour option suppression)
    $tableTitle = NULL, //Titre du tableau
    $titlingShowKey = NULL,   //Champ qui sera recopié en propriété html 'title' de chaque ligne
    $primaryValuesGroups = NULL,  //array rassemblant les valeurs primaires des occurences dans le même ordre que $datas
    $deleteButton = false
)
{
    global $idString;   //Chaîne de charactères remplacée par la clé primaire d'une donnée 'string'
    global $contentString; // Chaîne de charactères remplacée par le contenu lui même dans une cellule
    
    //var_dump($joinArray, $selectArray);
    
    $columnsList = [];
    $areKeys = 1;
    $tableElementsEdition = ['table' => '', 'tr' => '', 'thead' => '', 'tbody' => '', 'th' => '', 'td' => ''];
    if($tableElementsEditionRequested != NULL)
    {
        //Si l'on veut affecter des attributs à certains éléments de tableau
        foreach($tableElementsEditionRequested as $tableElement => $attributesString)
        {
            //Pour chaque affectation d'attributs
            if(array_key_exists($tableElement, $tableElementsEdition))
            {
                //Si l'élément en $tableElement existe dans le tableau $tableElementsEdition
                $tableElementsEdition[$tableElement] = $attributesString;
            }
        }
    }
    
    //Les colonnes renommées doivent aussi l'être dans $titlingShowKey
    if($renamedTitlingShowColumns = array_intersect($titlingShowKey, array_values($replaceColumns)))
    {
        //Quelles colonnes sont en commun entre $titlingShowKey et $replaceColumns ?
        foreach((array)$renamedTitlingShowColumns as $renamedTitlingShowColumn)
        {
            //Pour chacune d'entre elles, on remplace l'ancienne clé de $titlingShowKey par la nouvelle
            $titlingShowKey[] = array_search($renamedTitlingShowColumn, $replaceColumns);
            unset($titlingShowKey[array_search($renamedTitlingShowColumn, $titlingShowKey)]);
        }
    }

    //Les valeurs primaires sont groupées par table extra avec un nIndex, l'objectif est d'y accéder directement par le nom de colonne
    $primaryValues = [];
    foreach((array)$primaryValuesGroups as $primaryValuesGroup)
    {
        $primaryValues = array_merge($primaryValues, $primaryValuesGroup);
    }

    //Création du contenu html
    $html = "<table $tableElementsEdition[table]>";
    
    foreach($datas as $index => $tuple)
    {
        //Pour chaque ligne
        $tuple = (array)$tuple;
        if($areKeys)
        {
            //Si on en est encore aux noms de colonnes
            $html .= "<thead $tableElementsEdition[thead]>";
            if(isset($tableTitle))
            {
                $html .= "<tr $tableElementsEdition[tr]><th colspan = 100 $tableElementsEdition[th]>$tableTitle</th></tr>";
            }
            $html .= "<tr>";
            foreach($tuple as $column => $cell)
            {
                //Pour chaque key => cell d'une ligne
                if(!is_numeric($column))
                {
                    //On met une majuscule à la première lettre de chaque colonne si ce n'est pas déjà le cas
                    $column = ucwords($column);

                    //Si la key n'est pas numérique
                    if($replaceColumns AND array_key_exists($column, $replaceColumns))
                    {
                        //Si on a spécifié des $column à remplacer dans $replaceColumns, et que la $column doit être remplacée
                        $columnRename = $replaceColumns[$column];
                    }
                    else
                    {
                        //Aucun changement
                        $columnRename = $column;
                    }
                    //$columnsList[] = $column/*columnRename*/;
                    
                    if(!isset($replaceColumns[$column]) OR $replaceColumns[$column] != '')
                    {
                        //S'il faut afficher la clé (colonne)
                        //$orderColumn = isset($columnsAttributes[$column]['editkey'], $columnsAttributes[$column]['edittable']) ? $columnsAttributes[$column]['edittable'] . '.' . $columnsAttributes[$column]['editkey'] : $columnRename;
                        $orderColumn = $column;
                        $crossGet = $_GET;

                        if(isset($crossGet['order-by']) AND in_array("$orderColumn///ASC", $crossGet['order-by']))
                            unset($crossGet['order-by'][array_search("$orderColumn///ASC", $crossGet['order-by'])]);
                        elseif(isset($crossGet['order-by']) AND in_array("$orderColumn///DESC", $crossGet['order-by']))
                            unset($crossGet['order-by'][array_search("$orderColumn///DESC", $crossGet['order-by'])]);

                        $crossHiddensDatas = htmlInputs($crossGet);


                        $html .= 
                        "<th $tableElementsEdition[th]>
                            $columnRename<br />
                            <form method = 'GET' action = '$indexUrl'>" . 
                                (
                                    (isset($crossGet['order-by']) AND array_intersect(["$orderColumn///ASC", "$orderColumn///DESC"], $_GET['order-by']))
                                        ? "$crossHiddensDatas<button class = order-button>X</a>"
                                        : htmlBuildHiddens() . 
                                            "<button class = order-button name = 'order-by[]' value = '$orderColumn///ASC'>^</button>
                                            <button class = order-button name = 'order-by[]' value = $orderColumn///DESC'>v</button>"
                                ) . 
                            "</form>
                        </th>";
                    }
                }
            }
            $html .= "</tr></thead><tr $tableElementsEdition[tr]><tbody $tableElementsEdition[tbody]>";
        }
        
        $rootPrimaryValue = '';
        $html .= "<tr $tableElementsEdition[tr]>";
        foreach($tuple as $column => $cell) if(!is_numeric($column) AND (!isset($replaceColumns[$column]) OR $replaceColumns[$column] != ''))
        {
            //Si la key n'est pas numérique et que la valeur correspondante dans $replaceColumns (si elle existe) n'est pas ''
            //OBTENTION DE LA VALEUR PRIMAIRE: 2 METHODES  (tracking de la valeur affichée)
            
            $tuple[ucwords($column)] = $tuple[$column];
            if(isset($primaryValues[$index][$column]))
            {
                $primaryValue = [$primaryValues[$index][$column]];
            }
            elseif(isset($primaryKey))  //2
            {
                //Sinon la clé primaire sera celle contenue dans la table
                if(inString($primaryKey, ','))
                {
                    //Donner des valeurs primaires à partir des colonnes DE LA TABLE écrites dans la clé primaire
                    $primaryValue = [];
                    foreach(explode(',', $primaryKey) as $primaryColumn)
                        if(isset($tuple[$primaryColumn]))
                        {
                            $primaryValue[] = $tuple[$primaryColumn];
                        }
                        else var_dump($tuple, 'Missing: ' . $primaryColumn);
                }
                else $primaryValue = [$tuple[$primaryKey]];

                $rootPrimaryValue = implode(',', $primaryValue);
            }
            else
            {
                $primaryValue = [0];
            }
            $primaryValue = implode(',', $primaryValue);
            //Comme $cellMisc doit pouvoir avoir une valeur différente à chaque ligne à cause de la valeur primaire
            $cellMisc_temp = $cellMisc;

            //Insertion de la valeur primaire dans la cellule
            if(isset($cellMisc_temp[$column], $tuple[$primaryKey], $idString) AND inString($cellMisc_temp[$column], $idString))
            {
                //Si la key courante correspond à une key devant comprendre une balise de clé primaire
                //Insérer la clé primaire dans la cellule
                $cellMisc_temp[$column] = str_replace($idString, $primaryValue, $cellMisc_temp[$column]);
            }
            
            //Insertion du contenu dans le $cellMisc_temp s'il contient $contentString
            if(isset($cellMisc_temp[$column]) AND inString($cellMisc_temp[$column], $contentString))
            {
                $cell = str_replace($contentString, $cell, $cellMisc_temp[$column]);
            }
            
            //Mise en attributs html des attributs sql de chaque donnée
            $additiveAttributes = '';

            //Si la colonne est issue d'une jointure, on copie les données de columns attributes vers la clé correspondant à columnAlias
            if(!isset($columnsAttributes[$column]))
            {
                foreach($joinArray as $constraintName => $join)
                {
                    if(isset($join['columnAlias']) AND $join['columnAlias'] === $column)
                    {
                        $columnsAttributes[$column] = $columnsAttributes[implode(',', $join['rootTable']['joinKey'])];
                    }
                    //else var_dump($join);
                }
            }

            //Ajout en attributs HTML de $columnsAttributes
            if(isset($columnsAttributes[$column]))
            {
                foreach($columnsAttributes[$column] as $attribute => $value)
                {
                    $additiveAttributes .= " $attribute = '" . implode(',', (array)$value) . "'";
                }
                $additiveAttributes = ltrim($additiveAttributes);
            }
            else var_dump('Missing in $columnsAttributes: ' . $column);

            $title = [];
            $entitledKey = '';
            foreach((array)$titlingShowKey as $titlelingShowColumn)
            {
                //Si l'on souhaite utiliser un champ en tant que propriété html/element/title
                /*foreach(array_intersect(array_keys($tuple), array_column($selectArray, 'Rename')) as $showColumnToRename)
                {

                }*/

                $title[] = $tuple[$titlelingShowColumn];
                $entitledKey = str_replace('_', ' ', $column);
            }
            $title = implode(', ', $title);
            $html .=  "<td title = '$title /// $entitledKey' primaryvalue = '$primaryValue' $additiveAttributes ondblclick = \"editTable('$location')\" $tableElementsEdition[td]>$cell</td>";
        }

        if($deleteButton AND isset($rootTable, $primaryKey, $primaryValue))
        {
            //Si l'on veut ajouter une case pour supprimer l'occurence qui vient d'être représentée
            $html .=    
                "<td>
                    <form method = 'POST' action = '$indexUrl'>
                        <input type = 'hidden' name = 'queryType' value = 'delete'>
                        <input type = 'hidden' name = 'table' value = '$rootTable'>
                        <input type = 'hidden' name = 'primaryKey' value = '$primaryKey'>
                        <input type = 'hidden' name = 'primaryValue' value = '$rootPrimaryValue'>
                        <button type = 'submit'>Delete</button>
                    </form>
                </td>";
        }

        if(isset($tuple[$primaryKey], $idString) AND inString($html, $idString))
        {
            //Si la balise idString existe dans le code html
            //Remplacer par la clé primaire dans le html
            $html = str_replace($idString, $tuple[$primaryKey], $html);
        }
        
        //ligne suivante
        $areKeys = 0;
        $html .= "</tr>";
    }

    $html .= "</tbody></table>";
    return $html;
}

function htmlDatalist(
    $datas,   
    $attributes = NULL, //Les clés présentes dans la table sql que l'on veut afficher dans le datalist html array(string, ...)
    $showKey = NULL,    //clé à afficher (string)
    $generalAttributes = NULL,  //Attributs pour chaque colonnes (html) [string(htmlTag) => string(htmlAttributes], ...)
    $currentDatalistString = NULL   //Si l'on veut checker que le htmlDataList n'existe pas
)
{
    global $showKeyColumnName;
    $html = '';
    $datalistElementsEdition = ['datalist' => '', 'option' => ''];
    if($generalAttributes != NULL)
    {
        //Si le programmeur veut changer des attributs
        foreach($generalAttributes as $datalistElement => $attributesString)
        {
            //Pour chaque affectation d'attributs
            if(isset($datalistElementsEdition[$datalistElement]))
            {
                //Si l'élément en $tableElement existe dans le tableau $tableElementsEdition
                $datalistElementsEdition[$datalistElement] = $attributesString;
            }
        }
    }
    if(isset($attributes, $showKey))
    {
        $showKey = (array)$showKey;

        //choisis-t-on un datalist plus complet ?
        foreach($datas as $data)
        {
            $data = numericKeys($data, 'drop');

            //Pourchaque ligne
            $individualAttributes = '';
            foreach($data as $sqlKey => $sqlValue)
            {
                //Pour chaque paramètre sql => enrichir les attributs html...
                if(isset($attributes[$sqlKey]))
                {
                    //...si le programeur veut l'afficher
                    $individualAttributes = "$individualAttributes $attributes[$sqlKey] = '$sqlValue'";
                }
            }//var_dump($data, $showKey);
            //Ajout de l'option

            //Mise en forme de la valeur de l'attribut 'value' des options de datalist
            $value = [];
            foreach($showKey as $showColumn)
            {
                $value[] = $data[$showColumn];
            }
            $value = implode(',', $value);

            $html = "$html<option $datalistElementsEdition[option] $individualAttributes value = '$value'>";
        }
    }
    elseif(isset($datas[key($datas)]) AND !is_array($datas[key($datas)]))  //Si datas contient pas des tableaux (test restreint à la première ligne)
    {
        //ou un de base ?
        foreach($datas as $data)
        {
            //Pour chaque ligne
            $html = "$html<option $datalistElementsEdition[option] value = '$data'>";
        }
    }

    $html = "<datalist $datalistElementsEdition[datalist]>$html</datalist>";
    if(isset($currentDatalistString) AND inString($currentDatalistString, $html))
    {
        //Checker si le datalist créé existe déjà
        return '';
    }
    else
    {
        return $html;
    }
}

function htmlCreateInputs($db, $tableName, $listsInfo, $userGrants, $indexUrlWithGets)
{
    global $dbName;

    //Récupérer chaque colonne de la table et dire si l'on peut créer une occurence sans initialiser chaque colonne
    $selectedTableColumns = [];
    $columnsInitialisationInputs = '<div id = \'preset-create-inputs\'>';
    $hiddenColumnsNames = sqlGetTableHiddenColumns($db, $tableName);
    foreach(array_cross(sqlGetColumnsProperties($db, $tableName, ['Field', 'Default'])) as $nIndex => $column)
        if(!in_array($column['Field'], $hiddenColumnsNames))
    {
        $class = $required = '';
        if($column['Default'] === NULL)
        {
            //S'il n'y a pas de valeur par défaut (colonne NULL ou aucune AUCUNE)
            try
            {
                //La colonne est NULL
                sqlQuery($db, "SELECT DEFAULT($column[Field]) FROM $tableName");
                $selectedTableColumns[$column['Field']] = false;
            }
            catch(Exception $e)
            {
                //Cette colonne DOIT être initialisée à la création
                $selectedTableColumns[$column['Field']] = true;
                $required = ' required';
                $class = ' class = \'required\'';
            }
        }
        else
        {
            //Cette colonne à une valeur par défaut
            $selectedTableColumns[$column['Field']] = false;
        }
        $list = isset($lists[$column['Field']])
            ?   ' list = \'' . $lists[$column['Field']] . '\''
            :   '';
        $columnsInitialisationInputs .= "<input$list type = 'text' name = 'field-$column[Field]' placeholder = '$column[Field]'$class$required>";
    }
    $columnsInitialisationInputs .= '</div>';

    //On n'affiche le formulaire de création que si l'on a le droit de créer des occurences
    $createForm = in_array('INSERT', $userGrants["$dbName.$tableName"]['tableswide permissions'][$tableName])
        ?   "<form action = '$indexUrlWithGets' method = 'POST'>
        <input type = 'hidden' name = 'table' value = '$tableName'>
        $columnsInitialisationInputs
        <button id = create-button type = 'submit' name = 'queryType' value = 'insert' >Create</button>
    </form>"
        :   '';

    return $createForm;
}

function htmlMenu($datas,   $hrefMask = NULL,  //href avec la partie variable en fonction de l'occurence remplacée par $contentString
                            $generalAttributes = NULL, //Attributs pour chaque élément html
                            $showKey = NULL,    //Clé affichée
                            $hrefKey = NULL,    //Clé utilisée pour le href des <a></a>
                            $displayKeys = false,   //Faire apparaître le nom de la clé devant l'option
                            $split = NULL,  //Injection d'un </ul><ul> après ce $showKey / $data si $datas est array 1 dimension
                            $tupleMisc = '',     //Contenu ajouté dans une ligne
                            $selectParameter = '')
{
    global $contentString, $hiddenTables;

    $menuElementsEdition = ['nav' => '', 'ul' => '', 'li' => '', 'a' => ''];
    
    if(isset($hiddenTables))
    {
        //Mais que $hiddenTables existe, on en a quand même une !
        $blacklist = $hiddenTables;
    }
    else
    {
        //dangereux: aucune blackliste
        $blacklist = [];
    }

    if($generalAttributes != NULL)
    {
        //Si le programmeur veut changer des attributs
        foreach($generalAttributes as $menuElement => $attributesString)
        {
            //Pour chaque affectation d'attributs
            if(array_key_exists($menuElement, $menuElementsEdition))
            {
                //Si l'élément en $tableElement existe dans le tableau $tableElementsEdition
                $menuElementsEdition[$menuElement] = $attributesString;
            }
        }
    }
    
    if(isset($split))
    {
        $split = (array)$split;
    }
    
    $html = '';
    foreach($datas as $key => $data)
    {
        //Pour chaque ligne
        $toAdd = '';
        if(!isset($blacklist) OR    //Pas de blacklist OU
                (
                    isset($blacklist) AND  //Blacklist ET
                    (
                        !is_array($data) AND !in_array($data, $blacklist)
                    ) //$data = pas array et pas dans blacklist
                    OR
                    (
                        isset($showKey, $data[$showKey]) AND !in_array($data[$showKey], $blacklist)
                    )
                )
            ) //$data[$showKey] existe et pas dans Blacklist
        {
            //Élement pas blacklisté
            if($displayKeys)
            {
                //Si l'on doit afficher la clé qui correspond au tableau représenté
                $displayedKey = "$key    ---     ";
            }
            else
            {
                $displayedKey = '';
            }
            
            //Concaténation de l'option dans un href (lien)
            if(isset($hrefMask))
            {
                //Si on veut des href
                $aclass = '\'menu-a\'';
                if(isset($_GET[$selectParameter]) AND $_GET[$selectParameter] === $data)
                {
                    $aclass = '\'menu-select-a\'';
                }

                $a = "<a class = $aclass $menuElementsEdition[a] href = \"$contentString\">";
                $sa  = "</a>";
            }
            else
            {
                $a = $sa = '';
            }
            
            if(!isset($showKey) AND !isset($hrefKey))
            {
                //Si aucune clé n'est désignée ni pour être montrée, ni pour être dans le href,
                //Alors on affiche data
                $href = str_replace($contentString, $data, $hrefMask);
                $a = str_replace($contentString, $href, $a);
                $toAdd = ucwords($data);
            }

            if(isset($hrefKey))
            {
                //Si une clé est désignée pour apparaître dans le href,
                //Alors on le construit avec
                $href = str_replace($contentString, $data[$hrefKey], $hrefMask);
                $a = str_replace($contentString, $href, $a);
            }

            if(isset($showKey))
            {
                //Si une clé à montrer spécifiquement existe,
                //Alors on la fait apparaître
                $toAdd = ucwords($data[$showKey]);
            }
            
            if(inString($tupleMisc, $contentString))
            {
                //Si on veut que ce contenu soit de part et d'autre de la valeur à afficher initialement
                $toAdd = str_replace($contentString, $tupleMisc, $toAdd);
            }
            else
            {
                //Si on veut juste la mettre après
                $toAdd = $toAdd . $tupleMisc;
            }

            $html .= "<li $menuElementsEdition[li]>$a$displayedKey$toAdd$sa</li>";
    
            if( isset($split) AND                                                               //$split existe
                ((isset($showKey, $data[$showKey]) AND in_array($data[$showKey], $split)) OR        //$data est un tableau et sa valeur ayant pou clé $showKey apparait dans $split
                (!is_array($data) AND in_array($data, $split))))                                    //$data n'est pas un tableau (=> valeur) et apparait dans $split
            {
                //Si le menu doit être splité ici,
                //Alors on crée une rupture dans le <ul></ul>
                $html .= "</ul><ul $menuElementsEdition[ul]>";
            }
        }
    }

    if(isset($split))
    {
        $html = "<ul $menuElementsEdition[ul]>$html</ul>";
    }
    return "<nav $menuElementsEdition[nav]>$html</nav>";
}

function htmlStructure( $datas, 
                        $htmlStructureType, //<tag>html</tag> => 'tag'
                        $primaryKey = NULL, //Nom de la clé primaire dans la base de données [forme: 'ID_element'] 'string'
                        $showKey = NULL,    //Clé de la valeur à cibler
                        $replaceKeys = NULL, //array assossiatif [forme: array('ancienne clé' => 'nouvelle clé', ...)](array('string' => 'string', ...))
                        $structureElementsEditionRequested = NULL,  //Contient un array pour chaque élément à modifier [array('string' => "'attribute1' = 'value1' 'attribute2' = 'value2' [...]" , ...)]
                        $columnsAttributes = NULL, //Ajout dans cellules correspondantes aux colonnes des attributs [array($key => array($attribute => $value, ...), ...)]
                        $cellMisc = NULL, //Ajout dans cellules de contenu [forme: 'avant contenu ' + '__ID__' + 'après contenu']'string'
                        $location = NULL) //attribut 'action' du formulaire contenant le tableau 'string')
{
    global $idString;   //Chaîne de charactères remplacée par la clé primaire d'une donnée (string)
    global $contentString;  // Chaîne de charactères remplacée par le contenu lui même dans une cellule (string)
    $possibleStructureTypes =   [   
                                    'table' => ['#0' => 'table',  '#1' => 'tr', '#2a' => 'th', '#2' => 'td'], 
                                    'datalist' => ['#0' => 'datalist', '#1' => 'option/'], 
                                    'nav' => ['#0' => 'nav', '#1' => 'ul', '#2' => 'li'], 
                                    'select' => ['#0' => 'select', '#1' => 'option'],
                                    'list' => ['#0' => 'element', '#1' => 'subelement']
                                ];
    $tableElementsEdition = ['table' => '', 'tr' => '', 'thead' => '', 'tbody' => '', 'th' => '', 'td' => ''];
    $datalistElementsEdition = ['datalist' => '', 'option' => ''];
    $menuElementsEdition = ['nav' => '', 'ul' => '', 'li' => '', 'a' => ''];
    $structureElementsEdition = array_merge($tableElementsEdition, $datalistElementsEdition, $menuElementsEdition, ['select' => '', 'list' => '', 'element' => '', 'subelement' => '']);  //Tous les tags que l'on peut rencontrer
    if($structureElementsEditionRequested != NULL)
    {
        //Si l'on veut affecter des attributs à certains éléments de tableau
        foreach($structureElementsEditionRequested as $structureElement => $attributesString)
        {
            //Pour chaque affectation d'attributs
            if(isset($structureElementsEdition[$structureElement]))
            {
                //Si l'élément en $tableElement existe dans le tableau $tableElementsEdition
                $structureElementsEdition[$structureElement] = $attributesString;
            }
        }
    }
    $htmlGrid = '';
    if(isset($possibleStructureTypes[$htmlStructureType]))
    {
        //Si l'on demande une structure html EXISTANTE
        //Cas d'un <table>: le premier passage du foreach ecrit les th
        $areKeys = 1;
        if(isset($possibleStructureTypes[$htmlStructureType]['#2a'], $possibleStructureTypes[$htmlStructureType]['#2']) AND $areKeys)
        {
            //Pour un tableau: afficher les noms de clés en première ligne
            $htmlRow = '';
            $data = numericKeys($datas[0], 'drop');
            
            //pour chaque ligne
            foreach($data as $key => $value)
            {
                if($key != $primaryKey)
                {
                    //Pour chaque valeur de la ligne
                    $tag = $possibleStructureTypes[$htmlStructureType]['#2a'];
                    $htmlRow = "$htmlRow<$tag $structureElementsEdition[$tag]>$key</$tag>";
                }
            }
    
            //$htmlRow prêt
            $tag = $possibleStructureTypes[$htmlStructureType]['#1'];
            $htmlGrid = "$htmlGrid<$tag $structureElementsEdition[$tag]>$htmlRow</$tag>";
            $areKeys = 0;
        }
        foreach($datas as $key => $data)
        {
            //Pour chaque ligne de données
            $htmlRow = '';
            if(inString($possibleStructureTypes[$htmlStructureType]['#1'], '/') AND isset($primaryKey, $showKey, $data[$showKey]))
            {
                //Le tag sera de forme <tag ... /> (= datalist > option)
                $data = numericKeys($data, 'drop');
                $tag = str_replace('/', '', $possibleStructureTypes[$htmlStructureType]['#1']);
                $htmlGrid = "$htmlGrid<$tag $structureElementsEdition[$tag] primarykey = $primaryKey value = '$data[$showKey]' />";
            }
            else
            {
                //Le tag sera de forme <tag>...</tag>
                if(isset($possibleStructureTypes[$htmlStructureType]['#2']))
                {
                    //Si $la structure séléctionnée doit encore contenir des sous-sections
                    $data = numericKeys($data, 'drop');
                    foreach($data as $key => $value)
                    {
                        if($key != $primaryKey)
                        {
                            //Pour chaque valeur de la ligne
                            $tag = $possibleStructureTypes[$htmlStructureType]['#2'];
                            $htmlRow = "$htmlRow<$tag $structureElementsEdition[$tag]>$value</$tag>";
                        }
                    }
                    //$htmlRow prêt
                    $tag = $possibleStructureTypes[$htmlStructureType]['#1'];
                    $htmlGrid = "$htmlGrid<$tag $structureElementsEdition[$tag]>$htmlRow</$tag>";
                }
                else
                {
                    $tag = $possibleStructureTypes[$htmlStructureType]['#1'];
                    $htmlGrid = "$htmlGrid<$tag $structureElementsEdition[$tag]>$data</$tag>";
                }
            }
        }
    }
    //$htmlGrid prêt
    $tag = $possibleStructureTypes[$htmlStructureType]['#0'];
    return "<$tag $structureElementsEdition[$tag]>$htmlGrid</$tag>";
}

function htmlInputs($datas, $inputType = 'hidden', $attributes = '')
{
    $html = '';
    foreach($datas as $key => $data)
    {
        $brackets = is_array($data)
            ?   '[]'
            :   '';
        foreach((array)$data as $value)
        {
            $html .= "<input $attributes type = '$inputType' name = '$key$brackets' value = '" . array_unpack($value) . "'>";
        }
    }
    return $html;
}

function htmlFilterBox($get, $keys, $indexUrl, $selectArray)
{
    global $valueString, $tableString, $regexHintHref;
    $lastFilter = 1;

    foreach($get as $name => $value) if(preg_match("#f([0-9]+)_(\w+)#", $name, $matches))
    {
        //Pour chaque sous-variable de la superglobale $_GET correspondant à un filtre
        $lastFilter = (int)($matches[1] + 1);
        
        //Création de croix de suppression
        $href = preg_replace("#[&?]f$matches[1]" . "_\w+.*#", '', rebuildGetString());
        
        switch($matches[2])
        {
            case 'Key':
            case 'key':
                $cross[$lastFilter] = "<span class = 'filter'>$value";
                break;

            case 'Operator':
            case 'operator':
                //Si l'opérateur est REGEX, on glisse un hint pour aider les opérateurs à s'en servir
                $a = $sa = '';
                if($value === 'REGEXP')
                {
                    $a = "<a href = '$regexHintHref' target = 'blank'>";
                    $sa = '</a>';
                }
                $cross[$lastFilter] .= " $a$value$sa ";
                break;

            default:
                $cross[$lastFilter] .= "$value<a href = '$indexUrl$href' class = 'clicky'>x</a></span>";
                break;
        }
    }
    
    //Écriture du formulaire avec les noms d'input valides
    $html = 
    "<form method = 'GET' action = '$indexUrl' id = 'filter-box'>" . 
        htmlInputs($get) .
        htmlInputs(['f' . $lastFilter . '_Key' => ''], 'text', 'placeholder = \'Champ\' list = \'keys\'') .
        "<select name = 'f" . $lastFilter . "_Operator'>
            <option value = '='>=</option>
            <option value = '<'><</option>
            <option value = '>'>></option>
            <option value = 'REGEXP'>contains</option>
        </select>" .
        htmlInputs(['f' . $lastFilter . '_Value' => ''], 'text', 'id = \'valueInput\' placeholder = \'Valeur\'') . 
        '<button type = \'submit\'>Nouveau filtre</button>
        <div id = \'filter-list\'>';
        
    $html .= 
        (isset($cross) ? implode('', $cross) : '') . '</div>' . 
    '</form>' . htmlDatalist($keys, NULL, NULL, ['datalist' => 'id = \'keys\'']);
    return $html;
}

function htmlColumnFiltersList($boxesNames, $global, $indexUrl = 'index.php')
{
    $form = '';
    
    //Clés du tableau à joindre sur une seule table, alors qu'elles sont plusieurs
    foreach($boxesNames as $boxName)
    {
        //Pour chaque box
        $checkedAttr = (int)isset($global["hide-$boxName"]) ? ' checked' : '';
        $form .= "<span class = hider-checkbox>Cacher les champs '$boxName': <input type = 'checkbox' name = 'hide-$boxName'$checkedAttr></span>";
    }

    $hiddens = preg_replace("#<input[\w'=\-/ ]*value = 'on'>#", '', htmlInputs($global));
    return "<form method = GET action = '$indexUrl'>$hiddens$form<button type = submit>Recharger</button></form>";
}

/*
Mathematic functions
*/

function lagrangeMember($x, $xind, $values)
{
    $result = $values["a$xind"];
    $formula = "$result";
    foreach($values as $value)
    {
        if($xind != $value)
        {
            $result = $result * ($x - $value) / ($xind - $value);
            $formula = "$formula * ($x - $value) / ($xind - $value)";
        }
    }
    return $result;
}

function Lagrange($x, $values)
{
    $result = 0;
    foreach($values as $abscissa => $ordinate)
    {
        //Pour chaque couple de valeurs absisse / ordonnée
        $result = $result + lagrangeMember($x, trim($abscissa, 'a'), $values);
    }
    return $result;
}

/*
  _____  _               _                     __  __                                                   _   
 |  __ \(_)             | |                   |  \/  |                                                 | |  
 | |  | |_ _ __ ___  ___| |_ ___  _ __ _   _  | \  / | __ _ _ __   __ _  __ _  ___ _ __ ___   ___ _ __ | |_ 
 | |  | | | '__/ _ \/ __| __/ _ \| '__| | | | | |\/| |/ _` | '_ \ / _` |/ _` |/ _ \ '_ ` _ \ / _ \ '_ \| __|
 | |__| | | | |  __/ (__| || (_) | |  | |_| | | |  | | (_| | | | | (_| | (_| |  __/ | | | | |  __/ | | | |_ 
 |_____/|_|_|  \___|\___|\__\___/|_|   \__, | |_|  |_|\__,_|_| |_|\__,_|\__, |\___|_| |_| |_|\___|_| |_|\__|
                                        __/ |                            __/ |                              
                                       |___/                            |___/                               
*/

function sqlGenerateFilesInDb($db, $table, $pathKey, $directory, $mimeKey = NULL, $search = NULL, $replace = NULL, $baseNameKey = NULL)
{
    //Récupération des chemins d'accès
    $filesPaths = glob("$directory*.*");
    foreach($filesPaths as $filePath)
    {
        //Pour chaque fichier trouvé
        //Obtenir le MIME depuis le chemin relatif
        $mime = mime_content_type($filePath);

        //Obtenir le chemin absolu
        if(isset($search, $replace))
        {
            $filePath = str_replace($search, $replace, $filePath);
        }
        //Enregistrer dans la db avec le chemin absolu en 'Path'
        if(!sqlDataExists($db, $table, $pathKey, $filePath))
        {
            //S'il n'existe pas dans la table sql, le créer
            sqlInsert($db, $table, $pathKey, $filePath);
        }
        if(isset($mimeKey))
        {
            //Updater le mime
            sqlUpdate($db, $table, $pathKey, $filePath, $mimeKey, $mime);
        }
        if(isset($baseNameKey))
        {
            //Updater le nom
            sqlUpdate($db, $table, $pathKey, $filePath, $baseNameKey, basename($filePath));
        }
    }
}

function appendFiles($filesPaths, $attributes = NULL, $clicky = NULL, $hrefMask = '')
{
    global $contentString;

    $html = '';
    foreach($filesPaths as $filePath)
    {
        //Pour chaque fichier
        $mime = mime_content_type(str_replace("http://localhost/", "../../", $filePath));

        switch($mime)
        {
            case 'image/png':
            case 'image/jpeg':
            case 'image/gif':
                //Si c'est une image
                $element = "<img mime = '$mime' src = \"$filePath\" $attributes>";
                break;
                
            case 'video/ogg':
            case 'video/mp4':
                //Si c'est une vidéo
                $element = "<video mime = '$mime' src = \"$filePath\" $attributes controls></video>";
                break;
            
            default:
                //Mime non pris en charge
                $element = "Unsupported mime: $mime";
                break;
        }

        //Ajout de l'attribut clickable vers un lien de l'élément
        $link = (int)isset($hrefMask) ? str_replace($contentString, $filePath, $hrefMask) : $filePath;
        $html = (int)isset($clicky) ? "$html<a href = \"$link\" target = '_blank'>$element</a>" : $html;
    }
    return $html;
}



//INITIAL ACTIONS

$sudo['mysql'] = sqlConnect('localhost', 'mysql', 'root', parse_ini_file($pathToProperties)[4]);
$dbLogger = sqlConnect($host, $dbName, 'dbLogger', parse_ini_file($pathToProperties)[2]);
$dbManager = sqlConnect($host, $dbName, 'dbManager', parse_ini_file($pathToProperties)[3]);

sqlQuery($sudo[$dbName], 'FLUSH PRIVILEGES', false);

session_start();