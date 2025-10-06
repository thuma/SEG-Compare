<?php

include 'import.php';

function filkontroll($fil, $path, $typ){
    if(!isset($fil['size']) || !($fil['size'] > 0) ){
        return Array("status"=>"error", "message"=>"No file");
    } else if($fil['type'] !== 'text/plain' && $fil['type'] !== 'text/csv'){
        return Array("status"=>"error","message"=>'Fileformat error in '.$fil['name']);
    } else if($fil['error'] > 0){
        return Array("status"=>"error","message"=>'File upload error code '.$fil['error'].' in '.$fil['name']);
    } else {
        //$file = file_get_contents(, true);
        $line = fgets(fopen($fil['tmp_name'], 'r'));
        if( count( explode(";", $line) ) < 2){
            return Array("status"=>"error","message"=>'Semicolon not used as separator in '.$fil['name']);  
        }
        move_uploaded_file($fil['tmp_name'], __DIR__.'/'.$path.'/'.$typ);
        if ($typ == 'bruttoprislista.txt'){
            $status = uppdatera_brutto($path);
        } else if ($typ == 'rabattbrev.txt'){
            $status = uppdatera_rabatt($path);
        } else if ($typ == 'nettoprislista.txt'){
           $status = uppdatera_netto($path);
        }
        if ($status !== true){
            return Array("status"=>"error","message"=> $status);
        }
        return Array("status"=>"ok","message"=>$typ . ' uploaded.');
    }
}

