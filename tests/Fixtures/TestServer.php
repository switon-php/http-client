<?php

declare(strict_types=1);

/**
 * Test HTTP server for HTTP Client integration tests.
 *
 * This script is used with PHP's built-in server (php -S) to provide
 * a local test server that mimics httpbin.cn functionality.
 *
 * Supported endpoints:
 * - GET /get - Returns request information (headers, query params)
 * - POST /post - Returns request information (headers, body)
 * - PUT /put - Returns request information (headers, body)
 * - PATCH /patch - Returns request information (headers, body)
 * - DELETE /delete - Returns request information (headers)
 * - HEAD /get - Returns empty response body, only headers
 */

// Get request method
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$parsed = parse_url($uri);
$path = $parsed['path'] ?? '/';

// Read request body
$body = null;
if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
    $body = file_get_contents('php://input');
    // Convert empty string to null for JSON consistency
    if ($body === '') {
        $body = null;
    }
}

// Get all request headers
$headers = [];
foreach ($_SERVER as $key => $value) {
    if (str_starts_with($key, 'HTTP_')) {
        $headerName = str_replace('_', '-', substr($key, 5));
        $headerName = strtolower($headerName);
        // Convert to title case: accept-encoding -> Accept-Encoding
        $headerName = str_replace(' ', '-', ucwords(str_replace('-', ' ', $headerName)));
        $headers[$headerName] = $value;
    } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
        $headerName = str_replace('_', '-', $key);
        $headerName = strtolower($headerName);
        $headerName = str_replace(' ', '-', ucwords(str_replace('-', ' ', $headerName)));
        $headers[$headerName] = $value;
    }
}

// Get query parameters
$query = [];
if (!empty($_SERVER['QUERY_STRING'])) {
    parse_str($_SERVER['QUERY_STRING'], $query);
}

// Build response data
$responseData = [
    'method' => $method,
    'url' => $_SERVER['REQUEST_URI'] ?? '/',
    'headers' => $headers,
    'query' => $query,
    'body' => $body,
];

// Handle different endpoints
switch ($path) {
    case '/get':
        if ($method === 'HEAD') {
            // HEAD request: return headers only, no body
            header('Content-Type: application/json');
            header('Content-Length: 0');
            http_response_code(200);
            exit;
        }
        // GET request: return full response
        header('Content-Type: application/json');
        echo json_encode($responseData, JSON_PRETTY_PRINT);
        break;

    case '/post':
        if ($method !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            echo json_encode(['error' => 'Method Not Allowed']);
            exit;
        }
        header('Content-Type: application/json');
        echo json_encode($responseData, JSON_PRETTY_PRINT);
        break;

    case '/put':
        if ($method !== 'PUT') {
            http_response_code(405);
            header('Allow: PUT');
            echo json_encode(['error' => 'Method Not Allowed']);
            exit;
        }
        header('Content-Type: application/json');
        echo json_encode($responseData, JSON_PRETTY_PRINT);
        break;

    case '/patch':
        if ($method !== 'PATCH') {
            http_response_code(405);
            header('Allow: PATCH');
            echo json_encode(['error' => 'Method Not Allowed']);
            exit;
        }
        header('Content-Type: application/json');
        echo json_encode($responseData, JSON_PRETTY_PRINT);
        break;

    case '/delete':
        if ($method !== 'DELETE') {
            http_response_code(405);
            header('Allow: DELETE');
            echo json_encode(['error' => 'Method Not Allowed']);
            exit;
        }
        header('Content-Type: application/json');
        echo json_encode($responseData, JSON_PRETTY_PRINT);
        break;

    default:
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Not Found', 'path' => $path]);
        break;
}
