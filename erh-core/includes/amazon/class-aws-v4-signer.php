<?php
/**
 * AWS Signature Version 4 Signing Class for PA-API 5.0.
 *
 * Based on Amazon's official PHP example:
 * https://webservices.amazon.com/paapi5/documentation/without-sdk.html
 *
 * Copyright 2019 Amazon.com, Inc. or its affiliates. All Rights Reserved.
 * Licensed under the Apache License, Version 2.0.
 *
 * @package ERH\Amazon
 */

declare(strict_types=1);

namespace ERH\Amazon;

/**
 * AWS Signature Version 4 signer for Amazon PA-API requests.
 */
class AwsV4Signer {

    private ?string $accessKeyID = null;
    private ?string $secretAccessKey = null;
    private ?string $path = null;
    private ?string $regionName = null;
    private ?string $serviceName = null;
    private string $httpMethodName = 'POST';
    private array $awsHeaders = [];
    private string $payload = '';

    private string $HMACAlgorithm = 'AWS4-HMAC-SHA256';
    private string $aws4Request = 'aws4_request';
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
     * @param string $path The request path.
     */
    public function setPath(string $path): void {
        $this->path = $path;
    }

    /**
     * Set the AWS service name.
     *
     * @param string $serviceName The service name.
     */
    public function setServiceName(string $serviceName): void {
        $this->serviceName = $serviceName;
    }

    /**
     * Set the AWS region name.
     *
     * @param string $regionName The region name.
     */
    public function setRegionName(string $regionName): void {
        $this->regionName = $regionName;
    }

    /**
     * Set the request payload.
     *
     * @param string $payload The JSON payload.
     */
    public function setPayload(string $payload): void {
        $this->payload = $payload;
    }

    /**
     * Set the HTTP request method.
     *
     * @param string $method The HTTP method.
     */
    public function setRequestMethod(string $method): void {
        $this->httpMethodName = strtoupper($method);
    }

    /**
     * Add a header for signing.
     *
     * @param string $headerName Header name.
     * @param string $headerValue Header value.
     */
    public function addHeader(string $headerName, string $headerValue): void {
        $this->awsHeaders[$headerName] = $headerValue;
    }

    /**
     * Prepare the canonical request string.
     *
     * @return string The canonical request.
     * @throws \Exception If required properties not set.
     */
    private function prepareCanonicalRequest(): string {
        if (null === $this->httpMethodName || null === $this->path) {
            throw new \Exception('HTTP method and path must be set.');
        }

        $canonicalURL = '';
        $canonicalURL .= $this->httpMethodName . "\n";
        $canonicalURL .= $this->path . "\n" . '' . "\n";

        $signedHeaders = '';
        ksort($this->awsHeaders);
        foreach ($this->awsHeaders as $key => $value) {
            $signedHeaders .= $key . ';';
            $canonicalURL .= $key . ':' . $value . "\n";
        }
        $canonicalURL .= "\n";
        $this->strSignedHeader = substr($signedHeaders, 0, -1);
        $canonicalURL .= $this->strSignedHeader . "\n";
        $canonicalURL .= $this->generateHex($this->payload);

        return $canonicalURL;
    }

    /**
     * Prepare the string to sign.
     *
     * @param string $canonicalURL The canonical request.
     * @return string The string to sign.
     * @throws \Exception If required properties not set.
     */
    private function prepareStringToSign(string $canonicalURL): string {
        if (null === $this->currentDate || null === $this->regionName || null === $this->serviceName || null === $this->xAmzDate) {
            throw new \Exception('Date, region, service name, and timestamp must be set.');
        }

        $stringToSign = '';
        $stringToSign .= $this->HMACAlgorithm . "\n";
        $stringToSign .= $this->xAmzDate . "\n";
        $stringToSign .= $this->currentDate . '/' . $this->regionName . '/' . $this->serviceName . '/' . $this->aws4Request . "\n";
        $stringToSign .= $this->generateHex($canonicalURL);

        return $stringToSign;
    }

    /**
     * Calculate the signature.
     *
     * @param string $stringToSign The string to sign.
     * @return string The hex-encoded signature.
     * @throws \Exception If required properties not set.
     */
    private function calculateSignature(string $stringToSign): string {
        if (null === $this->secretAccessKey || null === $this->currentDate || null === $this->regionName || null === $this->serviceName) {
            throw new \Exception('Secret key, date, region, and service name must be set.');
        }

        $signatureKey = $this->getSignatureKey($this->secretAccessKey, $this->currentDate, $this->regionName, $this->serviceName);
        $signature = hash_hmac('sha256', $stringToSign, $signatureKey, true);

        return strtolower(bin2hex($signature));
    }

    /**
     * Get the signed headers including Authorization.
     *
     * @return array Headers array.
     * @throws \Exception If signing fails.
     */
    public function getHeaders(): array {
        if (null === $this->xAmzDate) {
            throw new \Exception('Timestamp must be set.');
        }

        if (!isset($this->awsHeaders['x-amz-date'])) {
            $this->awsHeaders['x-amz-date'] = $this->xAmzDate;
        }

        $canonicalURL = $this->prepareCanonicalRequest();
        $stringToSign = $this->prepareStringToSign($canonicalURL);
        $signature = $this->calculateSignature($stringToSign);

        if ($signature) {
            $this->awsHeaders['Authorization'] = $this->buildAuthorizationString($signature);
            return $this->awsHeaders;
        }

        throw new \Exception('Failed to calculate signature.');
    }

    /**
     * Build the Authorization header string.
     *
     * @param string $strSignature The signature.
     * @return string The Authorization header value.
     * @throws \Exception If required properties not set.
     */
    private function buildAuthorizationString(string $strSignature): string {
        if (null === $this->accessKeyID || null === $this->currentDate || null === $this->regionName || null === $this->serviceName || null === $this->strSignedHeader) {
            throw new \Exception('Required properties missing for authorization.');
        }

        return $this->HMACAlgorithm . ' '
            . 'Credential=' . $this->accessKeyID . '/' . $this->currentDate . '/' . $this->regionName . '/' . $this->serviceName . '/' . $this->aws4Request . ','
            . 'SignedHeaders=' . $this->strSignedHeader . ','
            . 'Signature=' . $strSignature;
    }

    /**
     * Generate SHA256 hash as hex.
     *
     * @param string $data Data to hash.
     * @return string Hex-encoded hash.
     */
    private function generateHex(string $data): string {
        return strtolower(bin2hex(hash('sha256', $data, true)));
    }

    /**
     * Derive the signature key.
     *
     * @param string $key Secret Access Key.
     * @param string $date Date (YYYYMMDD).
     * @param string $regionName AWS Region.
     * @param string $serviceName AWS Service Name.
     * @return string The signing key.
     */
    private function getSignatureKey(string $key, string $date, string $regionName, string $serviceName): string {
        $kSecret = 'AWS4' . $key;
        $kDate = hash_hmac('sha256', $date, $kSecret, true);
        $kRegion = hash_hmac('sha256', $regionName, $kDate, true);
        $kService = hash_hmac('sha256', $serviceName, $kRegion, true);
        $kSigning = hash_hmac('sha256', $this->aws4Request, $kService, true);

        return $kSigning;
    }

    /**
     * Get current timestamp (ISO8601 basic format).
     *
     * @return string Timestamp.
     */
    private function getTimeStamp(): string {
        return gmdate('Ymd\THis\Z');
    }

    /**
     * Get current date (YYYYMMDD).
     *
     * @return string Date.
     */
    private function getDate(): string {
        return gmdate('Ymd');
    }
}
