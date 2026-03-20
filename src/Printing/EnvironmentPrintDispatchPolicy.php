<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Printing;

use OneStopMobile\PrintNodeSdk\Contracts\DecidesPrintDispatch;
use OneStopMobile\PrintNodeSdk\Enums\PrintDispatchAction;

final readonly class EnvironmentPrintDispatchPolicy implements DecidesPrintDispatch
{
    /**
     * @param  list<string>  $allowedEnvironments
     */
    public function __construct(
        private string $currentEnvironment,
        private array $allowedEnvironments = ['production'],
        private PrintDispatchAction $actionWhenDisallowed = PrintDispatchAction::Skip,
    ) {}

    public function decide(PrintDispatchContext $context): PrintDispatchDecision
    {
        if (in_array($this->currentEnvironment, $this->allowedEnvironments, true)) {
            return PrintDispatchDecision::send();
        }

        return match ($this->actionWhenDisallowed) {
            PrintDispatchAction::Send => PrintDispatchDecision::send(),
            PrintDispatchAction::Skip => PrintDispatchDecision::skip(sprintf(
                'Printing is disabled in the "%s" environment.',
                $this->currentEnvironment,
            )),
            PrintDispatchAction::Fail => PrintDispatchDecision::fail(sprintf(
                'Printing is disabled in the "%s" environment.',
                $this->currentEnvironment,
            )),
        };
    }
}
