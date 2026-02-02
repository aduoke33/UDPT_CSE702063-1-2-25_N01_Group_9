<?php
/**
 * IDE Helper file for Laravel
 * 
 * This file helps IDE understand Laravel's magic methods and facades.
 * The actual Laravel classes are installed via Composer inside Docker container.
 * 
 * To eliminate IDE errors locally, run: composer install
 * Or ignore these warnings as they don't affect the application in Docker.
 */

// This is a placeholder to indicate that vendor directory is managed by Docker
// The application works correctly when deployed via Docker containers

namespace {
    // Suppress IDE warnings for Laravel's global helper functions
    if (false) {
        function env($key, $default = null) { return $default; }
        function config($key = null, $default = null) { return $default; }
        function app($abstract = null, array $parameters = []) { return null; }
        function view($view = null, $data = [], $mergeData = []) { return null; }
        function redirect($to = null, $status = 302, $headers = [], $secure = null) { return null; }
        function route($name, $parameters = [], $absolute = true) { return ''; }
        function url($path = null, $parameters = [], $secure = null) { return ''; }
        function asset($path, $secure = null) { return ''; }
        function session($key = null, $default = null) { return null; }
        function request($key = null, $default = null) { return null; }
        function response($content = '', $status = 200, array $headers = []) { return null; }
        function abort($code, $message = '', array $headers = []) {}
        function old($key = null, $default = null) { return $default; }
        function csrf_token() { return ''; }
        function csrf_field() { return ''; }
        function method_field($method) { return ''; }
        function auth($guard = null) { return null; }
        function back($status = 302, $headers = [], $fallback = false) { return null; }
        function cache($key = null, $default = null) { return null; }
        function collect($value = null) { return null; }
        function event(...$args) { return null; }
        function now($tz = null) { return null; }
        function storage_path($path = '') { return ''; }
        function public_path($path = '') { return ''; }
        function base_path($path = '') { return ''; }
        function resource_path($path = '') { return ''; }
        function database_path($path = '') { return ''; }
        function lang_path($path = '') { return ''; }
    }
}
