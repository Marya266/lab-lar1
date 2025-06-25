<?php

namespace App\DTOs;

use Illuminate\Pagination\LengthAwarePaginator;

class LogRequestCollectionDTO
{
    private array $data;
    private ?array $pagination;
    private ?array $statistics;

    public function __construct(array $data, ?array $pagination = null, ?array $statistics = null)
    {
        $this->data = $data;
        $this->pagination = $pagination;
        $this->statistics = $statistics;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getPagination(): ?array
    {
        return $this->pagination;
    }

    public function getStatistics(): ?array
    {
        return $this->statistics;
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

        if ($this->statistics) {
            $result['statistics'] = $this->statistics;
        }

        return $result;
    }

    public static function fromCollection($logRequests, bool $detailed = false): self
    {
        $data = $logRequests->map(function ($log) use ($detailed) {
            $dto = LogRequestDTO::fromModel($log);
            return $detailed ? $dto->toFullArray() : $dto->toShortArray();
        })->toArray();

        $pagination = null;
        if ($logRequests instanceof LengthAwarePaginator) {
            $pagination = [
                'current_page' => $logRequests->currentPage(),
                'last_page' => $logRequests->lastPage(),
                'per_page' => $logRequests->perPage(),
                'total' => $logRequests->total(),
                'from' => $logRequests->firstItem(),
                'to' => $logRequests->lastItem(),
            ];
        }

        return new self($data, $pagination);
    }

    public static function fromCollectionWithStats($logRequests, array $statistics, bool $detailed = false): self
    {
        $instance = self::fromCollection($logRequests, $detailed);
        $instance->statistics = $statistics;
        
        return $instance;
    }
}
