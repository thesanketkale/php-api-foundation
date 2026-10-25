<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Tests\TestCase;

final class NoteIntegrationTest extends TestCase
{
    public function testCreateNoteReturnsCreatedAndEtag(): void
    {
        $user = $this->createUser('author@example.com');
        $jwt = $this->generateAccessToken($user);

        $payload = [
            'title' => 'First Note',
            'content' => 'This is the note content.',
        ];

        $request = $this->createRequest('POST', '/v1/notes', [
            'Authorization' => 'Bearer ' . $jwt,
        ], $payload);

        $response = $this->handleRequest($request);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertTrue($response->hasHeader('Location'));
        $this->assertTrue($response->hasHeader('ETag'));
        $this->assertStringStartsWith('W/"1-', $response->getHeaderLine('ETag'));

        /** @var array<string, mixed> $body */
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame('First Note', $body['data']['title']);
        $this->assertSame('This is the note content.', $body['data']['content']);
        $this->assertSame(1, $body['data']['version']);
    }

    public function testListNotesWithPaginationAndSearch(): void
    {
        $user = $this->createUser('lister@example.com');
        $jwt = $this->generateAccessToken($user);

        // Create 3 notes
        for ($i = 1; $i <= 3; $i++) {
            $this->handleRequest($this->createRequest('POST', '/v1/notes', [
                'Authorization' => 'Bearer ' . $jwt,
            ], [
                'title' => 'Note #' . $i,
                'content' => 'Keyword content #' . $i,
            ]));
        }

        // List all notes
        $request = $this->createRequest('GET', '/v1/notes?page=1&per_page=2', [
            'Authorization' => 'Bearer ' . $jwt,
        ]);
        $response = $this->handleRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        /** @var array<string, mixed> $body */
        $body = json_decode((string) $response->getBody(), true);
        $this->assertCount(2, $body['data']);
        $this->assertSame(1, $body['meta']['current_page']);
        $this->assertSame(2, $body['meta']['per_page']);
        $this->assertSame(3, $body['meta']['total_items']);
        $this->assertSame(2, $body['meta']['total_pages']);

        // Search notes
        $searchReq = $this->createRequest('GET', '/v1/notes?search=Note+%232', [
            'Authorization' => 'Bearer ' . $jwt,
        ]);
        $searchRes = $this->handleRequest($searchReq);
        /** @var array<string, mixed> $searchBody */
        $searchBody = json_decode((string) $searchRes->getBody(), true);
        $this->assertCount(1, $searchBody['data']);
        $this->assertSame('Note #2', $searchBody['data'][0]['title']);
    }

    public function testGetNoteByIdReturnsEtag(): void
    {
        $user = $this->createUser('reader@example.com');
        $jwt = $this->generateAccessToken($user);

        $createRes = $this->handleRequest($this->createRequest('POST', '/v1/notes', [
            'Authorization' => 'Bearer ' . $jwt,
        ], [
            'title' => 'Single Note',
            'content' => 'Content details',
        ]));
        /** @var array<string, mixed> $createBody */
        $createBody = json_decode((string) $createRes->getBody(), true);
        $noteId = (string) $createBody['data']['id'];

        $getRes = $this->handleRequest($this->createRequest('GET', '/v1/notes/' . $noteId, [
            'Authorization' => 'Bearer ' . $jwt,
        ]));

        $this->assertSame(200, $getRes->getStatusCode());
        $this->assertTrue($getRes->hasHeader('ETag'));
        /** @var array<string, mixed> $getBody */
        $getBody = json_decode((string) $getRes->getBody(), true);
        $this->assertSame($noteId, $getBody['data']['id']);
    }

