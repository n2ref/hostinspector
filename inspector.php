<?php

if (PHP_SAPI === 'cli') {
    require_once 'classes/Inspector.php';
    require_once 'classes/Tools.php';

    $options = getopt('sh', array(
        'silent',
        'help',
    ));


    if ( ! isset($options['h']) && ! isset($options['help'])) {
        $silent = isset($options['silent']) ? true : isset($options['s']);


        try {
            $inspector = new Inspector(__DIR__ . '/conf.ini');
            $config    = $inspector->getConfig();
            $inspector->setSilent($silent);

            $problems_http_inside  = $inspector->checkHttpInside();
            $problems_http_outside = $inspector->checkHttpOutside();


            if ( ! empty($config['mail']) && ! empty($config['admin_email']) &&
                ( ! empty($problems_http_inside) || ! empty($problems_http_outside))
            ) {
                $report_message = '';

                if ( ! empty($problems_http_inside)) {
                    $report_message .= '<h4>HTTP inside problems</h4>';
                    $report_message .= '<ol>';
                    foreach ($problems_http_inside as $problem) {
                        $host  = htmlspecialchars($problem['host']);
                        $error = htmlspecialchars($problem['error']);
                        $report_message .= "<li><b>{$host}</b> {$error}</li>";
                    }
                    $report_message .= '</ol>';
                }

                if ( ! empty($problems_http_outside)) {
                    $report_message .= '<h4>HTTP outside problems</h4>';
                    $report_message .= '<ol>';
                    foreach ($problems_http_outside as $problem) {
                        $host  = htmlspecialchars($problem['host']);
                        $error = htmlspecialchars($problem['error']);
                        $report_message .= "<li><b>{$host}</b> {$error}</li>";
                    }
                    $report_message .= '</ol>';
                }

                $is_send = Tools::sendMail(
                    $config['admin_email'],
                    ! empty($config['email_subject']) ? $config['email_subject'] : 'Detected suspicious files',
                    $report_message,
                    $config['mail']
                );

                if ( ! $is_send) {
                    throw new Exception('Error send email');
                }
                echo PHP_EOL;
            }

        } catch (Exception $e) {
            echo $e->getMessage() . PHP_EOL;
        }

    } else {
        echo implode(PHP_EOL, array(
            'Host inspector',
            'Usage: php inspector.php [OPTIONS]',
            'Optional arguments:',
            "\t-s\t--silent\tSilent mode",
            "\t-h\t--help\t\tHelp info",
            "Examples of usage:",
            "php inspector.php -s",
        )) . PHP_EOL;
    }

    echo 'Done.' . PHP_EOL;

} else {
    echo 'Bad SAPI! Need cli.';
}