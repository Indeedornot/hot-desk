<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Domain\RuleViolation;
use App\Entity\User;
use Doctrine\DBAL\Exception\ConstraintViolationException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

final class ApiSubscriber implements EventSubscriberInterface
{
    public function __construct(private CsrfTokenManagerInterface $csrf, private TokenStorageInterface $tokens, private LoggerInterface $logger)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return ['kernel.request' => ['checkCsrf', 12], 'kernel.controller' => 'checkAccount', 'kernel.exception' => ['onException', 10]];
    }

    public function checkCsrf(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !str_starts_with($request->getPathInfo(), '/api/') || $request->isMethodSafe()) {
            return;
        }
        if (!$this->csrf->isTokenValid(new CsrfToken('api', $request->headers->get('X-CSRF-TOKEN', '')))) {
            throw new RuleViolation('Your session has expired. Reload and try again.', 403);
        }
    }

    public function checkAccount(ControllerEvent $event): void
    {
        $user = $this->tokens->getToken()?->getUser();
        if ($user instanceof User && !$user->active) {
            $this->tokens->setToken(null);
            $event->getRequest()->getSession()->invalidate();
            throw new RuleViolation('This account has been disabled.', 401);
        }
    }

    public function onException(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/')) {
            return;
        }
        $error = $event->getThrowable();
        $status = match (true) {
            $error instanceof RuleViolation => $error->status,
            $error instanceof ConstraintViolationException => 409,
            $error instanceof AccessDeniedException => $this->tokens->getToken()?->getUser() instanceof User ? 403 : 401,
            $error instanceof HttpExceptionInterface => $error->getStatusCode(),
            default => 500,
        };
        if ($status >= 500) {
            $this->logger->error('API request failed: '.$error->getMessage(), ['exception' => $error]);
        }
        $message = match (true) {
            $status >= 500 => 'Something went wrong. Please try again.',
            401 === $status => 'Please sign in.',
            $error instanceof ConstraintViolationException => 'This operation conflicts with existing data. Refresh and try again.',
            403 === $status && !$error instanceof RuleViolation => 'You do not have permission for this action.',
            default => $error->getMessage(),
        };
        $fields = [];
        $previous = $error->getPrevious();
        if ($previous instanceof ValidationFailedException) {
            foreach ($previous->getViolations() as $violation) {
                $fields[$violation->getPropertyPath()][] = $violation->getMessage();
            }
            $message = 'Please check the highlighted fields.';
        }
        $event->setResponse(new JsonResponse(['error' => ['message' => $message, 'fields' => $fields]], $status));
    }
}
