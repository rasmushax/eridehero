<?php
declare(strict_types=1);

namespace Housefresh\Tools\Libs\Amazon;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AWS Signature Version 4 Signing Class for Product Advertising API 5.0.
 *
 * Based on the official PHP example from Amazon documentation:
 * https://webservices.amazon.com/paapi5/documentation/without-sdk.html
 *
 * Copyright 2019 Amazon.com, Inc. or its affiliates. All Rights Reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License").
 * You may not use this file except in compliance with the License.
 * A copy of the License is located at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * or in the "license" file accompanying this file. This file is distributed
 * on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either
 * express or implied. See the License for the specific language governing
 * permissions and limitations under the License.
 */
class HFT_Aws_V4_Signer {

    private ?string $accessKeyID = null;
    private ?string $secretAccessKey = null;
    private ?string $path = null;
    private ?string $regionName = null;
    private ?string $serviceName = null;
    private string $httpMethodName = 'POST'; // Defaulting to POST for PA-API
    private array $awsHeaders = [];
    private string $payload = "";

    private string $HMACAlgorithm = "AWS4-HMAC-SHA256";
    private string $aws4Request = "aws4_request";
    private ?string $strSignedHeader = null;
    private ?string $xAmzDate = null;
    private ?string $currentDate = null;

    /**
     * Constructor.
     *
     * @param string $accessKeyID AWS Access Key ID.
     * @param string $secretAccessKey AWS Secret Access Key.
     */
    public function __construct(string $accessKeyID, string $secretAccessKey) {
        $this->accessKeyID = $accessKeyID;
        $this->secretAccessKey = $secretAccessKey;
        $this->xAmzDate = $this->getTimeStamp();
        $this->currentDate = $this->getDate();
    }

    /**
     * Set the request path.
     *
     * @param string $path The request path (e.g., '/paapi5/getitems').
     */
    public function setPath(string $path): void {
        $this->path = $path;
    }

    /**
     * Set the AWS service name.
     *
     * @param string $serviceName The service name (e.g., 'ProductAdvertisingAPI').
     */
    public function setServiceName(string $serviceName): void {
        $this->serviceName = $serviceName;
    }

    /**
     * Set the AWS region name.
     *
     * @param string $regionName The region name (e.g., 'us-east-1').
     */
    public function setRegionName(string $regionName): void {
        $this->regionName = $regionName;
    }

    /**
     * Set the request payload (body).
     *
     * @param string $payload The JSON payload string.
     */
    public function setPayload(string $payload): void {
        $this->payload = $payload;
    }

    /**
     * Set the HTTP request method.
     *
     * @param string $method The HTTP method (e.g., 'POST').
     */
    public function setRequestMethod(string $method): void {
        $this->httpMethodName = strtoupper($method);
    }

    /**
     * Add a header to be included in the signing process.
     *
     * @param string $headerName Header name (lowercase recommended).
     * @param string $headerValue Header value.
     */
    public function addHeader(string $headerName, string $headerValue): void {
        $this->awsHeaders[$headerName] = $headerValue;
    }

    /**
     * Prepares the canonical request string.
     *
     * @return string The canonical request.
     * @throws \Exception If required properties are not set.
     */
    private function prepareCanonicalRequest(): string {
        if (null === $this->httpMethodName || null === $this->path) {
            throw new \Exception('HTTP method and path must be set before preparing canonical request.');
        }

        $canonicalURL = "";
        $canonicalURL .= $this->httpMethodName . "\n";
        $canonicalURL .= $this->path . "\n" . "" . "\n"; // Query string is empty for PA-API POST requests

        $signedHeaders = '';
        // Ensure headers are sorted by key for canonicalization
        ksort($this->awsHeaders);
        foreach ( $this->awsHeaders as $key => $value ) {
            $signedHeaders .= $key . ";";
            $canonicalURL .= $key . ":" . $value . "\n";
        }
        $canonicalURL .= "\n";
        $this->strSignedHeader = substr ( $signedHeaders, 0, - 1 );
        $canonicalURL .= $this->strSignedHeader . "\n";
        $canonicalURL .= $this->generateHex ( $this->payload );
        return $canonicalURL;
    }

    /**
     * Prepares the string to sign.
     *
     * @param string $canonicalURL The canonical request string.
     * @return string The string to sign.
     * @throws \Exception If required properties are not set.
     */
    private function prepareStringToSign(string $canonicalURL): string {
        if (null === $this->currentDate || null === $this->regionName || null === $this->serviceName || null === $this->xAmzDate) {
            throw new \Exception('Date, region, service name, and timestamp must be set before preparing string to sign.');
        }

        $stringToSign = '';
        $stringToSign .= $this->HMACAlgorithm . "\n";
        $stringToSign .= $this->xAmzDate . "\n";
        $stringToSign .= $this->currentDate . "/" . $this->regionName . "/" . $this->serviceName . "/" . $this->aws4Request . "\n";
        $stringToSign .= $this->generateHex ( $canonicalURL );
        return $stringToSign;
    }

