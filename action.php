<?php
ini_set('display_errors', 'On');
ini_set('error_reporting', E_ERROR);

ini_set('soap.wsdl_cache_enabled', 0);
ini_set('soap.wsdl_cache_ttl', 0);

// Service name for the MID services in DDS(Will be displayed to users mobile phones screen during signing process)
define('DDS_MID_SERVICE_NAME', 'Testimine');
// Explanatory message for the MID services in DDS.(Will be displayed to users mobile phones screen during signing process)
define('DDS_MID_INTRODUCTION_STRING', 'Allkirjastamine.');

require __DIR__.'/config/configuration.php';

require_once __DIR__.'/sk/exceptions/MobileIDException.php';
require_once __DIR__.'/sk/helpers/DocHelper.php';
require_once __DIR__.'/sk/helpers/CertificateHelper.php';
require_once __DIR__.'/sk/helpers/SessionHelper.php';
require_once __DIR__.'/sk/service/DigiDocService.php';

// Start session
session_start();

// Set timezone
date_default_timezone_set('Europe/Tallinn');

$dds = DigiDocService::instance($cfgDdsEndpointUrl);

// IDCARD SIGN ACTIONS

/**
 * Collect information about signer
 * @param array $requestParameters - POST request form parameters about signer
 * @return array
 */
function getPrepareSignatureParameters($requestParameters)
{
    $keyPrefix = 'signer';
    $roleParameters = array();
    $keys = array(
        'Role',
        'City',
        'State',
        'PostalCode',
    );

    foreach ($keys as $key) {
        $fullKey = $keyPrefix.$key;
        if (array_key_exists($fullKey, $requestParameters) === true) {
            $roleParameters[$key] = $requestParameters[$fullKey];
        }
    }

    return $roleParameters;
}

/**
 * @param DigiDocService $dds
 * @throws Exception
 */
function handleIdCardSigningFailure($dds)
{
    echo '<p class="alert alert-danger">'.$_POST['error_message'].'</p>';
    if (!empty($_POST['signature_id'])) {
        // The fact that there has been an error and there is a signature ID means that there is a prepared
        // but not finalized signature in the session that needs to be removed.
        $dds->RemoveSignature(
            array('Sesscode' => SessionHelper::getDdsSessionCode(), 'SignatureId' => $_POST['signature_id'])
        );
        // trace 'Adding a signature to the container was not completed successfully so the prepared signature
        // was removed from the container in DigiDocService session.'
    }
}

/**
 * @param DigiDocService $dds
 * @throws Exception
 */
function handleIdCardSigningSuccess($dds)
{
    if (!array_key_exists('signature_value', $_POST) || !array_key_exists('signature_id', $_POST)) {
        throw new InvalidArgumentException('There were missing parameters which are needed to sign with ID Card.');
    }

    // Everything is OK. Let's finalize the signing process in DigiDocService.
    $dds->FinalizeSignature(
        array(
            'Sesscode'       => SessionHelper::getDdsSessionCode(),
            'SignatureId'    => $_POST['signature_id'],
            'SignatureValue' => $_POST['signature_value'],
        )
    );

    // Rewrite the local container with new content
    $datafiles = DocHelper::getDatafilesFromContainer();
    $get_signed_doc_response = $dds->GetSignedDoc(array('Sesscode' => SessionHelper::getDdsSessionCode()));
    $container_data = $get_signed_doc_response['SignedDocData'];
    if (strpos($container_data, 'SignedDoc') === false) {
        $container_data = base64_decode($container_data);
    }

    DocHelper::createContainerWithFiles($container_data, $datafiles);
}

// MOBILE SIGN ACTIONS

/**
 * @param DigiDocService $dds
 * @param array $response
 * @return mixed
 * @throws Exception
 */
function prepareMobileSign($dds, $response)
{
    $phoneNumber = trim($_POST['phoneNo']);
    $identityCode = trim($_POST['idCode']);

    // In actual live situation, the language could be taken from the users customer database for example.
    $language = 'EST';

    $mobileSignResponse = $dds->MobileSign(
        array(
            'Sesscode'                    => SessionHelper::getDdsSessionCode(),
            'SignerIDCode'                => $identityCode,
            'SignerPhoneNo'               => $phoneNumber,
            'ServiceName'                 => DDS_MID_SERVICE_NAME,
            'AdditionalDataToBeDisplayed' => DDS_MID_INTRODUCTION_STRING,
            'Language'                    => $language,
            'MessagingMode'               => 'asynchClientServer',
            'ReturnDocInfo'               => false,
            'ReturnDocData'               => false,
        )
    );

    $response['challenge'] = $mobileSignResponse['ChallengeID'];

    return $response;
}

/**
 * @param DigiDocService $dds
 * @param string $statusCode
 * @throws MobileIDException
 */
function handleMobileSignError($dds, $statusCode)
{
    $messages = $dds->getMidStatusResponseErrorMessages;
    if (array_key_exists($statusCode, $messages)) {
        throw new MobileIDException($messages[$statusCode]);
    }
    throw new MobileIDException("There was an error signing with Mobile ID. Status code is '$statusCode'.");
}

/**
 * @param DigiDocService $dds
 * @param array $response
 * @return mixed
 * @throws Exception
 */
