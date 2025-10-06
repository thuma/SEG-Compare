<!DOCTYPE html>
<html lang="sv">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>KGY Prisinfo</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
</head>
<body>
  <div class="container">
  <h1>PrisInfo</h1>
<?php
if(isset($_POST['list']) AND trim($_POST['list']) != ""){
include 'prisfiler/import.php';
$conn = init_sql();

$conn->query("CREATE TEMPORARY TABLE `Varukorg` SELECT * FROM `VarukorgMall` LIMIT 0;");
$stmt = $conn->prepare("INSERT INTO Varukorg (Artikelnummer, Volym) VALUES (?,?);");
$stmt->bind_param('si', $artnr, $volym);

$pristabell ="CREATE TEMPORARY TABLE `VarukorgPris` SELECT 
`Varukorg`.`Artikelnummer` AS `Artikelnummer`,
IFNULL(`rexelPris`.`Benamning`, 
    IFNULL(`ahlsellPris`.`Benamning`,
        IFNULL(`soneparPris`.`Benamning`,
            IFNULL(`solarPris`.`Benamning`,
                `Varukorg`.`Artikelnummer`)))) AS `Benamning`,
`Varukorg`.`Volym` AS `Volym`,
ROUND(`rexelPris`.`Pris` * `Varukorg`.`Volym`, 2) AS `RexPris`,
`rexelPris`.`Enhet` AS `RexEnhet`,
ROUND(`ahlsellPris`.`Pris` * `Varukorg`.`Volym`, 2) AS `AhlsellPris`,
`ahlsellPris`.`Enhet` AS `AhlsellEnhet`,
ROUND(`soneparPris`.`Pris` * `Varukorg`.`Volym`, 2) AS `SoneparPris`,
`soneparPris`.`Enhet` AS `SoneparEnhet`,
ROUND(`solarPris`.`Pris` *  `Varukorg`.`Volym`, 2) AS `SolarPris`,
`solarPris`.`Enhet` AS `SolarEnhet`
from ((((`Varukorg` 
left join `ahlsellPris` on(`ahlsellPris`.`Artikelnummer` = `Varukorg`.`Artikelnummer`)) 
left join `rexelPris` on(`rexelPris`.`Artikelnummer` = `Varukorg`.`Artikelnummer`)) 
left join `soneparPris` on(`soneparPris`.`Artikelnummer` = `Varukorg`.`Artikelnummer`)) 
left join `solarPris` on(`solarPris`.`Artikelnummer` = `Varukorg`.`Artikelnummer`)) where 1;";

$pristabellget = "SELECT * FROM `VarukorgPris` WHERE 1";
$pristabellsumma = "SELECT 'Summa' as Summa, SUM(RexPris),SUM(AhlsellPris),SUM(SoneparPris),SUM(SolarPris) FROM `VarukorgPris` WHERE 1 GROUP BY Summa;";

  ?>
  <table class='table table-striped-columns border'>
    <thead>
      <tr>
        <th scope="col">Artikelnummer</th>
        <th scope="col">Beskrivning</th>
        <th scope="col">Volym</th>
        <th scope="col">Rexel</th>
        <th scope="col">Enhet</th>
        <th scope="col">Ahlsell</th>
        <th scope="col">Enhet</th>
        <th scope="col">Sonepar</th>
        <th scope="col">Enhet</th>
        <th scope="col">Solar</th>
        <th scope="col">Enhet</th>
      </tr>
    </thead>
    <tbody>
  <?php
  $listdata = array(); 
  $nummer = explode("\n",trim($_POST['list']));
  foreach ($nummer as $inrad) {
    $artnr = substr(trim($inrad), 0, 7);
    $volym = preg_replace("/\D+/",'',substr($inrad, 8));
    $listdata[] = array("antal"=>$volym ,"enummer"=>$artnr);
    $stmt->execute();
  }
  $conn->query($pristabell);
  $result = $conn->query($pristabellget);
  while ($row = $result->fetch_assoc() ){
    echo "<tr>";
    $keys = array_keys($row);
    foreach ($keys as $key) {
      if($row[$key]==""){
        $class = 'class="table-danger"';
      } else {
        $class = "";
      }
      if($key == "RexPris"){
        $link = '<a class="btn btn-outline-secondary btn-sm" style="float:right" href="https://www.rexel.se/swe/search/?text='.$row['Artikelnummer'].'&maxProd=4&filter=&aliasname=&categoryurl=" target="_blank">🔍</a>';
      } else if ($key == "AhlsellPris") {
        $link = '<a class="btn btn-outline-secondary btn-sm" style="float:right" href="https://www.ahlsell.se/search?parameters.SearchPhrase='.$row['Artikelnummer'].'" target="_blank">🔍</a>';
      } else if ($key == "SoneparPris") {
        $link = '<a class="btn btn-outline-secondary btn-sm" style="float:right" href="https://www.elektroskandia.se/s?s='.$row['Artikelnummer'].'" target="_blank">🔍</a>';
      } else if ($key == "SolarPris") {
        $link = '<a class="btn btn-outline-secondary btn-sm" style="float:right" href="https://www.solar.se/webshop-search/?query='.$row['Artikelnummer'].'" target="_blank">🔍</a>';
      } else if ($key == "Artikelnummer") {
        $link = '<a class="btn btn-outline-secondary btn-sm" style="float:right" href="https://www.e-nummersok.se/sok?Query='.$row['Artikelnummer'].'" target="_blank">🔍</a>';
      } else {
        $link ='';
      }
      echo "<td ".$class.">".$row[$key]." ".$link."</td>";
    }
    echo "<tr>";
  }
  $result = $conn->query($pristabellsumma);
  while ($row = $result->fetch_assoc() ){
    echo "<tr>";
    $keys = array_keys($row);
    foreach ($keys as $key) {
      if($key === "Summa"){
        echo "<td colspan='3'>".$row[$key]."</td>";
      } else {
        echo "<td colspan='2'>".$row[$key]."</td>";
      }
    }
    echo "<tr>";
  }
  echo "</tbody></table>";
  $conn->close();
} else {
  $_POST['list'] = "";
}
?>
  <form method="POST">
    <textarea class="form-control" name="list" style="height: 240px;"><?php echo $_POST['list'];?></textarea><br>
    <input class="btn btn-primary" type="submit">
  </form>
  <p>Fyll i E-Nummer följt av ett tecken tillexempel mellanslag (eller , eller . eller ;) där efter antalet/läng/mängd av produkten. Tryck sedan på knappen.</p>
  <p>Exempel på hur du fyller i tabellen:</p>
  <pre>3733820 24
