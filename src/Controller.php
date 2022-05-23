<?php

declare(strict_types=1);

namespace PingMonitoringTool;

use PingMonitoringTool\Mailer\Mailer;

class Controller
{
    private Monitoring $monitor;
    private Repository $repository;
    private Mailer $mailer;
    private ErrorHandler $handler;
    private Logger $logger;

    public function __construct(
        Monitoring $monitor,
        Repository $repository,
        Mailer $mailer,
        ErrorHandler $handler,
        Logger $logger)
    {
        $this->monitor = $monitor;
        $this->repository = $repository;
        $this->mailer = $mailer;
        $this->handler = $handler;
        $this->logger = $logger;

        $tables = $this->repository->getTables();

        if (!in_array('monitoring', $tables)) {
            throw new \RuntimeException('Database not initialized.');
        }
    }

    public function run()
    {
        $this->logger->info('Начало выполнения программы');
        foreach ($this->getDomains() as $domain) {
            $this->logger->info('Проверяем: ' . $domain->getValue());
            $status = $this->monitor->ping($domain);
            if ($this->monitor->isOK($status) ) {
                $this->repository->setUp($domain, $status);
                $this->repository->writeStats($domain->getValue(), 1);
                $this->logger->info('OK: ' . $domain->getValue());
            } else {
                $this->logger->info('DOWN: ' . $domain->getValue());
                $this->repository->setDown($domain, $status);
                $this->repository->writeStats($domain->getValue(), 0);
            }

            if ($typeSendNotify = $this->repository->canSendNotify($domain)) {
                $this->logger->info("SEND $typeSendNotify: " . $domain->getValue());
                $this->mailer->setDomain($domain->getValue());
                $this->mailer->setTypeMessage($typeSendNotify);
                $this->mailer->setStatusObject($status);
                $this->mailer->send();
                $this->repository->setNotify($domain, $typeSendNotify);
            }

            if ($this->repository->canSendRepeatNotify($domain->getValue())) {
                $this->logger->info("SEND REPEAT: " . $domain->getValue());
                $this->mailer->setDomain($domain->getValue());
                $this->mailer->setTypeMessage('repeat');
                $this->mailer->setStatusObject($status);
                $this->mailer->send();
                $this->repository->setNotify($domain, 'falls');
            }


        }
        if ($dataWeekReport = $this->repository->getWeekReport()) {
            $this->mailer->setDataReport($dataWeekReport);
            if($this->mailer->sendReport()) {
                $this->repository->setSendWeekReport();
            }
        }

        $this->logger->info('Конец выполнения программы');
    }

    private function getDomains(): array
    {
        $domains = $domains_list = [];
        foreach ($this->repository->getMonitoringList() as $item) {
            $domains_list[] = $item->domain;
            $domains[] = new Domain($item->domain);
        }
        $this->logger->info('Загружен список доменов для проверки: ' . implode(', ', $domains_list));
        return $domains;
    }
}