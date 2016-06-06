<?php

/**
 * Class Curl
 */
class Curl {

    private static $timeout = 4;

    /**
     * @param int $timeout
     */
    public static function setTimeout($timeout) {
        self::$timeout = $timeout;
    }


    /**
     * @param string $url
     * @param array  $params
     * @param array  $headers
     * @return string
     */
    public static function get($url, $params = array(), $headers = array()) {

        return self::request('get', $url, $params, $headers);
    }


    /**
     * @param string $url
     * @param array  $params
     * @param array  $headers
     * @return string
     */
    public static function post($url, $params = array(), $headers = array()) {

        return self::request('post', $url, $params, $headers);
    }


    /**
     * @param string $method
     * @param string $url
     * @param array  $params
     * @param array  $headers
     * @return string
     */
    private static function request($method, $url, $params = array(), $headers = array()) {

        $ch = curl_init();

        if ( ! empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        if ($method == 'post') {
            curl_setopt($ch, CURLOPT_POST,       true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        } else {
            $url .= ! empty($params) ? '?' . http_build_query($params) : '';
        }

        curl_setopt($ch, CURLOPT_URL,            $url);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::$timeout);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT,    true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $content  = curl_exec($ch);
        $info     = curl_getinfo($ch);

        $response = array(
            'content' => $content,
            'info'    => $info,
        );


        if (curl_errno($ch)) {
            $response['error'] = curl_error($ch);
        }

        curl_close($ch);

        return $response;
    }
}