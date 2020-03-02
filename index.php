<?php
// NOTE PHP5.6 throws error, PHP5.3 is OK
// SEE https://stackoverflow.com/questions/4318870/soapfault-exception-could-not-connect-to-host

ini_set('display_errors','On');
ini_set('error_reporting', E_ERROR);

ini_set('soap.wsdl_cache_enabled',0);
ini_set('soap.wsdl_cache_ttl',0);

require __DIR__.'/config/configuration.php';

require_once __DIR__.'/sk/helpers/FileHelper.php';
require_once __DIR__.'/sk/helpers/DocHelper.php';
require_once __DIR__.'/sk/helpers/SessionHelper.php';
require_once __DIR__.'/sk/service/DigiDocService.php';
require_once __DIR__.'/sk/hashcode-lib/FileSystemDataFile.php';

// Set timezone
date_default_timezone_set('Europe/Tallinn');

// Set variables
$sourceFiles=array(
	'C:\inetpub\wwwroot\var\id2\upload\img.jpg'=>'image/jpeg',
	'C:\inetpub\wwwroot\var\id2\upload\doc.pdf'=>'application/pdf'
);
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
foreach ($sourceFiles as $pathToDatafile=>$mimeType) {
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

// debug "Container created $path_to_created_container, datafile added and session started with hashcode form of container. DDS session ID: '$ddsSessionCode'.";
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

<div id="idSignModalHeader">
    <h4>Allkirjasta ID-kaardiga/Sign the document with ID Card</h4>
</div>
<div class="idSignModalContent">
	<div id="idSignModalErrorContainer" style="display: none;"></div>
	<table>
	     <tbody>
		     <tr>
		         <td><label for="idSignCity">Linn/City:</label></td>
		         <td><input id="idSignCity" type="text"></td>
		     </tr>
		     <tr>
		         <td><label for="idSignState">Maakond/State:</label></td>
		         <td><input id="idSignState" type="text"></td>
		     </tr>
		     <tr>
		         <td><label for="idSignCountry">Riik/Country:</label></td>
		         <td><input id="idSignCountry" type="text"></td>
		     </tr>
		     <tr>
		         <td><label for="idSignPostalCode">Postikood/Postal Code:</label></td>
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
    <button type="button" onclick="ee.sk.hashcode.IDCardSign()">Allkirjasta/Sign the document</button>
</div>

<hr />

<div id="mobileSignModalHeader">
	<h4>Allkirjasta Mobiil-ID'ga</h4>
</div>
<div class="mobileSignModalContent">
    <div id="mobileSignErrorContainer" style="display: none;"></div>
	<p>
		NB! Phone number must start with country prefix. <br>
		Example: +37212345678
	</p>
	<p>
		NB! Telefoninumber peab algama riigi koodiga. <br>
		Näiteks: +37212345678
	</p>
    <table>
        <tbody>
	        <tr>
	            <td><label for="mid_PhoneNumber">Telefoninumber/Mobile phone number:</label></td>
	            <td><input id="mid_PhoneNumber" type="text"></td>
	        </tr>
	        <tr>
	            <td><label for="mid_idCode">Isikukood/Social security number:</label></td>
	            <td><input id="mid_idCode" type="text"></td>
	        </tr>
    	</tbody>
    </table>
</div>
<div id="mobileSignModalFooter">
    <input type="hidden" name="_token" value="">
    <button type="button" onclick="ee.sk.hashcode.StartMobileSign()">Allkirjasta/Sign the document</button>
</div>

</body>
</html>