    /**
     * Calculates the signature.
     *
     * @param string $stringToSign The string to sign.
     * @return string The calculated signature (hex encoded).
     * @throws \Exception If required properties are not set.
     */
    private function calculateSignature(string $stringToSign): string {
        if (null === $this->secretAccessKey || null === $this->currentDate || null === $this->regionName || null === $this->serviceName) {
            throw new \Exception('Secret key, date, region, and service name must be set before calculating signature.');
        }
        $signatureKey = $this->getSignatureKey ( $this->secretAccessKey, $this->currentDate, $this->regionName, $this->serviceName );
        $signature = hash_hmac ( "sha256", $stringToSign, $signatureKey, true );
        $strHexSignature = strtolower ( bin2hex ( $signature ) );
        return $strHexSignature;
    }

    /**
     * Get the signed headers array including the Authorization header.
     *
     * @return array Associative array of headers.
     * @throws \Exception If signing fails or required properties are not set.
     */
    public function getHeaders(): array {
        if (null === $this->xAmzDate) {
            throw new \Exception('Timestamp (x-amz-date) must be set before getting headers.');
        }
        // Add x-amz-date just before signing if not already added
        if (!isset($this->awsHeaders['x-amz-date'])) {
             $this->awsHeaders ['x-amz-date'] = $this->xAmzDate;
        }

        // Headers must be sorted by key before preparing the canonical request
        // ksort($this->awsHeaders); // Moved inside prepareCanonicalRequest

        $canonicalURL = $this->prepareCanonicalRequest();
        $stringToSign = $this->prepareStringToSign($canonicalURL);
        $signature = $this->calculateSignature($stringToSign);

        if ($signature) {
            $this->awsHeaders['Authorization'] = $this->buildAuthorizationString($signature);
            // Ensure final headers array is sorted if needed by the HTTP client
            // ksort($this->awsHeaders);
            return $this->awsHeaders;
        } else {
            throw new \Exception('Failed to calculate signature.');
        }
    }

    /**
     * Builds the Authorization header string.
     *
     * @param string $strSignature The calculated hex signature.
     * @return string The full Authorization header value.
     * @throws \Exception If required properties are not set.
     */
    private function buildAuthorizationString(string $strSignature): string {
        if (null === $this->accessKeyID || null === $this->currentDate || null === $this->regionName || null === $this->serviceName || null === $this->strSignedHeader) {
            throw new \Exception('Required properties missing for building authorization string.');
        }
        return $this->HMACAlgorithm . " " . "Credential=" . $this->accessKeyID . "/" . $this->currentDate . "/" . $this->regionName . "/" . $this->serviceName . "/" . $this->aws4Request . "," . "SignedHeaders=" . $this->strSignedHeader . "," . "Signature=" . $strSignature;
    }

    /**
     * Generates the SHA256 hash of data and returns hex representation.
     *
     * @param string $data Data to hash.
     * @return string Hex encoded hash.
     */
    private function generateHex(string $data): string {
        return strtolower(bin2hex(hash("sha256", $data, true)));
    }

    /**
     * Derives the signature key.
     *
     * @param string $key Secret Access Key.
     * @param string $date Date in YYYYMMDD format.
     * @param string $regionName AWS Region.
     * @param string $serviceName AWS Service Name.
     * @return string The derived signing key.
     */
    private function getSignatureKey(string $key, string $date, string $regionName, string $serviceName): string {
        $kSecret = "AWS4" . $key;
        $kDate = hash_hmac ( "sha256", $date, $kSecret, true );
        $kRegion = hash_hmac ( "sha256", $regionName, $kDate, true );
        $kService = hash_hmac ( "sha256", $serviceName, $kRegion, true );
        $kSigning = hash_hmac ( "sha256", $this->aws4Request, $kService, true );
        return $kSigning;
    }

    /**
     * Gets the current timestamp in ISO8601 basic format (YYYYMMDD'T'HHMMSS'Z').
     *
     * @return string Timestamp.
     */
    private function getTimeStamp(): string {
        return gmdate ( "Ymd\THis\Z" );
    }

    /**
     * Gets the current date in YYYYMMDD format.
     *
     * @return string Date.
     */
    private function getDate(): string {
        return gmdate ( "Ymd" );
    }
} 