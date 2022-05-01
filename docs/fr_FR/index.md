# Plugin jeedisplay pour Jeedom

    Ce plugin permet l'affichage d'informations en provenance de Jeedom ainsi que le déclenchement de scénarios.

        Affichage sur un module M5Core2 basé sur un ESP32

## 1. Configuration du plugin

    Rien de particulier dans la configuration de ce plugin.

## 2. Configuration de l'équipement

    Pour utiliser le plugin il est nécessaire de créer un virtuel qui va contenir les informations qui seront envoyées 
    au module d'affichage. On peut également créer des scénarios qui seront activés par le module d'affichage.

    Par exemple, création d'un virtuel avec une consigne température et deux scénarios qui permettront d'augmenter ou de 
    diminuer cette consigne.

    La configuration de l'équipement permet de choisir un virtuel qui devra contenir les informations qui seront envoyées au module.
    Il permet également de configurer l'adresse IP du module.

## 3. Programmation du module

    Sous Windows, il faut tout d'abord installer les outils nécessaires à la programmation du module

    Installer Python ( https://www.python.org ) sans oublier de cocher Path 

    Lancer l'invite de commandes sous Windows

    Installer PySerial avec la commande
	    python -m pip install pyserial
    Installer esptool avec la commande
	    python -m pip install esptool
    Installer setuptools avec la commande 
	    python -m pip install setuptools

    Installer le firmware du module

    Copier le répertoire module du plugin de Jeedom vers Windows, se positionner dans le répertoire copié puis lancer les commandes

    python esptool.py --chip esp32 --port "COM3" --baud 460800 --before default_reset --after hard_reset write_flash -z --flash_mode dio --flash_freq 40m --flash_size detect 0x1000 bootloader_dio_40m.bin 0x8000 partitions.bin 0xe000 boot_app0.bin 0x10000 firmware.bin

    python esptool.py --chip esp32 --port "COM3" --baud 460800 --before default_reset --after hard_reset write_flash --flash_mode keep --flash_freq 40m --flash_size 4MB 0x290000 spiffs.bin

## 4. Premier démarrage du module

    Au premier démarrage du Wifi, le module se met en attente de connexion WIFI.
    
![Attente_WIFI](../images/attente_wifi.png "Attente_WIFI")

    Il crée un réseau WIFI nommé Core2AP sur lequel il faut se connecter avec un smartphone par exemple ou tout autre
    appareil capable de se connecter au WIFI. Quand la connexion WIFI est établie, il faut naviguer à l'adresse 192.168.4.1 
    pour accéder à la page Web de configuration du WIFI.

![Manager_WIFI](../images/manager_wifi.png "Manager_WIFI")

    Cliquez sur "Configure WIFI" pour choisir un réseau Wifi sur lequel le module se connectera. L'adresse IP devra être fixée sur le routeur.

## 5. Site Web du module

    La gestion du module s'effectue via son site Web, pour cela naviguez sur l'adresse attribuée au module ( cette adresse 
        s'affiche au démarrage du module)
    
    On retrouve 4 onglets sur la page Web du module. Acceuil, Répertoire, Actions et Statut.

## 5.1 Onglet Accueil

    Comme son nom l'indique, c'est le module d'accueil, aucunce action n'est possible sur cet onglet.

## 5.2 Onglet Répertoire

    Cet onglet permet d'afficher les fichiers contenus sur la partition SPIFFS du module, il permet le téléversement de fichiers, 
    la suppression et le renommage.

## 5.3 Onglet Actions

    Cet onglet permet de réinialiser les paramètres du WIFI, au prochain démarrage du module ces paramètres seront redemandés.
    Notez que si le module ne parvient pas à se connecter au WIFI, il offre également la possibilité d'introduire de nouveaux
        paramètres.

## 5.4 Onglet Statut

    Cet onglet affiche quelques informations concernant le module.

## 6 Configuration de l'affichage

    Un fichier important qui devra être téléverser sur le module est le fichier display.json, c'est lui qui permet de configurer 
    l'affichage du module. 

    Le module permet l'affichage de panneaux différents et permet évidemment de voyager d'un panneau à l'autre.

## 6.1 Affichage de la barre de statut

    Voici un code complet qui permet d'afficher toutes les informations disponibles sur le module. Avant l'affichage de la barre 
    de statut, on remarque un paramètre "defaultPanel" qui spécifie le panneau qui sera affiché au démarrage du module, 
    ici "Panel0".

    La barre de statut est configurée en "statusBar", tout d'abord sa poisition "top" ou "bottom" et ensuite les objets qui la compose.
    Chaque objet doit contenir un paramètre "x" qui indique la position de l'objet dans la barre de statut.

    Objet "acin". Indique si le module est connecté électriquement ou pas
    Objet "batteryLevel". Indique le niveau de la batterie
    Objet "time". Indique l'heure ( récupérée sur le Web au démarrage du module )
    Objet "coreTemperature". Indique la température du CPU
    Objet "wifiQuality". Indique la qualité de la connexion WIFI
    Objet "connections". Indique le nombre de clients connectés sur le module

![Barre](../images/barre.png "Barre")

```json
{
  "defaultPanel": "panel0",
  "statusBar": {
    "position": "top",
    "objects": [
      {
        "name": "acin",
        "x": 4
      },
      {
        "name": "batteryLevel",
        "x": 20
      },
      {
        "name": "time",
        "x": 230
      },
      {
        "name": "coreTemperature",
        "x": 70
      },
      {
        "name": "wifiQuality",
        "x": 290
      },
      {
        "name": "connections",
        "x": 304
      }
    ]
  }
}
```



