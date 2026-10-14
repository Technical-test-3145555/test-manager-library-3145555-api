<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class MemberTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_and_librarian_create_members_and_the_document_is_unique(): void
    {
        $librarian = $this->tokenFor('librarian');

        $this->postJson('/api/v1/members', ['full_name' => 'Ana Pérez', 'document_number' => 'CC-100'], $librarian)
            ->assertCreated()
            ->assertJsonPath('full_name', 'Ana Pérez');

        $this->postJson('/api/v1/members', ['full_name' => 'Otra', 'document_number' => ' CC-100 '], $this->tokenFor('admin'))
            ->assertStatus(409)
            ->assertJsonPath('code', 'DOCUMENT_TAKEN');
    }

    public function test_members_are_validated(): void
    {
        $this->postJson('/api/v1/members', ['full_name' => '  ', 'document_number' => 'X'], $this->tokenFor('librarian'))
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_detail_shows_the_count_of_unreturned_loans_and_a_missing_member_is_404(): void
    {
        $headers = $this->tokenFor('librarian');
        $id = $this->postJson('/api/v1/members', ['full_name' => 'Ana', 'document_number' => 'CC-200'], $headers)->json('id');

        $this->getJson("/api/v1/members/{$id}", $headers)->assertOk()->assertJsonPath('active_loans', 0);
        $this->getJson('/api/v1/members/11111111-2222-4333-8444-555555555555', $headers)->assertStatus(404);
    }

    public function test_search_by_name_or_document_is_paginated(): void
    {
        $headers = $this->tokenFor('admin');
        foreach ([['Carlos Ruiz', 'ZZ-1'], ['Carla Gómez', 'ZZ-2'], ['Pedro', 'QQ-9']] as [$name, $doc]) {
            $this->postJson('/api/v1/members', ['full_name' => $name, 'document_number' => $doc], $headers)->assertCreated();
        }

        $this->getJson('/api/v1/members?q=carl', $headers)->assertJsonPath('total', 2);
        $this->getJson('/api/v1/members?q=QQ', $headers)->assertJsonPath('total', 1);
        $this->getJson('/api/v1/members?q=ZZ&size=1&page=2', $headers)->assertJsonPath('total', 2)->assertJsonCount(1, 'items');
    }
}
