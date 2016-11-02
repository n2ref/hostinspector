<?php

require_once 'Tools.php';
require_once 'Curl.php';


/**
 * Class Inspector
 */
class Inspector {

    private $hosts     = array();
    private $is_silent = false;
    private $config;


    /**
     * Inspector constructor.
     * @param string $config_file
     * @throws Exception
     */
    public function __construct($config_file) {

        if ( ! file_exists($config_file)) {
            throw new Exception("Not found configuration file '{$config_file}'");
        }

        $this->config = Tools::getConfig($config_file);

        if (empty($this->config['host'])) {
            throw new Exception("Empty parameter 'host' in configuration file '{$config_file}'");
        }

        foreach ($this->config['host'] as $method => $hosts) {
            switch ($method) {
                case 'http':
                    if ( ! empty($hosts['inside'])) {
                        foreach ($hosts['inside'] as $host) {
                            if (trim($host) != '') {
                                $this->hosts['http']['inside'][] = $host;
                            }
                        }
                    }
                    if ( ! empty($hosts['outside'])) {
                        foreach ($hosts['outside'] as $host) {
                            if (trim($host) != '') {
                                $this->hosts['http']['outside'][] = $host;
                            }
                        }
                    }
                    break;
            }
        }

        if (empty($this->hosts)) {
            throw new Exception("Incorrect parameter 'host' in configuration file '{$config_file}'");
        }
    }


    /**
     * @return array
     */
    public function getConfig() {
        return $this->config;
    }


    /**
     * @param bool $is_silent
     * @return array
     */
    public function setSilent($is_silent) {
        $this->is_silent = $is_silent;
    }


    /**
     * @return array
     */
    public function checkHttpInside() {

        if ( ! empty($this->config['host']) &&
             ! empty($this->config['host']['http']) &&
             ! empty($this->config['host']['http']['timeout']) &&
             is_numeric($this->config['host']['http']['timeout']) &&
             $this->config['host']['http']['timeout'] > 0
        ) {
            Curl::setTimeout($this->config['host']['http']['timeout']);
        }

        $show_problem_inside = false;
        $problem_hosts       = array();

        if ( ! empty($this->hosts['http']) && ! empty($this->hosts['http']['inside'])) {
            foreach ($this->hosts['http']['inside'] as $host) {
                $response = Curl::get($host);

                if (isset($response['error']) ||
                    ! isset($response['info']['http_code']) ||
                    $response['info']['http_code'] == 404 ||
                    $response['info']['http_code'] >= 500
                ) {
                    $error = isset($response['error'])
                        ? $response['error']
                        : 'Response HTTP code: ' . $response['info']['http_code'];

                    $problem_hosts[] = array(
                        'host'  => $host,
                        'error' => $error,
                    );

                    if ( ! $this->is_silent) {
                        if ( ! $show_problem_inside) {
                            $show_problem_inside = true;
                            echo 'HTTP inside problems' . PHP_EOL;
                            echo '====================' . PHP_EOL;
                        }
                        echo $host . ' ' . $error . PHP_EOL;
                    }
                }
            }
        }

        return $problem_hosts;
    }


    /**
     * @return array
     */
    public function checkHttpOutside() {

        if ( ! empty($this->config['host']) &&
             ! empty($this->config['host']['http']) &&
             ! empty($this->config['host']['http']['timeout']) &&
             is_numeric($this->config['host']['http']['timeout']) &&
             $this->config['host']['http']['timeout'] > 0
        ) {
            Curl::setTimeout($this->config['host']['http']['timeout']);
        }

        $show_problem_outside = false;
        $problem_hosts        = array();

        if ( ! empty($this->hosts['http']) && ! empty($this->hosts['http']['outside'])) {
            foreach ($this->hosts['http']['outside'] as $host) {
                $headers = array(
                    'Accept: application/json'
                );
                $params = array(
                    'host'      => $host,
                    'max_nodes' => 5
                );
                $response        = Curl::get('https://check-host.net/check-http', $params, $headers);
                $response_decode = array();
                if ( ! empty($response['content'])) {
                    $response_decode = json_decode($response['content'], true);
                }

                if ( ! empty($response_decode) &&
                    ! empty($response_decode['ok']) &&
                    ! empty($response_decode['request_id']) &&
                    ! empty($response_decode['nodes'])
                ) {
                    sleep(1);

                    $url_check             = 'https://check-host.net/check-result/'.$response_decode['request_id'];
                    $response_check        = Curl::get($url_check, array(), $headers);
                    $response_check_decode = array();
                    if ( ! empty($response['content'])) {
                        $response_check_decode = json_decode($response_check['content'], true);
                    }


                    if ( ! empty($response_check_decode)) {
                        $error_count = 0;
                        $errors = array();

                        foreach ($response_decode['nodes'] as $key => $node) {
                            if ( ! empty($response_check_decode[$key]) &&
                                ! empty($response_check_decode[$key][0])
                            ) {
                                $place_name = ! empty($node[1]) && ! empty($node[2])
                                    ? $node[1] . ' ' . $node[2]
                                    : '';

                                if (isset($response_check_decode[$key][0][0])) {
                                    if ($response_check_decode[$key][0][0]) {
                                        // error http codes
                                        if (isset($response_check_decode[$key][0][3]) &&
                                            ($response_check_decode[$key][0][3] == 404 ||
                                            $response_check_decode[$key][0][3] >= 500)
                                        ) {

                                            $errors[] =  $place_name . ' - Response HTTP code: ' . $response_check_decode[$key][0][3];
                                        }

                                    // error request
                                    } elseif ( ! isset($response_check_decode[$key][0][3]) ||
                                             (isset($response_check_decode[$key][0][3]) &&
                                              ($response_check_decode[$key][0][3] == 404 ||
                                               $response_check_decode[$key][0][3] >= 500))
                                    ) {
                                        $error_count++;

                                        if ($error_count >= 2) {
                                            $error = !empty($response_check_decode[$key][0][2])
                                                ? $response_check_decode[$key][0][2]
                                                : '';
                                            $error .= !empty($response_check_decode[$key][0][3])
                                                ? ' http code:' . $response_check_decode[$key][0][3]
                                                : '';

                                            $errors[] = $place_name . ' - ' . $error;
                                        }
                                    }
                                }
                            }
                        }


                        if ( ! empty($errors)) {
                            $problem_hosts[] = array(
                                'host'  => $host,
                                'error' => implode(', ', $errors),
                            );


                            if ( ! $this->is_silent) {
                                if ( ! $show_problem_outside) {
                                    $show_problem_outside = true;
                                    echo 'HTTP outside problems' . PHP_EOL;
                                    echo '=====================' . PHP_EOL;
                                }
                                echo $host . ' ' . implode(', ', $errors) . PHP_EOL;
                            }
                        }
                    }
                }
            }
        }

        return $problem_hosts;
    }
}