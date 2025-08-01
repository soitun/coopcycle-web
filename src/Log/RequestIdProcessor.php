<?php

namespace AppBundle\Log;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class RequestIdProcessor implements ProcessorInterface
{
    private $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    public function __invoke(LogRecord $record): LogRecord
    {

        $request = $this->requestStack->getCurrentRequest();

        // for logs coming from ApiLogSubscriber the $request variable is null and `extra`s are added by the ApiRequestResponseProcessor

        if ($request && $request->headers->has('X-Request-ID')) {
            $record['extra']['request_id'] = $request->headers->get('X-Request-ID');
        }

        return $record;
    }
}