if(isset($_POST["submit"]) && isset($_POST['kod'])) {
    $settings = parse_ini_file(__DIR__.'/../../prisdata.ini', true);
    $kod = $_POST['kod'];
    if(isset($settings["levkoder"][$kod])){
        $path = $settings["levkoder"][$kod];
    } else {
        die("Felaktig leverantörskod");
    }
    $result = Array(
        'bruttoprislista' => filkontroll( $_FILES['bruttoprislista'], $path, 'bruttoprislista.txt' ),
        'rabattbrev' => filkontroll( $_FILES['rabattbrev'], $path, 'rabattbrev.txt' ),
        'nettoprislista' => filkontroll( $_FILES['nettoprislista'], $path, 'nettoprislista.txt' )
    );
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode($result));
}
?>
<!DOCTYPE html>
<html>
    <head>
        <link 
            href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
            rel="stylesheet"
            integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
            crossorigin="anonymous"
        >
    </head>
    <body>
        <div class="container">
            <form action="update.php" method="post" enctype="multipart/form-data">
            <h3>Välj nya filer att ladda upp:</h3>

            <div class="input-group mb-3">
                <input type="file" class="form-control" name="bruttoprislista" id="bruttoprislista">
                <span class="input-group-text">Bruttoprislista</span>
            </div>
            <div class="input-group mb-3">
                <input type="file" class="form-control" name="rabattbrev" id="rabattbrev">
                <span class="input-group-text">Rabattbrev</span>
            </div>
            <div class="input-group mb-3">
                <input type="file" class="form-control" name="nettoprislista" id="nettoprislista">
                <span class="input-group-text">Nettoprislista*</span>
            </div>
            <div class="input-group mb-3">
                <input type="text" class="form-control" name="kod" id="kod">
                <span class="input-group-text">Leverantörskod</span>
            </div>
            <input class="btn btn-primary" type="submit" value="Ladda upp" name="submit">
            </form>
            <p>
            * Nettoprislista är inte obligtorisk. Nettoprislistan används för avvikande priser mot rabattbrevet för enskilda produkter.
            </p>
            <div>
            <p>
            Standarden nedan accepteras alltid, önskas annan standard kontakta avtalsansvarig. <br>
            </p>
                <p><span style="font-size: xx-large;">Format bruttoprislista, rabattbrev, nettoprislista</span></p>
                <p>&nbsp;</p>
                <p><h3>Filformat bruttoprislista (GNP)</h3></p>
                <p>
                Fältseparator: Semikolon<br> 
                Filformat: text (.txt / .csv)<br>
                Encoding: UTF-8<br>
                Radbrytningsstandard: LF(#10)</p>
                <p>&nbsp;</p>
                <strong>Obligatoriska fällt:</strong><br>
                <table>
                    <tbody>
                    <tr>
                        <td align="left" valign="baseline"><strong>Fält</strong></td>
                        <td align="left" valign="baseline"><strong>Exempel/Kommentar</strong></td>
                    </tr>
                    <tr>
                        <td align="left" valign="baseline">Artikelnummer</td>
                        <td align="left" valign="baseline">1416002</td>
                    </tr>
                    <tr>
                        <td align="left" valign="baseline">Benämning</td>
                        <td align="left" valign="baseline">VP-rör 16 MM</td>
                    </tr>
                    <tr>
                        <td align="left" valign="baseline">Enhet</td>
                        <td align="left" valign="baseline">FP / M / ST / ...</td>
                    </tr>
                    <tr>
                        <td align="left" valign="baseline">Förpackningsstorlek</td>
                        <td align="left" valign="baseline">10</td>
                    </tr>
                    <tr>
                        <td align="left" valign="baseline">Materialgrupp</td>
                        <td align="left" valign="baseline">14AA</td>
                    </tr>
                    <tr>
                        <td align="left" valign="baseline">GN-Pris</td>
                        <td align="left" valign="baseline">15,00</td>
                    </tr>
                    <tr>
                        <td align="left" valign="baseline">Prisdatum</td>
                        <td align="left" valign="baseline">20130701</td>
                    </tr>
                    <tr>
                        <td align="left" valign="baseline">Lagerförd</td>
                        <td align="left" valign="baseline"> J / N / ... </td>
                    </tr>
                    </tbody>
                </table>
                <a href="exempel/bruttoprislista.csv" class="btn btn-secondary">Exempelfil för bruttoprislista</a>
                </p>

                <p><h3>Filformat rabattbrev</h3></p>
                <p>
                Fältseparator: Semikolon<br> 
                Filformat: text (.txt / .csv)<br>
                Encoding: UTF-8<br>
                Radbrytningsstandard: LF(#10)</p>
                <p>&nbsp;</p>
                <strong>Obligatoriska fällt:</strong><br>
                <table>
                    <tbody>
                    <tr>
                        <td align="left" valign="baseline"><strong>Fält</strong></td>
                        <td align="left" valign="baseline"><strong>Exempel/Kommentar</strong></td>
                    </tr>
                    <tr>
                        <td align="left" valign="baseline">Materialgrupp</td>
                        <td align="left" valign="baseline">00AB</td>
                    </tr>
                    <tr>
                        <td align="left" valign="baseline">Rabatt</td>
                        <td align="left" valign="baseline">15,6</td>
                    </tr>
                    </tbody>
                </table>
                <a href="exempel/rabattbrev.csv" class="btn btn-secondary">Exempelfil för rabattbrev</a>
                </p>
                <p><h3>Filformat Nettoprislista</h3></p>
                <p>
                Fältseparator: Semikolon<br> 
                Filformat: text (.txt / .csv)<br>
                Encoding: UTF-8<br>
                Radbrytningsstandard: LF(#10)</p>
                <p>&nbsp;</p>
                <strong>Obligatoriska fällt:</strong><br>
                <table>
                    <tbody>
                    <tr>
                        <td align="left" valign="baseline"><strong>Fält</strong></td>
                        <td align="left" valign="baseline"><strong>Exempel/Kommentar</strong></td>
                    </tr>
                    <tr>
                        <td align="left" valign="baseline">Artikelnummer</td>
                        <td align="left" valign="baseline">1416002</td>
                    </tr>
                    <tr>
                        <td align="left" valign="baseline">Pris</td>
                        <td align="left" valign="baseline">15,00</td>
                    </tr>
                    <tr>
                        <td align="left" valign="baseline">Prisdatum</td>
                        <td align="left" valign="baseline">20130701</td>
                    </tr>
                    </tbody>
                </table>
                <a href="exempel/nettoprislista.csv" class="btn btn-secondary">Exempelfil för nettoprislista</a>
                </p>
            </div>
            <p>
            För tekniska frågor vid integrering kontakta: <br>
            Martin Harari Thuresson<br>
            <a href="mailto:martin.harari-thuresson@molndal.se">martin.harari-thuresson@molndal.se</a><br>
            <a href="tel:+46723175800">+46723175800</a>
            </p>
        </div>
    </body>
</html>