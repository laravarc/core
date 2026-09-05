<?php

declare(strict_types=1);

namespace Laravarc\Core\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Thin app-facing mail gateway. Distinct from Illuminate's mail internals —
 * callers should depend on this class, not the Mail facade, so logging /
 * failure policy stays consistent.
 */
final class MailDispatcher
{
    public function send(string $to, Mailable $mailable): void
    {
        try {
            Mail::to($to)->send($mailable);
        } catch (Throwable $exception) {
            $this->logFailure('send', $to, $mailable, $exception);

            throw $exception;
        }
    }

    /**
     * Only catches failures while enqueueing (e.g. queue driver unavailable).
     * Delivery errors that occur later in a worker are outside this method's scope.
     */
    public function queue(string $to, Mailable $mailable): void
    {
        try {
            Mail::to($to)->queue($mailable);
        } catch (Throwable $exception) {
            $this->logFailure('queue', $to, $mailable, $exception);

            throw $exception;
        }
    }

    /**
     * Only catches failures while scheduling the delayed job (same limit as queue()).
     */
    public function later(string $to, Mailable $mailable, Carbon $delay): void
    {
        try {
            Mail::to($to)->later($delay, $mailable);
        } catch (Throwable $exception) {
            $this->logFailure('later', $to, $mailable, $exception);

            throw $exception;
        }
    }

    /**************************************************************
     *                     HELPER FUNCTIONS                       *
     **************************************************************/

    private function logFailure(string $action, string $to, Mailable $mailable, Throwable $exception): void
    {
        Log::error('mail.dispatch.failed', [
            'action' => $action,
            'to' => $to,
            'mailable' => $mailable::class,
            'error' => $exception->getMessage(),
        ]);
    }
}
