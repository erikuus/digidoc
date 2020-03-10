<?php

// NOTE PHP5.6 throws error
// SEE https://stackoverflow.com/questions/4318870/soapfault-exception-could-not-connect-to-host

ini_set('display_errors', 'On');
ini_set('error_reporting', E_ERROR);

ini_set('soap.wsdl_cache_enabled', 0);
ini_set('soap.wsdl_cache_ttl', 0);

require __DIR__.'/config/configuration.php';

require_once __DIR__.'/sk/helpers/FileHelper.php';
require_once __DIR__.'/sk/helpers/DocHelper.php';
require_once __DIR__.'/sk/helpers/SessionHelper.php';
require_once __DIR__.'/sk/service/DigiDocService.php';
require_once __DIR__.'/sk/hashcode-lib/FileSystemDataFile.php';

// Set timezone
date_default_timezone_set('Europe/Tallinn');

// Set variables
$sourceFiles=$cfgSourceFiles;
$format = 'BDOC';
$version = '2.1';

// start session
session_start();

// Get service instance
$dds = DigiDocService::instance($cfgDdsEndpointUrl);

// Init new dds session
SessionHelper::initDdsSession($dds, $cfgUploadDirectory, 'test.bdoc');

// Make dir for container
FileHelper::makeUploadDir();

// Create an empty container to DDS session.
$dds->CreateSignedDoc(
    array(
        'Sesscode' => SessionHelper::getDdsSessionCode(),
        'Format'   => $format,
        'Version'  => $version,
    )
);

// Add data files as HASHCODE to the container in DDS session
$datafiles = array();
foreach ($sourceFiles as $pathToDatafile => $mimeType) {
    DocHelper::addDatafileViaDds($dds, $pathToDatafile, $mimeType);
    $datafiles[] = new FileSystemDataFile($pathToDatafile);
}

// Get the HASHCODE container from DDS
$getSignedDocResponse = $dds->GetSignedDoc(array('Sesscode' => SessionHelper::getDdsSessionCode()));
$containerData = $getSignedDocResponse['SignedDocData'];
if (strpos($containerData, 'SignedDoc') === false) {
    $containerData = base64_decode($containerData);
}

// Rewrite the local container with new content
$pathToContainer=DocHelper::createContainerWithFiles($containerData, $datafiles);
?>

<html lang="en"><head>
    <meta http-equiv="x-ua-compatible" content="IE=Edge">
    <meta charset="UTF-8">
    <script src="assets/js/npo.js"></script>
    <script src="assets/js/jquery.js"></script>
    <script src="assets/js/hwcrypto.js"></script>
    <script src="assets/js/hashcode.js"></script>
    <script>
        ee.sk.hashcode.defaultPath='action.php';
        ee.sk.hashcode.phoneNumberIsMandatory='Telefoninumber on kohustuslik!';
        ee.sk.hashcode.socialSecurityNumberIsMandatory='Isikukood on kohustuslik!';
        ee.sk.hashcode.mobileSignIsInProgressMessage='<b>Toimub allkirjastamine.</b> Veendu kontrollkoodi õigsuses ja sisesta telefonil Mobiil-ID PIN2-kood. Kontrollkood: ';
        ee.sk.hashcode.mobileSignAjaxErrorMessage='Mobiil-ID kaudu allkirjastamist ei õnnestunud alustada!';
        ee.sk.hashcode.noBackendMessage='Veebilehitsejas puudub ID-kaardi tugi!';
        ee.sk.hashcode.userCancelMessage='Kasutaja katkestas digiallkirjastamise!';
        ee.sk.hashcode.invalidArgumentMessage='Tehniline tõrge!';
        ee.sk.hashcode.noCertificatesMessage='Sertifikaatide lugemine ebõnnestus!. Kontrolli, kas ID-kaardi lugeja ja ID-kaart on korrektselt ühendatud.';
        ee.sk.hashcode.noImplementationMessage='Paigalda oma arvutisse ID-kaardi tarkvara või puuduv veebilehitseja laiendus!';
        ee.sk.hashcode.unknownTechnicalErrorMessage='Tehniline viga!';
        ee.sk.hashcode.unknownErrorMessage='Tundmatu viga!';
    </script>
    <title>Digiallkirjastamine</title>
</head>
<body>

<h1>Digiallkirjastamise minimalistlik näidisrakendus</h1>

<p>Allkirjastatakse kaks faili (img.jpg ja doc.pdf), mis asuvad serveris.
Testimiseks peate oma ID-kaardi ja mobiil-ID sertifikaadid registreerima:
<a href="https://demo.sk.ee/upload_cert/" target="_blank">ID-kaart</a>,
<a href="https://demo.sk.ee/MIDCertsReg/" target="_blank">Mobiil-ID</a>
</p>

<div id="idSignModalHeader">
    <h4>Allkirjasta ID-kaardiga</h4>
</div>
<div class="idSignModalContent">
    <div id="idSignModalErrorContainer" style="display: none;"></div>
    <table>
         <tbody>
             <tr>
                 <td><label for="idSignCity">Linn:</label></td>
                 <td><input id="idSignCity" type="text"></td>
             </tr>
             <tr>
                 <td><label for="idSignState">Maakond:</label></td>
                 <td><input id="idSignState" type="text"></td>
             </tr>
             <tr>
                 <td><label for="idSignCountry">Riik:</label></td>
                 <td><input id="idSignCountry" type="text"></td>
             </tr>
             <tr>
                 <td><label for="idSignPostalCode">Postikood:</label></td>
                 <td><input id="idSignPostalCode" type="text"></td>
             </tr>
             <tr>
                 <td><label for="idSignRole">Roll/Role:</label></td>
                 <td><textarea id="idSignRole" cols="30" rows="10"></textarea></td>
             </tr>
        </tbody>
    </table>
</div>
<div id="idSignModalFooter">
    <input type="hidden" name="_token" value="">
    <button type="button" onclick="ee.sk.hashcode.IDCardSign()">Allkirjasta</button>
</div>

<br />
<br />

<div id="mobileSignModalHeader">
    <h4>Allkirjasta Mobiil-ID'ga</h4>
</div>
<div class="mobileSignModalContent">
    <div id="mobileSignErrorContainer" style="display: none;"></div>
    <p>
        NB! Telefoninumber peab algama riigi koodiga. <br>
        Näiteks: +37212345678
    </p>
    <table>
        <tbody>
            <tr>
                <td><label for="mid_PhoneNumber">Telefoninumber:</label></td>
                <td><input id="mid_PhoneNumber" type="text"></td>
            </tr>
            <tr>
                <td><label for="mid_idCode">Isikukood:</label></td>
                <td><input id="mid_idCode" type="text"></td>
            </tr>
        </tbody>
    </table>
</div>
<div id="mobileSignModalFooter">
    <input type="hidden" name="_token" value="">
    <button type="button" onclick="ee.sk.hashcode.StartMobileSign()">Allkirjasta</button>
</div>

</body>
</html>
