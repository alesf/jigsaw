<?php

namespace TightenCo\Jigsaw\Exceptions;

use Closure;
use Illuminate\Console\View\Components\BulletList;
use Illuminate\Console\View\Components\Error;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Traits\ReflectsClosures;
use InvalidArgumentException;
use NunoMaduro\Collision\Adapters\Laravel\Inspector;
use NunoMaduro\Collision\Contracts\Provider;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Exception\ExceptionInterface as SymfonyConsoleExceptionInterface;
use Throwable;

class Handler implements ExceptionHandler
{
    use ReflectsClosures;

    /** @var array<string, Closure> */
    private array $exceptionMap = [];

    public function map(Closure|string $from, Closure|string|null $to = null): static
    {
        if (is_string($to)) {
            $to = fn ($exception) => new $to('', 0, $exception);
        }

        if (is_callable($from) && is_null($to)) {
            $from = $this->firstClosureParameterType($to = $from);
        }

        if (! is_string($from) || ! $to instanceof Closure) {
            throw new InvalidArgumentException('Invalid exception mapping.');
        }

        $this->exceptionMap[$from] = $to;

        return $this;
    }

    public function report(Throwable $e): void
    {
        //
    }

    public function shouldReport(Throwable $e): bool
    {
        return true;
    }

    public function render($request, Throwable $e): void
    {
        //
    }

    /**
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    public function renderForConsole($output, Throwable $e): void
    {
        if ($e instanceof CommandNotFoundException) {
            $message = str($e->getMessage())->explode('.')->first();

            if (! empty($alternatives = $e->getAlternatives())) {
                $message .= '. Did you mean one of these?';

                with(new Error($output))->render($message);
                with(new BulletList($output))->render($e->getAlternatives());

                $output->writeln('');
            } else {
                with(new Error($output))->render($message);
            }

            return;
        }

        if ($e instanceof SymfonyConsoleExceptionInterface) {
            (new ConsoleApplication)->renderThrowable($e, $output);

            return;
        }

        $e = $this->mapException($e);

        /** @var \NunoMaduro\Collision\Contracts\Provider $provider */
        $provider = app(Provider::class);

        $handler = $provider->register()->getHandler()->setOutput($output);
        $handler->setInspector(new Inspector($e));

        $handler->handle();
    }

    protected function mapException(Throwable $e): Throwable
    {
        if (method_exists($e, 'getInnerException') && ($inner = $e->getInnerException()) instanceof Throwable) {
            return $inner;
        }

        foreach ($this->exceptionMap as $class => $mapper) {
            if ($e instanceof $class) {
                return $mapper($e);
            }
        }

        return $e;
    }
}
