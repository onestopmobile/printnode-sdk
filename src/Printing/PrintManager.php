<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Printing;

use OneStopMobile\PrintNodeSdk\Contracts\DecidesPrintDispatch;
use OneStopMobile\PrintNodeSdk\Contracts\ResolvesPrintTarget;
use OneStopMobile\PrintNodeSdk\Enums\PrintContentType;
use OneStopMobile\PrintNodeSdk\Enums\PrintDispatchAction;
use OneStopMobile\PrintNodeSdk\Exceptions\IncompletePrintJobException;
use OneStopMobile\PrintNodeSdk\Exceptions\PrintDispatchBlockedException;
use OneStopMobile\PrintNodeSdk\Exceptions\UnresolvablePrintTargetException;
use OneStopMobile\PrintNodeSdk\Http\Requests\JsonEndpointRequest;
use OneStopMobile\PrintNodeSdk\Http\Responses\PrintNodeResponse;
use OneStopMobile\PrintNodeSdk\Payloads\CreatePrintJobPayload;
use OneStopMobile\PrintNodeSdk\PrintNodeSdk;
use Psr\Log\LoggerInterface;
use Saloon\Enums\Method;
use Throwable;

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

        $idempotencyKey = $pendingPrint->configuredIdempotencyKey() ?? $this->buildIdempotencyKey($resolvedTarget, $payload);
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

        try {
            /** @var PrintNodeResponse $response */
            $response = $this->sdk->connector()->send(new JsonEndpointRequest(
                Method::POST,
                '/printjobs',
                $payload->toArray(),
                extraHeaders: array_filter([
                    'X-Idempotency-Key' => $this->nonEmptyStringOrNull($idempotencyKey),
                ], static fn (?string $value): bool => $value !== null),
            ));

            $response->throwIfFailed();

            $result = PrintResult::sent(
                printJobId: $this->extractPrintJobId($response->payload()),
                printNodePrinterId: $resolvedTarget->printNodePrinterId,
                title: $title,
                contentType: $contentType,
                source: $source,
                requestId: $response->requestId(),
                idempotencyKey: $idempotencyKey,
                options: $options,
            );

            $this->logSuccessfulDispatch($dispatchContext, $result);

            return $result;
        } catch (Throwable $throwable) {
            $this->logFailedDispatch($dispatchContext, $throwable);

            throw $throwable;
        }
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

    private function buildIdempotencyKey(ResolvedPrintTarget $target, CreatePrintJobPayload $payload): ?string
    {
        if ($this->defaultIdempotencyPrefix === null || $this->defaultIdempotencyPrefix === '') {
            return null;
        }

        return $this->defaultIdempotencyPrefix.'-'.sha1(json_encode([
            'printerId' => $target->printNodePrinterId,
            'title' => $payload->title,
            'contentType' => $payload->contentType->value,
            'content' => $payload->content,
            'source' => $payload->source,
            'contentTypeHeader' => $payload->contentTypeHeader,
            'expireAfter' => $payload->expireAfter,
            'clientKey' => $payload->clientKey,
            'options' => $payload->options,
        ], JSON_THROW_ON_ERROR));
    }

    private function extractPrintJobId(mixed $payload): int|string
    {
        if (is_int($payload)) {
            return $payload;
        }

        if (is_string($payload) && is_numeric($payload)) {
            return (int) $payload;
        }

        if (is_array($payload) && isset($payload['id']) && (is_int($payload['id']) || is_string($payload['id']))) {
            return $payload['id'];
        }

        if (is_array($payload)) {
            $nestedId = $this->extractNestedPrintJobId($payload);

            if ($nestedId !== null) {
                return $nestedId;
            }
        }

        throw new IncompletePrintJobException('The PrintNode API returned a successful response without a recognizable print job identifier.');
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

        $this->logger->info('PrintNode print job dispatched.', $context);
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

    private function nonEmptyStringOrNull(?string $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  array<mixed>  $payload
     */
    private function extractNestedPrintJobId(array $payload): int|string|null
    {
        foreach ($payload as $key => $value) {
            if ($key === 'id' && (is_int($value) || is_string($value))) {
                return $value;
            }

            if (is_array($value)) {
                $nestedId = $this->extractNestedPrintJobId($value);

                if ($nestedId !== null) {
                    return $nestedId;
                }
            }
        }

        return null;
    }
}
