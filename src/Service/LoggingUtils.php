<?php

namespace AppBundle\Service;

use Symfony\Component\HttpFoundation\RequestStack;

class LoggingUtils
{
    public function __construct(
        private RequestStack $requestStack,
    ) {}

    public function getBacktrace(int $firstFrame = 0, int $frameCount = 3): string
    {
        /**
         * Example:
         * (0) | function: getBacktrace | file: /var/www/html/src/Controller/RestaurantController.php | line: 907
         * (1) | function: persistAndFlushCart | file: /var/www/html/src/Controller/RestaurantController.php | line: 639
         * (2) | function: addProductToCartAction | file: /var/www/html/vendor/symfony/symfony/src/Symfony/Component/HttpKernel/HttpKernel.php | line: 163
         */

        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1 + $firstFrame + $frameCount); // +1 to compensate for the current function call

        $stack = [];

        for($i = 1 + $firstFrame; $i < count($backtrace); $i++) { // +1 to compensate for the current function call
            $item = $backtrace[$i];

            $callerFile = $this->getElementOrEmptyString($item, 'file');
            $callerLine = $this->getElementOrEmptyString($item, 'line');

            $calledClass = $this->getElementOrEmptyString($item, 'class');
            $calledFunction = $this->getElementOrEmptyString($item, 'function');

            $stack[] = new StackItem($callerFile, $callerLine, $calledClass, $calledFunction);
        }

        return implode(' | ', $stack);
    }

    private function getElementOrEmptyString($arr, $key): string {
        if (array_key_exists($key, $arr)) {
            return (string) $arr[$key];
        } else {
            return '';
        }
    }

    public function getRequest(): string {
        $request = $this->requestStack->getCurrentRequest();

        if ($request === null) {
            return '';
        }

        return sprintf('%s %s', $request->getMethod(), $request->getRequestUri());
    }

    /**
     * Redacts (masks) sensitive information to be written to logs.
     * @param string $text Text to redact
     * @param int $symbolsAtStart Number of symbols to keep at the start of the text
     * @param int $symbolsAtEnd Number of symbols to keep at the end of the text
     * @param int $symbolsInMiddle Number of symbols to use as a mask in the middle of the text
     */
    public function redact(string $text, int $symbolsAtStart=4, int $symbolsAtEnd=4, int $symbolsInMiddle=4): string {
        return substr($text, 0, $symbolsAtStart) . str_repeat('*', $symbolsInMiddle) . substr($text, -$symbolsAtEnd);
    }

    public function getOrderId($order): string {
        $isPersisted = $order->getId() !== null;

        if ($isPersisted) {
            return sprintf('#%d', $order->getId());
        } else {
            return sprintf('(not persisted yet; created_at = %s)', $order->getCreatedAt()->format(\DateTime::ATOM));
        }
    }

    public function getVendors($order): string {
        $vendors = $order->getVendors()->toArray();

        if (count($vendors) === 0) {
            return 'no vendors';
        } else {
            return sprintf('%s (LocalBusiness/Restaurant)', implode(', ', array_map(function($vendor) {
                return $vendor->getRestaurant()->getId();
            }, $vendors)));
        }
    }
}

class StackItem
{
    public function __construct(
        public ?string $callerFile,
        public ?string $callerLine,
        public ?string $calledClass,
        public ?string $calledFunction,
    ) {}

    public function __toString(): string
    {
        return sprintf('%s::%s() called at: file: %s line: %s',
            $this->calledClass,
            $this->calledFunction,
            $this->callerFile,
            $this->callerLine);
    }
}
