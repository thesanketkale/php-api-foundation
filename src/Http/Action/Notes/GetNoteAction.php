<?php

declare(strict_types=1);

namespace App\Http\Action\Notes;

use App\Domain\Note\NoteRepositoryInterface;
use App\Support\ProblemDetailsException;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class GetNoteAction implements RequestHandlerInterface
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
