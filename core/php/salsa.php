<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
require __DIR__ . '/clientWebSocket.php';
require __DIR__ . '/../class/jeedisplay.class.php';

$run = true;
$error = false;
$errorsCount = 0;
$lastErrorTime;

// Stratégie pour la gestion des erreurs
//
//   Si erreur, on arrête la boucle de lecture/écriture, on réinitialise et on reprend sans quitter le service
//      $error = true
//
//   Si pas d'erreur depuis plus d'une heure, on remet à zéro le compteur d'erreurs
//      $errorsCount = 0
//
//   Si trop d'erreurs en moins d'une heure, on arrête le service qui sera relancé par Jeedom
//      $run = false
//

// Mémorisation de la connexion pour chaque équipement
//
class RemoteDevice
{
    public $id;
    public $ip;
    public $client;
    public $temps;
    public $lastReadTimestamp;
    
    public function __construct($pId, $pIp)
    {
        $this->id = $pId;
        $this->ip = $pIp;
        $this->client = new clientWebSocket();
        $this->temps = time() - 45 - random_int(0, 10);
        $this->lastReadTimestamp = time();
    }

    public function __destruct()
    {
    }
}

// L'usage des ticks est nécessaire
//
declare(ticks = 1);

// Gestionnaire de signaux système
//
function sig_handler($signo)
{
    global $run;
    
    switch ($signo) {
         case SIGTERM:
             // gestion de l'extinction
             $run = false;
             break;
         case SIGHUP:
             // gestion du redémarrage
             $run = false;
             break;
         case SIGUSR1:
            break;
         default:
             
     }
}

// Gestion des erreurs de lecture du socket
//
function handleErrorRead($errno, $errstr, $errfile, $errline, $errctx)
{
    global $error;
    global $errorsCount;
    global $lastErrorTime;

    if (error_reporting() == 0) {
        return;
    }

    if ($errno != E_USER_ERROR) {
        return true;
    }

    $error = true;
    $errorsCount++;
    $lastErrorTime = time();
    
    log::add('jeedisplay', 'debug', $errstr . ' on line ' . $errline . ' (' . $errfile . ')');
    
    return true;
}

// Gestion des erreurs d'écriture du socket
//
function handleErrorWrite($errno, $errstr, $errfile, $errline, $errctx)
{
    global $error;
    global $errorsCount;
    global $lastErrorTime;
 
    if (error_reporting() == 0) {
        return;
    }

    if ($errno != E_USER_ERROR) {
        return;
    }
      
    $error = true;
    $errorsCount++;
    $lastErrorTime = time();
    
    log::add('jeedisplay', 'debug', $errstr . ' on line ' . $errline . ' (' . $errfile . ')');
    
    return true;
}

// Et on démarre
//
//   On capte les signaux du système
//
pcntl_signal(SIGTERM, "sig_handler");
pcntl_signal(SIGHUP, "sig_handler");
pcntl_signal(SIGUSR1, "sig_handler");

log::add('jeedisplay', 'debug', 'Démarrage du service');

$lastErrorTime = time();

