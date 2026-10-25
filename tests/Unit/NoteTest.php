<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\Note\Note;
use PHPUnit\Framework\TestCase;

final class NoteTest extends TestCase
{
    public function testCreateNoteAndInitialEtag(): void
    {
        $note = Note::create('note-1', 'user-1', 'Initial Title', 'Initial Content');

        $this->assertSame('note-1', $note->getId());
        $this->assertSame('user-1', $note->getUserId());
        $this->assertSame('Initial Title', $note->getTitle());
        $this->assertSame('Initial Content', $note->getContent());
        $this->assertSame(1, $note->getVersion());
        $this->assertStringStartsWith('W/"1-', $note->getEtag());
        $this->assertTrue($note->matchesEtag($note->getEtag()));
    }

    public function testUpdateNoteIncrementsVersionAndUpdatesEtag(): void
    {
        $note = Note::create('note-1', 'user-1', 'Initial Title', 'Initial Content');
        $oldEtag = $note->getEtag();

        $note->update('Updated Title', 'Updated Content');

        $this->assertSame('Updated Title', $note->getTitle());
        $this->assertSame(2, $note->getVersion());
        $this->assertNotSame($oldEtag, $note->getEtag());
        $this->assertStringStartsWith('W/"2-', $note->getEtag());
        $this->assertTrue($note->matchesEtag($note->getEtag()));
        $this->assertFalse($note->matchesEtag($oldEtag));
    }
}
