<?php

namespace App\Jobs;

use App\DTOs\ClientDataDTO;
use App\DTOs\DatabaseInfoDTO;
use App\DTOs\PhpInfoDTO;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class GenerateReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $reportType;
    protected $format;
    protected $requestData;

    public function __construct($reportType, $format = 'json', $requestData = null)
    {
        $this->reportType = $reportType;
        $this->format = $format;
        $this->requestData = $requestData;
    }

    public function handle()
    {
        $startTime = microtime(true);
        
        try {
            $data = $this->generateReportData();
            $filename = $this->saveReport($data);
            
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            
            Log::info("Отчёт создан успешно", [
                'type' => $this->reportType,
                'format' => $this->format,
                'filename' => $filename,
                'execution_time_ms' => $executionTime
            ]);
            
        } catch (\Exception $e) {
            Log::error("Ошибка при создании отчёта", [
                'type' => $this->reportType,
                'format' => $this->format,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function generateReportData()
    {
        switch ($this->reportType) {
            case 'php_info':
                return new PhpInfoDTO([
                    'version' => phpversion(),
                    'extensions' => get_loaded_extensions(),
                    'server_info' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
                ]);
                
            case 'database_info':
                try {
                    $connection = DB::connection();
                    return new DatabaseInfoDTO([
                        'driver' => $connection->getDriverName(),
                        'database' => $connection->getDatabaseName(),
                        'version' => DB::select('SELECT VERSION() as version')[0]->version,
                        'charset' => config('database.connections.mysql.charset'),
                        'tables_count' => count(DB::select('SHOW TABLES'))
                    ]);
                } catch (\Exception $e) {
                    return new DatabaseInfoDTO([
                        'error' => 'Database connection failed: ' . $e->getMessage()
                    ]);
                }
                
            case 'client_data':
                return new ClientDataDTO($this->requestData ?? [
                    'ip' => '127.0.0.1',
                    'user_agent' => 'Job Runner',
                    'method' => 'JOB',
                    'url' => 'background-job',
                    'headers' => [],
                    'parameters' => []
                ]);
                
            default:
                throw new \InvalidArgumentException("Неизвестный тип отчёта: {$this->reportType}");
        }
    }

    private function saveReport($dto)
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename = "reports/{$this->reportType}_{$timestamp}.{$this->format}";
        
        $content = $this->formatData($dto->toArray());
        
        Storage::disk('local')->put($filename, $content);
        
        return $filename;
    }


    private function formatData(array $data)
    {
        switch ($this->format) {
            case 'json':
                return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                
            case 'xml':
                return $this->arrayToXml($data);
                
            case 'csv':
                return $this->arrayToCsv($data);
                
            case 'txt':
                return $this->arrayToText($data);
                
            default:
                return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
    }

    private function arrayToXml(array $data, $rootElement = 'report', $xml = null)
    {
        if ($xml === null) {
            $xml = new \SimpleXMLElement("<?xml version='1.0' encoding='UTF-8'?><{$rootElement}></{$rootElement}>");
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if (is_numeric($key)) {
                    $key = 'item';
                }
                $subnode = $xml->addChild($key);
                $this->arrayToXml($value, $key, $subnode);
            } else {
                $xml->addChild($key, htmlspecialchars($value));
            }
        }

        return $xml->asXML();
    }

    private function arrayToCsv(array $data)
    {
        $output = '';
        
        if (!empty($data)) {
            $keys = array_keys($data);
            $output .= implode(',', $keys) . "\n";
            
            $values = array_map(function($value) {
                if (is_array($value)) {
                    return '"' . str_replace('"', '""', json_encode($value)) . '"';
                }
                return '"' . str_replace('"', '""', $value) . '"';
            }, array_values($data));
            
            $output .= implode(',', $values) . "\n";
        }
        
        return $output;
    }

    private function arrayToText(array $data)
    {
        $output = "=== ОТЧЁТ ===\n\n";
        
        foreach ($data as $key => $value) {
            $output .= strtoupper($key) . ": ";
            
            if (is_array($value)) {
                $output .= "\n" . json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
            } else {
                $output .= $value . "\n";
            }
        }
        
        return $output;
    }
}