// Boucle principale tant qu'on peut s'exécuter
//
while ($run) {

    global $remoteDevice;

    // On mémorise tous les équipements actifs
    //
    $listeRemoteDevice = array();
             
    foreach (eqLogic::byType('jeedisplay', true) as $eqLogic) {
        if ($eqLogic->getIsEnable() == 1) {
            $listeRemoteDevice[] = new RemoteDevice($eqLogic->getId(), $eqLogic->getConfiguration('adresse_ip'));
            $cmd = $eqLogic->getCmd(null, 'status');
            if (is_object($cmd)) {
                $cmd->event('Déconnecté');
            }
        }
    }

    // Boucle secondaire tant qu'on peut s'exécuter et qu'on a pas d'erreur
    //
    $error = false;
    while ($run && !$error) {

        // Si plus d'erreur depuis plus d'une heure, remise à zéro du compteur
        //
        if (time() > $lastErrorTime+3600) {
            $errorsCount = 0;
            $lastErrorTime = time();
        }

        // Pour chaque équipement mémorisé
        //
        foreach ($listeRemoteDevice as $remoteDevice) {
            $eqLogic = eqLogic::byId($remoteDevice->id);

            if (!$remoteDevice->client->isConnected()) {
                if ((time() > $remoteDevice->temps+60) && ($remoteDevice->ip !== '')) {

                    // Toutes les minutes, tentative de connexion
                    //
                    log::add('jeedisplay', 'debug', "Tentative de connexion : " . $remoteDevice->ip);
                    $remoteDevice->temps = time();

                    try {
                        if ($remoteDevice->client->connect($remoteDevice->ip, 81, '', $error_string) == false) {
                            log::add('jeedisplay', 'debug', $error_string);
                            $remoteDevice->client->disconnect();
                        }
                        if ($remoteDevice->client->isConnected()) {
                            log::add('jeedisplay', 'debug', "Connexion réussie: " . $remoteDevice->ip);
                            $eqLogic->estConnecte();                       
                        } else {
                            log::add('jeedisplay', 'debug', "Connexion échouée : " . $remoteDevice->ip);
                        }
                    } catch (Exception $e) {
                        log::add('jeedisplay', 'error', $e->getMessage());
                    }
                }
            } else {

                // Si connecté, je lis une éventuelle remontée d'informations
                //
                set_error_handler("handleErrorRead");
                $reponse = $remoteDevice->client->read();
                set_error_handler(null);

                if ($error == true) {
                    log::add('jeedisplay', 'debug', "Déconnexion de l'équipement : " . $remoteDevice->ip);
                    $remoteDevice->client->disconnect();
                    $remoteDevice->temps = time() - 55;
                    $cmd = $eqLogic->getCmd(null, 'status');
                    if (is_object($cmd)) {
                        $cmd->event('Déconnecté');
                    }
                    if ($errorsCount < 30) {
                        $error = false;
                    }
                }

                if ($reponse !== false) {
                    if ($reponse === '') {
                    } elseif ($reponse === 'alive') {
                        $remoteDevice->temps = time();
                    } else {
                        $eqLogic->execute($reponse);
                    }
                }
                // ToDo Lock Mutex
                //
                $listeCmds = $eqLogic->getCache('listeCmds', '');
                $eqLogic->setCache('listeCmds', '');
                if (is_array($listeCmds)) {
                    foreach ($listeCmds as $cmds) {
                        if ($error == false) {
                            set_error_handler("handleErrorWrite");
                            $remoteDevice->client->write($cmds);
                            set_error_handler(null);
                        }
                    }
                    if ($error == true) {
                        $error = false;
                        log::add('jeedisplay', 'debug', "Déconnexion de l'équipement : " . $remoteDevice->ip);
                        $remoteDevice->client->disconnect();
                        $remoteDevice->temps = time() - 55;
                        $cmd = $eqLogic->getCmd(null, 'status');
                        if (is_object($cmd)) {
                            $cmd->event('Déconnecté');
                        }
                        if ($errorsCount < 30) {
                            $error = false;
                        }
                    }
                }
                //
                // ToDo Unlock Mutex

                if ($remoteDevice->lastReadTimestamp != time()) {
                    $events = event::changes($remoteDevice->lastReadTimestamp);
                    $remoteDevice->lastReadTimestamp = time();
                    if (count($events['result']) > 0) {
                        $eqLogic->broadcast($events);
                    }
                }

                // Si plus de deux minutes sans un "alive", je déconnecte
                //
                if (time() > $remoteDevice->temps+120) {
                    log::add('jeedisplay', 'debug', "Déconnexion de l'équipement ( Pas reçu en vie ) : " . $remoteDevice->ip);
                    $remoteDevice->client->disconnect();
                    $remoteDevice->temps = time() - 55;
                    $cmd = $eqLogic->getCmd(null, 'status');
                    if (is_object($cmd)) {
                        $cmd->event('Déconnecté');
                    }
                }
            }
        }
        usleep(20000);
    }

    // Stop
    //
    foreach ($listeRemoteDevice as $remoteDevice) {
        $remoteDevice->client->disconnect();
        $eqLogic = eqLogic::byId($remoteDevice->id);
        $cmd = $eqLogic->getCmd(null, 'status');
        if (is_object($cmd)) {
            $cmd->event('Déconnecté');
        }
    }
    unset($remoteDevice);

    // Trop d'erreurs, je sors
    //
    if ($errorsCount > 20) {
        log::add('jeedisplay', 'debug', "Trop d'erreurs");
        $run = false;
    }
}

log::add('jeedisplay', 'debug', 'Fin du service');
