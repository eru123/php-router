<?php

namespace eru123\router;

use Throwable;

class Builtin
{
    public static function cors()
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    }

    public static function parse_json_body($state)
    {
        $state->json = json_decode(file_get_contents('php://input'), true);
    }

    public static function parse_query($state)
    {
        $state->query = $_GET;
    }

    public static function parse_body($state)
    {
        $state->body = $_POST ?: json_decode(file_get_contents('php://input'), true);
    }

    public static function parse_xml_body($state)
    {
        if (isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] === 'application/xml') {
            $state->body = simplexml_load_string(file_get_contents('php://input'));
        }
    }

    public static function remove_header_ads()
    {
        header_remove('X-Powered-By');
        header_remove('Server');
    }

    public static function response($res, $state)
    {
        if (is_array($res) || is_object($res)) {
            header('Content-Type: application/json');
            if ($state->is_debug && is_array($res)) {
                $res['debug'] = $state->debug;
            }
            print json_encode($res);
            exit;
        }

        if (is_string($res) && strpos($res, '<?xml') === 0) {
            header('Content-Type: application/xml');
            print $res;
            exit;
        }

        print $res;
        exit;
    }

    public static function error($e, RouteState $state)
    {   
        http_response_code($e->getCode() ?? 500);
        return static::response([
            'error' => $e->getMessage()
        ], $state);
    }
}