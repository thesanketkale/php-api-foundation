<?php

declare(strict_types=1);

namespace App\Http\Action\Notes;

use App\Domain\Note\NoteRepositoryInterface;
use App\Support\ProblemDetailsException;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class DeleteNoteAction implements RequestHandlerInterface
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

        $deleted = $this->noteRepository->delete($id, $userId);

        if (!$deleted) {
            throw new ProblemDetailsException(
                type: 'https://api.example.com/errors/not-found',
                title: 'Note Not Found',
                status: 404,
                detail: sprintf('Note with ID "%s" was not found or has already been deleted.', $id),
                instance: $request->getUri()->getPath()
            );
        }

        return new Response(204);
    }
}
