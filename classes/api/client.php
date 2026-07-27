<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_dixeo\api;

use local_dixeo\dto\file_upload_part;
use local_dixeo\api\exception\api_exception;
use local_dixeo\api\exception\authentication_exception;
use local_dixeo\api\exception\rate_limit_exception;

/**
 * HTTP client for communicating with the Dixeo API.
 *
 * Handles all low-level HTTP communication, including authentication,
 * request formatting, and error response parsing.
 *
 * @package    local_dixeo
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class client {
    /** @var string The API base URL. */
    protected string $baseurl;

    /** @var string The API key for authentication. */
    protected string $apikey;

    /** @var int Request timeout in seconds. */
    protected int $timeout;

    /** @var int Connection timeout in seconds. */
    protected int $connecttimeout;

    /**
     * Constructor.
     *
     * @param string|null $baseurl The API base URL. If null, uses configured value.
     * @param string|null $apikey The API key. If null, uses configured value.
     * @param int $timeout Request timeout in seconds.
     * @param int $connecttimeout Connection timeout in seconds.
     */
    public function __construct(
        ?string $baseurl = null,
        ?string $apikey = null,
        int $timeout = 30,
        int $connecttimeout = 10
    ) {
        $this->baseurl = $baseurl ?? get_config('local_dixeo', 'api_url') ?: 'https://api.dixeo.io';
        $this->apikey = $apikey ?? get_config('local_dixeo', 'api_key') ?: '';
        $this->timeout = $timeout;
        $this->connecttimeout = $connecttimeout;
    }

    /**
     * Send a POST request to the API.
     *
     * @param string $endpoint The API endpoint (e.g., '/v1/modules/generate').
     * @param array $data The request payload.
     * @param string|null $idempotencykey Optional idempotency key to prevent duplicate requests.
     * @return array The decoded response data.
     * @throws api_exception If the request fails.
     */
    public function post(string $endpoint, array $data, ?string $idempotencykey = null): array {
        return $this->request('POST', $endpoint, $data, $idempotencykey);
    }

    /**
     * Send a GET request to the API.
     *
     * @param string $endpoint The API endpoint (e.g., '/v1/jobs/{id}').
     * @param array $queryparams Optional query parameters.
     * @return array The decoded response data.
     * @throws api_exception If the request fails.
     */
    public function get(string $endpoint, array $queryparams = []): array {
        return $this->request('GET', $endpoint, $queryparams);
    }

    /**
     * Send a PUT request to the API.
     *
     * @param string $endpoint The API endpoint (e.g., '/v1/courses/templates/{id}').
     * @param array $data The request payload.
     * @return array The decoded response data.
     * @throws api_exception If the request fails.
     */
    public function put(string $endpoint, array $data = []): array {
        return $this->request('PUT', $endpoint, $data);
    }

    /**
     * Send an HTTP request to the API.
     *
     * @param string $method The HTTP method (GET, POST, etc.).
     * @param string $endpoint The API endpoint.
     * @param array $data The request data (body for POST, query params for GET).
     * @param string|null $idempotencykey Optional idempotency key.
     * @return array The decoded response data.
     * @throws api_exception If the request fails.
     */
    protected function request(string $method, string $endpoint, array $data = [], ?string $idempotencykey = null): array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $this->validate_configuration();

        $curl = $this->create_curl($this->timeout);
        $url = rtrim($this->baseurl, '/') . '/' . ltrim($endpoint, '/');

        // Set headers.
        $headers = [
            'X-API-Key: ' . $this->apikey,
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: local_dixeo/1.0 (Moodle Plugin)',
        ];

        if ($method === 'POST') {
            $headers[] = 'X-Idempotency-Key: ' . ($idempotencykey ?? \core\uuid::generate());
        } else if ($idempotencykey !== null) {
            $headers[] = 'X-Idempotency-Key: ' . $idempotencykey;
        }

        $curl->setHeader($headers);

        // Execute request based on method.
        if ($method === 'GET') {
            if (!empty($data)) {
                $url .= '?' . http_build_query($data);
            }
            $response = $curl->get($url);
        } else if ($method === 'DELETE') {
            if (!empty($data)) {
                $url .= '?' . http_build_query($data);
            }
            $response = $curl->delete($url);
        } else {
            $jsonpayload = json_encode($data);
            if ($jsonpayload === false) {
                throw new api_exception(
                    'invalid_payload',
                    'Failed to encode request payload as JSON: ' . json_last_error_msg(),
                    0
                );
            }

            if ($method === 'PUT') {
                $curl->setopt(['CURLOPT_CUSTOMREQUEST' => 'PUT']);
            }
            $response = $curl->post($url, $jsonpayload);
        }

        // Check for curl errors.
        $errno = $curl->get_errno();
        if ($errno) {
            throw new api_exception(
                'connection_error',
                'Failed to connect to Dixeo API: ' . $curl->error,
                0,
                ['curl_errno' => $errno]
            );
        }

        // Parse the response.
        return $this->parse_response($response, $curl->get_info());
    }

    /**
     * Parse and validate the API response.
     *
     * @param string $response The raw response body.
     * @param array $info The curl info array.
     * @return array The decoded response data.
     * @throws api_exception If the response indicates an error.
     */
    protected function parse_response(string $response, array $info): array {
        $httpcode = (int) ($info['http_code'] ?? 0);
        $url = (string) ($info['url'] ?? '');
        $contenttype = (string) ($info['content_type'] ?? '');
        $responsebytes = strlen($response);
        $data = json_decode($response, true);

        // Log metadata only — never response bodies (may contain course content or PII).
        debugging(sprintf(
            'Dixeo API Response - HTTP %d, bytes=%d, content_type=%s, url=%s',
            $httpcode,
            $responsebytes,
            $contenttype !== '' ? $contenttype : 'n/a',
            $url !== '' ? $url : 'n/a'
        ), DEBUG_DEVELOPER);

        // Handle 204 No Content (e.g., DELETE responses) — no body to parse.
        if ($httpcode === 204) {
            return [];
        }

        // Handle JSON parse errors.
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new api_exception(
                'invalid_response',
                'Invalid JSON response from Dixeo API',
                $httpcode,
                [
                    'response_bytes' => $responsebytes,
                    'json_error' => json_last_error_msg(),
                    'content_type' => $contenttype,
                ]
            );
        }

        // Successful responses: unwrap the optional 'data' key when present.
        if ($httpcode >= 200 && $httpcode < 300) {
            return $data['data'] ?? $data;
        }

        // Error responses follow RFC 7807 (Problem Details).
        $errortype = is_array($data) ? (string) ($data['type'] ?? 'unknown_error') : 'unknown_error';
        $errortitle = is_array($data) ? (string) ($data['title'] ?? '') : '';
        debugging(sprintf(
            'Dixeo API Error - HTTP %d, type=%s, title=%s, url=%s',
            $httpcode,
            $errortype,
            $errortitle !== '' ? $errortitle : 'n/a',
            $url !== '' ? $url : 'n/a'
        ), DEBUG_DEVELOPER);
        throw api_exception::from_response(is_array($data) ? $data : [], $httpcode);
    }

    /**
     * Whether the URL is an absolute HTTPS URL with a host.
     *
     * @param string $url Candidate API base URL.
     * @return bool
     */
    public static function is_https_url(string $url): bool {
        $url = trim($url);
        if ($url === '') {
            return false;
        }

        $parts = parse_url($url);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }

        return strtolower((string) $parts['scheme']) === 'https';
    }

    /**
     * Validate that the API is properly configured.
     *
     * @throws authentication_exception If the API key is not configured.
     * @throws api_exception If the base URL is missing or not HTTPS.
     */
    protected function validate_configuration(): void {
        if (empty($this->apikey)) {
            throw new authentication_exception(
                'Dixeo API key is not configured. Please configure it in the plugin settings.'
            );
        }

        if (!self::is_https_url($this->baseurl)) {
            throw new api_exception(
                'insecure_transport',
                get_string('error:api_url_https_required', 'local_dixeo'),
                0,
                ['baseurl' => $this->baseurl]
            );
        }
    }

    /**
     * Default curl options for Dixeo API calls.
     *
     * Redirects are disabled so the API key is never followed to another host.
     *
     * @param int $timeout Request timeout in seconds.
     * @return array Options for {@see \curl::setopt()}.
     */
    protected function get_default_curl_options(int $timeout): array {
        return [
            'CURLOPT_TIMEOUT' => $timeout,
            'CURLOPT_CONNECTTIMEOUT' => $this->connecttimeout,
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_FOLLOWLOCATION' => false,
            'CURLOPT_SSL_VERIFYPEER' => true,
            'CURLOPT_SSL_VERIFYHOST' => 2,
        ];
    }

    /**
     * Create a configured curl instance with common options.
     *
     * @param int $timeout Request timeout in seconds.
     * @return \curl The configured curl instance.
     */
    private function create_curl(int $timeout): \curl {
        $curl = new \curl();
        $curl->setopt($this->get_default_curl_options($timeout));
        return $curl;
    }

    /**
     * Resolve the namespace to use for file operations.
     *
     * @param string|null $namespace Optional namespace override.
     * @return string The resolved namespace.
     */
    private function resolve_namespace(?string $namespace): string {
        global $CFG;
        require_once($CFG->dirroot . '/local/dixeo/lib.php');
        return $namespace ?? \local_dixeo_get_configured_namespace();
    }

    /**
     * Check if the API is configured and reachable.
     *
     * @return bool True if the API is configured with a key.
     */
    public function is_configured(): bool {
        return !empty($this->apikey) && !empty($this->baseurl);
    }

    /**
     * Get the configured API base URL.
     *
     * @return string The API base URL.
     */
    public function get_base_url(): string {
        return $this->baseurl;
    }

    /**
     * Send a DELETE request to the API.
     *
     * @param string $endpoint The API endpoint.
     * @param array $queryparams Optional query parameters.
     * @return array The decoded response data.
     * @throws api_exception If the request fails.
     */
    public function delete(string $endpoint, array $queryparams = []): array {
        return $this->request('DELETE', $endpoint, $queryparams);
    }

    /**
     * Upload files to Dixeo.
     *
     * @param string $courseid The course ID (used for identification).
     * @param array $files Array of stored_file and/or {@see file_upload_part} items to upload.
     * @param string|null $namespace Optional namespace override.
     * @param callable|null $uploadprogress Optional callback (float $percent0to100, int $uploadedbytes, int $uploadtotalbytes)
     *     for outbound upload progress (throttled). Extra args are 0 when unknown.
     * @param bool $finalchunk When false, the server treats this upload as an intermediate chunk
     *     of a multi-part sync and only appends the supplied files without pruning anything that
     *     is no longer part of the course. Defaults to true (single-call behaviour).
     * @param array|null $expectedfiles When $finalchunk is true and the sync was split across several chunks,
     *     this should be the full manifest of expected files for the course (each row: hash, filename).
     *     Supplied to the server so it knows which older files to drop and which prior chunk uploads to index.
     *     Ignored when $finalchunk is false, and unnecessary on a single-call sync.
     * @param int|null $expectedfilescount Total number of files expected after this sync. Sent on every chunk.
     * @return array The API response data.
     * @throws api_exception If the upload fails.
     */
    public function upload_files(
        string $courseid,
        array $files,
        ?string $namespace = null,
        ?callable $uploadprogress = null,
        bool $finalchunk = true,
        ?array $expectedfiles = null,
        ?int $expectedfilescount = null
    ): array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $this->validate_configuration();

        if (empty($files)) {
            return ['uploaded' => 0, 'files' => []];
        }

        $namespace = $this->resolve_namespace($namespace);

        $curl = $this->create_curl(300); // 5 minutes for file uploads.
        $url = rtrim($this->baseurl, '/') . '/v1/files';

        // Headers for multipart upload - Content-Type set automatically by curl.
        $curl->setHeader([
            'X-API-Key: ' . $this->apikey,
            'Accept: application/json',
            'User-Agent: local_dixeo/1.0 (Moodle Plugin)',
        ]);

        // Build multipart form data.
        $postdata = [
            'courseId' => $courseid,
            'namespace' => $namespace,
            'finalChunk' => $finalchunk ? 'true' : 'false',
        ];

        if ($finalchunk && $expectedfiles !== null && $expectedfiles !== []) {
            $encoded = json_encode($expectedfiles);
            if ($encoded === false) {
                throw new api_exception(
                    'invalid_payload',
                    'Failed to encode expected file manifest as JSON: ' . json_last_error_msg(),
                    0
                );
            }
            $postdata['expectedFiles'] = $encoded;
        }

        if ($expectedfilescount !== null && $expectedfilescount >= 0) {
            $postdata['expectedFilesCount'] = (string) $expectedfilescount;
        }

        // Add files to the request (sequential multipart field names).
        $requestdir = make_request_directory();
        $tempfiles = [];
        $payloadbytes = 0;
        $partindex = 0;
        foreach ($files as $file) {
            if ($file instanceof \stored_file) {
                // Create temp file for upload since curl needs a file path.
                $temppath = $requestdir . '/dixeo_upload_' . uniqid() . '_' . $file->get_filename();
                $file->copy_content_to($temppath);
                $tempfiles[] = $temppath;

                $postdata["files[$partindex]"] = new \CURLFile(
                    $temppath,
                    $file->get_mimetype(),
                    $file->get_filename()
                );
                $payloadbytes += (int) $file->get_filesize();
                $partindex++;
            } else if ($file instanceof file_upload_part) {
                $temppath = $file->path;
                if (!is_readable($temppath)) {
                    continue;
                }
                $postdata["files[$partindex]"] = new \CURLFile(
                    $temppath,
                    $file->mimetype,
                    $file->filename
                );
                $size = filesize($temppath);
                $payloadbytes += $size !== false ? (int) $size : 0;
                if ($file->deleteafterupload) {
                    $tempfiles[] = $temppath;
                }
                $partindex++;
            }
        }

        if ($partindex === 0) {
            return ['uploaded' => 0, 'files' => []];
        }

        // Estimated multipart body size when libcurl does not report upload total.
        $estimateduploadtotal = $payloadbytes + max(4096, (int) round($payloadbytes * 0.08));

        $curlextra = [];
        if ($uploadprogress !== null && defined('CURLOPT_XFERINFOFUNCTION')) {
            $throttle = (object) [
                'lastemit' => microtime(true),
                'lastpct' => -1.0,
            ];
            $curlextra['CURLOPT_NOPROGRESS'] = false;
            $curlextra['CURLOPT_XFERINFOFUNCTION'] = static function (
                $ch,
                $dltotal,
                $dlnow,
                $ultotal,
                $ulnow
            ) use (
                $uploadprogress,
                $estimateduploadtotal,
                $throttle
            ): int {
                unset($ch, $dltotal, $dlnow);
                $ulnow = (int) $ulnow;
                $ultotal = (int) $ultotal;

                if ($ulnow <= 0) {
                    return 0;
                }

                if ($ultotal > 0) {
                    $pct = min(99.0, ($ulnow / $ultotal) * 100.0);
                } else if ($estimateduploadtotal > 0) {
                    $pct = min(99.0, ($ulnow / $estimateduploadtotal) * 100.0);
                } else {
                    return 0;
                }

                $now = microtime(true);
                $delta = abs($pct - $throttle->lastpct);
                // Emit more often so Moodle file-sync polls can show smoother 0–100% during upload.
                if ($delta < 0.2 && ($now - $throttle->lastemit) < 0.1 && $pct < 99.0) {
                    return 0;
                }

                $throttle->lastemit = $now;
                $throttle->lastpct = $pct;
                $totalbytes = $ultotal > 0 ? $ultotal : $estimateduploadtotal;
                $uploadprogress($pct, $ulnow, $totalbytes);

                return 0;
            };
        }

        try {
            $response = $curl->post($url, $postdata, $curlextra);

            $errno = $curl->get_errno();
            if ($errno) {
                throw new api_exception(
                    'connection_error',
                    'Failed to upload files to Dixeo API: ' . $curl->error,
                    0,
                    ['curl_errno' => $errno]
                );
            }

            $parsed = $this->parse_response($response, $curl->get_info());
            if ($uploadprogress !== null) {
                $uploadprogress(100.0, $estimateduploadtotal, $estimateduploadtotal);
            }

            return $parsed;
        } finally {
            // Clean up temp files.
            foreach ($tempfiles as $temppath) {
                if (file_exists($temppath)) {
                    @unlink($temppath);
                }
            }
        }
    }

    /**
     * Get the file sync status for a course.
     *
     * @param string $courseid The course ID.
     * @param string|null $namespace Optional namespace override.
     * @return array Status data with keys: status, file_count, synced_count, progress.
     * @throws api_exception If the request fails.
     */
    public function get_files_status(string $courseid, ?string $namespace = null): array {
        $namespace = $this->resolve_namespace($namespace);

        return $this->get('/v1/files/status', [
            'courseId' => $courseid,
            'namespace' => $namespace,
        ]);
    }

    /**
     * List files for a course stored in Dixeo.
     *
     * @param string $courseid The course ID.
     * @param string|null $namespace Optional namespace override.
     * @return array List of files.
     * @throws api_exception If the request fails.
     */
    public function list_files(string $courseid, ?string $namespace = null): array {
        $namespace = $this->resolve_namespace($namespace);

        return $this->get('/v1/files', [
            'courseId' => $courseid,
            'namespace' => $namespace,
        ]);
    }

    /**
     * Delete all files for a course from Dixeo.
     *
     * @param string $courseid The course ID.
     * @param string|null $namespace Optional namespace override.
     * @return array Response data.
     * @throws api_exception If the request fails.
     */
    public function delete_files(string $courseid, ?string $namespace = null): array {
        $namespace = $this->resolve_namespace($namespace);

        return $this->delete('/v1/files', [
            'courseId' => $courseid,
            'namespace' => $namespace,
        ]);
    }
}
