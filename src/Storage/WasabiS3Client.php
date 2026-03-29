<?php

declare(strict_types=1);

namespace App\Storage;

use RuntimeException;

final class WasabiS3Client
{
    public function __construct(
        private readonly string $endpoint,
        private readonly string $region,
        private readonly string $bucket,
        private readonly string $accessKey,
        private readonly string $secretKey,
        private readonly string $publicBaseUrl,
        private readonly string $pathPrefix
    ) {
    }

    public function uploadFile(string $objectKey, string $filePath, string $mimeType): void
    {
        $this->request('PUT', $objectKey, [], [
            'content-type' => $mimeType,
        ], $filePath);
    }

    public function uploadFileMultipart(string $objectKey, string $filePath, string $mimeType, int $partSizeBytes): void
    {
        $partSizeBytes = max(5 * 1024 * 1024, $partSizeBytes);
        $uploadId = $this->createMultipartUpload($objectKey, $mimeType);
        $parts = [];
        $handle = fopen($filePath, 'rb');

        if ($handle === false) {
            throw new RuntimeException('Could not open the file for multipart upload.');
        }

        try {
            $partNumber = 1;

            while (!feof($handle)) {
                $chunk = fread($handle, $partSizeBytes);

                if ($chunk === false) {
                    throw new RuntimeException('Could not read a file chunk for multipart upload.');
                }

                if ($chunk === '') {
                    break;
                }

                $etag = $this->uploadPart($objectKey, $uploadId, $partNumber, $chunk, $mimeType);
                $parts[] = [
                    'PartNumber' => $partNumber,
                    'ETag' => $etag,
                ];
                $partNumber++;
            }

            $this->completeMultipartUpload($objectKey, $uploadId, $parts);
        } catch (RuntimeException $exception) {
            $this->abortMultipartUpload($objectKey, $uploadId);
            throw $exception;
        } finally {
            fclose($handle);
        }
    }

    public function presignGetObject(string $objectKey, int $expiresSeconds): string
    {
        $expiresSeconds = max(1, min(604800, $expiresSeconds));
        $amzDate = gmdate('Ymd\THis\Z');
        $dateStamp = gmdate('Ymd');
        $signedHeaders = 'host';
        $credentialScope = $dateStamp . '/' . $this->region . '/s3/aws4_request';
        $urlParts = $this->buildUrlParts($objectKey);
        $canonicalUri = $urlParts['canonical_uri'];
        $host = $urlParts['host'];
        $query = [
            'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential' => $this->accessKey . '/' . $credentialScope,
            'X-Amz-Date' => $amzDate,
            'X-Amz-Expires' => (string) $expiresSeconds,
            'X-Amz-SignedHeaders' => $signedHeaders,
        ];

        $canonicalQuery = $this->buildCanonicalQuery($query);
        $canonicalHeaders = "host:{$host}\n";
        $canonicalRequest = "GET\n{$canonicalUri}\n{$canonicalQuery}\n{$canonicalHeaders}\n{$signedHeaders}\nUNSIGNED-PAYLOAD";
        $stringToSign = "AWS4-HMAC-SHA256\n{$amzDate}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);
        $signature = hash_hmac('sha256', $stringToSign, $this->signingKey($dateStamp), false);
        $query['X-Amz-Signature'] = $signature;

        return $urlParts['base_url'] . '?' . $this->buildCanonicalQuery($query);
    }

    public function publicObjectUrl(string $objectKey): string
    {
        $publicBaseUrl = $this->publicBaseUrl !== ''
            ? rtrim($this->publicBaseUrl, '/')
            : rtrim($this->normalizeEndpoint($this->endpoint), '/') . '/' . rawurlencode($this->bucket);

        return $publicBaseUrl . '/' . $this->encodePath($objectKey);
    }

    public function qualifyObjectKey(string $relativePath): string
    {
        return trim(trim($this->pathPrefix, '/') . '/' . trim($relativePath, '/'), '/');
    }

    public function deleteObject(string $objectKey): void
    {
        $this->request('DELETE', $objectKey, [], [], '');
    }

    public function bucket(): string
    {
        return $this->bucket;
    }

