$(document).ready(function() 
{
    //Actions à effectuer au chargement de la page
    //Cacher le message d'erreur
    $("noquery").text("");
    $("noquery").hide();
});

/*
  _____        _                            _                               _   
 |  __ \      | |                          (_)                             | |  
 | |  | | __ _| |_ __ _    __ _  __ _ _   _ _ _ __ ___ _ __ ___   ___ _ __ | |_ 
 | |  | |/ _` | __/ _` |  / _` |/ _` | | | | | '__/ _ \ '_ ` _ \ / _ \ '_ \| __|
 | |__| | (_| | || (_| | | (_| | (_| | |_| | | | |  __/ | | | | |  __/ | | | |_ 
 |_____/ \__,_|\__\__,_|  \__,_|\__, |\__,_|_|_|  \___|_| |_| |_|\___|_| |_|\__|
                                   | |                                          
                                   |_|                                          
*/

$(event.target).click(ajaxGet(fetchUrl, table_, conditionKey_, conditionValue_, editKey_, editValue_, target_ = '', ret = 'html'));

function ajaxGet(fetchUrl, table_, conditionKey_, conditionValue_, editKey_, editValue_ , target_ = '', ret = 'html')
{
    $.ajax({
        url: fetchUrl,
        type: 'POST',
        dataType: ret,
        data: {
            table: table_,
            conditionkey: conditionKey_,
            conditionvalue: conditionValue_,
            editkey: editKey_,
            editvalue: editValue_
        },
        success: function(fetch)
        {
            var results = fetch;
            if(target_ != '')
            {
                $(target_).html(results);
            }
        }
    });
}

/*
  _______    _     _                      _ _ _   _             
 |__   __|  | |   | |                    | (_) | (_)            
    | | __ _| |__ | | ___  ___    ___  __| |_| |_ _  ___  _ __  
    | |/ _` | '_ \| |/ _ \/ __|  / _ \/ _` | | __| |/ _ \| '_ \ 
    | | (_| | |_) | |  __/\__ \ |  __/ (_| | | |_| | (_) | | | |
    |_|\__,_|_.__/|_|\___||___/  \___|\__,_|_|\__|_|\___/|_| |_|
                                                                  
*/

$(event.target).dblclick(editTable(url_));
$(event.target).keydown(enterCell(url_));

