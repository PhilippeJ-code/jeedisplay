<?php

/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/

if (!isConnect('admin')) {
    throw new Exception('{{401 - Accès non autorisé}}');
}

$eqLogics = calendar::byType('jeedisplay');
?>

<table class="table table-condensed tablesorter" id="table_healthmpower">
    <thead>
        <tr>
            <th>{{Module}}</th>
            <th>{{ID}}</th>
            <th>{{Adresse IP}}</th>
            <th>{{Connexion}}</th>
            <th>{{Date connexion}}</th>
            <th>{{Etat}}</th>
            <th>{{Date création}}</th>
        </tr>
    </thead>
    <tbody>
        <?php
foreach ($eqLogics as $eqLogic) {
    
    $connexion = '';
    $dateConnexion = '';
    $adresse_ip = $eqLogic->getConfiguration('adresse_ip', '');

    if ($eqLogic->getIsEnable() == 1) {
        $connexion = "NOK";
        $obj = $eqLogic->getCmd(null, 'status');
        if (is_object($obj)) {
            $statut = $obj->execCmd();
            if ($statut == "Connecté") {
                $connexion = "OK";
            }
        }
        $obj = $eqLogic->getCmd(null, 'connectionDate');
        if (is_object($obj)) {
            $dateConnexion = $obj->execCmd();
        }
    }

    $state = '<span class="label label-danger" style="font-size : 1em;cursor:default;">{{Inactif}}</span>';
    if ($eqLogic->getIsEnable() == 1) {
        $state = '<span class="label label-success" style="font-size : 1em;cursor:default;">{{Actif}}</span>';
    }
    echo '<tr><td><a href="' . $eqLogic->getLinkToConfiguration() . '" style="text-decoration: none;">' . $eqLogic->getHumanName(true) . '</a></td>';
    echo '<td><span class="label label-info" style="font-size : 1em;">' . $eqLogic->getId() . '</span></td>';
    echo '<td><span class="label label-info" style="font-size : 1em;">'.$adresse_ip.'</span></td>';
    echo '<td><span class="label label-info" style="font-size : 1em;">'.$connexion.'</span></td>';
    echo '<td><span class="label label-info" style="font-size : 1em;">'.$dateConnexion.'</span></td>';
    echo '<td>' . $state . '</td>';
    echo '<td><span class="label label-info" style="font-size : 1em;">' . $eqLogic->getConfiguration('createtime') . '</span></td></tr>';
}
?>
    </tbody>
</table>