    private function createMultipartUpload(string $objectKey, string $mimeType): string
    {
        $response = $this->request('POST', $objectKey, ['uploads' => ''], [
            'content-type' => $mimeType,
        ], '');

        if (!preg_match('/<UploadId>([^<]+)<\/UploadId>/', $response['body'], $matches)) {
            throw new RuntimeException('Wasabi did not return an UploadId for multipart upload.');
        }

        return html_entity_decode($matches[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function uploadPart(string $objectKey, string $uploadId, int $partNumber, string $body, string $mimeType): string
    {
        $response = $this->request('PUT', $objectKey, [
            'partNumber' => (string) $partNumber,
            'uploadId' => $uploadId,
        ], [
            'content-type' => $mimeType,
        ], $body);

        $etag = $response['headers']['etag'] ?? null;

        if (!is_string($etag) || $etag === '') {
            throw new RuntimeException('Wasabi did not return an ETag for part ' . $partNumber . '.');
        }

        return $etag;
    }

    /**
     * @param array<int, array{PartNumber:int,ETag:string}> $parts
     */
    private function completeMultipartUpload(string $objectKey, string $uploadId, array $parts): void
    {
        $xml = '<CompleteMultipartUpload>';

        foreach ($parts as $part) {
            $etag = trim($part['ETag']);

            if (!str_starts_with($etag, '"')) {
                $etag = '"' . trim($etag, '"') . '"';
            }

            $xml .= '<Part><PartNumber>' . $part['PartNumber'] . '</PartNumber><ETag>' . $etag . '</ETag></Part>';
        }

        $xml .= '</CompleteMultipartUpload>';

        $response = $this->request('POST', $objectKey, [
            'uploadId' => $uploadId,
        ], [
            'content-type' => 'application/xml',
        ], $xml);

        if (str_contains($response['body'], '<Error>')) {
            throw new RuntimeException('Wasabi returned an error while completing the multipart upload.');
        }
    }

    private function abortMultipartUpload(string $objectKey, string $uploadId): void
    {
        try {
            $this->request('DELETE', $objectKey, [
                'uploadId' => $uploadId,
            ], [], '');
        } catch (RuntimeException) {
            // Best effort cleanup. Do not mask the original upload exception.
        }
    }

    /**
     * @param array<string, string> $query
     * @param array<string, string> $headers
     * @return array{status:int,headers:array<string,string>,body:string}
     */
    private function request(string $method, string $objectKey, array $query, array $headers, string $bodyOrFilePath): array
    {
        if (!extension_loaded('curl')) {
            throw new RuntimeException('The PHP cURL extension is required to use Wasabi.');
        }

        $normalizedHeaders = [];

        foreach ($headers as $name => $value) {
            $normalizedHeaders[strtolower(trim($name))] = trim($value);
        }

        $isExistingFile = $bodyOrFilePath !== '' && is_file($bodyOrFilePath);
        $payloadHash = $isExistingFile ? hash_file('sha256', $bodyOrFilePath) : hash('sha256', $bodyOrFilePath);
        $amzDate = gmdate('Ymd\THis\Z');
        $dateStamp = gmdate('Ymd');
        $urlParts = $this->buildUrlParts($objectKey);
        $canonicalUri = $urlParts['canonical_uri'];
        $host = $urlParts['host'];
        $normalizedHeaders['host'] = $host;
        $normalizedHeaders['x-amz-content-sha256'] = $payloadHash;
        $normalizedHeaders['x-amz-date'] = $amzDate;
        ksort($normalizedHeaders);

        $signedHeaders = implode(';', array_keys($normalizedHeaders));
        $canonicalHeaders = '';

        foreach ($normalizedHeaders as $name => $value) {
            $canonicalHeaders .= $name . ':' . preg_replace('/\s+/', ' ', $value) . "\n";
        }

        $canonicalQuery = $this->buildCanonicalQuery($query);
        $canonicalRequest = "{$method}\n{$canonicalUri}\n{$canonicalQuery}\n{$canonicalHeaders}\n{$signedHeaders}\n{$payloadHash}";
        $credentialScope = $dateStamp . '/' . $this->region . '/s3/aws4_request';
        $stringToSign = "AWS4-HMAC-SHA256\n{$amzDate}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);
        $signature = hash_hmac('sha256', $stringToSign, $this->signingKey($dateStamp), false);
        $authorization = sprintf(
            'AWS4-HMAC-SHA256 Credential=%s/%s, SignedHeaders=%s, Signature=%s',
            $this->accessKey,
            $credentialScope,
            $signedHeaders,
            $signature
        );

        $httpHeaders = [];

        foreach ($normalizedHeaders as $name => $value) {
            $httpHeaders[] = $name . ': ' . $value;
        }

        $httpHeaders[] = 'Authorization: ' . $authorization;
        $httpHeaders[] = 'Expect:';

        $responseHeaders = [];
        $requestUrl = $urlParts['base_url'] . ($canonicalQuery !== '' ? '?' . $canonicalQuery : '');
        $curl = curl_init($requestUrl);

        if ($curl === false) {
            throw new RuntimeException('Could not start the cURL connection to Wasabi.');
        }

        $options = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $httpHeaders,
            CURLOPT_TIMEOUT => 180,
            CURLOPT_HEADERFUNCTION => static function ($curlHandle, string $headerLine) use (&$responseHeaders): int {
                $length = strlen($headerLine);
                $parts = explode(':', $headerLine, 2);

                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }

                return $length;
            },
        ];

        $handle = null;

        if ($isExistingFile) {
            $handle = fopen($bodyOrFilePath, 'rb');

            if ($handle === false) {
                throw new RuntimeException('Could not open the file for Wasabi upload.');
            }

            $options[CURLOPT_UPLOAD] = true;
            $options[CURLOPT_INFILE] = $handle;
            $options[CURLOPT_INFILESIZE] = (int) filesize($bodyOrFilePath);
        } else {
            $options[CURLOPT_POSTFIELDS] = $bodyOrFilePath;
        }

        curl_setopt_array($curl, $options);
        $responseBody = curl_exec($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if (is_resource($handle)) {
            fclose($handle);
        }

        if ($responseBody === false || $statusCode < 200 || $statusCode >= 300) {
            $details = $error !== '' ? $error : trim(strip_tags((string) $responseBody));
            throw new RuntimeException('Wasabi request failed. ' . ($details !== '' ? $details : 'HTTP response ' . $statusCode . '.'));
        }

        return [
            'status' => $statusCode,
            'headers' => $responseHeaders,
            'body' => (string) $responseBody,
        ];
    }

    /**
     * @return array{base_url:string,canonical_uri:string,host:string}
     */
    private function buildUrlParts(string $objectKey): array
    {
        $normalizedEndpoint = $this->normalizeEndpoint($this->endpoint);
        $parsed = parse_url($normalizedEndpoint);

        if (!is_array($parsed) || empty($parsed['host'])) {
            throw new RuntimeException('Invalid Wasabi endpoint.');
        }

        $scheme = $parsed['scheme'] ?? 'https';
        $host = $parsed['host'];
        $basePath = rtrim((string) ($parsed['path'] ?? ''), '/');
        $canonicalUri = $basePath . '/' . rawurlencode($this->bucket) . '/' . $this->encodePath($objectKey);

        return [
            'base_url' => $scheme . '://' . $host . $canonicalUri,
            'canonical_uri' => $canonicalUri,
            'host' => $host,
        ];
    }

    /**
     * @param array<string, string> $query
     */
    private function buildCanonicalQuery(array $query): string
    {
        if ($query === []) {
            return '';
        }

        ksort($query);
        $items = [];

        foreach ($query as $key => $value) {
            $items[] = rawurlencode((string) $key) . '=' . rawurlencode((string) $value);
        }

        return implode('&', $items);
    }

    private function normalizeEndpoint(string $endpoint): string
    {
        $endpoint = trim($endpoint);

        if (!str_starts_with($endpoint, 'http://') && !str_starts_with($endpoint, 'https://')) {
            return 'https://' . $endpoint;
        }

        return $endpoint;
    }

    private function encodePath(string $path): string
    {
        return implode('/', array_map('rawurlencode', array_filter(explode('/', $path), 'strlen')));
    }

    private function signingKey(string $dateStamp): string
    {
        $kDate = hash_hmac('sha256', $dateStamp, 'AWS4' . $this->secretKey, true);
        $kRegion = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);

        return hash_hmac('sha256', 'aws4_request', $kService, true);
    }
}
