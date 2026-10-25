<?php

declare(strict_types=1);

namespace App\Http\Action\Notes;

use App\Domain\Note\NoteRepositoryInterface;
use App\Support\ProblemDetailsException;
use App\Support\ValidationException;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class UpdateNoteAction implements RequestHandlerInterface
{
    private NoteRepositoryInterface $noteRepository;

    public function __construct(NoteRepositoryInterface $noteRepository)
    {
        $this->noteRepository = $noteRepository;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $userId = (string) $request->getAttribute('user_id');
        $id = (string) $request->getAttribute('id');
        /** @var array<string, mixed> $body */
        $body = (array) ($request->getParsedBody() ?? []);

        $ifMatch = trim($request->getHeaderLine('If-Match'));
        if ($ifMatch === '') {
            throw new ProblemDetailsException(
                type: 'https://api.example.com/errors/precondition-required',
                title: 'Precondition Required',
                status: 412,
                detail: 'The If-Match request header is required for updates to prevent lost updates.',
                instance: $request->getUri()->getPath()
            );
        }

        $note = $this->noteRepository->findById($id, $userId);
        if ($note === null) {
            throw new ProblemDetailsException(
                type: 'https://api.example.com/errors/not-found',
                title: 'Note Not Found',
                status: 404,
                detail: sprintf('Note with ID "%s" was not found.', $id),
                instance: $request->getUri()->getPath()
            );
        }

        // Optimistic concurrency check
        if (!$note->matchesEtag($ifMatch)) {
            throw new ProblemDetailsException(
                type: 'https://api.example.com/errors/precondition-failed',
                title: 'Precondition Failed',
                status: 412,
                detail: 'The resource has been modified by another process. Your ETag is out of date.',
                instance: $request->getUri()->getPath(),
                extensions: [
                    'current_etag' => $note->getEtag(),
                    'provided_etag' => $ifMatch,
                ]
            );
        }

        $title = trim((string) ($body['title'] ?? ''));
        $content = (string) ($body['content'] ?? '');

        $errors = [];
        if ($title === '') {
            $errors['title'] = ['The title field is required.'];
        } elseif (strlen($title) > 255) {
            $errors['title'] = ['The title cannot exceed 255 characters.'];
        }

        if (count($errors) > 0) {
            throw new ValidationException($errors, $request->getUri()->getPath());
        }

        $previousVersion = $note->getVersion();
        $note->update($title, $content);

        $updated = $this->noteRepository->updateWithOptimisticLock($note, $previousVersion);
        if (!$updated) {
            throw new ProblemDetailsException(
                type: 'https://api.example.com/errors/precondition-failed',
                title: 'Precondition Failed',
                status: 412,
                detail: 'Concurrent edit detected during commit. Please refetch and retry.',
                instance: $request->getUri()->getPath()
            );
        }

        $payload = [
            'data' => $note->toArray(),
            'meta' => [
                'timestamp' => time(),
            ],
        ];

        return (new Response(
            200,
            ['Content-Type' => 'application/json; charset=utf-8'],
            (string) json_encode($payload, JSON_UNESCAPED_SLASHES)
        ))->withHeader('ETag', $note->getEtag());
    }
}