3745050 5
3766000 10</pre>
  <p>eller</p>
  <pre>3733820,24
3745050,5
3766000,10</pre>

<h2>Importera listan i webshopparna</h2>
<p>För att imporera listan med produkter för att göra en punchout gör följande:</p>
<ol>
  <li>Bekräfta att du har öppnat punchout webshopen för leverantören via Proceedo</li>
  <li>Kopiera listan nedan för vald leverantör.</li>
  <li>Klicka på leveantören och klistra in på leveantörens punchout webshop.</li>
</ol>
<h3><a href="https://www.elektroskandia.se/snabborder" target="_blank">Sonepar</a></h3>
<p>Att klistra in på Sonepar webshopen:</p>
<pre><?php
  foreach ($listdata as $rad) {
    echo $rad["antal"]." ".$rad["enummer"]."\n";
  }
  ?></pre>
<h3><a href="https://www.rexel.se/swe/newQuickorder" target="_blank">Rexel</a></h3>
<p>I webshopen hos Rexel tryck på: "Klipp & Klistra in din order" och klistra in:</p>
<pre><?php
  foreach ($listdata as $rad) {
    echo $rad["enummer"].", ".$rad["antal"]."\n";
  }
  ?></pre>
<h3><a href="https://www.solar.se/new-cart-page/" target="_blank">Solar</a></h3>
<p>I webshopen hos solar tryck på: "Lägg till flera rader" och klistra in:</p>
<pre><?php
  foreach ($listdata as $rad) {
    echo $rad["enummer"].", ".$rad["antal"]."\n";
  }
  ?></pre>
<h3><a href="https://www.ahlsell.se/mina-sidor/bestall-via-excel/" target="_blank">Ahlsell</a></h3>
<p>Att klistra in på Ahlsell webshopen:</p>
<pre><?php
  foreach ($listdata as $rad) {
    echo $rad["enummer"]."\t".$rad["antal"]."\n";
  }
  ?></pre>

</div>
</body>

</html>