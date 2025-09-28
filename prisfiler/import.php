<?php

function init_sql(){
    $settings = parse_ini_file(__DIR__.'/../../prisdata.ini', true);
    $servername = "localhost";
    $username = $settings["db"]["username"];
    $password = $settings["db"]["password"];
    $dbname = $settings["db"]["dbname"];

    // Create connection
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    mysqli_set_charset($conn, "utf8mb4");

    return $conn;
}

function initdb(){
    $conn = init_sql();
    $levs = array("sonepar","solar","rexel","ahlsell");

    $gn = <<<SQL
    CREATE TABLE IF NOT EXISTS `__Brutto` (
    `Artikelnummer` varchar(7) NOT NULL,
    `Benämning` text NOT NULL,
    `Enhet` varchar(3) NOT NULL,
    `Förpackningsstorlek` int(11) DEFAULT NULL,
    `Materialgrupp` varchar(12) NOT NULL,
    `GN-Pris` decimal(12,2) NOT NULL,
    `Prisdatum` date NOT NULL,
    `Lagerförd` varchar(2) NOT NULL,
    PRIMARY KEY (`Artikelnummer`(7))
    );
    SQL;

    $rabatt = <<<SQL
    CREATE TABLE IF NOT EXISTS `__Rabattbrev` (
    `Materialgrupp` varchar(12) NOT NULL,
    `Rabatt` decimal(5,2) NOT NULL,
    PRIMARY KEY (`Materialgrupp`(12))
    );
    SQL;

    $netto = <<<SQL
    CREATE TABLE IF NOT EXISTS `__Netto` (
    `Artikelnummer` VARCHAR(7) NOT NULL ,
    `Pris` DECIMAL(12,2) NOT NULL ,
    `Prisdatum` DATE NOT NULL ,
    PRIMARY KEY (`Artikelnummer`(7))
    );
    SQL;

    $vy = <<<SQL
    CREATE VIEW IF NOT EXISTS __Pris AS SELECT
    __Brutto.`Artikelnummer` AS `Artikelnummer`,
    __Brutto.`Benämning` AS `Benamning`,
    __Brutto.`Lagerförd` AS `Lagerförd`,
    IFNULL(
            __Netto.`Pris`,
            (( 1 - __Rabattbrev.`Rabatt` / 100 ) * __Brutto.`GN-Pris`)) AS `Pris`,
    __Brutto.`Enhet` AS `Enhet` 
    FROM
    __Brutto
        LEFT JOIN __Rabattbrev 
        ON (__Brutto.`Materialgrupp` = __Rabattbrev.`Materialgrupp`)
            LEFT JOIN __Netto
            ON(__Brutto.`Artikelnummer` = __Netto.`Artikelnummer`) 
    WHERE 1;
    SQL;

    $korgmall = <<<SQL
    CREATE TABLE IF NOT EXISTS  `VarukorgMall` (
    `Artikelnummer` VARCHAR(7) NOT NULL,
    `Volym` int(11) NOT NULL,
    PRIMARY KEY (`Artikelnummer`(7))
    )
    SQL;

    foreach ($levs as $lev) {
        $conn->query(str_replace("__", $lev, $gn));
        $conn->query(str_replace("__", $lev, $rabatt));
        $conn->query(str_replace("__", $lev, $netto));    
        $conn->query(str_replace("__", $lev, $vy));    
    }
    $conn->query($korgmall);

}

