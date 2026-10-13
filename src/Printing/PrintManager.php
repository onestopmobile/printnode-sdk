<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Printing;

use OneStopMobile\PrintNodeSdk\Contracts\DecidesPrintDispatch;
use OneStopMobile\PrintNodeSdk\Contracts\ResolvesPrintTarget;
use OneStopMobile\PrintNodeSdk\Enums\PrintContentType;
use OneStopMobile\PrintNodeSdk\Enums\PrintDispatchAction;
use OneStopMobile\PrintNodeSdk\Exceptions\DuplicatePrintJobException;
use OneStopMobile\PrintNodeSdk\Exceptions\IncompletePrintJobException;
use OneStopMobile\PrintNodeSdk\Exceptions\PrintDispatchBlockedException;
use OneStopMobile\PrintNodeSdk\Exceptions\UnresolvablePrintTargetException;
use OneStopMobile\PrintNodeSdk\Payloads\CreatePrintJobPayload;
use OneStopMobile\PrintNodeSdk\PrintNodeSdk;
use OneStopMobile\PrintNodeSdk\Values\ChildAccountContext;
use OneStopMobile\PrintNodeSdk\Values\CreatedPrintJobResult;
use Psr\Log\LoggerInterface;
use Throwable;
use UnexpectedValueException;