function editTable(url_)
{
    if($(event.target).attr("class") == "dynamic-td")
    {
        if(!$(event.target).html().match(/^<input [=\"\'a-zA-Z0-9 ]* >/))
        {
            //Si l'élément n'est pas un input (donc un td)
            var cellPureContent = $(event.target).text();
            var cellSplittedContent = cellPureContent.replace('/(.)*/', cellPureContent);

            //Transformer l'élément déclencheur en input
            $(event.target).html("<input class = 'dynamic-input' type = text list = '" + $(event.target).attr("list") +
                                 "' onkeydown = 'enterCell(\"" + url_ + "\")' onfocusout = 'enterCell(\"" + url_ + "\")' value = '" + cellSplittedContent + "'>");
        }
        else
        {
            //Retransformer l'input en la valeur qu'il représente
            $(event.target).html($(event.target).attr("value"));
        }
    }
}

function enterCell(url_)
{
    if(event.which == 13 || event.type == "focusout")
    {
        if(event.which == 13 && $(event.target).attr("class") == "dynamic-input")
        {
            //Si la touche enfoncée est ENTER (13), et que l'élément déclencheur est un .dynamic-input
            //AJAX pour envoyer les données
            var cell = $(event.target).parent();
            var values = {
                edittable: cell.attr("edittable"),  //Table à éditer
                primarykey: cell.attr("primarykey"),    //Clé primaire sur la table à éditer
                primaryvalue: cell.attr("primaryvalue"),    //Valeur primaire sur la table à éditer
                editkey: cell.attr("editkey"),  //Valeur à modifier [nom] sur la table à éditer
                list: cell.attr("list"),    //Table à afficher [cas d'une datalist]
                listprimarykey: cell.attr("listprimarykey"),    //Clé primaire de la list (= editkey si non renseignée)
                displaykey: cell.attr("displaykey"),    //Valeur à saisir [valeur][cas d'une datalist]
                value: $(event.target).val()    //Valeur à modifier [valeur] sur la table à éditer
            }
            $.ajax(
            {
                url: url_,
                type: 'POST',
                data: values/*,
                success: function(){},
                error: function(){}*/
            });

            //Affichage de la nouvelle donnée
            $(event.target).parent().html($(event.target).val());
        }
        else
        {
            //Réaffichage de l'ancienne donnée
            $(event.target).parent().html($(event.target).attr("value"));
        }
    }
}

/*  TABLES RESEARCH */

$(event.target).click(ChangeAttribute(id, parameter, action, toChange));

function ChangeAttribute(id, parameter, action, toChange)
{
    var element = (id === '__THIS__') ? $(event.target) : $('#' + id);
    var value = element.attr(parameter);
    
    if(toChange === '__VALUE__')
    {
        toChange = $(event.target).val();
    }
    
    if(action === 'add')
    {
        value += toChange;
    }
    else //if(action === 'replace')
    {
        value = toChange;
    }
    element.attr(parameter, value);
}

/*  PERMISSIONS */

$(event.target).click(UpdatePrivileges(url_));

function UpdatePrivileges(url_)
{
    var values =
    {
        name: $(event.target).attr('name'),
        value: $(event.target).attr('value'),
        user: $(event.target).attr('user')
    }

    //if($(event.target).attr('class') == 'grant-button') var toChange = 'revoke-button';
    //else var toChange = 'grant-button';

    $.ajax(
    {
        url: url_,
        type: 'POST',
        data: values
    });
}

/*
   _____                 _     _         __                  _   _                 
  / ____|               | |   (_)       / _|                | | (_)                
 | |  __ _ __ __ _ _ __ | |__  _  ___  | |_ _   _ _ __   ___| |_ _  ___  _ __  ___ 
 | | |_ | '__/ _` | '_ \| '_ \| |/ __| |  _| | | | '_ \ / __| __| |/ _ \| '_ \/ __|
 | |__| | | | (_| | |_) | | | | | (__  | | | |_| | | | | (__| |_| | (_) | | | \__ \
  \_____|_|  \__,_| .__/|_| |_|_|\___| |_|  \__,_|_| |_|\___|\__|_|\___/|_| |_|___/
                  | |                                                              
                  |_|                                                              
*/

$(event.target).click(appendSimpleGraph(targetCanvas, keys_, data_, label_));

function appendSimpleGraph(targetCanvas, fetchUrl, table_, conditionKey_, conditionValue_, objectAbscissaKey, objectOrdinateKey, label_)
{
    $.ajax({
        url: fetchUrl,
        type: 'POST',
        dataType: 'json',
        data: {
            table: table_,
            conditionkey: conditionKey_,
            conditionvalue: conditionValue_,
            orderby: objectAbscissaKey
        },
        success: function(objects)
        {
            var keys = [];
            var values = [];
            objects.forEach(function(object)
            {
                objectKeys = Object.keys(object);
                objectKeys.forEach(function(key)
                {
                    if(key == objectAbscissaKey)
                    {
                        keys.push(object[key]);
                    }
                    if(key == objectOrdinateKey)
                    {
                        values.push(object[key]);
                    }
                })
            })
            useChart(targetCanvas, keys, values, label_);
        }
    });
}

function useChart(targetCanvas, keys_, data_, label_)
{
    return new Chart($(targetCanvas),
    {
        type: 'line',
        data: {
            labels: keys_,
            datasets: [{
                label: label_,
                data: data_
            }]
        },
        options: {
            scales: {
                yAxes: [{
                    ticks: {
                        beginAtZero: true
                    }
                }]
            }
        }
    });
}

/*
  _                 _       
 | |               (_)      
 | |     ___   __ _ _ _ __  
 | |    / _ \ / _` | | '_ \ 
 | |___| (_) | (_| | | | | |
 |______\___/ \__, |_|_| |_|
               __/ |        
              |___/         
*/

$("#login-form").onsubmit(login(url_, method = 'POST'));
$(event.target).onclick(loadPage(url_));

function login(url_, method = 'POST')
{
    var values = {
        firstnamevalue: $("#firstnamevalue").val(),
        namevalue: $("#namevalue").val(),
        passwordvalue: $("#passwordvalue").val()
    }
    $.ajax(
    {
        url: url_,
        type: method,
        data: values/*,
        success: alert('sent')/*,
        error: function(){}*/
    });
}

function loadPage(url_)
{
    $.ajax(
    {
        url: url_,
        success: alert('load')
    });
}