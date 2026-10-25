<?php

declare(strict_types=1);

namespace App\Http\Action\Notes;

use App\Domain\Note\Note;
use App\Domain\Note\NoteRepositoryInterface;
use App\Support\ValidationException;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class CreateNoteAction implements RequestHandlerInterface
{
    private NoteRepositoryInterface $noteRepository;

    public function __construct(NoteRepositoryInterface $noteRepository)
    {
        $this->noteRepository = $noteRepository;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $userId = (string) $request->getAttribute('user_id');
        /** @var array<string, mixed> $body */
        $body = (array) ($request->getParsedBody() ?? []);

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

        // Generate UUIDv4
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        $noteId = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

        $note = Note::create($noteId, $userId, $title, $content);
        $this->noteRepository->save($note);

        $payload = [
            'data' => $note->toArray(),
            'meta' => [
                'timestamp' => time(),
            ],
        ];

        return (new Response(
            201,
            ['Content-Type' => 'application/json; charset=utf-8'],
            (string) json_encode($payload, JSON_UNESCAPED_SLASHES)
        ))
            ->withHeader('Location', '/v1/notes/' . $noteId)
            ->withHeader('ETag', $note->getEtag());
    }
}
