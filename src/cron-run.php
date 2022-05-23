<?php
declare(strict_types=1);

namespace PingMonitoringTool;

use PingMonitoringTool\Mailer\ChainMailer;
use PingMonitoringTool\Mailer\PhpMailer;
use PingMonitoringTool\Mailer\SmtpMailer;

error_reporting(1);
@ini_set('display_errors', "1");

$root_dir = realpath(__DIR__.'/');
define('ROOT', $root_dir);

require_once $root_dir . '/../vendor/autoload.php';

try {
    $http_client = new HttpClient();
    $repository = new Repository();
    $logger = new Logger('prod', 1, $repository);
    $error_handler = new ErrorHandler($logger);
    (new Setup($error_handler))->init();

    $mailServers = $repository->getMailerServers();
    $mailer = new ChainMailer(
        $error_handler,
        new PhpMailer($mailServers['base']),
        new SmtpMailer($mailServers['reserve'])
    );

    $monitor = new Monitoring($error_handler,$http_client);

    $controller = new Controller($monitor, $repository, $mailer, $error_handler, $logger);
    $controller->run();

    //$printer = new PrintDb();
    //$printer->printConsole('logs');

} catch (\Exception $e) {
    echo $e->getMessage() . PHP_EOL;
}
