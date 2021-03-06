<?php
/**
 * DDS digidoc endpoint URL
 */
$cfgDdsEndpointUrl='https://tsp.demo.sk.ee/';
/**
 * Directory where the uploaded files are copied and temporary files stored. SHOULD END WITH A DIRECTORY_SEPARATOR!!!
 */
$cfgUploadDirectory=dirname(__DIR__).DIRECTORY_SEPARATOR.'upload'.DIRECTORY_SEPARATOR;
/**
 * Web root dir
 */
$cfgBaseDirectory='C:\inetpub\wwwroot\digidoc';
/**
 * Web host
 */
$cfgBaseUrl='https://localhost/digidoc';
/**
 * Source files
 */
$cfgSourceFiles=array(
	'C:\inetpub\wwwroot\digidoc\img.jpg'=>'image/jpeg',
	'C:\inetpub\wwwroot\digidoc\doc.pdf'=>'application/pdf'
);