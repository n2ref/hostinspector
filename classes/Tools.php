<?php

/**
 * Class Tools
 */
class Tools {


    /**
     * Parses INI file adding extends functionality via ":base" postfix on namespace.
     *
     * @param  string $filename
     * @param  string $section
     * @return array
     * @throws Exception
     */
    public static function getConfig($filename, $section = null) {

        $p_ini  = parse_ini_file($filename, true);
        $config = array();

        foreach ($p_ini as $namespace => $properties) {
            if (is_array($properties)) {
                @list($name, $extends) = explode(':', $namespace);
                $name = trim($name);
                $extends = trim($extends);
                // create namespace if necessary
                if (!isset($config[$name])) $config[$name] = array();
                // inherit base namespace
                if (isset($p_ini[$extends])) {
                    foreach ($p_ini[$extends] as $key => $val)
                        $config[$name] = self::processKey($config[$name], $key, $val);;
                }
                // overwrite / set current namespace values
                foreach ($properties as $key => $val)
                    $config[$name] = self::processKey($config[$name], $key, $val);
            } else {
                if ( ! isset($config['global'])) {
                    $config['global'] = array();
                }
                $parsed_key = self::processKey(array(), $namespace, $properties);
                $config['global'] = self::array_merge_recursive_distinct($config['global'], $parsed_key);
            }
        }
        if ($section) {
            if (isset($config[$section])) {
                return $config[$section];
            } else {
                throw new Exception("Section '{$section}' not found");
            }
        } else {
            if (count($config) === 1 && isset($config['global'])) {
                return $config['global'];
            }

            return $config;
        }
    }


    /**
     * Добавление в лог текста
     * @param mixed $file
     * @param mixed $text
     * @return void
     */
    public static function log($file, $text) {

        $f = fopen($file, 'a');
        if ( ! is_scalar($text)) {
            ob_start();
            print_r($text);
            $text = ob_get_clean();
        }
        fwrite($f, $text . chr(10) . chr(13));
        fclose($f);
    }


    /**
     * Отправка письма
     * @param string $to Email поучателя. Могут содержать несколько адресов разделенных зяпятой.
     * @param string $subject Тема письма
     * @param string $message Тело письма
     * @param array $options
     *      Опциональные значения для письма.
     *      Может содержать такие ключи как
     *      charset - Кодировка сообщения. По умолчанию содержет - utf-8
     *      content_type - Тип сожержимого. По умолчанию содержет - text/html
     *      from - Адрес отправителя. По умолчанию содержет - noreply@localhost
     *      cc - Адреса вторичных получателей письма, к которым направляется копия. По умолчанию содержет - false
     *      bcc - Адреса получателей письма, чьи адреса не следует показывать другим получателям. По умолчанию содержет - false
     *      method - Метод отправки. Может принимать значения smtp и mail. По умолчанию содержет - mail
     *      smtp_host - Хост для smtp отправки. По умолчанию содержет - localhost
     *      smtp_port - Порт для smtp отправки. По умолчанию содержет - 25
     *      smtp_auth - Признак аутентификации для smtp отправки. По умолчанию содержет - false
     *      smtp_secure - Название шифрования, TLS или SSL. По умолчанию без шифрования
     *      smtp_user - Пользователь при использовании аутентификации для smtp отправки. По умолчанию содержет пустую строку
     *      smtp_pass - Пароль при использовании аутентификации для smtp отправки. По умолчанию содержет пустую строку
     *      smtp_timeout - Таймаут для smtp отправки. По умолчанию содержет - 15
     * @return bool Успешна либо нет отправка сообщения
     * @throws Exception Исключение с текстом произошедшей ошибки
     */
    public static function sendMail($to, $subject, $message, array $options = array()) {

        $options['charset']      = isset($options['charset']) && trim($options['charset']) != '' ? $options['charset'] : 'utf-8';
        $options['content_type'] = isset($options['content_type']) && trim($options['content_type']) != '' ? $options['content_type'] : 'text/html';
        $options['server_name']  = isset($options['server_name']) && trim($options['server_name']) != '' ? $options['server_name'] : 'localhost';
        $options['from']         = isset($options['from']) && trim($options['from']) != '' ? $options['from'] : 'noreply@' . $options['server_name'];
        $options['cc']           = isset($options['cc']) && trim($options['cc']) != '' ? $options['cc'] : false;
        $options['bcc']          = isset($options['bcc']) && trim($options['bcc']) != '' ? $options['bcc'] : false;
        $subject                 = $subject != null && trim($subject) != '' ? $subject : '(No Subject)';


        $headers = array(
            "MIME-Version: 1.0",
            "Content-type: {$options['content_type']}; charset={$options['charset']}",
            "From: {$options['from']}",
            "Content-Transfer-Encoding: base64",
            "X-Mailer: PHP/" . phpversion()
        );

        if ($options['cc']) $headers[] = $options['cc'];
        if ($options['bcc']) $headers[] = $options['bcc'];


        if (isset($options['method']) && strtoupper($options['method']) == 'SMTP') {

            $options['smtp_host']    = isset($options['smtp_host']) && trim($options['smtp_host']) != '' ? $options['smtp_host'] : $options['server_name'];
            $options['smtp_port']    = isset($options['smtp_port']) && (int)($options['smtp_port']) > 0  ? $options['smtp_port'] : 25;
            $options['smtp_secure']  = isset($options['smtp_secure']) ? $options['smtp_secure'] : '';
            $options['smtp_auth']    = isset($options['smtp_auth']) ? (bool)$options['smtp_auth'] : false;
            $options['smtp_user']    = isset($options['smtp_user']) ? $options['smtp_user'] : '';
            $options['smtp_pass']    = isset($options['smtp_pass']) ? $options['smtp_pass'] : '';
            $options['smtp_timeout'] = isset($options['smtp_timeout']) && (int)($options['smtp_timeout']) > 0 ? $options['smtp_timeout'] : 15;

            $headers[] = "Subject: {$subject}";
            $headers[] = "To: <" . implode('>, <', explode(',', $to)) . ">";
            $headers[] = "\r\n";
            $headers[] = wordwrap(base64_encode($message), 75, "\n", true);
            $headers[] = "\r\n";

            $recipients = explode(',', $to);
            $errno      = '';
            $errstr     = '';


            if (strtoupper($options['smtp_secure']) == 'SSL') {
                $options['smtp_host'] = 'ssl://' . preg_replace('~^([a-zA-Z0-9]+:|)//~', '', $options['smtp_host']);
            }


            if ( ! ($socket = fsockopen($options['smtp_host'], $options['smtp_port'], $errno, $errstr, $options['smtp_timeout']))) {
                throw new Exception("Error connecting to '{$options['smtp_host']}': {$errno} - {$errstr}");
            }

            if (substr(PHP_OS, 0, 3) != "WIN") socket_set_timeout($socket, $options['smtp_timeout'], 0);

            self::serverParse($socket, '220');

            fwrite($socket, 'EHLO ' . $options['smtp_host'] . "\r\n");
            self::serverParse($socket, '250');

            if (strtoupper($options['smtp_secure']) == 'TLS') {
                fwrite($socket, 'STARTTLS' . "\r\n");
                self::serverParse($socket, '250');
            }


            if ($options['smtp_auth']) {
                fwrite($socket, 'AUTH LOGIN' . "\r\n");
                self::serverParse($socket, '334');

                fwrite($socket, base64_encode($options['smtp_user']) . "\r\n");
                self::serverParse($socket, '334');

                fwrite($socket, base64_encode($options['smtp_pass']) . "\r\n");
                self::serverParse($socket, '235');
            }

            fwrite($socket, "MAIL FROM: <{$options['from']}>\r\n");
            self::serverParse($socket, '250');


            foreach ($recipients as $email) {
                fwrite($socket, 'RCPT TO: <' . $email . '>' . "\r\n");
                self::serverParse($socket, '250');
            }

            fwrite($socket, 'DATA' . "\r\n");
            self::serverParse($socket, '354');

            fwrite($socket, implode("\r\n", $headers));
            fwrite($socket, '.' . "\r\n");
            self::serverParse($socket, '250');

            fwrite($socket, 'QUIT' . "\r\n");
            fclose($socket);

            return true;

        } else {

            return mail($to, $subject, wordwrap(base64_encode($message), 75, "\n", true), implode("\r\n", $headers));
        }
    }