    public function testUpdateNoteOptimisticConcurrencyEnforcement(): void
    {
        $user = $this->createUser('updater@example.com');
        $jwt = $this->generateAccessToken($user);

        $createRes = $this->handleRequest($this->createRequest('POST', '/v1/notes', [
            'Authorization' => 'Bearer ' . $jwt,
        ], [
            'title' => 'Original Title',
            'content' => 'Original Content',
        ]));
        /** @var array<string, mixed> $createBody */
        $createBody = json_decode((string) $createRes->getBody(), true);
        $noteId = (string) $createBody['data']['id'];
        $initialEtag = $createRes->getHeaderLine('ETag');

        // 1. Update without If-Match header -> 412 Precondition Required
        $missingIfMatchReq = $this->createRequest('PUT', '/v1/notes/' . $noteId, [
            'Authorization' => 'Bearer ' . $jwt,
        ], [
            'title' => 'New Title',
            'content' => 'New Content',
        ]);
        $missingIfMatchRes = $this->handleRequest($missingIfMatchReq);
        $this->assertSame(412, $missingIfMatchRes->getStatusCode());

        // 2. Update with wrong/outdated If-Match header -> 412 Precondition Failed
        $wrongIfMatchReq = $this->createRequest('PUT', '/v1/notes/' . $noteId, [
            'Authorization' => 'Bearer ' . $jwt,
            'If-Match' => 'W/"wrong-etag"',
        ], [
            'title' => 'New Title',
            'content' => 'New Content',
        ]);
        $wrongIfMatchRes = $this->handleRequest($wrongIfMatchReq);
        $this->assertSame(412, $wrongIfMatchRes->getStatusCode());

        // 3. Update with correct If-Match header -> 200 OK with new version & ETag
        $validUpdateReq = $this->createRequest('PUT', '/v1/notes/' . $noteId, [
            'Authorization' => 'Bearer ' . $jwt,
            'If-Match' => $initialEtag,
        ], [
            'title' => 'Updated Title Successfully',
            'content' => 'Updated Content Successfully',
        ]);
        $validUpdateRes = $this->handleRequest($validUpdateReq);
        $this->assertSame(200, $validUpdateRes->getStatusCode());
        $newEtag = $validUpdateRes->getHeaderLine('ETag');
        $this->assertNotSame($initialEtag, $newEtag);
        $this->assertStringStartsWith('W/"2-', $newEtag);

        /** @var array<string, mixed> $updateBody */
        $updateBody = json_decode((string) $validUpdateRes->getBody(), true);
        $this->assertSame(2, $updateBody['data']['version']);
        $this->assertSame('Updated Title Successfully', $updateBody['data']['title']);
    }

    public function testDeleteNoteSuccess(): void
    {
        $user = $this->createUser('deleter@example.com');
        $jwt = $this->generateAccessToken($user);

        $createRes = $this->handleRequest($this->createRequest('POST', '/v1/notes', [
            'Authorization' => 'Bearer ' . $jwt,
        ], [
            'title' => 'To Delete',
            'content' => 'To Delete Content',
        ]));
        /** @var array<string, mixed> $createBody */
        $createBody = json_decode((string) $createRes->getBody(), true);
        $noteId = (string) $createBody['data']['id'];

        $deleteRes = $this->handleRequest($this->createRequest('DELETE', '/v1/notes/' . $noteId, [
            'Authorization' => 'Bearer ' . $jwt,
        ]));
        $this->assertSame(204, $deleteRes->getStatusCode());

        // Subsequent GET must return 404
        $getRes = $this->handleRequest($this->createRequest('GET', '/v1/notes/' . $noteId, [
            'Authorization' => 'Bearer ' . $jwt,
        ]));
        $this->assertSame(404, $getRes->getStatusCode());
    }

    public function testOwnerIsolationBetweenUsers(): void
    {
        $userA = $this->createUser('usera@example.com');
        $jwtA = $this->generateAccessToken($userA);

        $userB = $this->createUser('userb@example.com');
        $jwtB = $this->generateAccessToken($userB);

        // User A creates a note
        $createRes = $this->handleRequest($this->createRequest('POST', '/v1/notes', [
            'Authorization' => 'Bearer ' . $jwtA,
        ], [
            'title' => 'Secret Note User A',
            'content' => 'Private data',
        ]));
        /** @var array<string, mixed> $createBody */
        $createBody = json_decode((string) $createRes->getBody(), true);
        $noteId = (string) $createBody['data']['id'];

        // User B tries to read User A's note -> 404 Not Found
        $getRes = $this->handleRequest($this->createRequest('GET', '/v1/notes/' . $noteId, [
            'Authorization' => 'Bearer ' . $jwtB,
        ]));
        $this->assertSame(404, $getRes->getStatusCode());

        // User B tries to update User A's note -> 412 (requires If-Match) then 404
        $updateRes = $this->handleRequest($this->createRequest('PUT', '/v1/notes/' . $noteId, [
            'Authorization' => 'Bearer ' . $jwtB,
            'If-Match' => $createRes->getHeaderLine('ETag'),
        ], [
            'title' => 'Hacked Title',
            'content' => 'Hacked Content',
        ]));
        $this->assertSame(404, $updateRes->getStatusCode());

        // User B tries to delete User A's note -> 404
        $delRes = $this->handleRequest($this->createRequest('DELETE', '/v1/notes/' . $noteId, [
            'Authorization' => 'Bearer ' . $jwtB,
        ]));
        $this->assertSame(404, $delRes->getStatusCode());
    }
}