function uppdatera_brutto($lev){
    $conn = init_sql();
    $bruttoinsert = <<<SQL
        REPLACE INTO __Brutto (
        `Artikelnummer`,
        `Benämning`,
        `Enhet`,
        `Förpackningsstorlek`,
        `Materialgrupp`,
        `GN-Pris`,
        `Prisdatum`,
        `Lagerförd`) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?);
    SQL;

    $stmt = $conn->prepare(str_replace("__", $lev, $bruttoinsert));

    $stmt->bind_param("sssissss", 
    $Artikelnummer, 
    $Benamning, 
    $Enhet, 
    $Forpackningsstorlek,
    $Materialgrupp,
    $Pris,
    $Prisdatum,
    $Lagerford);

    if (($handle = fopen($lev."/bruttoprislista.txt", "r")) !== FALSE) {
        $headers = fgetcsv($handle, 1000, ";");
        $ordnad = array();
        $rubriker = array("Artikelnummer","Benämning","Enhet","Förpackningsstorlek","Materialgrupp","GN-Pris","Prisdatum","Lagerförd");
        foreach($rubriker as $rubrik){
            $kollumn = array_search($rubrik, $headers);
            if($kollumn === false){
                return ("Missing header: ".$rubrik);
            } else {
                $ordnad[$rubrik] = $kollumn;
            }
        }
        while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
            $Artikelnummer = $data[$ordnad["Artikelnummer"]];
            $Benamning = $data[$ordnad["Benämning"]];
            $Enhet = $data[$ordnad["Enhet"]];
            $Forpackningsstorlek = $data[$ordnad["Förpackningsstorlek"]];
            $Materialgrupp = $data[$ordnad["Materialgrupp"]];
            $Pris = str_replace(",", ".",$data[$ordnad["GN-Pris"]]);
            $Prisdatum = $data[$ordnad["Prisdatum"]];
            $Lagerford = $data[$ordnad["Lagerförd"]];
            try {
                $stmt->execute();
            } catch (Exception $e) {
                return $e->getMessage()." data: ".implode(";", $data).")";;
            }
        }
        fclose($handle);
    }
    return true;
}

function uppdatera_rabatt($lev){
    $conn = init_sql();
    $rabattinsert = <<<SQL
        REPLACE INTO __Rabattbrev (
        `Materialgrupp`,
        `Rabatt`) 
        VALUES (?, ?);
    SQL;

    $stmt = $conn->prepare(str_replace("__", $lev, $rabattinsert));

    $stmt->bind_param("ss", 
    $Materialgrupp, 
    $Rabatt);

    if (($handle = fopen($lev."/rabattbrev.txt", "r")) !== FALSE) {
        $headers = fgetcsv($handle, 1000, ";");
        $ordnad = array();
        $rubriker = array("Materialgrupp","Rabatt");
        foreach($rubriker as $rubrik){
            $kollumn = array_search($rubrik, $headers);
            if($kollumn === false){
                return ("Missing header: ".$rubrik);
            } else {
                $ordnad[$rubrik] = $kollumn;
            }
        }
        while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
            $Materialgrupp = $data[$ordnad["Materialgrupp"]];
            $Rabatt = str_replace(",", ".",$data[$ordnad["Rabatt"]]);
            if( 
                str_contains($Rabatt,',') === false
            ){
                $Rabatt = ((float)$Rabatt) / 10;
            }
            try {
                $stmt->execute();
            } catch (Exception $e) {
                return $e->getMessage()." data: ".implode(";", $data).")";;
            }
        }
        fclose($handle);
    }
    return true;
}

function uppdatera_netto($lev){
    $conn = init_sql();
    $nettoinsert = <<<SQL
        REPLACE INTO __Netto (
        `Artikelnummer`,
        `Pris`,
        `Prisdatum` ) 
        VALUES (?, ?, ?);
    SQL;

    $stmt = $conn->prepare(str_replace("__", $lev, $nettoinsert));

    $stmt->bind_param("sss", 
    $Artikelnummer, 
    $Pris,
    $Prisdatum);

    if (($handle = fopen($lev."/nettoprislista.txt", "r")) !== FALSE) {
        $headers = fgetcsv($handle, 1000, ";");
        $ordnad = array();
        $rubriker = array("Artikelnummer","Pris","Prisdatum");
        foreach($rubriker as $rubrik){
            $kollumn = array_search($rubrik, $headers);
            if($kollumn === false){
                return ("Missing header: ".$rubrik);
            } else {
                $ordnad[$rubrik] = $kollumn;
            }
        }
        while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
            $Artikelnummer = substr($data[$ordnad["Artikelnummer"]],0,7);
            $Pris = str_replace(",", ".",$data[$ordnad["Pris"]]);
            $Prisdatum =  $data[$ordnad["Prisdatum"]];
            try {
                $stmt->execute();
            } catch (Exception $e) {
                return $e->getMessage()." data: ".implode(";", $data).")";
            }
        }
        fclose($handle);
    }
    return true;
}

if(isset($_GET["init"])){
    initdb();
    echo("done");
}

?>