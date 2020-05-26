<!Doctype #html>
<html lang = fr>
    <head>
        <link rel = "stylesheet" type = "text/css" href = "public/style.css">
        <meta charset = "UTF-8" />
        <!--<script src = "http://ajax.googleapis.com/ajax/libs/jquery/1.10.0/jquery.min.js"></script>-->
        <script src = "public/chartjs/Chart.min.js"></script>
        <!--<script src = "https://www.chartjs.org/dist/2.9.2/Chart.min.js"></script>-->
        <script src = "public/jquery/jquery.min.js"></script>
        <script src = "public/functions.js"></script>
        <title>dbManager<?=isset($contentTitle) ? " - $contentTitle" : ''?></title>
    </head>
    <body <?=isset($onLoad) ? "onload = $onLoad" : ''?> <?=isset($bodyAttributes) ? $bodyAttributes : ''?>>
        <noquery>Erreur de client, rechargez la page ou changez de navigateur pour avoir accès aux fonctionnalités.</noquery>
        <nav id = "pages">
            <?=isset($hideDisconnect) ? '' : '<ul><li><a href = index.php?disconnect>Se déconnecter ' . ($nextTimeUseDisconnect ? '<- La prochaine fois, pensez à cliquer ici !' : '') . '</a></li></ul>'?>
            <ul>
                <?=isset($hideTables) ? '' : '<li><a href = index.php?tables>Base de données</a></li>'?>
                <?=isset($hideParameters) ? '' : '<li><a href = index.php?parameters>Paramètres</a></li>'?>
            </ul>
        </nav>
        <?=(isset($_GET['code']) AND $_GET['code'] === 'watchphpinfo') ? phpinfo(INFO_MODULES) : '';?>
        <?=isset($contentBody) ? $contentBody : ''?>
        <?php if(isset($_GET['phpinfo'])) phpinfo() ?>
    </body>
</html>