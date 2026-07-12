<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Http;

use Gamad\Core\Shared\Application\HealthSummaryQueryService;
use Gamad\Core\Shared\Application\ReplayDeadLetterHandler;
use Gamad\Core\Shared\Outbox\DeadLetterRepository;
use Gamad\Core\Shared\Outbox\OutboxDashboardRepository;

final readonly class AdministrativeRuntimeController
{
    public function __construct(
        private HealthSummaryQueryService $health,
        private OutboxDashboardRepository $dashboard,
        private DeadLetterRepository $deadLetters,
        private ReplayDeadLetterHandler $replay,
    ) {
    }

    public function health(Request $request): Response
    {
        $summary = $this->health->summary();

        return Response::json(200, [
            'healthy' => $summary->healthy,
            'live_workers' => $summary->liveWorkers,
            'ready_workers' => $summary->readyWorkers,
            'stale_workers' => $summary->staleWorkers,
            'workers' => array_map(static fn ($worker): array => [
                'worker_id' => $worker->workerId,
                'live' => $worker->live,
                'ready' => $worker->ready,
                'stale' => $worker->stale,
                'last_seen_at' => $worker->lastSeenAt->format(DATE_ATOM),
                'metadata' => $worker->metadata,
            ], $summary->workers),
        ]);
    }

    public function outbox(Request $request): Response
    {
        $snapshot = $this->dashboard->snapshot();

        return Response::json(200, [
            'pending' => $snapshot->pending,
            'locked' => $snapshot->locked,
            'published' => $snapshot->published,
            'dead_letters' => $snapshot->deadLetters,
            'oldest_pending_at' => $snapshot->oldestPendingAt,
            'last_published_at' => $snapshot->lastPublishedAt,
        ]);
    }

    public function listDeadLetters(Request $request): Response
    {
        $limit = isset($request->query['limit']) ? (int) $request->query['limit'] : 100;
        $offset = isset($request->query['offset']) ? (int) $request->query['offset'] : 0;
        $messages = $this->deadLetters->list($limit, $offset);

        return Response::json(200, array_map(static fn ($message): array => [
            'id' => $message->id,
            'aggregate_id' => $message->aggregateId,
            'event_name' => $message->eventName,
            'payload' => $message->payload,
            'attempts' => $message->attempts,
            'last_error' => $message->lastError,
            'failed_at' => $message->failedAt->format(DATE_ATOM),
        ], $messages));
    }

    public function inspectDeadLetter(Request $request): Response
    {
        $message = $this->deadLetters->find($request->pathParameters['messageId']);
        if ($message === null) {
            return Response::json(404, ['error' => 'dead_letter_not_found']);
        }

        return Response::json(200, [
            'id' => $message->id,
            'aggregate_id' => $message->aggregateId,
            'event_name' => $message->eventName,
            'payload' => $message->payload,
            'attempts' => $message->attempts,
            'last_error' => $message->lastError,
            'failed_at' => $message->failedAt->format(DATE_ATOM),
        ]);
    }

    public function replayDeadLetter(Request $request): Response
    {
        $replayed = $this->replay->replay(
            $request->actor?->actorId ?? '',
            $request->pathParameters['messageId'],
        );

        return $replayed
            ? Response::json(202, ['replayed' => true])
            : Response::json(404, ['error' => 'dead_letter_not_found']);
    }
}
