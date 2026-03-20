<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk;

use OneStopMobile\PrintNodeSdk\Contracts\DecidesPrintDispatch;
use OneStopMobile\PrintNodeSdk\Contracts\ResolvesPrintTarget;
use OneStopMobile\PrintNodeSdk\Data\AccountData;
use OneStopMobile\PrintNodeSdk\Http\PrintNodeConnector;
use OneStopMobile\PrintNodeSdk\Printing\PrintManager;
use OneStopMobile\PrintNodeSdk\Printing\PrintManagerConfig;
use OneStopMobile\PrintNodeSdk\Resources\AccountResource;
use OneStopMobile\PrintNodeSdk\Resources\ComputersResource;
use OneStopMobile\PrintNodeSdk\Resources\DownloadsResource;
use OneStopMobile\PrintNodeSdk\Resources\MiscResource;
use OneStopMobile\PrintNodeSdk\Resources\PrintersResource;
use OneStopMobile\PrintNodeSdk\Resources\PrintJobsResource;
use OneStopMobile\PrintNodeSdk\Resources\ScalesResource;
use OneStopMobile\PrintNodeSdk\Resources\WebhooksResource;
use OneStopMobile\PrintNodeSdk\Resources\WhoAmIResource;
use Psr\Log\LoggerInterface;

final readonly class PrintNodeSdk
{
    private PrintNodeConnector $connector;

    public function __construct(
        private PrintNodeConfig $config,
    ) {
        $this->connector = new PrintNodeConnector($this->config);
    }

    public function connector(): PrintNodeConnector
    {
        return $this->connector;
    }

    public function computers(): ComputersResource
    {
        return new ComputersResource($this->connector);
    }

    public function whoAmI(): AccountData
    {
        return $this->whoAmIResource()->get();
    }

    public function whoAmIResource(): WhoAmIResource
    {
        return new WhoAmIResource($this->connector);
    }

    public function printers(): PrintersResource
    {
        return new PrintersResource($this->connector);
    }

    public function printJobs(): PrintJobsResource
    {
        return new PrintJobsResource($this->connector);
    }

    public function scales(): ScalesResource
    {
        return new ScalesResource($this->connector);
    }

    public function webhooks(): WebhooksResource
    {
        return new WebhooksResource($this->connector);
    }

    public function account(): AccountResource
    {
        return new AccountResource($this->connector);
    }

    public function downloads(): DownloadsResource
    {
        return new DownloadsResource($this->connector);
    }

    public function misc(): MiscResource
    {
        return new MiscResource($this->connector);
    }

    /**
     * @param  array<string, mixed>  $defaultOptions
     */
    public function printing(
        ?ResolvesPrintTarget $targetResolver = null,
        string $defaultTitle = 'Print job',
        string $defaultSource = 'PrintNode SDK',
        array $defaultOptions = [],
        ?string $defaultIdempotencyPrefix = null,
        ?DecidesPrintDispatch $dispatchPolicy = null,
        ?LoggerInterface $logger = null,
        bool $logSkipped = true,
        bool $logSuccess = false,
        bool $logFailures = true,
        bool $includeContentHashInLogs = false,
        bool $includeContentLengthInLogs = true,
    ): PrintManager {
        return $this->printingWithConfig(new PrintManagerConfig(
            targetResolver: $targetResolver,
            defaultTitle: $defaultTitle,
            defaultSource: $defaultSource,
            defaultOptions: $defaultOptions,
            defaultIdempotencyPrefix: $defaultIdempotencyPrefix,
            dispatchPolicy: $dispatchPolicy,
            logger: $logger,
            logSkipped: $logSkipped,
            logSuccess: $logSuccess,
            logFailures: $logFailures,
            includeContentHashInLogs: $includeContentHashInLogs,
            includeContentLengthInLogs: $includeContentLengthInLogs,
        ));
    }

    public function printingWithConfig(PrintManagerConfig $config): PrintManager
    {
        return new PrintManager(
            sdk: $this,
            targetResolver: $config->targetResolver,
            defaultTitle: $config->defaultTitle,
            defaultSource: $config->defaultSource,
            defaultOptions: $config->defaultOptions,
            defaultIdempotencyPrefix: $config->defaultIdempotencyPrefix,
            dispatchPolicy: $config->dispatchPolicy,
            logger: $config->logger,
            logSkipped: $config->logSkipped,
            logSuccess: $config->logSuccess,
            logFailures: $config->logFailures,
            includeContentHashInLogs: $config->includeContentHashInLogs,
            includeContentLengthInLogs: $config->includeContentLengthInLogs,
        );
    }
}
