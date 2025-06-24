<?php

namespace App\DTOs;

use Illuminate\Pagination\LengthAwarePaginator;

class ChangeLogCollectionDTO
{
    private array $data;
    private ?array $pagination;

    public function __construct(array $data, ?array $pagination = null)
    {
        $this->data = $data;
        $this->pagination = $pagination;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getPagination(): ?array
    {
        return $this->pagination;
    }

    public function toArray(): array
    {
        $result = [
            'logs' => $this->data,
            'total' => count($this->data)
        ];

        if ($this->pagination) {
            $result['pagination'] = $this->pagination;
        }

        return $result;
    }

    public static function fromCollection($changeLogs): self
    {
        $data = $changeLogs->map(function ($log) {
            return ChangeLogDTO::fromModel($log)->toArray();
        })->toArray();

        $pagination = null;
        if ($changeLogs instanceof LengthAwarePaginator) {
            $pagination = [
                'current_page' => $changeLogs->currentPage(),
                'last_page' => $changeLogs->lastPage(),
                'per_page' => $changeLogs->perPage(),
                'total' => $changeLogs->total(),
                'from' => $changeLogs->firstItem(),
                'to' => $changeLogs->lastItem(),
            ];
        }

        return new self($data, $pagination);
    }
}