final readonly class PrintManager
{
    /**
     * @param  array<string, mixed>  $defaultOptions
     */
    public function __construct(
        private PrintNodeSdk $sdk,
        private ?ResolvesPrintTarget $targetResolver = null,
        private string $defaultTitle = 'Print job',
        private string $defaultSource = 'PrintNode SDK',
        private array $defaultOptions = [],
        private ?string $defaultIdempotencyPrefix = null,
        private ?DecidesPrintDispatch $dispatchPolicy = null,
        private ?LoggerInterface $logger = null,
        private bool $logSkipped = true,
        private bool $logSuccess = false,
        private bool $logFailures = true,
        private bool $includeContentHashInLogs = false,
        private bool $includeContentLengthInLogs = true,
    ) {}

    public function printer(int $printerId): PendingPrint
    {
        return PendingPrint::forPrinter($this, $printerId);
    }

    public function to(mixed $target): PendingPrint
    {
        return PendingPrint::forTarget($this, $target);
    }

    public function dispatch(PendingPrint $pendingPrint): PrintResult
    {
        [$contentType, $content] = $this->resolveContent($pendingPrint);
        $resolvedTarget = $this->resolveTarget($pendingPrint);

        $title = $pendingPrint->configuredTitle() ?? $this->defaultTitle;
        $source = $pendingPrint->configuredSource() ?? $resolvedTarget->source ?? $this->defaultSource;
        $options = [
            ...$this->defaultOptions,
            ...$resolvedTarget->options,
            ...$pendingPrint->optionValues(),
        ];

        $payload = new CreatePrintJobPayload(
            printerId: $resolvedTarget->printNodePrinterId,
            title: $title,
            contentType: $contentType,
            content: $content,
            source: $source,
            contentTypeHeader: $pendingPrint->configuredContentTypeHeader(),
            expireAfter: $pendingPrint->configuredExpireAfter(),
            clientKey: $pendingPrint->configuredClientKey(),
            options: $options,
        );

        $idempotencyKey = $this->idempotencyKey($pendingPrint);
        $dispatchContext = new PrintDispatchContext(
            pendingPrint: $pendingPrint,
            target: $resolvedTarget,
            payload: $payload,
            idempotencyKey: $idempotencyKey,
        );

        $decision = $this->dispatchPolicy?->decide($dispatchContext) ?? PrintDispatchDecision::send();

        if ($decision->action === PrintDispatchAction::Skip) {
            $this->logSkippedDispatch($dispatchContext, $decision->reason);

            return PrintResult::skipped(
                printNodePrinterId: $resolvedTarget->printNodePrinterId,
                title: $title,
                contentType: $contentType,
                source: $source,
                idempotencyKey: $idempotencyKey,
                options: $options,
            );
        }

        if ($decision->action === PrintDispatchAction::Fail) {
            $message = $decision->reason ?? 'Print dispatch was blocked by the configured print policy.';

            $this->logFailedDispatch($dispatchContext, new PrintDispatchBlockedException($message));

            throw new PrintDispatchBlockedException($message);
        }

        $childAccount = $pendingPrint->configuredChildAccount() ?? $resolvedTarget->childAccount;

        try {
            $createdPrintJob = $this->createPrintJob($payload, $idempotencyKey, $childAccount);
        } catch (DuplicatePrintJobException $duplicate) {
            // An earlier attempt with this key already reached PrintNode, for example a queued job
            // that is being retried. That is the outcome the key is for, not a failure.
            $result = PrintResult::alreadySent(
                printNodePrinterId: $resolvedTarget->printNodePrinterId,
                title: $title,
                contentType: $contentType,
                idempotencyKey: $duplicate->idempotencyKey,
                source: $source,
                requestId: $duplicate->requestId,
                options: $options,
            );

            $this->logSuccessfulDispatch($dispatchContext, $result);

            return $result;
        } catch (Throwable $throwable) {
            $this->logFailedDispatch($dispatchContext, $throwable);

            throw $throwable;
        }

        $result = PrintResult::sent(
            printJobId: $createdPrintJob->printJobId,
            printNodePrinterId: $resolvedTarget->printNodePrinterId,
            title: $title,
            contentType: $contentType,
            source: $source,
            requestId: $createdPrintJob->requestId,
            idempotencyKey: $idempotencyKey,
            options: $options,
        );

        $this->logSuccessfulDispatch($dispatchContext, $result);

        return $result;
    }

    public function defaultTitle(): string
    {
        return $this->defaultTitle;
    }

    public function defaultSource(): string
    {
        return $this->defaultSource;
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultOptions(): array
    {
        return $this->defaultOptions;
    }

    public function ensureReady(PendingPrint $pendingPrint): void
    {
        $this->resolveContent($pendingPrint);
    }

    /**
     * @return array{PrintContentType, string}
     */
    private function resolveContent(PendingPrint $pendingPrint): array
    {
        $contentType = $pendingPrint->contentType();
        $content = $pendingPrint->content();

        if (! $contentType instanceof PrintContentType || $content === null) {
            throw new IncompletePrintJobException('A print job requires content before it can be sent.');
        }

        return [$contentType, $content];
    }

    private function resolveTarget(PendingPrint $pendingPrint): ResolvedPrintTarget
    {
        if ($pendingPrint->printerId() !== null) {
            return ResolvedPrintTarget::forPrinter($pendingPrint->printerId());
        }

        if (! $this->targetResolver instanceof ResolvesPrintTarget) {
            throw new UnresolvablePrintTargetException('No print target resolver is configured. Bind '.ResolvesPrintTarget::class.' or use printer(...).');
        }

        return $this->targetResolver->resolve($pendingPrint->target());
    }

    /**
     * Only the caller knows when two prints are "the same print": the same receipt sent again by a
     * retried queue job is, a reprint or a second drawer kick with identical bytes is not. So a key
     * is only used when the caller gives one. The configured prefix namespaces it, so two apps that
     * share a PrintNode account cannot block each other's jobs with a key like "label-1001".
     */
    private function idempotencyKey(PendingPrint $pendingPrint): ?string
    {
        $key = $pendingPrint->configuredIdempotencyKey();

        if ($key === null || $key === '') {
            return null;
        }

        if ($this->defaultIdempotencyPrefix === null || $this->defaultIdempotencyPrefix === '') {
            return $key;
        }

        return $this->defaultIdempotencyPrefix.'-'.$key;
    }

    private function createPrintJob(
        CreatePrintJobPayload $payload,
        ?string $idempotencyKey,
        ?ChildAccountContext $childAccount,
    ): CreatedPrintJobResult {
        try {
            return $this->sdk->printJobs()->createWithMetadata($payload, $idempotencyKey, $childAccount);
        } catch (UnexpectedValueException $unexpectedValueException) {
            throw new IncompletePrintJobException('The PrintNode API returned a successful response without a recognizable print job identifier.', $unexpectedValueException->getCode(), previous: $unexpectedValueException);
        }
    }

    private function logSkippedDispatch(PrintDispatchContext $dispatchContext, ?string $reason = null): void
    {
        if (! $this->logSkipped || ! $this->logger instanceof LoggerInterface) {
            return;
        }

        $context = $this->buildLogContext($dispatchContext);

        if ($reason !== null) {
            $context['reason'] = $reason;
        }

        $this->logger->info('PrintNode print job skipped.', $context);
    }

    private function logSuccessfulDispatch(PrintDispatchContext $dispatchContext, PrintResult $result): void
    {
        if (! $this->logSuccess || ! $this->logger instanceof LoggerInterface) {
            return;
        }

        $context = $this->buildLogContext($dispatchContext);
        $context['printJobId'] = $result->printJobId;
        $context['requestId'] = $result->requestId;

        $this->logger->info(
            $result->wasAlreadySent()
                ? 'PrintNode print job was already sent with this idempotency key.'
                : 'PrintNode print job dispatched.',
            $context,
        );
    }

    private function logFailedDispatch(PrintDispatchContext $dispatchContext, Throwable $throwable): void
    {
        if (! $this->logFailures || ! $this->logger instanceof LoggerInterface) {
            return;
        }

        $context = $this->buildLogContext($dispatchContext);
        $context['exception'] = $throwable::class;
        $context['message'] = $throwable->getMessage();

        $this->logger->error('PrintNode print job failed.', $context);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildLogContext(PrintDispatchContext $dispatchContext): array
    {
        $context = [
            'printerId' => $dispatchContext->target->printNodePrinterId,
            'title' => $dispatchContext->payload->title,
            'source' => $dispatchContext->payload->source,
            'contentType' => $dispatchContext->payload->contentType->value,
            'idempotencyKey' => $dispatchContext->idempotencyKey,
            'options' => $dispatchContext->payload->options,
        ];

        if ($this->includeContentLengthInLogs) {
            $context['contentLength'] = strlen($dispatchContext->payload->content);
        }

        if ($this->includeContentHashInLogs) {
            $context['contentHash'] = sha1($dispatchContext->payload->content);
        }

        return $context;
    }
}
