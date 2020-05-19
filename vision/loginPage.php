<?php

$contentTitle = 'Login Page';
//$bodyAttributes = 'style="background-image: url(\'public/images/robotic.jpg\'); background-size: 100%;"';/*lapin*/

ob_start();
?>
<form id = login-form method = POST action = <?=$GLOBALS['indexUrl']?>>
    <h2>Connexion à dbManager</h2>
    <input type = "text" placeholder = Prénom name = firstnamevalue>
    <input type = "text" placeholder = Nom name = namevalue>
    <input type = "password" placeholder = 'Mot de passe' name = passwordvalue>
    <button type = submit>Connexion</button>
</form>
<?php
$contentBody = ob_get_clean();