    /**
     * Получение ответа от сервера
     * @param resource $socket
     * @param string $expected_response
     * @throws Exception
     */
    private static function serverParse($socket, $expected_response) {

        $server_response = '';
        while (substr($server_response, 3, 1) != ' ') {
            if ( ! ($server_response = fgets($socket, 256)))  {
                throw new Exception('Error while fetching server response codes.');
            }
        }
        if (substr($server_response, 0, 3) != $expected_response) {
            throw new Exception("Unable to send e-mail: {$server_response}");
        }
    }


    /**
     * array_merge_recursive does indeed merge arrays, but it converts values with duplicate
     * keys to arrays rather than overwriting the value in the first array with the duplicate
     * value in the second array, as array_merge does. I.e., with array_merge_recursive,
     * this happens (documented behavior):
     *
     * array_merge_recursive(array('key' => 'org value'), array('key' => 'new value'));
     *     => array('key' => array('org value', 'new value'));
     *
     * array_merge_recursive_distinct does not change the datatypes of the values in the arrays.
     * Matching keys' values in the second array overwrite those in the first array, as is the
     * case with array_merge, i.e.:
     *
     * array_merge_recursive_distinct(array('key' => 'org value'), array('key' => 'new value'));
     *     => array('key' => array('new value'));
     *
     * Parameters are passed by reference, though only for performance reasons. They're not
     * altered by this function.
     *
     * @param array $array1
     * @param array $array2
     * @return array
     * @author Daniel <daniel (at) danielsmedegaardbuus (dot) dk>
     * @author Gabriel Sobrinho <gabriel (dot) sobrinho (at) gmail (dot) com>
     */
    private static function array_merge_recursive_distinct (array &$array1, array &$array2) {
        $merged = $array1;

        foreach ( $array2 as $key => &$value ) {
            if ( is_array ( $value ) && isset ( $merged [$key] ) && is_array ( $merged [$key] ) ) {
                $merged [$key] = self::array_merge_recursive_distinct ( $merged [$key], $value );
            } else {
                $merged [$key] = $value;
            }
        }

        return $merged;
    }


    /**
     * Процесс разделения на субсекции ключей конфига
     * @param array $config
     * @param string $key
     * @param string $val
     * @return array
     */
    private static function processKey($config, $key, $val) {
        $nest_separator = '.';
        if (strpos($key, $nest_separator) !== false) {
            $pieces = explode($nest_separator, $key, 2);
            if (strlen($pieces[0]) && strlen($pieces[1])) {
                if ( ! isset($config[$pieces[0]])) {
                    if ($pieces[0] === '0' && ! empty($config)) {
                        // convert the current values in $config into an array
                        $config = array($pieces[0] => $config);
                    } else {
                        $config[$pieces[0]] = array();
                    }
                }
                $config[$pieces[0]] = self::processKey($config[$pieces[0]], $pieces[1], $val);
            }
        } else {
            $config[$key] = $val;
        }
        return $config;
    }
}