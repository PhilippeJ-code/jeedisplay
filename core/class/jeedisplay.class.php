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

  require_once __DIR__  . '/../../../../core/php/core.inc.php';

  class jeedisplay extends eqLogic
  {
      public static function deamon_info()
      {
          $return = array();

          $status = trim(shell_exec('systemctl is-active jeedom-jeedisplay'));
          $return['state'] = ($status === 'active') ? 'ok' : 'nok';

          $return['launchable'] = 'ok';
          if (!file_exists('/etc/systemd/system/jeedom-jeedisplay.service')) {
              $return['launchable'] = 'nok';
              $return['launchable_message'] = __('Le démon n\'est pas installé ', __FILE__);
          }
          return $return;
      }

      public static function deamon_start($_debug = false)
      {
          exec(system::getCmdSudo() . 'systemctl restart jeedom-jeedisplay');
          $i = 0;
          while ($i < 30) {
              $deamon_info = self::deamon_info();
              if ($deamon_info['state'] == 'ok') {
                  break;
              }
              sleep(1);
              $i++;
          }
          if ($i >= 30) {
              log::add('jeedisplay', 'error', 'Unable to start daemon');
              return false;
          }
      }

      public static function deamon_stop()
      {
          exec(system::getCmdSudo() . 'systemctl stop jeedom-jeedisplay');
      }
    
      public static function health()
      {
          $return = array();

          foreach (self::byType('jeedisplay') as $jeedisplay) {
              if ($jeedisplay->getIsEnable() == 1) {
                  $adresse_ip = $jeedisplay->getConfiguration('adresse_ip', '');
                  $state = "NOK";
                  $obj = $jeedisplay->getCmd(null, 'status');
                  if (is_object($obj)) {
                      $statut = $obj->execCmd();
                      if ($statut == "Connecté") {
                          $state = "OK";
                      }
                  }
      
                  $return[] = array(
                        'test' => __('Connexion au module', __FILE__) . ' ' . $adresse_ip,
                        'result' => $state,
                        'advice' => '',
                        'state' => ($state == 'OK')
                    );
              }
          }
          return $return;
      }
    
      public function EstConnecte()
      {
          $cmd = $this->getCmd(null, 'status');
          if (is_object($cmd)) {
              $cmd->event('Connecté');
          }

          $cmd = $this->getCmd(null, 'connectionDate');
          if (is_object($cmd)) {
              $date = new DateTime();
              $date = $date->format('d-m-Y H:i:s');
              $cmd->event($date);
          }

          $listeCmds = $this->getCache('listeCmds', '');
          if (!is_array($listeCmds)) {
              $listeCmds = array();
          }

          $idVirtuel = $this->getConfiguration('virtuel');
          foreach (cmd::byEqLogicId($idVirtuel) as $commande) {
              $cmdId = $commande->getId();
              $type = $commande->getType();
              if ($type === "info") {
                $val = $commande->execCmd();
                $listeCmds[] = 'id:'.$cmdId.':'.$val;
              }
          }

          $this->setCache('listeCmds', $listeCmds);
      }

      public function broadcast($events)
      {
          
          $listeCmds = $this->getCache('listeCmds', '');
          if (!is_array($listeCmds)) {
              $listeCmds = array();
          }

          $idVirtuel = $this->getConfiguration('virtuel');
          $n = count($events['result']);
          for ($i=0; $i<$n; $i++) {
              $cmdId = intval($events['result'][$i]['option']['cmd_id']);
              $val = $events['result'][$i]['option']['display_value'];

              foreach (cmd::byEqLogicId($idVirtuel) as $commande) {
                  $id = $commande->getId();
                  if ($id == $cmdId) {
                      $listeCmds[] = 'id:'.$cmdId.':'.$val;
                      break;
                  }
              }             
           }
          $this->setCache('listeCmds', $listeCmds);
      }

      // Créer les commandes remontées par le device
      //
      public function creerCommandes($elements)
      {
          $n = count($elements);
          $i = 2;
          $idMaster = null;

          $o=0;
          while ($i+3 < $n) {
              $obj = $this->getCmd(null, $elements[1] . '_' . $elements[$i]);
              if (!is_object($obj)) {
                  $obj = new jeedisplayCmd();
                  $obj->setName(__($elements[$i], __FILE__));
                  $obj->setEqLogic_id($this->getId());
                  $obj->setLogicalId($elements[1] . '_' . $elements[$i]);
                  $obj->setType($elements[$i+1]);
                  $obj->setSubType($elements[$i+2]);
                  if (($elements[$i+3] === 'slave') && ($idMaster !== null)) {
                      $obj->setValue($idMaster);
                  }
                  $obj->setOrder($o+1);
                  $obj->save();
              }
              if ($elements[$i+3] === 'master') {
                  $idMaster = $obj->getId();
              }

              $o++;
              $i += 4;
          }
      }
      public function execute($commande)
      {
          $elements = explode(':', $commande);
          $n = count($elements);
          if ($n > 1) {
              if ($elements[0] == 'Scenario') {
                  $id = $elements[1];
                  $scenario = scenario::byid($id);
                  if (is_object($scenario)) {
                      $scenario->execute($options);
                  }
              } elseif ($elements[0] == 'Equipement') {
                  $oldEquipement = $this->getConfiguration('oldEquipement', '');
                  if ($oldEquipement !== $elements[1]) {
                      log::add('jeedisplay', 'debug', 'Suppression');
                      $Cmds = $this->getCmd();
                      foreach ($Cmds as $Cmd) {
                          if (($Cmd->getLogicalId() != 'status') && ($Cmd->getLogicalId() != 'connectionDate')) {
                              $Cmd->remove();
                          }
                      }
                      $this->setConfiguration('oldEquipement', $elements[1])->save(true);
                  }
                  $this->creerCommandes($elements);
              } elseif ($elements[0] == 'Commande') {
                  if ($n == 3) {
                      $cmd = $this->getCmd(null, $elements[1]);
                      if (is_object($cmd)) {
                          if ($cmd->getType() == 'info') {
                              if ($cmd->getSubType() == 'numeric') {
                                  $cmd->event(floatval($elements[2]));
                              } else {
                                  $cmd->event($elements[2]);
                              }
                          }
                      }
                  }
              }
          }
      }

      // Fonction exécutée automatiquement avant la création de l'équipement
      //
      public function preInsert()
      {
      }

      // Fonction exécutée automatiquement après la création de l'équipement
      //
      public function postInsert()
      {
      }

      // Fonction exécutée automatiquement avant la mise à jour de l'équipement
      //
      public function preUpdate()
      {
      }

      // Fonction exécutée automatiquement après la mise à jour de l'équipement
      //
      public function postUpdate()
      {
      }

      // Fonction exécutée automatiquement avant la sauvegarde (création ou mise à jour) de l'équipement
      //
      public function preSave()
      {
      }

      // Fonction exécutée automatiquement après la sauvegarde (création ou mise à jour) de l'équipement
      //
      public function postSave()
      {
          // Statut
          //
          $status = $this->getCmd(null, 'status');
          if (!is_object($status)) {
              $status = new jeedisplayCmd();
              $status->setName(__('Statut', __FILE__));
              $status->setIsVisible(1);
              $status->setIsHistorized(0);
          }
          $status->setEqLogic_id($this->getId());
          $status->setLogicalId('status');
          $status->setType('info');
          $status->setSubType('string');
          $status->setOrder(1);
          $status->save();

          // Date de connexion
          //
          $obj = $this->getCmd(null, 'connectionDate');
          if (!is_object($obj)) {
              $obj = new viessmannIotCmd();
              $obj->setName(__('Date de connexion', __FILE__));
              $obj->setIsVisible(1);
              $obj->setIsHistorized(0);
          }
          $obj->setEqLogic_id($this->getId());
          $obj->setType('info');
          $obj->setSubType('string');
          $obj->setLogicalId('connectionDate');
          $obj->save();

          self::deamon_start();
      }

      // Fonction exécutée automatiquement avant la suppression de l'équipement
      //
      public function preRemove()
      {
      }

      // Fonction exécutée automatiquement après la suppression de l'équipement
      //
      public function postRemove()
      {
      }
  }
  
  class jeedisplayCmd extends cmd
  {
      // Exécution d'une commande
      //
      public function execute($_options = array())
      {
      }
  }