function mobileSignSuccessResponse($dds, $response)
{
    $datafiles = DocHelper::getDatafilesFromContainer();
    $signedResponse = $dds->GetSignedDoc(array('Sesscode' => SessionHelper::getDdsSessionCode()));
    $containerData = $signedResponse['SignedDocData'];
    if (strpos($containerData, 'SignedDoc') === false) {
        $containerData = base64_decode($containerData);
    }

    // Rewrite the local container with new content
    DocHelper::createContainerWithFiles($containerData, $datafiles);

    $response['is_success'] = true;

    return $response;
}

// RUN

switch ($_POST['request_act']) {
    case 'ID_SIGN_CREATE_HASH':
        header('Content-Type: application/json');
        $response = array();
        try {
            // trace 'User started the preparation of signature with ID Card to the container.'
            if (!array_key_exists('signersCertificateHEX', $_POST)) {
                throw new InvalidArgumentException('There were missing parameters which are needed to sign with ID Card.');
            }

            // Let's prepare the parameters for PrepareSignature method.
            $prepareSignatureReqParams['Sesscode'] = SessionHelper::getDdsSessionCode();
            $prepareSignatureReqParams['SignersCertificate'] = $_POST['signersCertificateHEX'];
            $prepareSignatureReqParams['SignersTokenId'] = '';

            array_merge($prepareSignatureReqParams, getPrepareSignatureParameters($_POST));
            $prepareSignatureReqParams['SigningProfile'] = '';

            // Invoke PrepareSignature.
            $prepareSignatureResponse = $dds->PrepareSignature($prepareSignatureReqParams);

            // If we reach here then everything must be OK with the signature preparation.
            $response['signature_info_digest'] = $prepareSignatureResponse['SignedInfoDigest'];
            $response['signature_id'] = $prepareSignatureResponse['SignatureId'];
            $response['signature_hash_type'] = CertificateHelper::getHashType($response['signature_info_digest']);
            $response['is_success'] = true;
        } catch (Exception $e) {
            $code = $e->getCode();
            $message = (!!$code ? $code.': ' : '').$e->getMessage();
            // trace $message
            $response['error_message'] = $message;
        }
        echo json_encode($response);
        break;
    case 'ID_SIGN_COMPLETE':
        // Check if there was any kind of error during ID Card signing.
        if (array_key_exists('error_message', $_POST)) {
            handleIdCardSigningFailure($dds);
        } else {
            handleIdCardSigningSuccess($dds);
        }

        if (!array_key_exists('error_message', $_POST)) {
            // trace 'User successfully added a signature with ID Card to the container.'

            $path_to_created_container=SessionHelper::getUploadDirectory().DIRECTORY_SEPARATOR.SessionHelper::getOriginalContainerName();
            $link=str_replace($cfgBaseDirectory, $cfgBaseUrl, $path_to_created_container);
            echo "<a href='$link' target=_blank download>$link</a>";
        }
        break;
    case 'MID_SIGN':
        header('Content-Type: application/json');
        $response = array();
        try {
            if (!array_key_exists('subAct', $_POST)) {
                throw new HttpInvalidParamException('There are missing parameters which are needed to sign with MID.');
            }

            $subAction = $_POST['subAct'];
            if ($subAction === 'START_SIGNING') {
                if (!array_key_exists('phoneNo', $_POST) || !array_key_exists('idCode', $_POST)) {
                    throw new HttpInvalidParamException('There were missing parameters which are needed to sign with MID.');
                }

                $response = prepareMobileSign($dds, $response);
            }

            if ($subAction === 'GET_SIGNING_STATUS') {
                $statusResponse = $dds->GetStatusInfo(
                    array(
                        'Sesscode'      => SessionHelper::getDdsSessionCode(),
                        'ReturnDocInfo' => false,
                        'WaitSignature' => false,
                    )
                );

                $statusCode = $statusResponse['StatusCode'];
                // trace "User is asking about the status of mobile signing. The status is '$statusCode'.";

                $success = $statusCode === 'SIGNATURE';
                if ($success) {
                    $response = mobileSignSuccessResponse($dds, $response);
                } elseif ($statusCode !== 'REQUEST_OK' && $statusCode !== 'OUTSTANDING_TRANSACTION') {
                    //Process has finished unsuccessfully.
                    handleMobileSignError($dds, $statusCode);
                }
            }
        } catch (Exception $e) {
            $code = $e->getCode();
            $message = ((bool) $code ? $code.': ' : '').$e->getMessage();
            // trace $message;
            $response['error_message'] = $message;
        }

        echo json_encode($response);
        break;
    case 'MID_SIGN_COMPLETE':
        // Check if there was any kind of error during MID signing.
        if (array_key_exists('error_message', $_POST)) {
            echo("<p class=\"alert alert-danger\">".$_POST['error_message'].'</p>');
        }

        if (!array_key_exists('error_message', $_POST)) {
            // trace 'User successfully added a signature with Mobile ID to the container.';

            $path_to_created_container=SessionHelper::getUploadDirectory().DIRECTORY_SEPARATOR.SessionHelper::getOriginalContainerName();
            $link=str_replace($cfgBaseDirectory, $cfgBaseUrl, $path_to_created_container);
            echo "<a href='$link' target=_blank download>$link</a>";
        }
        break;
}

DocHelper::persistHashcodeSession();
