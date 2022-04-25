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

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';

// Fonction exécutée automatiquement après l'installation du plugin
//
function jeedisplay_install()
{
    // Installation du démon
    //
    exec(system::getCmdSudo() . 'cp '.dirname(__FILE__).'/../resources/jeedom-jeedisplay.service /etc/systemd/system/jeedom-jeedisplay.service');
    exec(system::getCmdSudo() . 'systemctl daemon-reload');
    exec(system::getCmdSudo() . 'systemctl start jeedom-jeedisplay');
    exec(system::getCmdSudo() . 'systemctl enable jeedom-jeedisplay');
    $active = trim(shell_exec('systemctl is-active jeedom-jeedisplay'));
    $enabled = trim(shell_exec('systemctl is-enabled jeedom-jeedisplay'));
    if ($active !== 'active' || $enabled !== 'enabled') {
        log::add('jeedisplay', 'error', "Démon pas installé (is-active : " . $active . " is_enabled : " . $enabled);
    }
}

// Fonction exécutée automatiquement après la mise à jour du plugin
//
function jeedisplay_update()
{
    // Redémarrage du démon
    //
    exec(system::getCmdSudo() . 'systemctl restart jeedom-jeedisplay');
}

// Fonction exécutée automatiquement après la suppression du plugin
//
function jeedisplay_remove()
{
    // Désinstallation du démon
    //
    exec(system::getCmdSudo() . 'systemctl disable jeedom-jeedisplay');
    exec(system::getCmdSudo() . 'systemctl stop jeedom-jeedisplay');
    exec(system::getCmdSudo() . 'rm /etc/systemd/system/jeedom-jeedisplay.service');
    exec(system::getCmdSudo() . 'systemctl daemon-reload');
}
