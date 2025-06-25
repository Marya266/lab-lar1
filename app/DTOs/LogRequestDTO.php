<?php

namespace App\DTOs;

class LogRequestDTO
{
    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function getId(): ?int
    {
        return $this->data['id'] ?? null;
    }

    public function getMethod(): string
    {
        return $this->data['method'];
    }

    public function getUrl(): string
    {
        return $this->data['url'];
    }

    public function getRouteName(): ?string
    {
        return $this->data['route_name'] ?? null;
    }

    public function getControllerClass(): ?string
    {
        return $this->data['controller_class'] ?? null;
    }

    public function getControllerMethod(): ?string
    {
        return $this->data['controller_method'] ?? null;
    }

    public function getUserId(): ?int
    {
        return $this->data['user_id'] ?? null;
    }

    public function getIpAddress(): string
    {
        return $this->data['ip_address'];
    }

    public function getResponseStatus(): int
    {
        return $this->data['response_status'];
    }

    public function getExecutionTime(): ?float
    {
        return $this->data['execution_time'] ?? null;
    }

    public function getCreatedAt(): ?string
    {
        return $this->data['created_at'] ?? null;
    }

    /**
     * Полное представление для детального просмотра
     */
    public function toFullArray(): array
    {
        return [
            'id' => $this->getId(),
            'method' => $this->getMethod(),
            'url' => $this->getUrl(),
            'route_name' => $this->getRouteName(),
            'controller_class' => $this->getControllerClass(),
            'controller_method' => $this->getControllerMethod(),
            'request_body' => $this->data['request_body'] ?? null,
            'request_headers' => $this->data['request_headers'] ?? [],
            'user_id' => $this->getUserId(),
            'user' => $this->data['user'] ?? null,
            'ip_address' => $this->getIpAddress(),
            'user_agent' => $this->data['user_agent'] ?? null,
            'response_status' => $this->getResponseStatus(),
            'response_body' => $this->data['response_body'] ?? null,
            'response_headers' => $this->data['response_headers'] ?? [],
            'execution_time' => $this->getExecutionTime(),
            'request_size' => $this->data['request_size'] ?? 0,
            'response_size' => $this->data['response_size'] ?? 0,
            'is_successful' => $this->data['is_successful'] ?? null,
            'created_at' => $this->getCreatedAt(),
        ];
    }

    /**
     * Краткое представление для списка
     */
    public function toShortArray(): array
    {
        $controllerShort = $this->getControllerClass() ? 
            class_basename($this->getControllerClass()) : 
            'Unknown';

        return [
            'id' => $this->getId(),
            'method' => $this->getMethod(),
            'url' => $this->getUrl(),
            'controller' => $controllerShort . '@' . ($this->getControllerMethod() ?? 'unknown'),
            'user_id' => $this->getUserId(),
            'ip_address' => $this->getIpAddress(),
            'response_status' => $this->getResponseStatus(),
            'execution_time' => $this->getExecutionTime(),
            'is_successful' => $this->data['is_successful'] ?? null,
            'created_at' => $this->getCreatedAt(),
        ];
    }

    public static function fromModel($logRequest): self
    {
        $data = $logRequest->toArray();
        
        // Добавить связанные данные если загружены
        if ($logRequest->relationLoaded('user')) {
            $data['user'] = $logRequest->user;
        }

        // Добавить вычисляемые атрибуты
        $data['is_successful'] = $logRequest->is_successful;
        $data['request_size'] = $logRequest->request_size;
        $data['response_size'] = $logRequest->response_size;

        return new self($data);
    }


    public function toCreateArray(): array
    {
        return [
            'method' => $this->getMethod(),
            'url' => $this->getUrl(),
            'route_name' => $this->getRouteName(),
            'controller_class' => $this->getControllerClass(),
            'controller_method' => $this->getControllerMethod(),
            'request_body' => $this->data['request_body'] ?? null,
            'request_headers' => $this->data['request_headers'] ?? [],
            'user_id' => $this->getUserId(),
            'ip_address' => $this->getIpAddress(),
            'user_agent' => $this->data['user_agent'] ?? null,
            'response_status' => $this->getResponseStatus(),
            'response_body' => $this->data['response_body'] ?? null,
            'response_headers' => $this->data['response_headers'] ?? [],
            'execution_time' => $this->getExecutionTime(),
        ];
    }
}
