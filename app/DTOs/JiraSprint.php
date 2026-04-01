<?php

namespace App\DTOs;

/**
 * T20 — JiraSprint DTO
 */
readonly class JiraSprint
{
    public function __construct(
        public int     $id,
        public string  $name,
        public string  $state,
        public ?string $startDate,
        public ?string $endDate,
        public int     $boardId,
    ) {}

    public static function fromApiResponse(array $data): self
    {
        return new self(
            id:        $data['id'],
            name:      $data['name'],
            state:     $data['state'],
            startDate: $data['startDate'] ?? null,
            endDate:   $data['endDate'] ?? null,
            boardId:   $data['originBoardId'] ?? 0,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'state'      => $this->state,
            'start_date' => $this->startDate,
            'end_date'   => $this->endDate,
            'board_id'   => $this->boardId,
        ];
    }
}
